<?php

namespace App\Livewire\Admin\CoinPurchases;

use App\Enums\HelpItem;
use App\Models\CoinTransaction;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Pembelian Toko Koin')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $itemFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingItemFilter(): void
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
        $this->reset(['search', 'itemFilter', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function render()
    {
        $purchases = CoinTransaction::query()
            ->purchases()
            ->with(['user.instansi'])
            ->tap(fn (Builder $query) => $this->applyFilters($query))
            ->latest()
            ->paginate(15);

        $filteredPurchases = CoinTransaction::query()
            ->purchases()
            ->tap(fn (Builder $query) => $this->applyFilters($query));

        $statsQuery = clone $filteredPurchases;

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'coins_spent' => (int) (clone $statsQuery)->sum('amount') * -1,
            'unique_buyers' => (clone $statsQuery)->distinct('user_id')->count('user_id'),
            'today' => CoinTransaction::query()
                ->purchases()
                ->whereDate('created_at', today())
                ->count(),
        ];

        $userIds = $purchases->getCollection()->pluck('user_id')->unique()->filter()->values();

        $balances = $userIds->isEmpty()
            ? collect()
            : CoinTransaction::query()
                ->whereIn('user_id', $userIds)
                ->groupBy('user_id')
                ->selectRaw('user_id, SUM(amount) as balance')
                ->pluck('balance', 'user_id')
                ->map(fn ($balance) => (int) $balance);

        return view('livewire.admin.coin-purchases.index', [
            'purchases' => $purchases,
            'stats' => $stats,
            'balances' => $balances,
            'helpItems' => HelpItem::cases(),
        ]);
    }

    private function applyFilters(Builder $query): void
    {
        $query
            ->when($this->search, fn (Builder $q) => $q->whereHas('user', function (Builder $userQuery) {
                $userQuery->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->when($this->itemFilter, function (Builder $q) {
                $item = HelpItem::tryFrom($this->itemFilter);

                if ($item !== null) {
                    $q->where('reason', 'Beli '.$item->label());
                }
            })
            ->when($this->dateFrom, fn (Builder $q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn (Builder $q) => $q->whereDate('created_at', '<=', $this->dateTo));
    }
}
