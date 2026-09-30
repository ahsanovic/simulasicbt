<?php

namespace App\Exports;

use App\Models\Event;
use App\Models\EventSession;
use App\Services\EventResultsService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * "Hasil Ujian" export for one exam type (SKD or SKB), either one session
 * (partial) or every session of the event (full).
 *
 * WithStrictNullComparison keeps 0 scores and blank cells from being dropped
 * (PhpSpreadsheet's default row-write treats 0 == null as "skip").
 */
class EventResultsExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithTitle, WithCustomValueBinder, WithStrictNullComparison
{
    public function __construct(
        private readonly Event $event,
        private readonly string $type,
        private readonly ?EventSession $session = null,
    ) {}

    /**
     * Column C holds NIP/NIK — force it to text so 16-digit NIKs keep their
     * exact digits instead of being rounded as a float.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'C') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function title(): string
    {
        $label = strtoupper($this->type).' - '.($this->session?->name ?? 'Semua Sesi');

        return mb_substr($label, 0, 31);
    }

    public function headings(): array
    {
        $unit = $this->event->is_mode_ujian ? 'Jabatan' : 'Instansi';

        $scores = $this->type === 'skb'
            ? ['Benar', 'Total Skor']
            : ['Skor TWK', 'Skor TIU', 'Skor TKP', 'Total Skor'];

        return array_merge(
            ['Peringkat', 'Nama', 'NIP/NIK', $unit, 'Sesi', 'Dikerjakan', 'Total Soal'],
            $scores,
            ['Status', 'Mulai', 'Selesai'],
        );
    }

    public function collection(): Collection
    {
        return app(EventResultsService::class)
            ->rows($this->event, $this->type, $this->session?->id)
            ->map(function (array $row) {
                $scores = $this->type === 'skb'
                    ? [$row['benar'], $row['score']]
                    : [$row['twk'], $row['tiu'], $row['tkp'], $row['score']];

                return array_merge(
                    [$row['rank'], $row['name'], $row['identifier'], $row['unit'], $row['session'], $row['answered'], $row['total']],
                    $scores,
                    [$row['status'], $row['started_at'], $row['submitted_at']],
                );
            });
    }
}
