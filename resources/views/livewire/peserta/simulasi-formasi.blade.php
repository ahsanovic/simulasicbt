<div class="min-h-screen bg-gradient-to-b from-slate-50 to-primary-50/20">
    <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <x-ui.flash-toast />

        <div class="relative mb-8 overflow-hidden rounded-2xl bg-gradient-to-r from-teal-600 via-primary-600 to-indigo-600 p-6 text-white shadow-xl shadow-primary-500/20 sm:p-8">
            <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
            <div class="relative">
                <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold text-primary-100 ring-1 ring-white/20">
                    Simulasi Strategis
                </div>
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">Simulasi Kelulusan Formasi</h1>
                <p class="mt-2 max-w-2xl text-primary-100">
                    Pilih target jabatan (opsional) dan bandingkan skor terbaik Anda dengan pelamar jabatan yang sama di aplikasi ini.
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(280px,32%)] lg:items-start">
            <section class="ui-card p-6">
                <h2 class="text-lg font-bold text-slate-900">Target Jabatan</h2>
                <p class="mt-1 text-sm text-slate-500">Jabatan bersifat opsional. Riwayat tes Anda tetap dihitung meski belum memilih jabatan.</p>

                <div class="mt-5">
                    <label for="formation-search" class="mb-1.5 block text-sm font-medium text-slate-700">Cari jabatan</label>
                    <x-ui.formation-autocomplete
                        :suggestions="$suggestions"
                        :search="$formationSearch"
                    />
                </div>

                @if (auth()->user()->formation)
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <span class="ui-badge bg-primary-50 text-primary-700">
                            {{ auth()->user()->formation->name }} · {{ auth()->user()->formation->group }}
                        </span>
                        <button type="button" wire:click="clearFormation" class="text-sm font-semibold text-rose-600 hover:text-rose-700">
                            Hapus target jabatan
                        </button>
                    </div>
                @endif
            </section>

            <aside class="space-y-4">
                @if ($analysis['user_scores'])
                    <div class="ui-card p-5">
                        <h3 class="text-sm font-bold text-slate-900">Skor Terbaik Anda</h3>
                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-slate-500">TWK</dt><dd class="font-bold tabular-nums text-slate-900">{{ $analysis['user_scores']['twk'] }}</dd></div>
                            <div><dt class="text-slate-500">TIU</dt><dd class="font-bold tabular-nums text-slate-900">{{ $analysis['user_scores']['tiu'] }}</dd></div>
                            <div><dt class="text-slate-500">TKP</dt><dd class="font-bold tabular-nums text-slate-900">{{ $analysis['user_scores']['tkp'] }}</dd></div>
                            <div><dt class="text-slate-500">Total</dt><dd class="font-bold tabular-nums text-primary-700">{{ $analysis['user_scores']['total'] }}</dd></div>
                        </dl>
                    </div>
                @endif

                @if ($analysis['averages'])
                    <div class="ui-card p-5">
                        <h3 class="text-sm font-bold text-slate-900">Rata-rata Pelamar Jabatan</h3>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $analysis['applicant_count'] }} pelamar</p>
                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-slate-500">TWK</dt><dd class="font-bold tabular-nums text-slate-900">{{ number_format($analysis['averages']['twk'], 1) }}</dd></div>
                            <div><dt class="text-slate-500">TIU</dt><dd class="font-bold tabular-nums text-slate-900">{{ number_format($analysis['averages']['tiu'], 1) }}</dd></div>
                            <div><dt class="text-slate-500">TKP</dt><dd class="font-bold tabular-nums text-slate-900">{{ number_format($analysis['averages']['tkp'], 1) }}</dd></div>
                            <div><dt class="text-slate-500">Total</dt><dd class="font-bold tabular-nums text-slate-900">{{ number_format($analysis['averages']['total'], 1) }}</dd></div>
                        </dl>
                    </div>
                @endif
            </aside>
        </div>

        <section class="mt-6">
            @if (! $analysis['has_history'])
                <div class="ui-card border-amber-200 bg-amber-50/50 p-6 text-center">
                    <p class="font-semibold text-amber-900">Belum ada riwayat simulasi</p>
                    <p class="mt-1 text-sm text-amber-800">{{ $analysis['message'] }}</p>
                    <a href="{{ route('peserta.dashboard') }}" wire:navigate class="ui-btn-success mt-4 inline-flex">Mulai simulasi</a>
                </div>
            @elseif ($analysis['formation'] === null)
                <div class="ui-card border-slate-200 bg-slate-50/80 p-6 text-center">
                    <p class="font-semibold text-slate-800">Pilih target jabatan</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $analysis['message'] }}</p>
                </div>
            @elseif ($analysis['insufficient_data'])
                <div class="ui-card border-slate-200 bg-slate-50/80 p-6">
                    <p class="font-semibold text-slate-800">Data pelamar masih terbatas</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $analysis['message'] }}</p>
                </div>
            @else
                <x-formation-matchmaking-zone :analysis="$analysis" />
            @endif
        </section>
    </main>

    @if ($showChangeConfirmation)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="cancelFormationChange"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <h2 class="text-lg font-bold text-slate-900">Ganti target jabatan?</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Mengganti target jabatan akan memindahkan perbandingan skor Anda ke jabatan baru. Posisi di jabatan sebelumnya tidak lagi ditampilkan.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancelFormationChange" class="ui-btn-secondary">Batal</button>
                    <button type="button" wire:click="confirmFormationChange" class="ui-btn-success">Ganti Jabatan</button>
                </div>
            </div>
        </div>
    @endif
</div>
