<div>
    <x-ui.page-header title="Hasil Testimoni" description="Pantau cerita peserta, fitur andalan yang disukai, dan reaksi dari sesama pejuang CPNS." />

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-ui.stat-card
            label="Total Testimoni"
            :value="number_format($stats['total'])"
            color="violet"
            trend="Cerita yang sudah dikirim"
            icon="testimonials"
        />
        <x-ui.stat-card
            label="Rating Rata-rata"
            :value="$stats['average_rating'] ? number_format($stats['average_rating'], 1).'/5' : '—'"
            color="amber"
            trend="Skor kepuasan peserta"
            icon="results"
        />
        <x-ui.stat-card
            label="Total Reaksi"
            :value="number_format($stats['reactions'])"
            color="amber"
            trend="❤️ dan 🔥 dari peserta"
            icon="results"
        />
        <x-ui.stat-card
            label="Anonim"
            :value="number_format($stats['anonymous'])"
            color="primary"
            trend="Testimoni tanpa identitas"
            icon="shield-check"
        />
        <x-ui.stat-card
            label="My Turning Point"
            :value="number_format($stats['with_turning_point'])"
            color="emerald"
            trend="Cerita progres nilai"
            icon="reports"
        />
    </div>

    <div class="ui-card mb-5 p-4 sm:p-5">
        <x-ui.filter-toolbar>
            <div class="relative min-w-0 w-full sm:max-w-md sm:flex-1">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama peserta, instansi, atau isi cerita..." class="ui-input pl-10">
            </div>
        </x-ui.filter-toolbar>
    </div>

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peserta</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Rating</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Instansi Target</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Cerita</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Fitur</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Reaksi</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Dikirim</th>
                        <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($testimonials as $testimonial)
                        <tr wire:key="testimonial-{{ $testimonial->id }}" class="transition hover:bg-slate-50/50">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700">
                                        {{ $testimonial->user->initials() }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $testimonial->user->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $testimonial->user->email }}</p>
                                        @if ($testimonial->is_anonymous)
                                            <span class="ui-badge mt-1 bg-slate-100 text-slate-600">Anonim · {{ $testimonialService->displayName($testimonial) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-center">
                                @if ($testimonial->rating)
                                    <x-star-rating :rating="$testimonial->rating" size="sm" show-value class="justify-center" />
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-slate-600">{{ $testimonial->target_instansi }}</td>
                            <td class="max-w-xs px-5 py-4">
                                <p class="line-clamp-2 text-slate-600">{{ $testimonial->story }}</p>
                                @if ($testimonial->turning_point)
                                    <span class="ui-badge mt-1.5 bg-emerald-50 text-emerald-700">Ada Turning Point</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex max-w-[12rem] flex-wrap gap-1">
                                    @foreach ($testimonial->resolvedFeatureTags() as $tag)
                                        <span class="ui-badge bg-violet-50 text-violet-700">{{ $tag->hashtag() }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <div class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700">
                                    <span>❤️ {{ number_format($testimonial->hearts_count) }}</span>
                                    <span>🔥 {{ number_format($testimonial->fires_count) }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-slate-500">{{ $testimonial->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="px-5 py-4 text-right">
                                <button type="button" wire:click="viewDetail({{ $testimonial->id }})" class="ui-btn-ghost px-3 py-1.5">Detail</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center text-slate-500">Belum ada testimoni dari peserta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($testimonials->hasPages())
        <div class="mt-5">
            {{ $testimonials->links() }}
        </div>
    @endif

    @if ($viewingTestimonial)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeDetail"></div>
            <div class="relative w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div class="sticky top-0 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Detail Testimoni</h2>
                        <p class="text-sm text-slate-500">{{ $viewingTestimonial->created_at->translatedFormat('d F Y, H:i') }}</p>
                    </div>
                    <button type="button" wire:click="closeDetail" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-5 p-6">
                    <div class="flex items-start gap-4 rounded-xl border border-slate-100 bg-slate-50/80 p-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-violet-100 text-sm font-bold text-violet-700">
                            {{ $viewingTestimonial->user->initials() }}
                        </div>
                        <div>
                            <p class="font-bold text-slate-900">{{ $viewingTestimonial->user->name }}</p>
                            <p class="text-sm text-slate-500">{{ $viewingTestimonial->user->email }}</p>
                            @if ($viewingTestimonial->user->instansi)
                                <p class="mt-1 text-xs text-slate-500">{{ $viewingTestimonial->user->instansi->nama }}</p>
                            @endif
                            @if ($viewingTestimonial->is_anonymous)
                                <p class="mt-2 text-sm text-slate-600">
                                    Ditampilkan sebagai: <strong>{{ $testimonialService->displayName($viewingTestimonial) }}</strong>
                                </p>
                            @endif
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Rating Pengalaman</p>
                        <div class="mt-2">
                            @if ($viewingTestimonial->rating)
                                <x-star-rating :rating="$viewingTestimonial->rating" size="lg" show-value />
                            @else
                                <p class="mt-1 text-sm text-slate-500">Belum ada rating</p>
                            @endif
                        </div>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Formasi & Instansi Target</p>
                        <p class="mt-1 text-sm font-semibold text-violet-700">{{ $viewingTestimonial->target_instansi }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cerita Pengalaman</p>
                        <p class="mt-2 text-sm leading-relaxed text-slate-700">{{ $viewingTestimonial->story }}</p>
                    </div>

                    @if ($viewingTestimonial->turning_point)
                        <div class="rounded-xl border border-emerald-100 bg-emerald-50/80 p-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">My Turning Point</p>
                            <p class="mt-2 text-sm leading-relaxed text-emerald-900">{{ $viewingTestimonial->turning_point }}</p>
                        </div>
                    @endif

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">#FiturAndalan</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($viewingTestimonial->resolvedFeatureTags() as $tag)
                                <span class="ui-badge bg-violet-50 text-violet-700">{{ $tag->hashtag() }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-4 rounded-xl border border-slate-100 bg-slate-50/50 p-4 text-sm">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Reaksi</p>
                            <p class="mt-1 font-semibold text-slate-800">❤️ {{ number_format($viewingTestimonial->hearts_count) }} · 🔥 {{ number_format($viewingTestimonial->fires_count) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Skor Popularitas</p>
                            <p class="mt-1 font-semibold text-slate-800">{{ number_format($viewingTestimonial->reactionsScore()) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
