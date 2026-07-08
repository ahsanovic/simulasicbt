document.addEventListener('alpine:init', () => {
    Alpine.data('examScratchpad', (attemptId) => ({
        open: false,
        showTip: false,
        isDrawing: false,
        canvas: null,
        ctx: null,
        storageKey: `scratchpad:${attemptId}`,
        _resizeHandler: null,

        init() {
            this._resizeHandler = () => this.resizeCanvas();
            window.addEventListener('resize', this._resizeHandler);
        },

        destroy() {
            this.saveToStorage();

            if (this._resizeHandler) {
                window.removeEventListener('resize', this._resizeHandler);
            }
        },

        openScratchpad() {
            this.showTip = false;
            this.open = true;
            this.$nextTick(() => {
                this.setupCanvas();
                this.loadFromStorage();
            });
        },

        closeScratchpad() {
            this.saveToStorage();
            this.open = false;
        },

        setupCanvas() {
            this.canvas = this.$refs.canvas;
            if (!this.canvas) {
                return;
            }

            this.ctx = this.canvas.getContext('2d');
            this.resizeCanvas();
        },

        resizeCanvas() {
            if (!this.canvas || !this.ctx) {
                return;
            }

            const parent = this.canvas.parentElement;
            if (!parent) {
                return;
            }

            const rect = parent.getBoundingClientRect();
            const dpr = window.devicePixelRatio || 1;
            let saved = null;

            if (this.canvas.width > 0) {
                saved = this.canvas.toDataURL();
            }

            this.canvas.width = Math.floor(rect.width * dpr);
            this.canvas.height = Math.floor(rect.height * dpr);
            this.canvas.style.width = `${rect.width}px`;
            this.canvas.style.height = `${rect.height}px`;

            this.ctx.setTransform(1, 0, 0, 1, 0, 0);
            this.ctx.scale(dpr, dpr);
            this.applyStrokeStyle();

            if (saved && saved !== 'data:,') {
                const img = new Image();
                img.onload = () => {
                    this.ctx.drawImage(img, 0, 0, rect.width, rect.height);
                };
                img.src = saved;
            }
        },

        applyStrokeStyle() {
            if (!this.ctx) {
                return;
            }

            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';
            this.ctx.lineWidth = 2.5;
            this.ctx.strokeStyle = '#1e293b';
        },

        getPos(event) {
            const rect = this.canvas.getBoundingClientRect();

            if (event.touches && event.touches.length > 0) {
                return {
                    x: event.touches[0].clientX - rect.left,
                    y: event.touches[0].clientY - rect.top,
                };
            }

            return {
                x: event.clientX - rect.left,
                y: event.clientY - rect.top,
            };
        },

        startDraw(event) {
            if (event.type.startsWith('touch')) {
                event.preventDefault();
            }

            this.isDrawing = true;
            const pos = this.getPos(event);
            this.ctx.beginPath();
            this.ctx.moveTo(pos.x, pos.y);
        },

        draw(event) {
            if (!this.isDrawing) {
                return;
            }

            if (event.type.startsWith('touch')) {
                event.preventDefault();
            }

            const pos = this.getPos(event);
            this.ctx.lineTo(pos.x, pos.y);
            this.ctx.stroke();
        },

        stopDraw() {
            if (!this.isDrawing) {
                return;
            }

            this.isDrawing = false;
            this.saveToStorage();
        },

        clearCanvas() {
            if (!this.ctx || !this.canvas) {
                return;
            }

            const parent = this.canvas.parentElement;
            const rect = parent.getBoundingClientRect();
            this.ctx.clearRect(0, 0, rect.width, rect.height);

            try {
                localStorage.removeItem(this.storageKey);
            } catch {
                // ignore storage errors
            }
        },

        saveToStorage() {
            if (!this.canvas) {
                return;
            }

            try {
                localStorage.setItem(this.storageKey, this.canvas.toDataURL());
            } catch {
                // ignore quota errors
            }
        },

        loadFromStorage() {
            if (!this.ctx || !this.canvas) {
                return;
            }

            const parent = this.canvas.parentElement;
            const rect = parent.getBoundingClientRect();
            this.ctx.clearRect(0, 0, rect.width, rect.height);

            try {
                const data = localStorage.getItem(this.storageKey);
                if (!data) {
                    return;
                }

                const img = new Image();
                img.onload = () => {
                    this.ctx.drawImage(img, 0, 0, rect.width, rect.height);
                };
                img.src = data;
            } catch {
                // ignore storage errors
            }
        },
    }));
});
