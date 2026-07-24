<?php

namespace App\Services;

use App\Models\ExamAttempt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Fills the public/sertifikat.docx template with the peserta's confirmed name
 * and scores, then converts it to PDF via the Gotenberg (LibreOffice) route
 * running in the "gotenberg-signer" container.
 *
 * Placeholders are replaced by direct XML surgery on word/document.xml rather
 * than a template-macro library: Word splits {nama_peserta}-style text into
 * several <w:t> runs interleaved with <w:proofErr> spellcheck markers, which
 * breaks naive "${macro}" merge logic (verified against the real template —
 * PhpOffice/PhpWord's TemplateProcessor left the placeholders untouched). The
 * approach here reconstructs the logical text across all <w:t> runs, locates
 * "{key}" spans in that logical text, and only ever edits the text inside
 * existing <w:t> runs — so formatting/structure is always preserved.
 */
class CertificateService
{
    private const TEMPLATE_PATH = 'sertifikat.docx';

    private const DISK = 'local';

    /**
     * Return the (cached, on-disk) PDF path for this attempt's certificate,
     * generating it first if it doesn't exist yet.
     */
    public function pathFor(ExamAttempt $attempt): string
    {
        $relativePath = $this->relativePath($attempt);

        if (! Storage::disk(self::DISK)->exists($relativePath)) {
            $this->generate($attempt);
        }

        return Storage::disk(self::DISK)->path($relativePath);
    }

    /**
     * Force (re)generate the certificate PDF for this attempt, e.g. after a
     * score reset/correction.
     */
    public function generate(ExamAttempt $attempt): string
    {
        $attempt->loadMissing('user');

        $templatePath = public_path(self::TEMPLATE_PATH);

        if (! is_file($templatePath)) {
            throw new RuntimeException('Template sertifikat tidak ditemukan di public/'.self::TEMPLATE_PATH);
        }

        $filledDocxPath = $this->fillTemplate($templatePath, [
            'nama_peserta' => $attempt->resolvedDisplayName(),
            'nilai_total' => (string) (int) $attempt->total_score,
            'nilai_tkp' => (string) (int) $attempt->score_tkp,
            'nilai_tiu' => (string) (int) $attempt->score_tiu,
            'nilai_twk' => (string) (int) $attempt->score_twk,
        ]);

        try {
            $pdfContents = $this->convertToPdf($filledDocxPath);
        } finally {
            @unlink($filledDocxPath);
        }

        $relativePath = $this->relativePath($attempt);
        Storage::disk(self::DISK)->put($relativePath, $pdfContents);

        return Storage::disk(self::DISK)->path($relativePath);
    }

    /**
     * Copy the template and replace {key} placeholders in word/document.xml,
     * returning the path to the filled-in temporary .docx.
     *
     * @param  array<string, string>  $values
     */
    private function fillTemplate(string $templatePath, array $values): string
    {
        $stub = tempnam(sys_get_temp_dir(), 'sertifikat_');
        @unlink($stub);
        $outputPath = $stub.'.docx';

        if (! copy($templatePath, $outputPath)) {
            throw new RuntimeException('Gagal menyalin template sertifikat.');
        }

        $zip = new ZipArchive;

        if ($zip->open($templatePath) !== true) {
            throw new RuntimeException('Gagal membuka template sertifikat.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            @unlink($outputPath);

            throw new RuntimeException('Template sertifikat tidak memiliki word/document.xml.');
        }

        $filledXml = $this->replacePlaceholders($xml, $values);

        $out = new ZipArchive;

        if ($out->open($outputPath) !== true || ! $out->addFromString('word/document.xml', $filledXml) || ! $out->close()) {
            @unlink($outputPath);

            throw new RuntimeException('Gagal menulis sertifikat yang sudah diisi.');
        }

        return $outputPath;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function replacePlaceholders(string $xml, array $values): string
    {
        preg_match_all('/(<w:t\b[^>]*>)(.*?)(<\/w:t>)/s', $xml, $matches, PREG_OFFSET_CAPTURE);

        $runs = [];
        $logicalText = '';

        foreach ($matches[2] as $i => [$innerXml, $_offset]) {
            $decoded = html_entity_decode($innerXml, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $runs[] = [
                'open' => $matches[1][$i][0],
                'close' => $matches[3][$i][0],
                'decoded' => $decoded,
                'fullStart' => $matches[0][$i][1],
                'fullLen' => strlen($matches[0][$i][0]),
                'logicalStart' => strlen($logicalText),
                'edits' => [],
            ];
            $logicalText .= $decoded;
        }

        foreach ($values as $key => $value) {
            $needle = '{'.$key.'}';
            $needleLen = strlen($needle);
            $searchFrom = 0;

            while (($pos = strpos($logicalText, $needle, $searchFrom)) !== false) {
                $start = $pos;
                $end = $pos + $needleLen;
                $isFirst = true;

                foreach ($runs as &$run) {
                    $runStart = $run['logicalStart'];
                    $runEnd = $runStart + strlen($run['decoded']);
                    $overlapStart = max($runStart, $start);
                    $overlapEnd = min($runEnd, $end);

                    if ($overlapStart < $overlapEnd) {
                        $localStart = $overlapStart - $runStart;
                        $localEnd = $overlapEnd - $runStart;
                        $run['edits'][] = [$localStart, $localEnd, $isFirst ? (string) $value : ''];
                        $isFirst = false;
                    }
                }
                unset($run);

                $searchFrom = $end;
            }
        }

        foreach ($runs as &$run) {
            if (empty($run['edits'])) {
                continue;
            }

            usort($run['edits'], fn ($a, $b) => $b[0] <=> $a[0]);
            $text = $run['decoded'];

            foreach ($run['edits'] as [$localStart, $localEnd, $replacement]) {
                $escaped = htmlspecialchars($replacement, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $text = substr_replace($text, $escaped, $localStart, $localEnd - $localStart);
            }

            $run['newInner'] = $text;
        }
        unset($run);

        usort($runs, fn ($a, $b) => $b['fullStart'] <=> $a['fullStart']);

        foreach ($runs as $run) {
            if (! isset($run['newInner'])) {
                continue;
            }

            $replacement = $run['open'].$run['newInner'].$run['close'];
            $xml = substr_replace($xml, $replacement, $run['fullStart'], $run['fullLen']);
        }

        return $xml;
    }

    private function convertToPdf(string $docxPath): string
    {
        $url = rtrim((string) config('services.gotenberg.url'), '/').'/forms/libreoffice/convert';
        $timeout = (int) config('services.gotenberg.timeout', 30);

        $response = Http::timeout($timeout)
            ->attach('files', file_get_contents($docxPath), 'sertifikat.docx')
            ->post($url);

        if (! $response->successful()) {
            throw new RuntimeException('Gagal mengonversi sertifikat via Gotenberg: HTTP '.$response->status());
        }

        return $response->body();
    }

    private function relativePath(ExamAttempt $attempt): string
    {
        // Namespaced by submitted_at timestamp so a re-generate after a score
        // correction produces a fresh file instead of serving a stale cache.
        $version = $attempt->submitted_at?->timestamp ?? 'draft';

        return "certificates/attempt-{$attempt->id}-{$version}.pdf";
    }
}
