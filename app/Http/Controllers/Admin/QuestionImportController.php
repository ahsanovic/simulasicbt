<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImportErrors;
use App\Http\Controllers\Controller;
use App\Services\QuestionImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuestionImportController extends Controller
{
    use HandlesImportErrors;

    public function store(Request $request, QuestionImportService $importService): RedirectResponse
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
            return $this->redirectWithValidationImportErrors(
                'admin.questions.index',
                'Import Soal Gagal',
                new ValidationException($validator),
            );
        }

        $storedPath = $request->file('file')->store('imports/questions', 'local');

        try {
            $result = $importService->import($storedPath, (int) auth()->id());

            if (! $result['queued']) {
                Storage::disk('local')->delete($storedPath);
            }

            return redirect()
                ->route('admin.questions.index')
                ->with($result['queued'] ? 'info' : 'success', $result['message']);
        } catch (Throwable $throwable) {
            Storage::disk('local')->delete($storedPath);

            return $this->redirectWithImportErrors(
                'admin.questions.index',
                'Import Soal Gagal',
                $throwable,
            );
        }
    }
}
