import './sweetalert-confirm.js';
import './sweetalert-toast.js';
import './exam-timer.js';
import './readiness-radar-chart.js';

document.addEventListener('livewire:navigated', () => {
    window.scrollTo({ top: 0, behavior: 'instant' });
});
