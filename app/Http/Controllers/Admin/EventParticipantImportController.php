<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventParticipantImportService;
use App\Support\ImportErrorReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class EventParticipantImportController extends Controller
{
    public function store(Request $request, EventParticipantImportService $importService, Event $event): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ], [
            'file.required' => 'File Excel wajib dipilih.',
            'file.file' => 'Unggahan harus berupa file.',
            'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 20 MB.',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.events.participants', $event)
                ->with('import_errors', ImportErrorReport::fromValidationException(
                    new ValidationException($validator),
                    'Import Peserta Gagal',
                )->toSession())
                ->with('error', 'Import Peserta Gagal');
        }

        $storedPath = $request->file('file')->store('imports/event-participants', 'local');

        try {
            $result = $importService->import(
                $storedPath,
                $event->id,
                $event->exam_mode->includesSkb(),
            );

            if (! $result['queued']) {
                Storage::disk('local')->delete($storedPath);
            }

            return redirect()
                ->route('admin.events.participants', $event)
                ->with($result['queued'] ? 'info' : 'success', $result['message']);
        } catch (Throwable $throwable) {
            Storage::disk('local')->delete($storedPath);

            $report = ImportErrorReport::fromThrowable($throwable, 'Import Peserta Gagal');

            return redirect()
                ->route('admin.events.participants', $event)
                ->with('import_errors', $report->toSession())
                ->with('error', 'Import Peserta Gagal');
        }
    }
}
