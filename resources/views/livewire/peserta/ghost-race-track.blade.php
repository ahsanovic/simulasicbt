<div>
    @if ($state->visible)
        <section class="mb-8 ui-card relative overflow-hidden border-amber-200/80 bg-gradient-to-b from-white via-amber-50/40 to-orange-50/30 shadow-lg shadow-amber-200/30">
            <div class="border-b border-amber-100/80 bg-gradient-to-r from-amber-400 via-orange-500 to-rose-500 px-4 py-3 sm:px-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-2.5">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white/25 ring-1 ring-white/30 shadow-sm">
                            <span class="text-sm" aria-hidden="true">🏁</span>
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <h2 class="text-sm font-bold text-white drop-shadow-sm">Adu Progres</h2>
                                <span class="rounded-md bg-white/20 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-50 ring-1 ring-white/25">
                                    Ghost Race
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-[10px] font-semibold text-amber-50/95">
                                {{ $state->formationName ?? 'Mode Latihan' }}
                                · {{ $state->tier->label() }}
                            </p>
                            <p class="mt-1.5 text-[11px] leading-relaxed text-white/90 sm:text-xs">
                                Bandingkan perjalanan belajar Anda dengan rival anonim di lintasan yang sama.
                                Posisi ditentukan dari skor SKD, aktivitas minggu ini, dan kesiapan ujian.
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-2">
                        @if ($state->gapPoints > 0)
                            <span class="rounded-lg bg-white/25 px-2.5 py-1 text-[10px] font-bold text-white shadow-sm ring-1 ring-white/30">
                                −{{ $state->gapPoints }} poin
                            </span>
                        @else
                            <span class="rounded-lg bg-emerald-500/30 px-2.5 py-1 text-[10px] font-bold text-white shadow-sm ring-1 ring-white/25">
                                Memimpin 🎉
                            </span>
                        @endif
                        <button type="button"
                                wire:click="toggleNotifications"
                                class="rounded-lg bg-white/15 px-2 py-1 text-[9px] font-semibold text-white ring-1 ring-white/20 transition hover:bg-white/25"
                                title="{{ $state->notificationsMuted ? 'Aktifkan notifikasi rival' : 'Matikan notifikasi rival' }}">
                            {{ $state->notificationsMuted ? '🔕 Notif mati' : '🔔 Notif aktif' }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-5">
                @if ($state->weeklyRecap)
                    <div class="mb-4 rounded-xl border border-amber-200/80 bg-gradient-to-r from-amber-50 to-orange-50 px-3.5 py-3 ring-1 ring-amber-100">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-amber-700">Rekap Minggu Ini</p>
                        <p class="mt-1 text-sm font-semibold leading-relaxed text-slate-800">{{ $state->weeklyRecap->message() }}</p>
                        @if ($state->weeklyRecap->trackedSinceToday)
                            <p class="mt-1 text-[10px] text-slate-500">Data rekap mulai dihitung sejak kunjungan pertama Anda minggu ini.</p>
                        @endif
                    </div>
                @endif

                @if (count($state->availableRivals) > 0)
                    <div class="mb-4">
                        <label for="ghost-race-rival" class="mb-1.5 block text-[10px] font-bold uppercase tracking-wide text-slate-500">
                            Pilih Rival
                        </label>
                        <select id="ghost-race-rival"
                                wire:change="selectRival($event.target.value)"
                                class="w-full rounded-xl border-amber-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 shadow-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-200">
                            <option value="" @selected($state->selectedRivalUserId === null)>
                                Rival terdepan (otomatis)
                            </option>
                            @foreach ($state->availableRivals as $rival)
                                <option value="{{ $rival['user_id'] }}" @selected($rival['is_selected'])>
                                    #{{ $rival['rank'] }} {{ $rival['alias'] }} · {{ $rival['race_score'] }}%
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-[10px] text-slate-400">Pilih pelamar rank ±3 terdekat dengan Anda di formasi ini.</p>
                    </div>
                @endif

                <p class="mb-3 text-xs leading-relaxed text-slate-500">
                    <span class="font-semibold text-slate-700">Cara baca lintasan:</span>
                    mobil biru (Anda) dan hantu rival bergerak dari kiri ke kanan menuju trofi target kelulusan.
                    Semakin rajin belajar dan skor SKD meningkat, semakin dekat Anda ke garis finish.
                </p>

                <div class="relative mx-1 h-20 rounded-2xl bg-gradient-to-r from-amber-50/80 via-white to-orange-50/80 ring-1 ring-amber-200/60">
                    <div class="absolute inset-x-6 top-1/2 h-1.5 -translate-y-1/2 rounded-full bg-gradient-to-r from-amber-200 via-orange-300 to-rose-300"></div>

                    @foreach ([25, 50, 75] as $checkpoint)
                        <div class="absolute top-1/2 h-4 w-px -translate-y-1/2 bg-amber-300/80"
                             style="left: calc({{ $checkpoint }}% + 1.5rem)"></div>
                    @endforeach

                    <div class="absolute left-3 top-1/2 flex -translate-y-1/2 flex-col items-center gap-0.5">
                        <span class="text-sm leading-none" aria-hidden="true">🏁</span>
                        <span class="text-[8px] font-bold uppercase tracking-wide text-amber-700">Start</span>
                    </div>

                    <div class="absolute right-3 top-1/2 flex -translate-y-1/2 flex-col items-center">
                        <span class="text-base" aria-hidden="true">🏆</span>
                        <span class="text-[8px] font-bold uppercase text-amber-700">Target</span>
                    </div>

                    <div class="absolute top-2 flex -translate-x-1/2 flex-col items-center transition-all duration-700 ease-out"
                         style="left: calc({{ max(8, min(92, $state->ghostPosition)) }}% * 0.84 + 8%)">
                        <span class="text-xl drop-shadow-sm" title="{{ $state->ghost->alias }}">👻</span>
                        <span class="mt-0.5 rounded-md bg-orange-100 px-1.5 py-0.5 text-[9px] font-bold tabular-nums text-orange-800 ring-1 ring-orange-200/60">
                            {{ $state->ghost->alias }}
                        </span>
                        <span class="text-[9px] font-bold tabular-nums text-orange-600">{{ $state->ghostPosition }}%</span>
                    </div>

                    <div class="absolute bottom-1 flex -translate-x-1/2 flex-col items-center transition-all duration-700 ease-out"
                         style="left: calc({{ max(8, min(92, $state->userPosition)) }}% * 0.84 + 8%)">
                        <span class="text-[9px] font-bold tabular-nums text-sky-600">{{ $state->userPosition }}%</span>
                        <span class="inline-block -scale-x-100 text-xl drop-shadow-sm" title="Anda">🚗</span>
                        <span class="mt-0.5 text-[9px] font-bold text-sky-700">Anda</span>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2">
                    @foreach ([
                        ['label' => 'SKD', 'value' => $state->userScore->skdComponent, 'ghost' => $state->ghost->score->skdComponent],
                        ['label' => 'Aktivitas', 'value' => $state->userScore->activityComponent, 'ghost' => $state->ghost->score->activityComponent],
                        ['label' => 'Kesiapan', 'value' => $state->userScore->readinessComponent, 'ghost' => $state->ghost->score->readinessComponent],
                    ] as $item)
                        <div class="rounded-xl bg-white/95 px-2.5 py-2 text-center ring-1 ring-amber-100">
                            <p class="text-[9px] font-bold uppercase tracking-wide text-slate-400">{{ $item['label'] }}</p>
                            <p class="mt-0.5 text-sm font-extrabold tabular-nums text-slate-800">{{ $item['value'] }}%</p>
                            <p class="text-[9px] text-orange-500/80">
                                Rival {{ $item['ghost'] }}%
                            </p>
                        </div>
                    @endforeach
                </div>

                @if ($state->checkpoint)
                    <p class="mt-3 text-xs text-slate-500">
                        Checkpoint berikutnya: <span class="font-semibold text-amber-700">{{ $state->checkpoint['label'] }}</span>
                        ({{ $state->checkpoint['position'] }}%)
                    </p>
                @endif

                <p class="mt-2 text-xs leading-relaxed text-slate-600">{{ $state->message }}</p>

                @if ($state->ghost->lastActivity)
                    <p class="mt-1 text-[11px] font-medium text-orange-600">
                        Aktivitas rival terakhir: {{ $state->ghost->lastActivity }}
                    </p>
                @endif

                @if ($state->cta)
                    <a href="{{ $state->cta['url'] }}"
                       wire:navigate
                       class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-orange-500 to-rose-500 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-orange-300/50 transition hover:from-orange-600 hover:to-rose-600">
                        Kejar Rival → {{ $state->cta['label'] }}
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <p class="mt-1.5 text-center text-[10px] text-slate-400">{{ $state->cta['reason'] }}</p>
                @endif
            </div>
        </section>
    @endif
</div>
