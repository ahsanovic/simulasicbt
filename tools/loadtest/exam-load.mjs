#!/usr/bin/env node
/**
 * Load test for Mode Ujian (SKD / SKB exam rooms).
 *
 * Each virtual participant speaks the same Livewire protocol as the browser:
 * exam login -> PIN -> exam room -> answer questions with "Simpan & Lanjutkan"
 * (with X-Exam-Seq), plus the 30 s deadline poll. No browser needed, so one
 * machine can simulate hundreds of participants.
 *
 * Accounts: php artisan exam:loadtest-users <session_id> --count=420
 * Cleanup:  php artisan exam:loadtest-users --cleanup
 *
 * Usage (Node 18+):
 *   node tools/loadtest/exam-load.mjs --base=https://host/simulasi-cbt \
 *        --users=420 --pin=1234 --phase=skd --questions=15 --think=5-15 --ramp=120
 *
 * Options:
 *   --base       app URL (with base path, no trailing slash)        [required]
 *   --pin        session PIN for the chosen phase                    [required]
 *   --users      number of virtual participants                      (420)
 *   --phase      skd | skb                                           (skd)
 *   --questions  answers each participant saves                      (15)
 *   --think      seconds between answers, "min-max"                  (5-15)
 *   --ramp       seconds over which participants log in              (120)
 *   --poll       deadline poll interval in seconds                   (30)
 *   --password   password of the loadtest_ accounts                  (loadtest123)
 *   --submit     also click "Selesai Ujian" at the end               (off)
 *   --start      first account number                                (1)
 */

const args = Object.fromEntries(process.argv.slice(2).map((a) => {
    const [k, ...v] = a.replace(/^--/, '').split('=');
    return [k, v.length ? v.join('=') : true];
}));

const BASE = String(args.base || '').replace(/\/$/, '');
const PIN = String(args.pin || '');
const USERS = Number(args.users || 420);
const PHASE = String(args.phase || 'skd');
const QUESTIONS = Number(args.questions || 15);
const [THINK_MIN, THINK_MAX] = String(args.think || '5-15').split('-').map(Number);
const RAMP = Number(args.ramp ?? 120);
const POLL = Number(args.poll || 30);
const PASSWORD = String(args.password || 'loadtest123');
const SUBMIT = Boolean(args.submit);
const START = Number(args.start || 1);

if (!BASE || !PIN) {
    console.error('Wajib: --base=<url aplikasi> --pin=<PIN sesi>. Lihat komentar di atas file.');
    process.exit(1);
}

// ---------------------------------------------------------------- metrics
const metrics = new Map(); // action -> { ms: [], ok, fail }
const errors = new Map(); // message -> count
const metric = (action) => {
    if (!metrics.has(action)) metrics.set(action, { ms: [], ok: 0, fail: 0 });
    return metrics.get(action);
};
const recordError = (msg) => errors.set(msg, (errors.get(msg) || 0) + 1);
const pct = (sorted, p) => (sorted.length ? sorted[Math.min(sorted.length - 1, Math.floor((p / 100) * sorted.length))] : 0);
let finished = 0;
let failedUsers = 0;
const startedAt = Date.now();

function report(final = false) {
    const secs = ((Date.now() - startedAt) / 1000).toFixed(0);
    const lines = [`\n[${secs}s] peserta selesai ${finished}/${USERS}, gagal ${failedUsers}`];
    lines.push('aksi                     n     ok   gagal   p50    p95    p99    max (ms)');
    for (const [action, m] of metrics) {
        const s = [...m.ms].sort((a, b) => a - b);
        lines.push(`${action.padEnd(22)} ${String(s.length).padStart(5)} ${String(m.ok).padStart(6)} ${String(m.fail).padStart(6)} ${String(pct(s, 50)).padStart(6)} ${String(pct(s, 95)).padStart(6)} ${String(pct(s, 99)).padStart(6)} ${String(s.at(-1) || 0).padStart(6)}`);
    }
    if (final && errors.size) {
        lines.push('\nerror teratas:');
        [...errors].sort((a, b) => b[1] - a[1]).slice(0, 10).forEach(([m, n]) => lines.push(`  ${n}x ${m}`));
    }
    console.log(lines.join('\n'));
}

// ---------------------------------------------------------------- http
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const rand = (min, max) => min + Math.random() * (max - min);
const decode = (s) => s.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');

class Client {
    constructor() {
        this.cookies = new Map();
    }

    cookieHeader() {
        return [...this.cookies].map(([k, v]) => `${k}=${v}`).join('; ');
    }

    store(res) {
        const set = res.headers.getSetCookie ? res.headers.getSetCookie() : [];
        for (const c of set) {
            const [pair] = c.split(';');
            const i = pair.indexOf('=');
            this.cookies.set(pair.slice(0, i).trim(), pair.slice(i + 1).trim());
        }
    }

    async request(url, options = {}, timeoutMs = 60000) {
        const ctrl = new AbortController();
        const timer = setTimeout(() => ctrl.abort(), timeoutMs);
        try {
            const res = await fetch(url, {
                redirect: 'manual',
                ...options,
                signal: ctrl.signal,
                headers: { cookie: this.cookieHeader(), 'user-agent': 'simulasicbt-loadtest', ...(options.headers || {}) },
            });
            this.store(res);
            return res;
        } finally {
            clearTimeout(timer);
        }
    }

    /** GET a page, following redirects; returns { url, html }. */
    async page(url) {
        for (let hop = 0; hop < 5; hop++) {
            const res = await this.request(url);
            if (res.status >= 300 && res.status < 400) {
                url = new URL(res.headers.get('location'), url).toString();
                continue;
            }
            if (res.status !== 200) throw new Error(`GET ${new URL(url).pathname} -> HTTP ${res.status}`);
            return { url, html: await res.text() };
        }
        throw new Error('terlalu banyak redirect');
    }
}

function parsePage(html, componentName) {
    const csrf = html.match(/name="csrf-token" content="([^"]+)"/)?.[1];
    const updateUri = html.match(/data-update-uri="([^"]+)"/)?.[1];
    let snapshot = null;
    for (const m of html.matchAll(/<[^>]+wire:snapshot="([^"]+)"[^>]*>/g)) {
        if (m[0].match(/wire:name="([^"]+)"/)?.[1] === componentName) snapshot = decode(m[1]);
    }
    return { csrf, updateUri, snapshot };
}

const optionIds = (html) => [...String(html || '').matchAll(/name="option"[^>]*value="(\d+)"|value="(\d+)"[^>]*name="option"/g)].map((m) => Number(m[1] || m[2]));

/** One Livewire update; returns the component's { snapshot, effects }. */
async function wire(client, state, action, { updates = {}, calls = [], seq = null, timeoutMs = 60000 } = {}) {
    const t0 = Date.now();
    const m = metric(action);
    try {
        const res = await client.request(state.updateUri, {
            method: 'POST',
            headers: {
                'content-type': 'application/json',
                'x-livewire': '1',
                ...(seq !== null ? { 'x-exam-seq': String(seq) } : {}),
            },
            body: JSON.stringify({
                _token: state.csrf,
                components: [{ snapshot: state.snapshot, updates, calls: calls.map(([method, params = [], metadata = {}]) => ({ method, params, metadata })) }],
            }),
        }, timeoutMs);
        const body = await res.text();
        m.ms.push(Date.now() - t0);
        if (res.status !== 200) {
            m.fail++;
            throw new Error(`${action} -> HTTP ${res.status}`);
        }
        const json = JSON.parse(body);
        const component = json.components[0];
        state.snapshot = component.snapshot;
        m.ok++;
        return component;
    } catch (e) {
        if (e.name === 'AbortError') {
            m.ms.push(Date.now() - t0);
            m.fail++;
            throw new Error(`${action} -> timeout`);
        }
        throw e;
    }
}

// ---------------------------------------------------------------- participant
async function participant(n) {
    const username = `loadtest_${String(n).padStart(3, '0')}`;
    const client = new Client();
    const roomName = PHASE === 'skb' ? 'peserta.mode-ujian.skb-exam-room' : 'peserta.exam-room';

    // 1. Exam login
    let t0 = Date.now();
    let { html } = await client.page(`${BASE}/ujian/login`);
    metric('buka login').ms.push(Date.now() - t0); metric('buka login').ok++;
    let state = parsePage(html, 'auth.exam-login');
    if (!state.snapshot) throw new Error('komponen login tidak ditemukan');
    let comp = await wire(client, state, 'login', { updates: { login: username, password: PASSWORD }, calls: [['authenticate']] });
    const afterLogin = comp.effects?.redirect;
    if (!afterLogin) throw new Error('login gagal (tidak ada redirect) — akun/password?');

    // 2. Dashboard + PIN
    t0 = Date.now();
    ({ html } = await client.page(afterLogin));
    metric('buka dashboard').ms.push(Date.now() - t0); metric('buka dashboard').ok++;
    state = { ...parsePage(html, 'peserta.mode-ujian.dashboard') };
    if (!state.snapshot) throw new Error('dashboard mode ujian tidak ditemukan');
    await wire(client, state, 'buka PIN', { calls: [['openPinModal', [PHASE]]] });
    comp = await wire(client, state, 'kirim PIN (mulai ujian)', { updates: { pinInput: PIN }, calls: [['submitPin']] });
    const roomUrl = comp.effects?.redirect;
    if (!roomUrl) throw new Error('PIN ditolak / sesi belum dibuka');

    // 3. Exam room
    t0 = Date.now();
    ({ html } = await client.page(roomUrl));
    metric('buka ruang ujian').ms.push(Date.now() - t0); metric('buka ruang ujian').ok++;
    state = parsePage(html, roomName);
    if (!state.snapshot) throw new Error('ruang ujian tidak ditemukan');
    const key = html.match(/data-attempt-key="([^"]+)"/)?.[1];
    let seq = Number(html.match(/data-answer-version="(\d+)"/)?.[1] || 0);
    let options = optionIds(html);

    if (JSON.parse(state.snapshot).data?.needsNameConfirmation) {
        comp = await wire(client, state, 'konfirmasi nama', { updates: { displayNameInput: username }, calls: [['confirmDisplayName']], seq: key ? ++seq : null });
        options = optionIds(comp.effects?.html);
    }

    // Requests of one participant never overlap (the browser sends one at a time).
    let busy = Promise.resolve();
    const exclusive = (fn) => (busy = busy.then(fn, fn));
    let stop = false;
    const poller = (async () => {
        await sleep(rand(0, POLL * 1000));
        while (!stop) {
            await exclusive(() => wire(client, state, 'poll waktu', { calls: [['checkExpiry', [], { type: 'poll' }]], seq: key ? ++seq : null, timeoutMs: 15000 }).catch((e) => recordError(e.message)));
            await sleep(POLL * 1000);
        }
    })();

    for (let q = 0; q < QUESTIONS; q++) {
        await sleep(rand(THINK_MIN, THINK_MAX) * 1000);
        if (!options.length) throw new Error('opsi jawaban tidak ditemukan di halaman');
        const pick = options[Math.floor(Math.random() * options.length)];
        // Like the participant: if a save fails or times out, click again (max 3x).
        let saved = false;
        for (let attempt = 1; attempt <= 3 && !saved; attempt++) {
            await exclusive(async () => {
                const c = await wire(client, state, 'simpan & lanjutkan', { updates: { selectedOptionId: pick }, calls: [['next']], seq: key ? ++seq : null, timeoutMs: 30000 });
                if (c.effects?.html) options = optionIds(c.effects.html);
                saved = true;
            }).catch((e) => recordError(e.message));
        }
        if (!saved) throw new Error('simpan gagal 3x berturut-turut');
    }

    if (SUBMIT) {
        await exclusive(() => wire(client, state, 'selesai ujian', { calls: [['submitExam']], seq: key ? ++seq : null, timeoutMs: 30000 }));
    }

    stop = true;
    await poller.catch(() => {});
}

// ---------------------------------------------------------------- run
console.log(`Uji beban: ${USERS} peserta, fase ${PHASE.toUpperCase()}, ${QUESTIONS} jawaban/peserta, jeda ${THINK_MIN}-${THINK_MAX} dtk, ramp ${RAMP} dtk -> ${BASE}`);
const ticker = setInterval(() => report(false), 30000);

await Promise.all(Array.from({ length: USERS }, (_, i) => (async () => {
    await sleep((RAMP * 1000 * i) / USERS);
    try {
        await participant(START + i);
        finished++;
    } catch (e) {
        failedUsers++;
        recordError(e.message);
    }
})()));

clearInterval(ticker);
report(true);
