<?php

namespace App\Livewire\Admin\SystemHealth;

use App\Models\SystemHealthLog;
use App\Support\SystemHealth;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Admin "Kesehatan Sistem": runs the exam:preflight checks from the browser,
 * shows light live figures and recent log errors, and keeps each run as a
 * printable report. The queue worker probe is settled by polling instead of
 * blocking the request for up to 20 seconds.
 */
#[Layout('layouts.admin')]
#[Title('Kesehatan Sistem')]
class Index extends Component
{
    /** @var list<array{section: string, label: string, status: string, hint: string}> */
    #[Locked]
    public array $results = [];

    #[Locked]
    public ?string $probeToken = null;

    #[Locked]
    public ?int $probeStartedAt = null;

    #[Locked]
    public ?int $lastLogId = null;

    public function runChecks(): void
    {
        $this->probeToken = null;
        $this->lastLogId = null;

        $this->results = (new SystemHealth)->run(function () {
            $this->probeToken = SystemHealth::dispatchWorkerProbe();
            $this->probeStartedAt = now()->getTimestamp();

            return null;
        });

        if ($this->probeToken === null) {
            $this->saveLog();
        }
    }

    public function checkWorkerProbe(): void
    {
        if ($this->probeToken === null) {
            return;
        }

        if (SystemHealth::workerProbeDone($this->probeToken)) {
            $this->settleWorker(SystemHealth::PASS, '');
        } elseif (now()->getTimestamp() - $this->probeStartedAt >= SystemHealth::WORKER_TIMEOUT_SECONDS) {
            $this->settleWorker(SystemHealth::FAIL, SystemHealth::workerFailureHint());
        }
    }

    #[Computed]
    public function summary(): ?array
    {
        return $this->results === [] ? null : SystemHealth::summarize($this->results);
    }

    #[Computed]
    public function metrics(): array
    {
        return SystemHealth::metrics();
    }

    #[Computed]
    public function logEntries(): array
    {
        return SystemHealth::recentLogEntries();
    }

    #[Computed]
    public function history()
    {
        return SystemHealthLog::query()->with('user:id,name')->latest('id')->limit(30)->get();
    }

    public function render()
    {
        return view('livewire.admin.system-health.index');
    }

    private function settleWorker(string $status, string $hint): void
    {
        $this->results = array_map(
            fn (array $item) => $item['status'] === SystemHealth::PENDING ? [...$item, 'status' => $status, 'hint' => $hint] : $item,
            $this->results,
        );
        $this->probeToken = null;
        $this->saveLog();
    }

    private function saveLog(): void
    {
        $log = SystemHealthLog::query()->create([
            'user_id' => Auth::id(),
            ...SystemHealth::summarize($this->results),
            'results' => $this->results,
            'metrics' => $this->metrics,
        ]);

        SystemHealthLog::prune();
        $this->lastLogId = $log->id;
        unset($this->history);
    }
}
