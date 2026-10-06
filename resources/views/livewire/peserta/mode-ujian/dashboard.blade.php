<div class="mx-auto max-w-2xl px-4 py-10">
    <div class="ui-card p-6 sm:p-8">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ $event->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">Mode Ujian</p>
            </div>
            <x-peserta.mode-ujian-logout-button />
        </div>

        <div class="mt-6 grid grid-cols-3 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
            <div>
                <p class="text-xs text-slate-500">Nama</p>
                <p class="font-semibold text-slate-900">{{ $participant->name }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">NIK</p>
                <p class="font-mono font-semibold text-slate-900">{{ $participant->nik }}</p>
            </div>
            <div>
                <p class="text-xs text-slate-500">Jabatan</p>
                <p class="font-semibold text-slate-900">{{ $participant->jabatan_label }}</p>
            </div>
        </div>

        <div class="mt-6 space-y-3">
            @if ($event->exam_mode->includesSkd())
                <div class="flex items-center justify-between rounded-xl border border-slate-200 p-4">
                    <div>
                        <p class="font-semibold text-slate-900">SKD</p>
                        @if ($skdRemainingSeconds !== null)
                            <div wire:ignore x-data="examTimer({{ max(0, $skdRemainingSeconds) }})">
                                <p class="text-xs text-slate-500">Sisa waktu <span class="font-mono font-semibold text-rose-600" x-text="formattedTime"></span></p>
                            </div>
                        @else
                            <p class="text-xs text-slate-500">Durasi {{ $event->exam?->duration_minutes }} menit</p>
                        @endif
                    </div>
                    @if ($skdDone)
                        <span class="ui-badge bg-emerald-50 text-emerald-700">Selesai</span>
                    @elseif ($skdAttempt)
                        <button wire:click="openPinModal('skd')" class="ui-btn-primary">Lanjutkan SKD</button>
                    @else
                        <button wire:click="openPinModal('skd')" class="ui-btn-primary">Mulai SKD</button>
                    @endif
                </div>
            @endif

            @if ($event->exam_mode->includesSkb())
                <div class="flex items-center justify-between rounded-xl border border-slate-200 p-4 {{ ! $skbUnlocked ? 'opacity-50' : '' }}">
                    <div>
                        <p class="font-semibold text-slate-900">SKB</p>
                        @if ($skbRemainingSeconds !== null)
                            <div wire:ignore x-data="examTimer({{ max(0, $skbRemainingSeconds) }})">
                                <p class="text-xs text-slate-500">Sisa waktu <span class="font-mono font-semibold text-rose-600" x-text="formattedTime"></span></p>
                            </div>
                        @else
                            <p class="text-xs text-slate-500">Durasi {{ $event->skb_duration_minutes }} menit &middot; {{ $event->skb_question_count }} soal</p>
                        @endif
                    </div>
                    @if ($skbDone)
                        <span class="ui-badge bg-emerald-50 text-emerald-700">Selesai</span>
                    @elseif (! $skbUnlocked)
                        <span class="ui-badge bg-slate-100 text-slate-500">Selesaikan SKD dulu</span>
                    @elseif ($skbAttempt)
                        <button wire:click="openPinModal('skb')" class="ui-btn-primary">Lanjutkan SKB</button>
                    @else
                        <button wire:click="openPinModal('skb')" class="ui-btn-primary">Mulai SKB</button>
                    @endif
                </div>
            @endif
        </div>

        @if ($skdDone && (! $event->exam_mode->includesSkb() || $skbDone))
            <div class="mt-6 rounded-xl bg-emerald-50 p-4 text-center text-sm font-semibold text-emerald-700">
                Semua ujian sudah selesai. Terima kasih.
            </div>
        @endif
    </div>

    @if ($showPinModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closePinModal"></div>
            <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                <h2 class="text-lg font-bold text-slate-900">PIN Sesi {{ strtoupper($pinPhase) }}</h2>
                <p class="mt-1 text-sm text-slate-500">Minta PIN sesi kepada pengawas ujian.</p>
                {{-- The button stays disabled from the click until the exam room opens:
                     re-enabled only when the server answers with an error, or after
                     30 s as a safety net (pressing again just resumes the same exam). --}}
                <form wire:submit="submitPin" class="mt-4"
                      x-data="{ busy: false, timer: null }"
                      x-on:submit="busy = true; clearTimeout(timer); timer = setTimeout(() => busy = false, 30000)"
                      x-on:pin-failed.window="busy = false; clearTimeout(timer)">
                    <input type="text" wire:model="pinInput" class="ui-input text-center text-lg tracking-widest" autofocus placeholder="PIN">
                    @if ($pinError)
                        <p class="mt-2 text-xs text-rose-600" x-show="! busy">{{ $pinError }}</p>
                    @endif
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" wire:click="closePinModal" class="ui-btn-secondary" x-show="! busy">Batal</button>
                        <button type="submit" class="ui-btn-primary" x-bind:disabled="busy">
                            <svg x-show="busy" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                            <span x-text="busy ? 'Menyiapkan soal…' : 'Mulai'">Mulai</span>
                        </button>
                    </div>
                    <p x-show="busy" x-cloak class="mt-3 text-center text-xs text-slate-500">Mohon tunggu, jangan muat ulang halaman.</p>
                </form>
            </div>
        </div>
    @endif
</div>
