import Swal from 'sweetalert2';

document.addEventListener('alpine:init', () => {
    const indonesianNumbers = {
        1: 'satu', 2: 'dua', 3: 'tiga', 4: 'empat', 5: 'lima',
        6: 'enam', 7: 'tujuh', 8: 'delapan', 9: 'sembilan', 10: 'sepuluh',
        11: 'sebelas', 12: 'dua belas', 13: 'tiga belas', 14: 'empat belas', 15: 'lima belas',
        16: 'enam belas', 17: 'tujuh belas', 18: 'delapan belas', 19: 'sembilan belas', 20: 'dua puluh',
        21: 'dua puluh satu', 22: 'dua puluh dua', 23: 'dua puluh tiga', 24: 'dua puluh empat', 25: 'dua puluh lima',
        26: 'dua puluh enam', 27: 'dua puluh tujuh', 28: 'dua puluh delapan', 29: 'dua puluh sembilan', 30: 'tiga puluh',
        31: 'tiga puluh satu', 32: 'tiga puluh dua', 33: 'tiga puluh tiga', 34: 'tiga puluh empat', 35: 'tiga puluh lima',
        36: 'tiga puluh enam', 37: 'tiga puluh tujuh', 38: 'tiga puluh delapan', 39: 'tiga puluh sembilan', 40: 'empat puluh',
        41: 'empat puluh satu', 42: 'empat puluh dua', 43: 'empat puluh tiga', 44: 'empat puluh empat', 45: 'empat puluh lima',
        46: 'empat puluh enam', 47: 'empat puluh tujuh', 48: 'empat puluh delapan', 49: 'empat puluh sembilan', 50: 'lima puluh',
    };

    let cachedIndonesianVoice = null;

    const resolveIndonesianVoice = () => {
        if (cachedIndonesianVoice) {
            return cachedIndonesianVoice;
        }

        const voices = window.speechSynthesis?.getVoices() ?? [];
        const indonesianVoices = voices.filter((voice) => voice.lang.toLowerCase().startsWith('id'));

        cachedIndonesianVoice =
            indonesianVoices.find((voice) => voice.name.toLowerCase().includes('indonesia')) ??
            indonesianVoices.find((voice) => voice.localService) ??
            indonesianVoices.find((voice) => voice.name.toLowerCase().includes('google')) ??
            indonesianVoices[0] ??
            null;

        return cachedIndonesianVoice;
    };

    const preloadIndonesianVoice = () => {
        resolveIndonesianVoice();

        if (!window.speechSynthesis) {
            return;
        }

        if (window.speechSynthesis.getVoices().length === 0) {
            window.speechSynthesis.addEventListener('voiceschanged', () => {
                cachedIndonesianVoice = null;
                resolveIndonesianVoice();
            }, { once: true });
        }
    };

    preloadIndonesianVoice();

    Alpine.data('audioModePlayer', (config) => ({
        questions: config.questions ?? [],
        thinkingSeconds: config.thinkingSeconds ?? 7,
        transitionSeconds: config.transitionSeconds ?? 2,
        optionPauseMs: config.optionPauseMs ?? 500,
        answerRevealPauseMs: config.answerRevealPauseMs ?? 1200,
        speechRate: config.speechRate ?? 0.92,
        speechLang: 'id-ID',

        currentIndex: 0,
        stage: 'idle',
        isPlaying: false,
        isPaused: false,
        countdown: 0,
        optionsReadIndex: 0,
        selectedOption: null,
        countdownTimer: null,
        transitionTimer: null,
        optionPauseTimer: null,
        answerPauseTimer: null,
        answerSubStage: 'reveal',
        finishing: false,
        speechGeneration: 0,

        get currentQuestion() {
            return this.questions[this.currentIndex] ?? null;
        },

        get progressPercent() {
            if (this.questions.length === 0) {
                return 0;
            }

            return Math.round(((this.currentIndex + 1) / this.questions.length) * 100);
        },

        get stageLabel() {
            return {
                idle: 'Siap memulai',
                question: 'Membaca soal',
                options: 'Membaca pilihan',
                thinking: 'Waktu berpikir',
                answer: 'Pembahasan',
                transition: 'Soal berikutnya',
            }[this.stage] ?? '';
        },

        init() {
            preloadIndonesianVoice();

            this.$nextTick(() => {
                if (config.autoplay) {
                    this.play();
                }
            });
        },

        numberWord(num) {
            return indonesianNumbers[num] ?? String(num);
        },

        stopSpeech() {
            window.speechSynthesis?.cancel();
        },

        cancelSpeech() {
            this.speechGeneration++;
            this.stopSpeech();
        },

        applyIndonesianVoice(utterance) {
            utterance.lang = this.speechLang;

            const voice = resolveIndonesianVoice();

            if (voice) {
                utterance.voice = voice;
            }
        },

        speak(text, onEnd) {
            const trimmed = (text ?? '').trim();

            if (!trimmed || !window.speechSynthesis) {
                onEnd?.();

                return;
            }

            this.stopSpeech();

            const generation = this.speechGeneration;

            const startSpeaking = () => {
                if (this.isPaused || generation !== this.speechGeneration) {
                    return;
                }

                const utterance = new SpeechSynthesisUtterance(trimmed);
                this.applyIndonesianVoice(utterance);
                utterance.rate = this.speechRate;
                utterance.onend = () => {
                    if (generation !== this.speechGeneration || this.isPaused) {
                        return;
                    }

                    onEnd?.();
                };
                utterance.onerror = () => {
                    if (generation !== this.speechGeneration || this.isPaused) {
                        return;
                    }

                    onEnd?.();
                };

                window.speechSynthesis.speak(utterance);
            };

            // Chrome needs a brief gap after cancel() before speak() works again.
            setTimeout(startSpeaking, 80);
        },

        clearTimers() {
            if (this.countdownTimer) {
                clearInterval(this.countdownTimer);
                this.countdownTimer = null;
            }

            if (this.transitionTimer) {
                clearTimeout(this.transitionTimer);
                this.transitionTimer = null;
            }

            if (this.optionPauseTimer) {
                clearTimeout(this.optionPauseTimer);
                this.optionPauseTimer = null;
            }

            if (this.answerPauseTimer) {
                clearTimeout(this.answerPauseTimer);
                this.answerPauseTimer = null;
            }
        },

        play() {
            if (this.finishing) {
                return;
            }

            this.isPlaying = true;
            this.isPaused = false;
            this.resumeFromPause();
        },

        pause() {
            this.isPaused = true;
            this.isPlaying = false;
            this.cancelSpeech();
            this.clearTimers();
        },

        togglePlayPause() {
            if (this.isPlaying) {
                this.pause();
            } else {
                this.play();
            }
        },

        resumeFromPause() {
            switch (this.stage) {
                case 'idle':
                    this.runStage('question');
                    break;
                case 'thinking':
                    if (this.countdown > 0) {
                        this.resumeThinkingCountdown();
                    } else {
                        this.runStage('answer');
                    }
                    break;
                case 'transition':
                    this.startTransitionTimer();
                    break;
                case 'options':
                    this.speakOptionsSequentially(this.currentQuestion);
                    break;
                case 'question':
                case 'answer':
                    this.speakAnswer(this.currentQuestion);
                    break;
                default:
                    this.runStage('question');
            }
        },

        previous() {
            if (this.currentIndex <= 0 || this.finishing) {
                return;
            }

            this.pause();
            this.currentIndex--;
            this.selectedOption = null;
            this.optionsReadIndex = 0;
            this.answerSubStage = 'reveal';
            this.stage = 'idle';
            this.countdown = 0;
            this.play();
        },

        next() {
            if (this.currentIndex >= this.questions.length - 1 || this.finishing) {
                return;
            }

            this.pause();
            this.$wire.completeQuestion(this.currentIndex);
            this.currentIndex++;
            this.selectedOption = null;
            this.optionsReadIndex = 0;
            this.answerSubStage = 'reveal';
            this.stage = 'idle';
            this.countdown = 0;
            this.play();
        },

        selectOption(label) {
            if (this.stage !== 'thinking') {
                return;
            }

            this.selectedOption = label;
        },

        speakOptionsSequentially(question) {
            if (!question || this.isPaused || this.finishing) {
                return;
            }

            const options = question.options ?? [];

            const speakNext = () => {
                if (this.isPaused || this.finishing) {
                    return;
                }

                if (this.optionsReadIndex >= options.length) {
                    this.optionsReadIndex = 0;
                    this.runStage('thinking');

                    return;
                }

                const option = options[this.optionsReadIndex];

                this.speak(`${option.label}. ${option.text}`, () => {
                    if (this.isPaused || this.finishing) {
                        return;
                    }

                    this.optionsReadIndex++;

                    if (this.optionsReadIndex >= options.length) {
                        this.optionsReadIndex = 0;
                        this.runStage('thinking');

                        return;
                    }

                    this.optionPauseTimer = setTimeout(() => {
                        this.optionPauseTimer = null;
                        speakNext();
                    }, this.optionPauseMs);
                });
            };

            speakNext();
        },

        speakAnswer(question) {
            if (!question || this.isPaused || this.finishing) {
                return;
            }

            this.stage = 'answer';

            if (this.answerSubStage === 'explanation') {
                const explanation = (question.explanation ?? '').trim();

                if (!explanation) {
                    this.completeAnswerStage();

                    return;
                }

                this.speak(`Pembahasan: ${explanation}`, () => this.completeAnswerStage());

                return;
            }

            this.speak(`Jawaban yang benar adalah ${question.correct_label}.`, () => {
                if (this.isPaused || this.finishing) {
                    return;
                }

                const explanation = (question.explanation ?? '').trim();

                if (!explanation) {
                    this.completeAnswerStage();

                    return;
                }

                this.answerPauseTimer = setTimeout(() => {
                    this.answerPauseTimer = null;

                    if (this.isPaused || this.finishing) {
                        return;
                    }

                    this.answerSubStage = 'explanation';
                    this.speak(`Pembahasan: ${explanation}`, () => this.completeAnswerStage());
                }, this.answerRevealPauseMs);
            });
        },

        completeAnswerStage() {
            this.answerSubStage = 'reveal';
            this.$wire.completeQuestion(this.currentIndex);
            this.runStage('transition');
        },

        runStage(stage) {
            if (this.isPaused || this.finishing) {
                return;
            }

            const question = this.currentQuestion;

            if (!question) {
                return;
            }

            this.stage = stage;

            switch (stage) {
                case 'question':
                    this.speak(
                        `Soal nomor ${this.numberWord(question.number)}. ${question.question}`,
                        () => this.runStage('options'),
                    );
                    break;

                case 'options':
                    this.optionsReadIndex = 0;
                    this.speakOptionsSequentially(question);
                    break;

                case 'thinking':
                    if (this.countdown <= 0) {
                        this.countdown = this.thinkingSeconds;
                    }

                    this.resumeThinkingCountdown();
                    break;

                case 'answer':
                    this.answerSubStage = 'reveal';
                    this.speakAnswer(question);
                    break;

                case 'transition':
                    this.startTransitionTimer();
                    break;
            }
        },

        startTransitionTimer() {
            if (this.isPaused || this.finishing) {
                return;
            }

            this.stage = 'transition';
            this.clearTimers();

            this.transitionTimer = setTimeout(() => {
                if (this.isPaused || this.finishing) {
                    return;
                }

                if (this.currentIndex >= this.questions.length - 1) {
                    this.finishSession();

                    return;
                }

                this.currentIndex++;
                this.selectedOption = null;
                this.optionsReadIndex = 0;
                this.answerSubStage = 'reveal';
                this.runStage('question');
            }, this.transitionSeconds * 1000);
        },

        resumeThinkingCountdown() {
            this.clearTimers();

            this.countdownTimer = setInterval(() => {
                if (this.isPaused) {
                    return;
                }

                this.countdown--;

                if (this.countdown <= 0) {
                    this.clearTimers();
                    this.runStage('answer');
                }
            }, 1000);
        },

        finishSession() {
            if (this.finishing) {
                return;
            }

            this.finishing = true;
            this.isPlaying = false;
            this.isPaused = true;
            this.cancelSpeech();
            this.clearTimers();
            this.$wire.finishSession();
        },

        endSessionEarly() {
            if (this.finishing) {
                return;
            }

            Swal.fire({
                title: 'Akhiri sesi Audio Mode?',
                html: `Progres belajar yang sudah selesai didengar akan disimpan sebagai XP.<br><br>Posisi saat ini: soal <strong>${this.currentIndex + 1}</strong> dari <strong>${this.questions.length}</strong>.`,
                icon: 'question',
                showCancelButton: true,
                showDenyButton: true,
                confirmButtonText: 'Akhiri & Simpan Progres',
                denyButtonText: 'Keluar tanpa simpan',
                cancelButtonText: 'Lanjutkan belajar',
                reverseButtons: true,
                confirmButtonColor: '#7c3aed',
                denyButtonColor: '#64748b',
                cancelButtonColor: '#94a3b8',
            }).then((result) => {
                if (result.isConfirmed) {
                    this.finishSession();
                } else if (result.isDenied) {
                    this.cancelSpeech();
                    this.clearTimers();
                    this.$wire.backToSetup();
                }
            });
        },

        destroy() {
            this.cancelSpeech();
            this.clearTimers();
        },
    }));
});
