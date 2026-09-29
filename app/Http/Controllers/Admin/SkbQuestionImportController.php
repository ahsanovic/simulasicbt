<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JabatanSkb;
use App\Services\SkbQuestionImportService;
use App\Support\ImportErrorReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SkbQuestionImportController extends Controller
{
    public function store(Request $request, SkbQuestionImportService $importService, JabatanSkb $jabatanSkb): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:51200'],
        ], [
            'file.required' => 'File Excel wajib dipilih.',
            'file.file' => 'Unggahan harus berupa file.',
            'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 50 MB.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.jabatan-skb.soal.index', $jabatanSkb)
                ->with('import_errors', ImportErrorReport::fromValidationException(
                    new ValidationException($validator),
                    'Import Soal SKB Gagal',
                )->toSession())
                ->with('error', 'Import Soal SKB Gagal');
        }

        $storedPath = $request->file('file')->store('imports/skb-questions', 'local');

        try {
            $result = $importService->import($storedPath, $jabatanSkb->id, (int) auth()->id());

            if (! $result['queued']) {
                Storage::disk('local')->delete($storedPath);
            }

            return redirect()
                ->route('admin.jabatan-skb.soal.index', $jabatanSkb)
                ->with($result['queued'] ? 'info' : 'success', $result['message']);
        } catch (Throwable $throwable) {
            Storage::disk('local')->delete($storedPath);

            $report = ImportErrorReport::fromThrowable($throwable, 'Import Soal SKB Gagal');

            return redirect()
                ->route('admin.jabatan-skb.soal.index', $jabatanSkb)
                ->with('import_errors', $report->toSession())
                ->with('error', 'Import Soal SKB Gagal');
        }
    }
}
