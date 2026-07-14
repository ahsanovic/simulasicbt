<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamResultsExportController extends Controller
{
    public function download(Request $request, ExportRequest $exportRequest): StreamedResponse
    {
        abort_unless(
            $exportRequest->user_id === $request->user()->id
            && $exportRequest->type === ExportRequest::TYPE_EXAM_RESULTS_SUMMARY,
            403,
        );

        abort_unless($exportRequest->isDownloadable(), 404);

        return Storage::disk('local')->download(
            $exportRequest->file_path,
            $exportRequest->file_name ?? 'hasil-ujian.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
