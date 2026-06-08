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
        if ($throwable instanceof ValidationException) {
            throw $throwable;
        }

        return redirect()
            ->route($route)
            ->with('import_errors', ImportErrorReport::fromThrowable($throwable, $title)->toSession());
    }

    protected function redirectWithValidationImportErrors(
        string $route,
        string $title,
        ValidationException $exception,
    ): RedirectResponse {
        return redirect()
            ->route($route)
            ->with('import_errors', ImportErrorReport::fromValidationException($exception, $title)->toSession());
    }
}
