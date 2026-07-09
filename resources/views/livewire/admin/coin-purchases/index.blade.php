<div>
    <x-ui.page-header
        title="Pembelian Toko Koin"
        description="Pantau siapa yang membeli item bantuan di toko koin, harga transaksi, dan saldo koin peserta."
    />

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat-card
            label="Total Pembelian"
            :value="number_format($stats['total'])"
            color="primary"
            trend="Semua transaksi toko koin"
            icon="coins"
        />
        <x-ui.stat-card
            label="Koin Terpakai"
            :value="number_format($stats['coins_spent'])"
            color="amber"
            trend="Total koin yang dibelanjakan"
            icon="bolt"
        />
        <x-ui.stat-card
            label="Pembeli Unik"
            :value="number_format($stats['unique_buyers'])"
            color="violet"
            trend="Peserta yang pernah belanja"
            icon="users"
        />
        <x-ui.stat-card
            label="Hari Ini"
            :value="number_format($stats['today'])"
            color="emerald"
            trend="Pembelian pada hari ini"
            icon="clock"
        />
    </div>

    <div class="ui-card mb-5 p-4 sm:p-5">
        <x-ui.filter-toolbar>
            <div class="relative min-w-0 w-full sm:max-w-md sm:flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau email peserta..." class="ui-input pl-10">
            </div>
            <select wire:model.live="itemFilter" class="ui-select w-full sm:w-52 sm:shrink-0">
                <option value="">Semua Item</option>
                @foreach ($helpItems as $item)
                    <option value="{{ $item->value }}">{{ $item->label() }}</option>
                @endforeach
            </select>
            <input type="date" wire:model.live="dateFrom" class="ui-input w-full sm:w-40 sm:shrink-0" title="Dari tanggal">
            <input type="date" wire:model.live="dateTo" class="ui-input w-full sm:w-40 sm:shrink-0" title="Sampai tanggal">
        </x-ui.filter-toolbar>
    </div>

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tanggal</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peserta</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Instansi</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Item Dibeli</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Harga</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Saldo Koin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($purchases as $purchase)
                        @php
                            $helpItem = $purchase->helpItem();
                            $user = $purchase->user;
                        @endphp
                        <tr wire:key="purchase-{{ $purchase->id }}" class="transition hover:bg-slate-50/50">
                            <td class="px-5 py-4 text-slate-500">
                                <div>{{ $purchase->created_at->format('d M Y') }}</div>
                                <div class="text-xs text-slate-400">{{ $purchase->created_at->format('H:i') }}</div>
                            </td>
                            <td class="px-5 py-4">
                                @if ($user)
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700">{{ $user->initials() }}</div>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600">
                                {{ $user?->instansi?->nama ?? '—' }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    @if ($helpItem)
                                        <span class="text-base" aria-hidden="true">{{ $helpItem->icon() }}</span>
                                    @endif
                                    <div>
                                        <p class="font-medium text-slate-900">{{ $purchase->itemLabel() }}</p>
                                        @if ($helpItem)
                                            <p class="text-xs text-slate-500">{{ $helpItem->usageHint() }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    -{{ number_format($purchase->pricePaid()) }} koin
                                </span>
                            </td>
                            <td class="px-5 py-4 text-center text-slate-600">
                                @if ($user)
                                    <span class="tabular-nums">{{ number_format($balances[$user->id] ?? 0) }}</span>
                                    <span class="text-xs text-slate-400"> koin</span>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center text-slate-500">
                                Belum ada pembelian di toko koin.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($purchases->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $purchases->links() }}</div>
        @endif
    </div>
</div>
