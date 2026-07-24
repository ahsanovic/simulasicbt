document.addEventListener('alpine:init', () => {
    Alpine.data('examStressTest', (config = {}) => ({
        enabled: Boolean(config.enabled),
        redZoneSeconds: Number(config.redZoneSeconds) || 600,
        questionThreshold: Number(config.questionThreshold) || 60,
        currentQuestionNumber: Number(config.currentQuestionNumber) || 1,
        remainingSeconds: Number(config.remainingSeconds) || 0,
        questionSeconds: 0,
        redZoneTriggers: 0,
        redZoneQuestions: [],
        showRedZoneFlash: false,
        redZoneFlashTimeout: null,
        questionIntervalId: null,
        audioContext: null,
        ambienceNodes: [],
        ambienceIntervalId: null,
        redZoneTriggeredForQuestion: false,

        init() {
            if (! this.enabled) {
                return;
            }

            this.startQuestionTimer();
            this.startAmbience();

            this.$watch('remainingSeconds', (value) => {
                if (value <= this.redZoneSeconds) {
                    this.evaluateRedZone();
                }
            });
        },

        handleTimerTick(event) {
            this.remainingSeconds = Number(event.detail?.remainingSeconds ?? this.remainingSeconds);
        },

        handleQuestionChanged(event) {
            this.currentQuestionNumber = Number(event.detail?.questionNumber ?? this.currentQuestionNumber);
            this.redZoneTriggeredForQuestion = false;
            this.startQuestionTimer();
        },

        startQuestionTimer() {
            this.stopQuestionTimer();
            this.questionSeconds = 0;

            this.questionIntervalId = setInterval(() => {
                this.questionSeconds++;
                this.evaluateRedZone();
            }, 1000);
        },

        stopQuestionTimer() {
            if (this.questionIntervalId !== null) {
                clearInterval(this.questionIntervalId);
                this.questionIntervalId = null;
            }
        },

        evaluateRedZone() {
            if (! this.enabled) {
                return;
            }

            if (this.remainingSeconds > this.redZoneSeconds) {
                return;
            }

            if (this.questionSeconds <= this.questionThreshold) {
                return;
            }

            if (this.redZoneTriggeredForQuestion) {
                return;
            }

            this.redZoneTriggeredForQuestion = true;
            this.redZoneTriggers++;
            this.redZoneQuestions.push(this.currentQuestionNumber);
            this.triggerRedZoneFlash();
            this.syncTelemetry();
        },

        triggerRedZoneFlash() {
            this.showRedZoneFlash = true;

            if (this.redZoneFlashTimeout !== null) {
                clearTimeout(this.redZoneFlashTimeout);
            }

            this.redZoneFlashTimeout = setTimeout(() => {
                this.showRedZoneFlash = false;
            }, 450);
        },

        syncTelemetry() {
            if (typeof this.$wire?.syncStressTestTelemetry !== 'function') {
                return;
            }

            this.$wire.syncStressTestTelemetry(this.redZoneTriggers, this.redZoneQuestions);
        },

        startAmbience() {
            if (typeof window.AudioContext === 'undefined' && typeof window.webkitAudioContext === 'undefined') {
                return;
            }

            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                this.audioContext = new AudioCtx();

                const bufferSize = this.audioContext.sampleRate * 2;
                const noiseBuffer = this.audioContext.createBuffer(1, bufferSize, this.audioContext.sampleRate);
                const output = noiseBuffer.getChannelData(0);

                for (let index = 0; index < bufferSize; index++) {
                    output[index] = (Math.random() * 2 - 1) * 0.015;
                }

                const noise = this.audioContext.createBufferSource();
                noise.buffer = noiseBuffer;
                noise.loop = true;

                const gain = this.audioContext.createGain();
                gain.gain.value = 0.04;

                noise.connect(gain);
                gain.connect(this.audioContext.destination);
                noise.start();

                this.ambienceNodes.push(noise, gain);

                this.ambienceIntervalId = setInterval(() => {
                    this.playAmbientClick();
                }, 4000 + Math.random() * 5000);
            } catch (error) {
                console.warn('Stress-test ambience unavailable.', error);
            }
        },

        playAmbientClick() {
            if (! this.audioContext) {
                return;
            }

            const oscillator = this.audioContext.createOscillator();
            const gain = this.audioContext.createGain();

            oscillator.type = 'square';
            oscillator.frequency.value = 900 + Math.random() * 400;
            gain.gain.value = 0.0001;

            oscillator.connect(gain);
            gain.connect(this.audioContext.destination);

            const now = this.audioContext.currentTime;
            gain.gain.exponentialRampToValueAtTime(0.02, now + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.04);

            oscillator.start(now);
            oscillator.stop(now + 0.05);
        },

        destroy() {
            this.stopQuestionTimer();

            if (this.redZoneFlashTimeout !== null) {
                clearTimeout(this.redZoneFlashTimeout);
            }

            if (this.ambienceIntervalId !== null) {
                clearInterval(this.ambienceIntervalId);
            }

            this.ambienceNodes.forEach((node) => {
                try {
                    node.stop?.();
                    node.disconnect?.();
                } catch (error) {
                    // Ignore teardown errors from already-stopped nodes.
                }
            });

            if (this.audioContext) {
                this.audioContext.close().catch(() => {});
            }

            this.syncTelemetry();
        },
    }));
});
