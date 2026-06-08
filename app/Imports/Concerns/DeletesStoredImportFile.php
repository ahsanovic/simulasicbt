<?php

namespace App\Imports\Concerns;

use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Events\AfterImport;

trait DeletesStoredImportFile
{
    public function registerEvents(): array
    {
        return [
            AfterImport::class => function () {
                if ($this->storedPath) {
                    Storage::disk('local')->delete($this->storedPath);
                }
            },
        ];
    }
}
