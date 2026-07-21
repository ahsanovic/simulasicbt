import './sweetalert-confirm.js';
import './sweetalert-toast.js';
import './exam-timer.js';
import './exam-stress-test.js';
import './exam-scratchpad.js';
import './readiness-radar-chart.js';
import './peserta-statistics-charts.js';
import './audio-mode-player.js';

document.addEventListener('livewire:navigated', () => {
    window.scrollTo({ top: 0, behavior: 'instant' });
});
