document.addEventListener('alpine:init', () => {
    Alpine.data('examTimer', (initialSeconds) => ({
        seconds: Math.max(0, Number(initialSeconds) || 0),
        intervalId: null,

        get formattedTime() {
            const hours = Math.floor(this.seconds / 3600);
            const minutes = Math.floor((this.seconds % 3600) / 60);
            const secs = this.seconds % 60;

            return [hours, minutes, secs]
                .map((value) => String(value).padStart(2, '0'))
                .join(':');
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
