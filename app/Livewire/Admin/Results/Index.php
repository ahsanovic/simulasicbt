<?php

namespace App\Livewire\Admin\Results;

use App\Data\ExamResultsExportFilters;
use App\Enums\ExportRequestStatus;
use App\Models\ExportRequest;
use App\Services\ExamResultsExportService;
use App\Support\ExamResultsQuery;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

#[Layout('layouts.admin')]
#[Title('Hasil Ujian')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $examTypeFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $activeExportId = null;

    /** @var list<int> */
    public array $dismissedExportIds = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingExamTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'examTypeFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function requestExport(ExamResultsExportService $exportService): void
    {
        $this->validate([
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => [
                'nullable',
                'date',
                Rule::when(filled($this->dateFrom), ['after_or_equal:dateFrom']),
            ],
        ], [
            'dateTo.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
        ]);

        try {
            $exportRequest = $exportService->requestExport(
                auth()->user(),
                $this->currentFilters(),
            );

            $this->activeExportId = $exportRequest->id;

            session()->flash('success', 'Export hasil ujian sedang diproses. File akan siap diunduh dalam beberapa saat.');
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'export' => $exception->getMessage(),
            ]);
        }
    }

    public function dismissExport(): void
    {
        if ($this->activeExportId !== null) {
            $this->dismissedExportIds[] = $this->activeExportId;
        }

        $this->activeExportId = null;
    }

    public function render()
    {
        $filters = $this->currentFilters();

        $attempts = ExamResultsQuery::filtered($filters)
            ->with(['exam', 'user', 'duelSession'])
            ->latest('submitted_at')
            ->paginate(15);

        $stats = ExamResultsQuery::submittedStats($filters);

        $activeExport = $this->resolveActiveExport();

        if ($activeExport) {
            $this->activeExportId = $activeExport->id;
        }

        return view('livewire.admin.results.index', [
            'attempts' => $attempts,
            'stats' => $stats,
            'passingGrades' => exam_passing_grades(),
            'activeExport' => $activeExport,
            'exportRowCount' => ExamResultsQuery::filtered($filters)->count(),
        ]);
    }

    private function currentFilters(): ExamResultsExportFilters
    {
        return new ExamResultsExportFilters(
            search: $this->search,
            examTypeFilter: $this->examTypeFilter,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
        );
    }

    private function resolveActiveExport(): ?ExportRequest
    {
        $query = ExportRequest::query()
            ->where('user_id', auth()->id())
            ->where('type', ExportRequest::TYPE_EXAM_RESULTS_SUMMARY)
            ->when($this->dismissedExportIds !== [], fn ($builder) => $builder->whereNotIn('id', $this->dismissedExportIds))
            ->latest();

        if ($this->activeExportId) {
            $export = (clone $query)->whereKey($this->activeExportId)->first();

            if ($export) {
                return $export;
            }
        }

        return (clone $query)
            ->where(function ($statusQuery) {
                $statusQuery->whereIn('status', [
                    ExportRequestStatus::Pending,
                    ExportRequestStatus::Processing,
                ])->orWhere('created_at', '>=', now()->subHours(48));
            })
            ->first();
    }
}
