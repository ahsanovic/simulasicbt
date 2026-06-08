<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesImportErrors;
use App\Http\Controllers\Controller;
use App\Imports\QuestionsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class QuestionImportController extends Controller
{
    use HandlesImportErrors;

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [
            'file.required' => 'File Excel wajib dipilih.',
            'file.file' => 'Unggahan harus berupa file.',
            'file.mimes' => 'Format file harus .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithValidationImportErrors(
                'admin.questions.index',
                'Import Soal Gagal',
                new ValidationException($validator),
            );
        }

        try {
            Excel::import(
                new QuestionsImport(auth()->id()),
                $request->file('file'),
            );
        } catch (Throwable $throwable) {
            return $this->redirectWithImportErrors(
                'admin.questions.index',
                'Import Soal Gagal',
                $throwable,
            );
        }

        return redirect()
            ->route('admin.questions.index')
            ->with('success', 'Soal berhasil diimpor.');
    }
}
