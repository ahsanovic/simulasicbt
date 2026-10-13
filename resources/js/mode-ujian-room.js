/**
 * Mode Ujian exam room running in the browser (App\Livewire\Peserta\ModeUjian\ClientExamRoom).
 *
 * Every question arrives once (script[data-room]); picking an option and
 * moving between questions never waits for the server. Each change is kept in
 * localStorage at once and sent in the background on every question change to
 * ModeUjianRoomController::answers, with a version that only ever increases so
 * a late, older request cannot overwrite a newer pick. "Selesai Ujian" and the
 * timer reaching zero send the unsent answers together with the submit.
 */
const RETRY_DELAYS_MS = [1000, 2000, 4000, 8000, 15000];
const REQUEST_TIMEOUT_MS = 15000;

const jitter = (ms) => ms + Math.random() * 1000;

function storageGet(key) {
    try {
        return window.localStorage.getItem(key);
    } catch (e) {
        return null; // private mode: the in-memory state still works
    }
}

function storageSet(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch (e) {
        // ignore
    }
}

function storageRemove(key) {
    try {
        window.localStorage.removeItem(key);
    } catch (e) {
        // ignore
    }
}

// A 200 that is not JSON (Mode Sedang Ujian page, captive portal) counts as a failed request.
async function readJson(response) {
    try {
        return await response.json();
    } catch (e) {
        return null;
    }
}

/**
 * Shared exam PCs keep localStorage between participants and sessions: drop
 * what other attempts left behind (their unsent answers can no longer be sent,
 * their device token is gone) so it never piles up to the storage quota.
 */
function clearOtherAttempts(keep) {
    try {
        const keys = [];

        for (let i = 0; i < window.localStorage.length; i++) {
            keys.push(window.localStorage.key(i));
        }

        keys.filter((key) => (key.startsWith('mode-ujian:') || key.startsWith('exam-seq:')) && ! keep.includes(key))
            .forEach((key) => window.localStorage.removeItem(key));
    } catch (e) {
        // Storage unavailable: nothing to clean.
    }
}

async function postJson(url, body) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

    try {
        return await fetch(url, {
            method: 'POST',
            keepalive: true,
            signal: controller.signal,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(body),
        });
    } finally {
        clearTimeout(timer);
    }
}

document.addEventListener('alpine:init', () => {
    Alpine.data('modeUjianRoom', () => ({
        kind: '',
        attemptId: 0,
        token: '',
        title: '',
        items: [],
        index: 0,
        seq: 0,
        pending: {},
        sending: false,
        failures: 0,
        retryTimer: null,
        offline: ! navigator.onLine,
        deviceReplaced: false,
        sessionExpired: false,
        finishing: false,
        showLastModal: false,
        channel: null,

        init() {
            const data = JSON.parse(this.$root.parentElement.querySelector('script[data-room]').textContent);

            this.kind = data.kind;
            this.attemptId = data.attemptId;
            this.token = data.deviceToken;
            this.title = data.title;
            // Question text and options never change: frozen, Alpine leaves them
            // unproxied and only tracks option_id / marked / version.
            this.items = data.items.map((item) => ({ ...item, options: Object.freeze(item.options.map((option) => Object.freeze(option))) }));

            clearOtherAttempts([this.seqKey, this.storageKey]);
            this.seq = Math.max(Number(storageGet(this.seqKey)) || 0, data.versionBase);

            this.restorePending();

            const firstOpen = this.items.findIndex((item) => item.option_id === null);
            this.index = firstOpen === -1 ? 0 : firstOpen;

            this.listen();
            window.dispatchEvent(new CustomEvent('exam-deadline-synced', { detail: { remainingSeconds: Number(this.$root.dataset.remaining) } }));
            this.flush();
        },

        get seqKey() {
            return `exam-seq:${this.kind}-${this.attemptId}`;
        },

        get storageKey() {
            return `mode-ujian:${this.kind}-${this.attemptId}`;
        },

        get current() {
            return this.items[this.index];
        },

        get answeredCount() {
            return this.items.filter((item) => item.option_id !== null).length;
        },

        get unansweredCount() {
            return this.items.length - this.answeredCount;
        },

        get progress() {
            return this.items.length === 0 ? 0 : Math.round((this.answeredCount * 100) / this.items.length);
        },

        get unsentCount() {
            return Object.keys(this.pending).length;
        },

        get locked() {
            return this.deviceReplaced || this.sessionExpired || this.finishing;
        },

        get isLast() {
            return this.index === this.items.length - 1;
        },

        navClass(item, position) {
            if (position === this.index) return 'qnav qnav-current';
            if (item.marked) return 'qnav qnav-marked';

            return item.option_id !== null ? 'qnav qnav-answered' : 'qnav qnav-empty';
        },

        listen() {
            window.addEventListener('online', () => {
                this.offline = false;
                setTimeout(() => this.flush(), Math.random() * 3000);
            });
            window.addEventListener('offline', () => { this.offline = true; });
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') this.flush();
            });
            window.addEventListener('exam-time-up', () => this.finish('time_up'));

            // The newest tab wins (same rule as the server's device token):
            // an older tab of this attempt locks itself straight away.
            if ('BroadcastChannel' in window) {
                this.channel = new BroadcastChannel(`mode-ujian-${this.kind}-${this.attemptId}`);
                this.channel.onmessage = (event) => {
                    if (event.data === 'opened') this.deviceReplaced = true;
                };
                this.channel.postMessage('opened');
            }
        },

        nextVersion() {
            this.seq = Math.max(this.seq, Number(storageGet(this.seqKey)) || 0) + 1;
            storageSet(this.seqKey, String(this.seq));

            return this.seq;
        },

        change(fields) {
            const item = this.current;

            Object.assign(item, fields, { version: this.nextVersion() });
            this.pending[item.id] = { id: item.id, option_id: item.option_id, is_marked: item.marked, version: item.version };
            this.persist();
        },

        pick(optionId) {
            if (this.locked) return;
            this.change({ option_id: optionId });
        },

        toggleMark() {
            if (this.locked) return;
            this.change({ marked: ! this.current.marked });
            this.flush();
        },

        go(index) {
            if (this.locked || index < 0 || index >= this.items.length) return;
            this.index = index;
            this.showLastModal = false;
            window.scrollTo({ top: 0, behavior: 'instant' });
            this.flush();
        },

        next() {
            if (this.isLast) {
                this.showLastModal = true;
                this.flush();

                return;
            }

            this.go(this.index + 1);
        },

        prev() {
            this.go(this.index - 1);
        },

        persist() {
            storageSet(this.storageKey, JSON.stringify(this.pending));
        },

        restorePending() {
            let stored = {};

            try {
                stored = JSON.parse(storageGet(this.storageKey) || '{}') || {};
            } catch (e) {
                stored = {};
            }

            Object.values(stored).forEach((entry) => {
                const item = this.items.find((candidate) => candidate.id === entry.id);

                if (item && entry.version > item.version) {
                    item.option_id = entry.option_id;
                    item.marked = entry.is_marked;
                    item.version = entry.version;
                    this.pending[item.id] = entry;
                }
            });

            this.persist();
        },

        body(answers, extra = {}) {
            return { kind: this.kind, attempt_id: this.attemptId, device_token: this.token, answers, ...extra };
        },

        async flush() {
            if (this.sending || this.deviceReplaced || this.sessionExpired || this.unsentCount === 0) return;

            clearTimeout(this.retryTimer);
            this.sending = true;
            const batch = Object.values(this.pending);
            let response;

            try {
                response = await postJson(this.$root.dataset.answersUrl, this.body(batch));
            } catch (e) {
                this.sending = false;
                this.retryLater();

                return;
            }

            this.sending = false;

            const result = response.ok ? await readJson(response) : null;

            if (result) {
                this.failures = 0;
                this.acknowledge(batch, [...result.saved, ...result.stale]);
                this.synced(result.remaining);

                if (result.stale.length > 0) {
                    // The server holds a newer answer (another device): show it.
                    window.location.reload();

                    return;
                }

                if (this.unsentCount > 0) this.flush();

                return;
            }

            if (response.ok) {
                this.retryLater();

                return;
            }

            this.handleRefusal(response, batch);
        },

        acknowledge(batch, ids) {
            batch.forEach((sent) => {
                const now = this.pending[sent.id];

                if (now && now.version === sent.version && ids.includes(sent.id)) {
                    delete this.pending[sent.id];
                }
            });
            this.persist();
        },

        async handleRefusal(response, batch) {
            if (response.status === 409) {
                const result = await response.json().catch(() => ({}));

                if (result.error === 'device') {
                    this.deviceReplaced = true;

                    return;
                }

                this.finish('time_up');

                return;
            }

            if (response.status === 401 || response.status === 419) {
                this.sessionExpired = true;

                return;
            }

            if (response.status === 422) {
                // Not something a retry fixes: drop it and show the server's state.
                this.acknowledge(batch, batch.map((sent) => sent.id));
                window.location.reload();

                return;
            }

            this.retryLater();
        },

        retryLater() {
            this.failures += 1;
            const delay = RETRY_DELAYS_MS[Math.min(this.failures, RETRY_DELAYS_MS.length) - 1];
            this.retryTimer = setTimeout(() => this.flush(), jitter(delay));
        },

        synced(remainingSeconds) {
            window.examLastSyncAt = Date.now();
            window.dispatchEvent(new CustomEvent('exam-deadline-synced', { detail: { remainingSeconds } }));
        },

        get finishMessage() {
            return this.unansweredCount > 0
                ? `Masih ada ${this.unansweredCount} soal belum dijawab. Yakin ingin menyelesaikan ujian sekarang? Skor akan disimpan.`
                : 'Semua soal sudah dijawab. Selesaikan ujian ini? Skor akan disimpan.';
        },

        async confirmFinish() {
            if (this.locked) return;

            const confirmed = window.examConfirm ? await window.examConfirm(this.finishMessage) : window.confirm(this.finishMessage);

            if (confirmed) this.finish('manual');
        },

        async finish(reason) {
            if (this.finishing || this.deviceReplaced) return;

            this.finishing = true;
            clearTimeout(this.retryTimer);
            let response;

            try {
                response = await postJson(this.$root.dataset.finishUrl, this.body(Object.values(this.pending), { reason }));
            } catch (e) {
                this.finishing = false;
                setTimeout(() => this.finish(reason), jitter(3000));

                return;
            }

            const result = response.ok ? await readJson(response) : null;

            if (result) {
                if (result.result_url) {
                    storageRemove(this.storageKey);
                    window.location.assign(result.result_url);

                    return;
                }

                // Time was added: carry on.
                this.finishing = false;
                this.pending = {};
                this.persist();
                this.synced(result.remaining);

                return;
            }

            if (response.status === 409) {
                this.deviceReplaced = true;

                return;
            }

            if (response.status === 401 || response.status === 419) {
                this.sessionExpired = true;

                return;
            }

            this.finishing = false;
            setTimeout(() => this.finish(reason), jitter(3000));
        },
    }));
});
