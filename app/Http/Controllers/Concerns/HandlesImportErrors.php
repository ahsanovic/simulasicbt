<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ImportErrorReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

trait HandlesImportErrors
{
    protected function redirectWithImportErrors(
        string $route,
        string $title,
        Throwable $throwable,
    ): RedirectResponse {
        $report = ImportErrorReport::fromThrowable($throwable, $title);

        if ($report->total() === 0) {
            $report = new ImportErrorReport($title, [[
                'row' => null,
                'column' => null,
                'value' => null,
                'message' => $throwable->getMessage() ?: 'Terjadi kesalahan saat memproses file Excel.',
            ]]);
        }

        return redirect()
            ->route($route)
            ->with('import_errors', $report->toSession())
            ->with('error', $title);
    }

    protected function redirectWithValidationImportErrors(
        string $route,
        string $title,
        ValidationException $exception,
    ): RedirectResponse {
        return redirect()
            ->route($route)
            ->with('import_errors', ImportErrorReport::fromValidationException($exception, $title)->toSession())
            ->with('error', $title);
    }
}
