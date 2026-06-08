<?php

namespace App\Livewire\Concerns;

trait HandlesImportErrorModal
{
    public bool $showImportErrorModal = false;

    /** @var array{title?: string, summary?: string, total?: int, errors?: array<int, array{row: ?int, column: ?string, value: ?string, message: string}>} */
    public array $importErrorReport = [];

    protected function mountImportErrorModal(): void
    {
        if (request()->boolean('open_import')) {
            $this->showImportModal = true;
        }

        $report = session()->pull('import_errors');

        if (! is_array($report) || ($report['errors'] ?? []) === []) {
            return;
        }

        $this->importErrorReport = $report;
        $this->showImportErrorModal = true;
    }

    public function closeImportErrorModal(): void
    {
        $this->showImportErrorModal = false;
        $this->importErrorReport = [];
    }

    public function reopenImportModal(): void
    {
        $this->closeImportErrorModal();
        $this->showImportModal = true;
    }
}
