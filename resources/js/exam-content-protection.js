import { showToast } from './sweetalert-toast.js';

/**
 * Mode Ujian content protection: stops copying the questions and makes
 * screenshots as hard as a web page can.
 *
 * A page cannot truly block OS-level screenshots (PrintScreen, Snipping Tool,
 * a phone camera). What it can do: wipe the clipboard after PrintScreen, cover
 * the questions for a moment, and blur them whenever the window loses focus
 * (snipping tools take focus). Violations are blocked and warned about — they
 * never log the peserta out; leaving the tab is handled by mode-ujian-guard.
 */

const BLOCKED_CTRL_KEYS = new Set(['a', 'c', 'p', 's', 'u', 'x']);
const BLOCKED_CTRL_SHIFT_KEYS = new Set(['c', 'i', 'j', 's']);
const WARNING_MESSAGE = 'Tindakan ini tidak diizinkan selama ujian.';
const SCREENSHOT_MESSAGE = 'Screenshot tidak diizinkan selama ujian.';

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
 * @param {{ doc: Document, win: Window, onViolation: (message: string) => void, onShield: (seconds: number) => void, onFocusChange: (focused: boolean) => void }} deps
 * @returns {() => void} uninstall
 */
export function installExamContentProtection({ doc, win, onViolation, onShield, onFocusChange }) {
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

    const wipeClipboard = () => {
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
            event.preventDefault();
            wipeClipboard();
            onShield(2);
            onViolation(SCREENSHOT_MESSAGE);

            return;
        }

        if (isBlockedShortcut(event)) {
            event.preventDefault();
            event.stopPropagation();
            onViolation(WARNING_MESSAGE);
        }
    }, true);

    // Most browsers only report PrintScreen on keyup, after the OS has
    // already taken the shot: wipe the clipboard copy it just made.
    on(doc, 'keyup', (event) => {
        if (String(event.key ?? '').toLowerCase() === 'printscreen') {
            wipeClipboard();
            onShield(2);
            onViolation(SCREENSHOT_MESSAGE);
        }
    }, true);

    on(win, 'beforeprint', () => onViolation(WARNING_MESSAGE));
    on(win, 'blur', () => onFocusChange(false));
    on(win, 'focus', () => onFocusChange(true));

    return () => listeners.splice(0).forEach((remove) => remove());
}

document.addEventListener('alpine:init', () => {
    Alpine.data('examContentProtection', () => ({
        shielded: false,
        shieldTimer: null,
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
                onShield: (seconds) => this.shield(seconds),
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

        shield(seconds) {
            this.shielded = true;
            clearTimeout(this.shieldTimer);
            this.shieldTimer = setTimeout(() => { this.shielded = false; }, seconds * 1000);
        },

        destroy() {
            this.uninstall?.();
            clearTimeout(this.shieldTimer);
            document.documentElement.classList.remove('exam-protected', 'exam-unfocused');
        },
    }));
});
