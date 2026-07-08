@props([
    'focus' => null,
])

@if ($focus)
    <div
        x-data="{
            focus: @js($focus),
            init() {
                const targetMap = {
                    readiness: 'feature-readiness-header',
                    'time-management': 'feature-time-management-button',
                    review: 'feature-review-button',
                    psychology: 'feature-review-button',
                };

                this.$nextTick(() => {
                    setTimeout(() => {
                        let targetId = targetMap[this.focus];

                        if (this.focus === 'time-management' && ! document.getElementById(targetId)) {
                            targetId = 'feature-readiness-card';
                        }

                        const el = document.getElementById(targetId);

                        if (! el) {
                            return;
                        }

                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });

                        const stop = () => {
                            el.classList.remove('ui-tour-pointer', 'ui-tour-pointer--inset');
                        };

                        setTimeout(stop, 15000);
                        el.addEventListener('click', stop, { once: true });

                        const url = new URL(window.location.href);
                        url.searchParams.delete('focus');
                        window.history.replaceState({}, '', url);
                    }, 350);
                });
            },
        }"
        x-init="init()"
        class="hidden"
        aria-hidden="true"
    ></div>
@endif
