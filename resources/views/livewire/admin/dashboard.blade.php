<div>
    <x-ui.page-header title="Dashboard" description="Ringkasan aktivitas dan statistik simulasi ujian.">
        <span class="ui-badge bg-primary-100 text-primary-700">{{ now()->translatedFormat('l, d F Y') }}</span>
    </x-ui.page-header>

    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-5">
        <x-ui.stat-card
            label="Total Pengguna"
            :value="number_format($stats['users'])"
            color="primary"
            trend="Admin & peserta terdaftar"
            icon="users"
        />
        <x-ui.stat-card
            label="Bank Soal"
            :value="number_format($stats['questions'])"
            color="emerald"
            trend="TWK, TIU, dan TKP"
            icon="questions"
        />
        <x-ui.stat-card
            label="Paket Ujian"
            :value="number_format($stats['exams'])"
            color="violet"
            trend="Ujian yang dibuat"
            icon="exams"
        />
        <x-ui.stat-card
            label="Ujian Selesai"
            :value="number_format($stats['attempts'])"
            color="amber"
            trend="Total attempt submitted"
            icon="results"
        />
        <x-ui.stat-card
            label="Testimoni"
            :value="number_format($stats['testimonials'])"
            color="violet"
            :trend="$stats['testimonial_avg_rating'] ? 'Rata-rata '.$stats['testimonial_avg_rating'].'/5 bintang' : 'Cerita dari peserta'"
            icon="testimonials"
        />
    </div>

    <div class="mt-8 grid gap-5 lg:grid-cols-2">
        <div class="ui-card p-6">
            <h2 class="text-base font-bold text-slate-900">Selamat datang kembali</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-500">
                Kelola bank soal, jadwalkan ujian, dan pantau hasil peserta dari panel admin ini.
                Gunakan menu sidebar untuk navigasi cepat antar modul.
            </p>
        </div>
        <div class="ui-card overflow-hidden p-0">
            <div class="border-b border-slate-100 bg-gradient-to-r from-primary-600 to-indigo-600 px-6 py-5">
                <h2 class="text-base font-bold text-white">Akses Cepat</h2>
                <p class="mt-1 text-sm text-primary-100">Modul yang sering digunakan</p>
            </div>
            <div class="grid grid-cols-2 gap-px bg-slate-100">
                @foreach([
                    ['admin.questions.index', 'Tambah Soal'],
                    ['admin.exams.index', 'Buat Ujian'],
                    ['admin.users.index', 'Kelola Peserta'],
                    ['admin.results.index', 'Lihat Hasil'],
                    ['admin.testimonials.index', 'Hasil Testimoni'],
                ] as [$route, $label])
                    <a href="{{ route($route) }}" wire:navigate class="bg-white px-5 py-4 text-sm font-semibold text-slate-700 transition hover:bg-primary-50 hover:text-primary-700">
                        {{ $label }} →
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    @if ($stats['testimonials'] > 0)
        <div class="mt-8 grid gap-5 lg:grid-cols-2">
            <div class="ui-card p-6">
                <div class="mb-5 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Rating Testimoni</h2>
                        <p class="mt-0.5 text-sm text-slate-500">Distribusi kepuasan peserta terhadap platform</p>
                    </div>
                    @if ($stats['testimonial_avg_rating'])
                        <div class="text-right">
                            <p class="text-3xl font-bold text-amber-600">{{ number_format($stats['testimonial_avg_rating'], 1) }}</p>
                            <x-star-rating :rating="(int) round($stats['testimonial_avg_rating'])" size="sm" class="justify-end" />
                        </div>
                    @endif
                </div>

                <div class="space-y-2.5">
                    @php $ratedTotal = max(1, array_sum($stats['testimonial_rating_distribution'])); @endphp
                    @foreach ($stats['testimonial_rating_distribution'] as $star => $count)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-8 font-semibold text-slate-600">{{ $star }}★</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-amber-400" style="width: {{ round(($count / $ratedTotal) * 100) }}%"></div>
                            </div>
                            <span class="w-8 text-right font-medium text-slate-500">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="ui-card overflow-hidden">
                <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                    <h2 class="text-lg font-bold text-slate-900">Testimoni Terbaru</h2>
                    <p class="text-sm text-slate-500">5 ulasan terakhir dari peserta</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($stats['recent_testimonials'] as $testimonial)
                        <div class="px-6 py-4" wire:key="recent-testimonial-{{ $testimonial->id }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ $testimonial->user->name }}</p>
                                    <p class="mt-0.5 line-clamp-2 text-sm text-slate-600">{{ $testimonial->story }}</p>
                                </div>
                                <x-star-rating :rating="$testimonial->rating" size="sm" class="shrink-0" />
                            </div>
                        </div>
                    @empty
                        <p class="px-6 py-8 text-center text-sm text-slate-500">Belum ada testimoni ber-rating.</p>
                    @endforelse
                </div>
                <div class="border-t border-slate-100 bg-slate-50/50 px-6 py-3 text-right">
                    <a href="{{ route('admin.testimonials.index') }}" wire:navigate class="text-sm font-semibold text-primary-600 hover:text-primary-700">
                        Lihat semua testimoni →
                    </a>
                </div>
            </div>
        </div>
    @endif

    <div class="mt-8">
        <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-bold text-slate-900">Leaderboard Peserta</h2>
            <span class="ui-badge bg-primary-100 text-primary-700">Live · refresh 15 detik</span>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <livewire:peserta.live-leaderboard />
            <livewire:peserta.duel-leaderboard />
            <livewire:peserta.xp-leaderboard />
        </div>
    </div>

    <div class="mt-8">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Rekap Pilihan Jabatan Peserta</h2>
                <p class="mt-0.5 text-sm text-slate-500">Ringkasan distribusi target jabatan yang sudah dipilih peserta</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="ui-badge bg-emerald-100 text-emerald-700">
                    {{ number_format($stats['formation_recap']['selected_count']) }} sudah memilih
                </span>
                <span class="ui-badge bg-slate-100 text-slate-700">
                    {{ number_format($stats['formation_recap']['unselected_count']) }} belum memilih
                </span>
            </div>
        </div>

        @if ($stats['formation_recap']['group_summary']->isNotEmpty())
            <div class="ui-card overflow-hidden">
                <div class="grid divide-y divide-slate-100 lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                    <div class="p-6">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Distribusi per Rumpun</h3>
                        <div class="mt-4 space-y-3">
                            @foreach ($stats['formation_recap']['group_summary'] as $groupSummary)
                                <a
                                    href="{{ route('admin.formations.index', ['groupFilter' => $groupSummary['group']]) }}"
                                    wire:navigate
                                    class="group block rounded-lg p-2 transition hover:bg-slate-50"
                                    wire:key="formation-group-{{ md5($groupSummary['group']) }}"
                                >
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate font-medium text-slate-700 group-hover:text-primary-700">{{ $groupSummary['group'] }}</span>
                                        <span class="shrink-0 font-semibold text-slate-900">{{ $groupSummary['peserta_count'] }}</span>
                                    </div>
                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            class="h-full rounded-full bg-teal-500 transition group-hover:bg-teal-600"
                                            style="width: {{ round(($groupSummary['peserta_count'] / $stats['formation_recap']['max_group_count']) * 100) }}%"
                                        ></div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="p-6">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500">Top Jabatan Terpilih</h3>
                        <div class="mt-4 divide-y divide-slate-100">
                            @foreach ($stats['formation_recap']['top_formations'] as $index => $formation)
                                <div class="flex items-center gap-3 py-3 first:pt-0" wire:key="formation-top-{{ $formation->id }}">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-50 text-xs font-bold text-teal-700">
                                        {{ $index + 1 }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-slate-900">{{ $formation->name }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $formation->group }}</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-3">
                                        <span class="text-sm font-semibold text-slate-700">{{ $formation->peserta_count }}</span>
                                        <a
                                            href="{{ route('admin.users.index', ['formationFilter' => $formation->id]) }}"
                                            wire:navigate
                                            class="text-xs font-semibold text-primary-600 hover:text-primary-700"
                                        >
                                            Lihat →
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/50 px-6 py-3">
                    <p class="text-sm text-slate-500">
                        {{ number_format($stats['formation_recap']['total_formations']) }} jabatan sudah dipilih peserta
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a href="{{ route('admin.formations.index') }}" wire:navigate class="text-sm font-semibold text-primary-600 hover:text-primary-700">
                            Kelola jabatan →
                        </a>
                        <a href="{{ route('admin.users.index') }}" wire:navigate class="text-sm font-semibold text-primary-600 hover:text-primary-700">
                            Filter peserta →
                        </a>
                    </div>
                </div>
            </div>
        @else
            <div class="ui-card px-6 py-12 text-center">
                <p class="text-sm text-slate-500">Belum ada peserta yang memilih target jabatan.</p>
            </div>
        @endif
    </div>
</div>
