import Swal from 'sweetalert2';

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4500,
    timerProgressBar: true,
    customClass: {
        popup: 'swal2-toast-custom',
        title: 'swal2-toast-title',
    },
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

const iconMap = {
    success: 'success',
    error: 'error',
    info: 'info',
    warning: 'warning',
};

const shownChallengeDialogs = new Set();

export function showToast(type, message) {
    if (!message) {
        return;
    }

    Toast.fire({
        icon: iconMap[type] ?? 'info',
        title: message,
    });
}

function processFlashToasts() {
    document.querySelectorAll('[data-flash-toasts]:not([data-flash-processed])').forEach((element) => {
        let toasts = {};

        try {
            toasts = JSON.parse(element.dataset.flashToasts ?? '{}');
        } catch {
            element.dataset.flashProcessed = 'true';

            return;
        }

        Object.entries(toasts).forEach(([type, message]) => {
            showToast(type, message);
        });

        element.dataset.flashProcessed = 'true';
        element.remove();
    });
}

document.addEventListener('DOMContentLoaded', processFlashToasts);
document.addEventListener('livewire:navigated', processFlashToasts);

document.addEventListener('livewire:init', () => {
    Livewire.on('duel-challenge-received', ({ message, sessionId, notificationId }) => {
        const dialogKey = notificationId ?? sessionId;

        if (shownChallengeDialogs.has(dialogKey)) {
            return;
        }

        shownChallengeDialogs.add(dialogKey);

        Swal.fire({
            title: 'Tantangan Duel!',
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Terima',
            cancelButtonText: 'Tolak',
            confirmButtonColor: '#16a34a',
            cancelButtonColor: '#dc2626',
            allowOutsideClick: false,
            reverseButtons: true,
        }).then((result) => {
            if (result.isConfirmed) {
                Livewire.dispatch('accept-duel-challenge', { sessionId });
                Livewire.dispatch('mark-challenge-handled', { notificationId });
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                Livewire.dispatch('reject-duel-challenge', { sessionId, notificationId });
            } else {
                shownChallengeDialogs.delete(dialogKey);
            }
        });
    });

    Livewire.on('duel-challenge-rejected', ({ message }) => {
        showToast('warning', message);
    });

    Livewire.on('duel-challenge-rejected-self', ({ message }) => {
        showToast('info', message);
    });

    Livewire.on('mark-challenge-handled', ({ notificationId }) => {
        if (notificationId) {
            Livewire.dispatch('mark-challenge-notification-read', { notificationId });
        }
    });

    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            queueMicrotask(processFlashToasts);
        });
    });
});

window.showToast = showToast;
window.showFlashToasts = processFlashToasts;
