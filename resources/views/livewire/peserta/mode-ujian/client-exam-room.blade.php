{{-- Browser exam room (Mode Ujian). The questions arrive once in the data
     script; wire:ignore keeps the room as the browser built it on every later
     Livewire request (background deadline poll). --}}
<div class="min-h-screen bg-slate-100">
    @unless ($timeUp)
        <div x-data="examDeadlinePoll(30000)" class="hidden"></div>
    @endunless

    <x-peserta.mode-ujian-guard />
    <x-ui.flash-toast />

    <div wire:ignore>
        @if ($room)
            <script type="application/json" data-room>{!! json_encode($room, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
            <div x-data="modeUjianRoom" data-answers-url="{{ $answersUrl }}" data-finish-url="{{ $finishUrl }}" data-remaining="{{ $remaining }}">
                <header class="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm">
                    <div class="mx-auto flex max-w-screen-2xl flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wider text-primary-600">Sedang Ujian</p>
                            <h1 class="truncate text-lg font-bold text-slate-900" x-text="title"></h1>
                            <p class="flex flex-wrap items-center gap-x-1.5 text-sm text-slate-500">
                                <x-peserta.exam-identity :name="$participantName" :nik="$participantNik" />
                                <span>Soal <span class="font-semibold text-slate-800" x-text="index + 1"></span> dari <span x-text="items.length"></span></span>
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                            <div class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2.5">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-rose-100">
                                    <svg class="h-4 w-4 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div x-data="examTimer({{ $remaining }}, { syncDeadline: true })">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Sisa Waktu</p>
                                    <p class="text-xl font-bold tabular-nums text-rose-700" x-text="formattedTime"></p>
                                </div>
                            </div>

                            <div class="flex gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm">
                                <div><span class="text-slate-500">Dijawab</span> <span class="ml-1.5 font-bold text-emerald-600" x-text="answeredCount"></span></div>
                                <div class="border-l border-slate-200 pl-4"><span class="text-slate-500">Belum</span> <span class="ml-1.5 font-bold text-amber-600" x-text="unansweredCount"></span></div>
                            </div>

                            <button type="button" class="ui-btn-danger shrink-0" x-on:click="confirmFinish()" x-bind:disabled="locked">Selesai Ujian</button>
                        </div>
                    </div>
                </header>

                {{-- Unsent answers / connection --}}
                <div x-show="unsentCount > 0 && (offline || failures > 0)" x-cloak class="fixed inset-x-0 top-0 z-[9000] flex justify-center p-3" role="alert" aria-live="assertive">
                    <div class="w-full max-w-2xl rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-lg">
                        <p class="font-semibold"><span x-text="unsentCount"></span> jawaban belum terkirim</p>
                        <p class="mt-0.5">Jawaban Anda tetap tersimpan di komputer ini dan dikirim otomatis begitu koneksi pulih. Tetap kerjakan; jangan tutup halaman.</p>
                    </div>
                </div>

                <main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8" data-exam-content>
                    <div class="grid gap-6 xl:grid-cols-[260px_1fr] 2xl:grid-cols-[280px_1fr]">
                        <aside class="ui-card h-fit p-5 xl:sticky xl:top-24">
                            <h2 class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Navigasi Soal</h2>
                            <div class="grid grid-cols-5 gap-2 sm:grid-cols-6 xl:grid-cols-5">
                                <template x-for="(item, position) in items" :key="item.id">
                                    <button type="button" x-on:click="go(position)" :class="navClass(item, position)" x-text="position + 1"></button>
                                </template>
                            </div>
                            <div class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
                                <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-emerald-500"></span> Sudah dijawab</div>
                                <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-rose-500"></span> Belum dijawab</div>
                                <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-amber-400"></span> Ditandai</div>
                                <div class="flex items-center gap-2"><span class="h-3 w-3 rounded bg-primary-600"></span> Soal aktif</div>
                            </div>
                        </aside>

                        <div class="min-w-0 space-y-5">
                            <div class="ui-card p-4">
                                <div class="mb-2 flex items-center justify-between text-sm">
                                    <span class="font-semibold text-slate-700">Progress Pengerjaan</span>
                                    <span class="font-bold text-primary-600" x-text="progress + '%'"></span>
                                </div>
                                <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-gradient-to-r from-primary-500 to-indigo-500 transition-all duration-500 ease-out" :style="`width: ${progress}%`"></div>
                                </div>
                            </div>

                            <template x-if="current">
                                <div class="ui-card p-6 sm:p-8">
                                    <div class="mb-5 flex flex-wrap items-center gap-2">
                                        <span class="ui-badge bg-slate-100 text-slate-700" x-text="'Soal ' + current.number"></span>
                                        <span class="ui-badge bg-primary-50 text-primary-700" x-show="current.badge" x-text="current.badge"></span>
                                        <span class="ui-badge bg-amber-100 text-amber-800" x-show="current.marked">★ Ditandai</span>
                                    </div>

                                    <div class="prose-exam mb-8 text-base" x-html="current.content"></div>

                                    <div class="space-y-3">
                                        <template x-for="option in current.options" :key="option.id">
                                            <label class="exam-option">
                                                <span class="exam-option-letter" x-text="option.label"></span>
                                                <input type="radio" class="sr-only" :name="'q' + current.id" :value="option.id"
                                                       :checked="current.option_id === option.id"
                                                       x-on:change="pick(option.id)" :disabled="locked">
                                                <span class="flex-1 pt-1 text-sm leading-relaxed text-slate-800">
                                                    <template x-if="option.image">
                                                        <img :src="option.image" :alt="'Pilihan ' + option.label" class="max-h-48 max-w-full rounded-lg object-contain">
                                                    </template>
                                                    <template x-if="! option.image">
                                                        <span x-html="option.content"></span>
                                                    </template>
                                                </span>
                                            </label>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <div class="ui-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <button type="button" class="ui-btn-secondary order-2 sm:order-1 disabled:opacity-40" x-on:click="prev()" :disabled="index === 0 || locked">Sebelumnya</button>
                                <div class="flex flex-col gap-2 sm:order-2 sm:flex-row">
                                    <button type="button" class="ui-btn-secondary" :class="current?.marked && 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100'"
                                            x-on:click="toggleMark()" x-text="current?.marked ? '★ Hapus Tanda' : '☆ Tandai Soal'" :disabled="locked"></button>
                                    <button type="button" class="ui-btn-primary disabled:cursor-not-allowed disabled:opacity-50"
                                            x-on:click="next()" :disabled="locked || current?.option_id === null">Simpan &amp; Lanjutkan</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>

                {{-- Last question --}}
                <div x-show="showLastModal" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" x-on:keydown.escape.window="showLastModal = false">
                    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" x-on:click="showLastModal = false"></div>
                    <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl sm:p-8">
                        <h2 class="text-lg font-bold text-slate-900">Soal Terakhir</h2>
                        <p class="mt-2 text-sm text-slate-600" x-show="unansweredCount === 0">Semua soal sudah dijawab. Selesaikan ujian untuk melihat skor.</p>
                        <p class="mt-2 text-sm text-amber-900" x-show="unansweredCount > 0">Masih ada <span class="font-bold" x-text="unansweredCount"></span> soal belum dijawab.</p>
                        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                            <button type="button" class="ui-btn-secondary" x-on:click="go(Math.max(0, items.findIndex((item) => item.option_id === null)))">Kembali ke Soal</button>
                            <button type="button" class="ui-btn-danger" x-on:click="showLastModal = false; confirmFinish()">Selesai Ujian</button>
                        </div>
                    </div>
                </div>

                {{-- Locks --}}
                <div x-show="deviceReplaced || sessionExpired" x-cloak class="fixed inset-0 z-[10000] flex items-center justify-center bg-slate-900/90 p-4" role="alertdialog" aria-modal="true">
                    <div class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-xl sm:p-8">
                        <h2 class="text-xl font-bold text-slate-900" x-text="deviceReplaced ? 'Ujian dibuka di tab atau perangkat lain' : 'Sesi login berakhir'"></h2>
                        <p class="mt-2 text-sm text-slate-600" x-show="deviceReplaced">Lanjutkan ujian di tab/perangkat yang terakhir dibuka. Halaman ini tidak lagi menyimpan jawaban.</p>
                        <p class="mt-2 text-sm text-slate-600" x-show="sessionExpired">Jawaban Anda tetap tersimpan di komputer ini. Silakan login ulang dan masuk ujian kembali; jawaban dikirim otomatis.</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <x-peserta.exam-time-up-overlay :time-up="$timeUp" :result-url="$resultUrl" :calls-server="false" />
</div>
