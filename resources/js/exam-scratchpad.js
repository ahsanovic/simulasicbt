document.addEventListener('alpine:init', () => {
    Alpine.data('examScratchpad', (attemptId) => ({
        open: false,
        showTip: false,
        isDrawing: false,
        canvas: null,
        ctx: null,
        storageKey: `scratchpad:${attemptId}`,
        _resizeHandler: null,
        _canvasWidth: 0,
        _canvasHeight: 0,

        activeTool: 'pen',
        strokes: [],
        currentPath: null,
        eraserPath: null,
        backgroundDataUrl: null,

        textInput: {
            active: false,
            x: 0,
            y: 0,
            content: '',
        },

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
            this.commitTextInput();
            this.saveToStorage();
            this.open = false;
        },

        setTool(tool) {
            this.commitTextInput();
            this.activeTool = tool;
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
            const newWidth = rect.width;
            const newHeight = rect.height;

            if (this._canvasWidth > 0 && this._canvasHeight > 0) {
                const scaleX = newWidth / this._canvasWidth;
                const scaleY = newHeight / this._canvasHeight;
                this.scaleStrokes(scaleX, scaleY);
            }

            this._canvasWidth = newWidth;
            this._canvasHeight = newHeight;

            this.canvas.width = Math.floor(newWidth * dpr);
            this.canvas.height = Math.floor(newHeight * dpr);
            this.canvas.style.width = `${newWidth}px`;
            this.canvas.style.height = `${newHeight}px`;

            this.ctx.setTransform(1, 0, 0, 1, 0, 0);
            this.ctx.scale(dpr, dpr);

            this.redraw();
        },

        scaleStrokes(scaleX, scaleY) {
            for (const stroke of this.strokes) {
                if (stroke.type === 'path') {
                    for (const point of stroke.points) {
                        point.x *= scaleX;
                        point.y *= scaleY;
                    }
                } else if (stroke.type === 'text') {
                    stroke.x *= scaleX;
                    stroke.y *= scaleY;
                    stroke.fontSize *= Math.min(scaleX, scaleY);
                }
            }

            if (this.textInput.active) {
                this.textInput.x *= scaleX;
                this.textInput.y *= scaleY;
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

            const pos = this.getPos(event);

            if (this.activeTool === 'text') {
                this.commitTextInput();
                this.textInput = {
                    active: true,
                    x: pos.x,
                    y: pos.y,
                    content: '',
                };
                this.canvas.focus();
                this.redraw();
                return;
            }

            this.isDrawing = true;

            if (this.activeTool === 'pen') {
                this.currentPath = {
                    id: crypto.randomUUID(),
                    type: 'path',
                    points: [pos],
                    color: '#1e293b',
                    width: 2.5,
                };
            } else if (this.activeTool === 'eraser') {
                this.eraserPath = [pos];
                this.eraseAt(pos);
            }
        },

        draw(event) {
            if (!this.isDrawing) {
                return;
            }

            if (event.type.startsWith('touch')) {
                event.preventDefault();
            }

            const pos = this.getPos(event);

            if (this.activeTool === 'pen' && this.currentPath) {
                this.currentPath.points.push(pos);
                this.redraw();
            } else if (this.activeTool === 'eraser') {
                this.eraserPath.push(pos);
                this.eraseAt(pos);
                this.redraw();
            }
        },

        stopDraw() {
            if (!this.isDrawing) {
                return;
            }

            this.isDrawing = false;

            if (this.activeTool === 'pen' && this.currentPath && this.currentPath.points.length > 0) {
                this.strokes.push(this.currentPath);
                this.currentPath = null;
            }

            if (this.activeTool === 'eraser') {
                this.eraserPath = null;
            }

            this.saveToStorage();
            this.redraw();
        },

        eraseAt(pos) {
            const radius = 16;
            const radiusSq = radius * radius;

            this.strokes = this.strokes.filter((stroke) => {
                if (stroke.type === 'path') {
                    return !stroke.points.some((point) => {
                        const dx = point.x - pos.x;
                        const dy = point.y - pos.y;
                        return dx * dx + dy * dy <= radiusSq;
                    });
                }

                if (stroke.type === 'text') {
                    const bounds = this.getTextBounds(stroke);
                    return !this.circleIntersectsRect(pos, radius, bounds);
                }

                return true;
            });
        },

        getTextBounds(stroke) {
            this.ctx.save();
            this.ctx.font = `${stroke.fontSize}px system-ui, sans-serif`;
            const width = this.ctx.measureText(stroke.content).width;
            this.ctx.restore();

            return {
                x: stroke.x,
                y: stroke.y - stroke.fontSize,
                width,
                height: stroke.fontSize * 1.2,
            };
        },

        circleIntersectsRect(center, radius, rect) {
            const closestX = Math.max(rect.x, Math.min(center.x, rect.x + rect.width));
            const closestY = Math.max(rect.y, Math.min(center.y, rect.y + rect.height));
            const dx = center.x - closestX;
            const dy = center.y - closestY;

            return dx * dx + dy * dy <= radius * radius;
        },

        handleKeydown(event) {
            if (!this.textInput.active || this.activeTool !== 'text') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (event.key === 'Enter') {
                this.commitTextInput();
                return;
            }

            if (event.key === 'Escape') {
                this.textInput = { active: false, x: 0, y: 0, content: '' };
                this.redraw();
                return;
            }

            if (event.key === 'Backspace') {
                this.textInput.content = this.textInput.content.slice(0, -1);
                this.redraw();
                return;
            }

            if (event.key.length === 1) {
                this.textInput.content += event.key;
                this.redraw();
            }
        },

        commitTextInput() {
            if (!this.textInput.active || this.textInput.content.length === 0) {
                this.textInput = { active: false, x: 0, y: 0, content: '' };
                return;
            }

            this.strokes.push({
                id: crypto.randomUUID(),
                type: 'text',
                x: this.textInput.x,
                y: this.textInput.y,
                content: this.textInput.content,
                color: '#1e293b',
                fontSize: 22,
            });

            this.textInput = { active: false, x: 0, y: 0, content: '' };
            this.saveToStorage();
            this.redraw();
        },

        redraw() {
            if (!this.ctx || !this.canvas) {
                return;
            }

            this.ctx.clearRect(0, 0, this._canvasWidth, this._canvasHeight);

            if (this.backgroundDataUrl) {
                const img = new Image();
                img.src = this.backgroundDataUrl;
                if (img.complete) {
                    this.ctx.drawImage(img, 0, 0, this._canvasWidth, this._canvasHeight);
                } else {
                    img.onload = () => {
                        this.ctx.clearRect(0, 0, this._canvasWidth, this._canvasHeight);
                        this.ctx.drawImage(img, 0, 0, this._canvasWidth, this._canvasHeight);
                        this.redrawStrokes();
                    };
                    return;
                }
            }

            this.redrawStrokes();
        },

        redrawStrokes() {
            if (!this.ctx) {
                return;
            }

            for (const stroke of this.strokes) {
                if (stroke.type === 'path') {
                    this.drawPath(stroke);
                } else if (stroke.type === 'text') {
                    this.drawText(stroke);
                }
            }

            if (this.currentPath) {
                this.drawPath(this.currentPath);
            }

            if (this.textInput.active) {
                this.drawText({
                    x: this.textInput.x,
                    y: this.textInput.y,
                    content: this.textInput.content,
                    color: '#1e293b',
                    fontSize: 22,
                });

                this.ctx.save();
                this.ctx.font = '22px system-ui, sans-serif';
                const textWidth = this.ctx.measureText(this.textInput.content).width;
                this.ctx.strokeStyle = '#1e293b';
                this.ctx.lineWidth = 1.5;
                this.ctx.beginPath();
                this.ctx.moveTo(this.textInput.x + textWidth + 2, this.textInput.y - 18);
                this.ctx.lineTo(this.textInput.x + textWidth + 2, this.textInput.y + 2);
                this.ctx.stroke();
                this.ctx.restore();
            }
        },

        drawPath(stroke) {
            if (!stroke.points || stroke.points.length === 0) {
                return;
            }

            this.ctx.save();
            this.applyStrokeStyle();
            this.ctx.strokeStyle = stroke.color;
            this.ctx.lineWidth = stroke.width;
            this.ctx.beginPath();
            this.ctx.moveTo(stroke.points[0].x, stroke.points[0].y);

            for (let i = 1; i < stroke.points.length; i++) {
                this.ctx.lineTo(stroke.points[i].x, stroke.points[i].y);
            }

            this.ctx.stroke();
            this.ctx.restore();
        },

        drawText(stroke) {
            this.ctx.save();
            this.ctx.font = `${stroke.fontSize}px system-ui, sans-serif`;
            this.ctx.fillStyle = stroke.color;
            this.ctx.textBaseline = 'alphabetic';
            this.ctx.fillText(stroke.content, stroke.x, stroke.y);
            this.ctx.restore();
        },

        clearCanvas() {
            this.commitTextInput();
            this.strokes = [];
            this.currentPath = null;
            this.eraserPath = null;
            this.backgroundDataUrl = null;

            if (!this.ctx || !this.canvas) {
                return;
            }

            this.ctx.clearRect(0, 0, this._canvasWidth, this._canvasHeight);

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
                const data = {
                    version: 2,
                    strokes: this.strokes,
                    backgroundDataUrl: this.backgroundDataUrl,
                    canvasWidth: this._canvasWidth,
                    canvasHeight: this._canvasHeight,
                };
                localStorage.setItem(this.storageKey, JSON.stringify(data));
            } catch {
                // ignore quota errors
            }
        },

        loadFromStorage() {
            if (!this.ctx || !this.canvas) {
                return;
            }

            this.strokes = [];
            this.currentPath = null;
            this.textInput = { active: false, x: 0, y: 0, content: '' };
            this.backgroundDataUrl = null;

            try {
                const raw = localStorage.getItem(this.storageKey);
                if (!raw) {
                    this.redraw();
                    return;
                }

                if (raw.startsWith('{')) {
                    const data = JSON.parse(raw);
                    if (data.version === 2 && Array.isArray(data.strokes)) {
                        this.strokes = data.strokes;
                        this.backgroundDataUrl = data.backgroundDataUrl || null;

                        if (data.canvasWidth && data.canvasHeight && this._canvasWidth > 0) {
                            const scaleX = this._canvasWidth / data.canvasWidth;
                            const scaleY = this._canvasHeight / data.canvasHeight;

                            if (scaleX !== 1 || scaleY !== 1) {
                                this.scaleStrokes(scaleX, scaleY);
                            }
                        }

                        this.redraw();
                        return;
                    }
                }

                this.backgroundDataUrl = raw;
                this.redraw();
            } catch {
                this.redraw();
            }
        },

        cursorClass() {
            if (this.activeTool === 'eraser') {
                return 'cursor-cell';
            }

            if (this.activeTool === 'text') {
                return 'cursor-text';
            }

            return 'cursor-crosshair';
        },
    }));
});
