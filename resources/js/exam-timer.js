document.addEventListener('alpine:init', () => {
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
