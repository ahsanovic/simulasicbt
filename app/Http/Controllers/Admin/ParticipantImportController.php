<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImportErrors;
use App\Http\Controllers\Controller;
use App\Services\ParticipantImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class ParticipantImportController extends Controller
{
    use HandlesImportErrors;

    public function store(Request $request, ParticipantImportService $importService): RedirectResponse
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
            return $this->redirectWithValidationImportErrors(
                'admin.users.index',
                'Import Peserta Gagal',
                new ValidationException($validator),
            );
        }

        $storedPath = $request->file('file')->store('imports/participants', 'local');

        try {
            $result = $importService->import($storedPath);

            if (! $result['queued']) {
                Storage::disk('local')->delete($storedPath);
            }

            return redirect()
                ->route('admin.users.index')
                ->with($result['queued'] ? 'info' : 'success', $result['message']);
        } catch (Throwable $throwable) {
            Storage::disk('local')->delete($storedPath);

            return $this->redirectWithImportErrors(
                'admin.users.index',
                'Import Peserta Gagal',
                $throwable,
            );
        }
    }
}
