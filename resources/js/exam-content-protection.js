import { showToast } from './sweetalert-toast.js';

/**
 * Mode Ujian content protection: stops copying the questions and makes
 * screenshots as hard as a web page can.
 *
 * A page cannot truly block OS-level screenshots (PrintScreen, Snipping Tool,
 * a phone camera). What it can do: wipe the clipboard after PrintScreen and
 * blur the questions whenever the window loses focus (snipping tools take
 * focus). Blocked copy/print/devtools actions get a warning; nothing here ever
 * logs the peserta out — leaving the tab is handled by mode-ujian-guard.
 *
 * PrintScreen is handled SILENTLY. Remote/recording apps common in exam rooms
 * (AnyDesk, TeamViewer, UltraViewer, OBS, Lightshot, ShareX…) inject or hook
 * that key at OS level, so the page receives trusted PrintScreen events the
 * peserta never pressed — e.g. on every answer click. Those are
 * indistinguishable from a real press, so they must never cover the screen or
 * accuse the peserta; quietly emptying the clipboard is harmless either way.
 */

const BLOCKED_CTRL_KEYS = new Set(['a', 'c', 'p', 's', 'u', 'x']);
const BLOCKED_CTRL_SHIFT_KEYS = new Set(['c', 'i', 'j', 's']);
const WARNING_MESSAGE = 'Tindakan ini tidak diizinkan selama ujian.';
const CLIPBOARD_WIPE_COOLDOWN_MS = 3000;

function isEditable(target) {
    return Boolean(target?.closest?.('input, textarea, [contenteditable="true"]'));
}

export function isBlockedShortcut(event) {
    const key = String(event.key ?? '').toLowerCase();

    if (key === 'f12') {
        return true;
    }

    if (! event.ctrlKey && ! event.metaKey) {
        return false;
    }

    return event.shiftKey ? BLOCKED_CTRL_SHIFT_KEYS.has(key) : BLOCKED_CTRL_KEYS.has(key);
}

/**
 * Wire the protection onto a document/window pair.
 *
 * @param {{ doc: Document, win: Window, onViolation: (message: string) => void, onFocusChange: (focused: boolean) => void, now?: () => number }} deps
 * @returns {() => void} uninstall
 */
export function installExamContentProtection({ doc, win, onViolation, onFocusChange, now = () => Date.now() }) {
    const listeners = [];
    const on = (target, type, handler, options) => {
        target.addEventListener(type, handler, options);
        listeners.push(() => target.removeEventListener(type, handler, options));
    };

    const block = (event) => {
        if (isEditable(event.target)) {
            return;
        }

        event.preventDefault();
        onViolation(WARNING_MESSAGE);
    };

    // Throttled: injected PrintScreen events can arrive on every click, and
    // clipboard sync in remote tools should not be hammered by our writes.
    let lastWipeAt = -Infinity;
    const wipeClipboard = () => {
        if (now() - lastWipeAt < CLIPBOARD_WIPE_COOLDOWN_MS) {
            return;
        }

        lastWipeAt = now();

        try {
            const result = win.navigator?.clipboard?.writeText?.('');
            result?.catch?.(() => {});
        } catch {
            // Clipboard access can be refused; covering the screen still applies.
        }
    };

    on(doc, 'copy', block, true);
    on(doc, 'cut', block, true);
    on(doc, 'contextmenu', block, true);
    on(doc, 'dragstart', block, true);
    on(doc, 'selectstart', (event) => {
        if (! isEditable(event.target)) {
            event.preventDefault();
        }
    }, true);

    on(doc, 'keydown', (event) => {
        if (String(event.key ?? '').toLowerCase() === 'printscreen') {
            wipeClipboard();

            return;
        }

        if (isBlockedShortcut(event)) {
            event.preventDefault();
            event.stopPropagation();
            onViolation(WARNING_MESSAGE);
        }
    }, true);

    // Most browsers only report PrintScreen on keyup, after the OS has
    // already taken the shot: wipe the clipboard copy it just made. Silent on
    // purpose — see the note at the top of this file.
    on(doc, 'keyup', (event) => {
        if (String(event.key ?? '').toLowerCase() === 'printscreen') {
            wipeClipboard();
        }
    }, true);

    on(win, 'beforeprint', () => onViolation(WARNING_MESSAGE));
    on(win, 'blur', () => onFocusChange(false));
    on(win, 'focus', () => onFocusChange(true));

    return () => listeners.splice(0).forEach((remove) => remove());
}

document.addEventListener('alpine:init', () => {
    Alpine.data('examContentProtection', () => ({
        lastWarningAt: 0,
        uninstall: null,

        init() {
            const root = document.documentElement;
            root.classList.add('exam-protected');
            root.classList.toggle('exam-unfocused', ! document.hasFocus());

            this.uninstall = installExamContentProtection({
                doc: document,
                win: window,
                onViolation: (message) => this.warn(message),
                onFocusChange: (focused) => root.classList.toggle('exam-unfocused', ! focused),
            });
        },

        warn(message) {
            // One toast per burst (e.g. a held-down shortcut).
            const now = Date.now();

            if (now - this.lastWarningAt < 1500) {
                return;
            }

            this.lastWarningAt = now;
            showToast('warning', message);
        },

        destroy() {
            this.uninstall?.();
            document.documentElement.classList.remove('exam-protected', 'exam-unfocused');
        },
    }));
});
