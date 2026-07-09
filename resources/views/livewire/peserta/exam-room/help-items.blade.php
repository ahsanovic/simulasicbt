@if ($helpItemsEnabled)
    <div class="ui-card p-4 sm:p-5"
         x-data="{
            seconds: 0,
            alerted: false,
            interval: null,
            resetTimer() {
                this.seconds = 0;
                this.alerted = false;
                clearInterval(this.interval);
                if (! @js($skipTrackerActive)) {
                    return;
                }
                this.interval = setInterval(() => {
                    this.seconds++;
                    if (this.seconds >= 60 && !this.alerted) {
                        this.alerted = true;
                        if (window.showToast) {
                            window.showToast('warning', 'Kamu sudah 1 menit di soal ini. Lewati dulu demi mengamankan poin soal lain!');
                        }
                    }
                }, 1000);
            }
         }"
         x-init="resetTimer()"
         @question-changed.window="resetTimer()">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-amber-700">Item Bantuan</p>
                <p class="mt-1 text-sm text-slate-600">Alat latihan manajemen waktu untuk simulasi harian.</p>
            </div>
            <button type="button"
                    wire:click="goToShop"
                    wire:confirm="Timer simulasi tetap berjalan. Buka toko koin?"
                    class="text-xs font-semibold text-primary-600 hover:text-primary-700">
                Beli item di Toko →
            </button>
        </div>

        <div class="mt-4 flex flex-wrap gap-2">
            @if ($skipTrackerActive)
                <span class="ui-badge bg-amber-100 text-amber-800">⏱️ Skip Tracker aktif</span>
            @elseif (($inventory[\App\Enums\HelpItem::SkipTracker->value] ?? 0) > 0)
                <button type="button"
                        wire:click="activateSkipTracker"
                        wire:confirm="Aktifkan Skip Tracker untuk simulasi ini? (1 item akan dipakai)"
                        class="ui-btn-secondary text-xs">
                    ⏱️ Aktifkan Skip Tracker
                </button>
            @else
                <span class="ui-badge bg-slate-100 text-slate-600">⏱️ Skip Tracker: stok habis</span>
            @endif

            @if ($this->canUseFiftyFifty)
                <button type="button"
                        wire:click="useFiftyFifty"
                        wire:confirm="Gunakan 50:50 di soal ini? (1 item akan dipakai)"
                        class="ui-btn-secondary text-xs">
                    🎯 50:50 Eliminator
                </button>
            @elseif (count($this->currentEliminatedOptionIds) > 0)
                <span class="ui-badge bg-emerald-100 text-emerald-800">🎯 50:50 sudah dipakai di soal ini</span>
            @elseif (($inventory[\App\Enums\HelpItem::FiftyFifty->value] ?? 0) > 0)
                <span class="ui-badge bg-slate-100 text-slate-600">🎯 50:50: hanya TWK/TIU</span>
            @else
                <span class="ui-badge bg-slate-100 text-slate-600">🎯 50:50: stok habis</span>
            @endif
        </div>

        @if ($skipTrackerActive)
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm text-amber-900">
                    Terjebak di soal ini? Tandai dulu, lalu loncat ke soal belum dijawab.
                </p>
                <button type="button"
                        wire:click="skipAndMarkQuestion"
                        class="ui-btn-primary shrink-0 text-xs">
                    Tandai & Lewati
                </button>
            </div>
        @endif
    </div>
@endif
