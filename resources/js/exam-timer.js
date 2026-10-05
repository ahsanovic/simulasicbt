/**
 * "Selesai Ujian" confirmation for the SKB room, counted in the browser so a
 * pick made on the last question (not yet sent to the server) already counts.
 */
window.skbFinishConfirmMessage = (wire) => {
    const pendingPick = Boolean(wire.selectedOptionId) && ! wire.savedOptionId;
    const unanswered = Math.max(0, Number(wire.unansweredSaved || 0) - (pendingPick ? 1 : 0));

    return unanswered > 0
        ? `Masih ada ${unanswered} soal belum dijawab. Yakin ingin menyelesaikan ujian SKB sekarang? Skor akan disimpan.`
        : 'Semua soal sudah dijawab. Selesaikan ujian SKB ini? Skor akan disimpan.';
};

/**
 * Report failed Livewire requests to the exam connection banner. Livewire
 * ignores a network failure silently, and on a 5xx (server overloaded) shows
 * a full-page error modal; inside an exam room both become the banner.
 */
document.addEventListener('livewire:init', () => {
    window.Livewire.interceptRequest(({ request, onFailure, onError, onSuccess }) => {
        // The background deadline poll succeeding must not clear a "not
        // saved" warning left by a failed click, so tell the two apart.
        const background = [...request.messages].every((message) => [...message.actions].every((action) => action.name === 'checkExpiry'));
        const notify = (name) => window.dispatchEvent(new CustomEvent(name, { detail: { background } }));

        onFailure(() => notify('exam-request-failed'));
        onError(({ response, preventDefault }) => {
            if (response.status >= 500 && document.querySelector('[data-exam-connection]')) {
                preventDefault();
                notify('exam-request-failed');
            }
        });
        onSuccess(() => notify('exam-request-ok'));
    });
});

document.addEventListener('alpine:init', () => {
    Alpine.data('examConnection', () => ({
        offline: ! navigator.onLine,
        // A click (Simpan / Selesai / navigation) failed: the answer is not saved.
        saveFailed: false,
        // Only the background poll failed: the server can't be reached.
        serverUnreachable: false,

        get problem() {
            return this.offline || this.saveFailed || this.serverUnreachable;
        },

        get title() {
            if (this.offline) return 'Koneksi internet terputus';

            return this.saveFailed ? 'Jawaban belum tersimpan' : 'Koneksi ke server terputus';
        },

        init() {
            this.handlers = {
                offline: () => { this.offline = true; },
                online: () => { this.offline = false; },
                'exam-request-failed': (event) => {
                    this.offline = ! navigator.onLine;
                    if (event.detail?.background) this.serverUnreachable = true;
                    else this.saveFailed = true;
                },
                'exam-request-ok': (event) => {
                    this.offline = false;
                    this.serverUnreachable = false;
                    if (! event.detail?.background) this.saveFailed = false;
                },
            };
            Object.entries(this.handlers).forEach(([name, fn]) => window.addEventListener(name, fn));
        },

        destroy() {
            Object.entries(this.handlers).forEach(([name, fn]) => window.removeEventListener(name, fn));
        },
    }));

    /**
     * Background deadline sync for exam rooms (replaces wire:poll).
     *
     * Participants usually enter at the same moment, so a fixed wire:poll made
     * all of them hit the server in the same second every interval and their
     * answer clicks queued behind that burst. Each browser starts at a random
     * offset instead, spreading the load evenly. The server answers without
     * re-rendering the page (see EnforcesExamDeadline::checkExpiry).
     */
    Alpine.data('examDeadlinePoll', (intervalMs = 10000) => ({
        starter: null,
        timer: null,

        init() {
            this.starter = setTimeout(() => {
                this.poll();
                this.timer = setInterval(() => this.poll(), intervalMs);
            }, Math.random() * intervalMs);

            // Back online: sync soon (closes the attempt if the time ran out
            // while the connection was down). Jittered, because when the room's
            // network comes back every participant comes back at once.
            this.onOnline = () => setTimeout(() => this.poll(), Math.random() * 3000);
            window.addEventListener('online', this.onOnline);
        },

        poll() {
            if (document.visibilityState === 'hidden') {
                return;
            }

            // A failed poll (offline) is reported by the connection banner.
            Promise.resolve(this.$wire.checkExpiry()).catch(() => {});
        },

        destroy() {
            clearTimeout(this.starter);
            clearInterval(this.timer);
            window.removeEventListener('online', this.onOnline);
        },
    }));

    Alpine.data('examTimer', (initialSeconds, options = {}) => ({
        seconds: Math.max(0, Number(initialSeconds) || 0),
        // Wall-clock end time, so a throttled/backgrounded tab can't make the
        // countdown run slow and drift away from the server's deadline.
        endsAt: 0,
        intervalId: null,
        stressMode: Boolean(options.stressMode),
        clockPressureSeconds: Number(options.clockPressureSeconds) || 1800,
        // Exam rooms: follow deadline changes pushed by the server (admin
        // "tambah waktu") and announce when time runs out.
        syncDeadline: Boolean(options.syncDeadline),
        timeUpAnnounced: false,

        get formattedTime() {
            const hours = Math.floor(this.seconds / 3600);
            const minutes = Math.floor((this.seconds % 3600) / 60);
            const secs = this.seconds % 60;

            return [hours, minutes, secs]
                .map((value) => String(value).padStart(2, '0'))
                .join(':');
        },

        get isClockPressure() {
            return this.stressMode
                && this.seconds > 0
                && this.seconds <= this.clockPressureSeconds;
        },

        init() {
            if (this.syncDeadline) {
                this.onDeadlineSynced = (event) => this.setRemaining(event.detail?.remainingSeconds);
                window.addEventListener('exam-deadline-synced', this.onDeadlineSynced);
            }

            this.setRemaining(this.seconds);
        },

        setRemaining(remainingSeconds) {
            const value = Number(remainingSeconds);

            if (! Number.isFinite(value)) {
                return;
            }

            this.seconds = Math.max(0, Math.round(value));
            this.endsAt = Date.now() + this.seconds * 1000;

            if (this.seconds > 0) {
                this.timeUpAnnounced = false;
            }

            this.start();
        },

        start() {
            this.stop();

            if (this.seconds <= 0) {
                this.announceTimeUp();

                return;
            }

            this.intervalId = setInterval(() => this.tick(), 1000);
        },

        tick() {
            const next = Math.max(0, Math.ceil((this.endsAt - Date.now()) / 1000));

            if (next !== this.seconds) {
                this.seconds = next;
                this.$dispatch('exam-timer-tick', { remainingSeconds: this.seconds });
            }

            if (this.seconds <= 0) {
                this.stop();
                this.announceTimeUp();
            }
        },

        announceTimeUp() {
            if (! this.syncDeadline || this.timeUpAnnounced) {
                return;
            }

            this.timeUpAnnounced = true;
            window.dispatchEvent(new CustomEvent('exam-time-up'));
        },

        stop() {
            if (this.intervalId !== null) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
        },

        destroy() {
            this.stop();

            if (this.onDeadlineSynced) {
                window.removeEventListener('exam-deadline-synced', this.onDeadlineSynced);
            }
        },
    }));
});
