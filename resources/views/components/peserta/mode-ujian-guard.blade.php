{{--
    Anti-cheat guard for Mode Ujian exam rooms:
    - Requests fullscreen on a user click (browsers block programmatic
      fullscreen without a gesture, so we show a one-time overlay button).
    - Starts a 10s logout countdown when ANY of these fire:
      - visibilitychange (tab switch, minimize, closing)
      - fullscreenchange (Esc key, F11, browser chrome button) — exiting
        fullscreen via Esc does not make the page "hidden" per the Page
        Visibility API, and some browsers silently drop fullscreen when a
        new tab is opened without the original tab ever reporting hidden.
      - window blur/focus (Alt+Tab, Windows+Tab / Task View, switching to
        another app or virtual desktop) — this is the OS handing input
        focus to another window. It's checked separately from visibility
        because a window can keep reporting itself as "visible" while
        merely losing focus underneath a task switcher overlay, so
        visibilitychange/fullscreenchange alone can miss it.
    - Logout is a plain form POST to the existing /logout route (works
      even if the tab is being closed, since it's a normal navigation).
--}}
<div
    x-data="{
        showOverlay: true,
        countdownVisible: false,
        secondsLeft: 10,
        timer: null,
        interval: null,
        isFullscreen() {
            return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
        },
        enterFullscreen() {
            const el = document.documentElement;
            const request = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen;
            if (request) {
                const result = request.call(el);
                if (result && result.catch) { result.catch(() => {}); }
            }
            this.showOverlay = false;
            // Some browsers don't fire fullscreenchange if the request silently
            // fails (e.g. permission denied) — re-check state explicitly so a
            // failed request still surfaces the warning instead of going quiet.
            setTimeout(() => this.checkViolation(), 700);
        },
        startCountdown() {
            if (this.countdownVisible) return;
            this.secondsLeft = 10;
            this.countdownVisible = true;
            this.interval = setInterval(() => { this.secondsLeft = Math.max(0, this.secondsLeft - 1); }, 1000);
            this.timer = setTimeout(() => { this.$refs.logoutForm.submit(); }, 10000);
        },
        cancelCountdown() {
            clearTimeout(this.timer);
            clearInterval(this.interval);
            this.countdownVisible = false;
        },
        checkViolation() {
            if (this.showOverlay) return;
            if (document.hidden || !this.isFullscreen() || !document.hasFocus()) {
                this.startCountdown();
            } else {
                this.cancelCountdown();
            }
        },
    }"
    x-init="
        document.addEventListener('visibilitychange', () => checkViolation());
        document.addEventListener('fullscreenchange', () => checkViolation());
        document.addEventListener('webkitfullscreenchange', () => checkViolation());
        document.addEventListener('msfullscreenchange', () => checkViolation());
        window.addEventListener('blur', () => checkViolation());
        window.addEventListener('focus', () => checkViolation());
    "
>
    {{-- Copy / screenshot protection (resources/js/exam-content-protection.js); never blocks the screen or logs out. --}}
    <div x-data="examContentProtection" data-exam-protection></div>

    <form x-ref="logoutForm" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>

    <div x-show="showOverlay" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/90 p-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <h2 class="text-lg font-bold text-slate-900">Mulai Ujian</h2>
            <p class="mt-2 text-sm text-slate-600">Ujian akan berjalan dalam mode layar penuh. Jangan berpindah tab/aplikasi (termasuk Alt+Tab atau Windows+Tab), keluar dari layar penuh, atau menutup browser — sistem akan logout otomatis setelah 10 detik jika terdeteksi.</p>
            <button type="button" x-on:click="enterFullscreen" class="ui-btn-primary mt-5 w-full justify-center">Mulai Sekarang</button>
        </div>
    </div>

    <div x-show="countdownVisible" x-cloak class="fixed inset-0 z-[9999] flex items-center justify-center bg-rose-900/90 p-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <h2 class="text-lg font-bold text-rose-700">Peringatan!</h2>
            <p class="mt-2 text-sm text-slate-600">Anda meninggalkan layar ujian (pindah tab/aplikasi atau keluar layar penuh). Kembali sekarang atau akan logout otomatis dalam:</p>
            <p class="mt-3 text-4xl font-extrabold text-rose-600" x-text="secondsLeft"></p>
            <button type="button" x-on:click="enterFullscreen()" class="ui-btn-primary mt-4 w-full justify-center">Kembali ke Layar Penuh</button>
        </div>
    </div>
</div>
