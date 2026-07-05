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
    Livewire.on('duel-challenge-received', ({ message, url }) => {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: message,
            showConfirmButton: true,
            confirmButtonText: 'Gabung Duel',
            showCancelButton: true,
            cancelButtonText: 'Nanti',
            timer: 15000,
            timerProgressBar: true,
            customClass: {
                popup: 'swal2-toast-custom',
                title: 'swal2-toast-title',
            },
        }).then((result) => {
            if (result.isConfirmed && url) {
                if (typeof Livewire !== 'undefined' && Livewire.navigate) {
                    Livewire.navigate(url);
                } else {
                    window.location.href = url;
                }
            }
        });
    });

    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            queueMicrotask(processFlashToasts);
        });
    });
});

window.showToast = showToast;
window.showFlashToasts = processFlashToasts;
