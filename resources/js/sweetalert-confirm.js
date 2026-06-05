import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

let bypassConfirm = false;

const nativeConfirm = window.confirm.bind(window);

window.confirm = (message) => {
    if (bypassConfirm) {
        return true;
    }

    return nativeConfirm(message);
};

const defaultOptions = {
    confirmButtonText: 'Ya',
    cancelButtonText: 'Batal',
    reverseButtons: true,
    customClass: {
        confirmButton: 'swal2-confirm-custom',
        cancelButton: 'swal2-cancel-custom',
    },
};

function isDestructiveAction(message) {
    const normalized = message.toLowerCase();

    return normalized.includes('hapus')
        || normalized.includes('selesai')
        || normalized.includes('delete');
}

function showConfirmDialog(message) {
    const destructive = isDestructiveAction(message);

    return Swal.fire({
        ...defaultOptions,
        title: destructive ? 'Konfirmasi' : 'Apakah Anda yakin?',
        text: message,
        icon: destructive ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonColor: destructive ? '#e11d48' : '#4f46e5',
        cancelButtonColor: '#64748b',
        focusCancel: destructive,
    });
}

function showPromptDialog(question, expected) {
    return Swal.fire({
        ...defaultOptions,
        title: 'Konfirmasi',
        text: question,
        icon: 'warning',
        input: 'text',
        inputPlaceholder: expected,
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        focusCancel: true,
        preConfirm: (value) => {
            if (value !== expected) {
                Swal.showValidationMessage(`Ketik "${expected}" untuk melanjutkan.`);

                return false;
            }

            return value;
        },
    });
}

function triggerConfirmedAction(el) {
    bypassConfirm = true;
    el.click();

    setTimeout(() => {
        bypassConfirm = false;
    }, 100);
}

function getConfirmElement(target) {
    return target.closest('[wire\\:confirm], [wire\\:confirm\\.prompt]');
}

function getConfirmMessage(el) {
    return el.getAttribute('wire:confirm')
        ?? el.getAttribute('wire:confirm.prompt')
        ?? '';
}

function isPromptConfirm(el) {
    return el.hasAttribute('wire:confirm.prompt');
}

document.addEventListener('click', (event) => {
    if (bypassConfirm) {
        return;
    }

    const el = getConfirmElement(event.target);

    if (!el) {
        return;
    }

    const rawMessage = getConfirmMessage(el).replaceAll('\\n', '\n');

    if (!rawMessage) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    if (isPromptConfirm(el)) {
        const [question, expected] = rawMessage.split('|');

        if (!expected) {
            console.warn('Livewire: wire:confirm.prompt membutuhkan format "pesan|input"');

            return;
        }

        showPromptDialog(question, expected).then((result) => {
            if (result.isConfirmed) {
                triggerConfirmedAction(el);
            }
        });

        return;
    }

    showConfirmDialog(rawMessage).then((result) => {
        if (result.isConfirmed) {
            triggerConfirmedAction(el);
        }
    });
}, true);
