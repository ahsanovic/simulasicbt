import './sweetalert-confirm.js';
import './sweetalert-toast.js';
import './exam-timer.js';

document.addEventListener('livewire:navigated', () => {
    window.scrollTo({ top: 0, behavior: 'instant' });
});
