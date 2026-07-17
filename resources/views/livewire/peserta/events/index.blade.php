<div class="min-h-screen bg-gradient-to-b from-slate-50 to-primary-50/20">
    <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <x-ui.flash-toast />

        @if ($activeAttempt)
            <div class="mb-6 rounded-2xl border-2 border-rose-300 bg-gradient-to-r from-rose-50 to-orange-50 p-4 shadow-sm sm:p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-rose-700">Ujian Sesi Sedang Berlangsung</p>
                        <p class="mt-1 truncate text-base font-bold text-slate-900">
                            {{ $activeAttempt->event->name }} — {{ $activeAttempt->eventSession?->name }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600">
                            Anda terputus? Lanjutkan tanpa memasukkan kode lagi — jawaban Anda tersimpan.
                            Sisa waktu: <span class="font-bold tabular-nums text-rose-700">{{ format_exam_remaining_time($activeAttempt->remainingSeconds()) }}</span>
                        </p>
                    </div>
                    <button wire:click="resume({{ $activeAttempt->event_session_id }})" class="ui-btn-success shrink-0 px-6">
                        Lanjutkan Ujian →
                    </button>
                </div>
            </div>
        @endif

        <div class="mb-8 rounded-2xl bg-gradient-to-r from-primary-600 to-indigo-600 p-6 text-white shadow-xl shadow-primary-500/20 sm:p-8">
            <h1 class="text-2xl font-bold tracking-tight">Event Offline</h1>
            <p class="mt-2 text-sm text-primary-100 sm:text-base">
                Pilih sesi yang sedang berlangsung lalu masukkan kode dari panitia untuk mulai mengerjakan.
            </p>
        </div>

        @if ($events->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-12 text-center">
                <p class="text-slate-500">Belum ada sesi yang sedang berlangsung. Silakan tanyakan ke panitia.</p>
            </div>
        @else
            <div class="space-y-6">
                @foreach ($events as $event)
                    <div wire:key="event-{{ $event->id }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="mb-4">
                            <h2 class="text-lg font-bold text-slate-900">{{ $event->name }}</h2>
                            <p class="text-sm text-slate-500">
                                {{ $event->exam?->title }}
                                @if($event->exam) &middot; {{ $event->exam->duration_minutes }} menit @endif
                            </p>
                            @if($event->description)
                                <p class="mt-1 text-sm text-slate-600">{{ $event->description }}</p>
                            @endif
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($event->sessions as $session)
                                <div wire:key="session-{{ $session->id }}" class="flex flex-col rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                                    <div class="flex-1">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Aktif
                                        </span>
                                        <p class="mt-2 font-bold text-slate-900">{{ $session->name }}</p>
                                        @if($session->ends_at)
                                            <p class="mt-1 text-xs text-slate-500">Berakhir {{ $session->ends_at->translatedFormat('d M H:i') }}</p>
                                        @endif
                                    </div>
                                    <button wire:click="openJoinModal({{ $session->id }})" class="ui-btn-primary mt-3 w-full justify-center">
                                        Ikuti Sesi
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </main>

    @if ($selectedSessionId)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeJoinModal"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <h2 class="text-lg font-bold text-slate-900">Masukkan Kode Sesi</h2>
                <p class="mt-1 text-sm text-slate-500">Kode diberikan oleh panitia saat sesi berlangsung.</p>
                <form wire:submit="join" class="mt-4 space-y-4">
                    <div>
                        <input type="text"
                               wire:model="code"
                               autofocus
                               autocomplete="off"
                               placeholder="mis. AB12CD"
                               class="ui-input text-center font-mono text-lg font-bold uppercase tracking-widest">
                        @error('code') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closeJoinModal" class="ui-btn-secondary">Batal</button>
                        <button type="submit" class="ui-btn-primary">
                            <span wire:loading.remove wire:target="join">Mulai Ujian</span>
                            <span wire:loading wire:target="join">Memproses…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
