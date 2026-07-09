<div class="min-h-screen bg-gradient-to-b from-slate-50 to-amber-50/30">
    <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <x-ui.flash-toast />

        @if ($activeAttempt)
            <x-peserta.active-exam-resume-banner :attempt="$activeAttempt" class="mb-6" />
        @endif

        <div class="mb-8 rounded-2xl bg-gradient-to-r from-amber-500 via-yellow-500 to-orange-500 p-6 text-white shadow-xl shadow-amber-500/20 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-amber-100">Item Bantuan Simulasi</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">Toko Koin 🪙</h1>
                    <p class="mt-2 max-w-2xl text-sm text-amber-50">
                        Beli alat bantu latihan untuk melatih insting manajemen waktu saat simulasi harian.
                        Koin didapat dari menyelesaikan simulasi — bukan dari menukar XP.
                    </p>
                </div>
                <div class="flex items-center gap-2 rounded-xl bg-white/15 px-4 py-3 text-sm font-semibold ring-1 ring-white/20">
                    <span class="text-xl" aria-hidden="true">🪙</span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-amber-100">Saldo Koin</p>
                        <p class="text-xl font-bold tabular-nums">{{ number_format($balance) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div class="ui-card flex items-center gap-3 p-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600">
                    <span class="text-lg">✅</span>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reward Lulus</p>
                    <p class="text-lg font-bold text-slate-900">+{{ \App\Services\CoinService::EXAM_PASS_COIN_REWARD }} koin</p>
                </div>
            </div>
            <div class="ui-card flex items-center gap-3 p-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-100 text-sky-600">
                    <span class="text-lg">📝</span>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Reward Ikut Simulasi</p>
                    <p class="text-lg font-bold text-slate-900">+{{ \App\Services\CoinService::EXAM_FAIL_COIN_REWARD }} koin</p>
                </div>
            </div>
            <div class="ui-card flex items-center gap-3 p-4 sm:col-span-2 lg:col-span-1">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-600">
                    <span class="text-lg">🎒</span>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Inventori Anda</p>
                    <p class="text-lg font-bold text-slate-900">
                        @if ($inventory === [])
                            Belum ada item
                        @else
                            {{ array_sum($inventory) }} item
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">Katalog Item</h2>
            <span class="ui-badge bg-amber-100 text-amber-800">Khusus simulasi harian</span>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            @foreach ($items as $item)
                @php
                    $owned = $inventory[$item->value] ?? 0;
                    $canAfford = $balance >= $item->price();
                @endphp
                <article class="ui-card overflow-hidden">
                    <div class="flex h-full flex-col p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-amber-50 text-3xl ring-1 ring-amber-100">
                                {{ $item->icon() }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-bold text-slate-900">{{ $item->label() }}</h3>
                                    @if ($owned > 0)
                                        <span class="ui-badge bg-emerald-50 text-emerald-700">Dimiliki: {{ $owned }}</span>
                                    @endif
                                </div>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $item->description() }}</p>
                            </div>
                        </div>

                        <div class="mt-5 rounded-2xl border border-amber-100 bg-amber-50/60 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Cara pakai</p>
                            <p class="mt-1 text-sm text-amber-950/80">{{ $item->usageHint() }}</p>
                        </div>

                        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
                            <div class="flex items-center gap-2">
                                <span class="text-2xl" aria-hidden="true">🪙</span>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Harga</p>
                                    <p class="text-xl font-bold text-slate-900">{{ number_format($item->price()) }} koin</p>
                                </div>
                            </div>

                            <button type="button"
                                    wire:click="purchase('{{ $item->value }}')"
                                    wire:confirm="Beli {{ $item->label() }} seharga {{ number_format($item->price()) }} koin?"
                                    @disabled(! $canAfford)
                                    @class([
                                        'ui-btn-primary shrink-0',
                                        'opacity-50 cursor-not-allowed' => ! $canAfford,
                                    ])>
                                Beli Item
                            </button>
                        </div>

                        @unless ($canAfford)
                            <p class="mt-3 text-xs text-rose-600">
                                Butuh {{ number_format($item->price() - $balance) }} koin lagi. Selesaikan simulasi untuk mengumpulkan koin.
                            </p>
                        @endunless
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6">
            <h3 class="text-sm font-bold text-slate-900">Aturan Penggunaan</h3>
            <ul class="mt-3 space-y-2 text-sm text-slate-600">
                <li>Item hanya bisa dipakai di <strong>simulasi harian</strong>, bukan duel mini tryout atau ujian remedial.</li>
                <li><strong>Skip Tracker</strong> diaktifkan sekali di awal simulasi, lalu memberi peringatan setelah 60 detik di soal yang sama.</li>
                <li><strong>50:50 Eliminator</strong> hanya berlaku untuk soal TWK/TIU dan dipakai per soal.</li>
                <li>XP tetap untuk badge & leaderboard — tidak bisa ditukar ke koin.</li>
            </ul>
        </div>
    </main>
</div>
