<?php

namespace App\Http\Controllers\Peserta;

use App\Enums\ExamAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\ExamAttempt;
use App\Services\CertificateService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateController extends Controller
{
    public function download(ExamAttempt $attempt, CertificateService $certificates): BinaryFileResponse
    {
        abort_unless($attempt->user_id === auth()->id(), 403);

        // Only offline event attempts get a certificate, and only once
        // finished (score must be final).
        abort_unless($attempt->event_id !== null, 404);
        abort_unless($attempt->status === ExamAttemptStatus::Submitted, 404, 'Sertifikat tersedia setelah ujian selesai.');

        $path = $certificates->pathFor($attempt);

        $fileName = 'Sertifikat - '.$attempt->resolvedDisplayName().'.pdf';

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
        ])->setContentDisposition('inline', $fileName);
    }
}
