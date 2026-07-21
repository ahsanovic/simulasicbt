document.addEventListener('alpine:init', () => {
    Alpine.data('examTimer', (initialSeconds, options = {}) => ({
        seconds: Math.max(0, Number(initialSeconds) || 0),
        intervalId: null,
        stressMode: Boolean(options.stressMode),
        clockPressureSeconds: Number(options.clockPressureSeconds) || 1800,

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
            this.start();
        },

        start() {
            this.stop();
            this.intervalId = setInterval(() => {
                if (this.seconds <= 0) {
                    this.stop();
                    return;
                }

                this.seconds--;
                this.$dispatch('exam-timer-tick', { remainingSeconds: this.seconds });
            }, 1000);
        },

        stop() {
            if (this.intervalId !== null) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
        },

        destroy() {
            this.stop();
        },
    }));
});
