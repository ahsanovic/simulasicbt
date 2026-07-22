import Sortable from 'sortablejs';

function rebindAllPlannerBoards() {
    document.querySelectorAll('[data-planner-kanban]').forEach((boardEl) => {
        const component = window.Alpine?.$data(boardEl);

        if (component?.bindSortables) {
            component.bindSortables();
        }
    });
}

document.addEventListener('alpine:init', () => {
    Alpine.data('plannerKanban', () => ({
        sortables: [],

        init() {
            this.$nextTick(() => this.bindSortables());
        },

        bindSortables() {
            this.sortables.forEach((instance) => instance.destroy());
            this.sortables = [];

            this.$el.querySelectorAll('[data-sortable-column]').forEach((columnEl) => {
                const sortable = Sortable.create(columnEl, {
                    group: {
                        name: 'rencana-belajar-board',
                        pull: true,
                        put: true,
                    },
                    animation: 180,
                    easing: 'cubic-bezier(0.2, 0, 0, 1)',
                    draggable: '[data-task-id]',
                    handle: '[data-drag-handle]',
                    filter: '.no-drag',
                    preventOnFilter: false,
                    delay: 150,
                    delayOnTouchOnly: true,
                    touchStartThreshold: 4,
                    forceFallback: true,
                    fallbackTolerance: 5,
                    swapThreshold: 0.65,
                    emptyInsertThreshold: 56,
                    ghostClass: 'planner-card-ghost',
                    chosenClass: 'planner-card-chosen',
                    dragClass: 'planner-card-drag',
                    onEnd: (evt) => this.onDragEnd(evt),
                    onAdd: () => this.syncColumnMeta(),
                    onUpdate: () => this.syncColumnMeta(),
                    onRemove: () => this.syncColumnMeta(),
                });

                this.sortables.push(sortable);
            });

            this.syncColumnMeta();
        },

        collectTaskIds(columnEl) {
            return Array.from(columnEl.querySelectorAll(':scope > [data-task-id]'))
                .map((card) => Number(card.dataset.taskId))
                .filter((id) => Number.isFinite(id) && id > 0);
        },

        syncColumnMeta() {
            this.$el.querySelectorAll('[data-sortable-column]').forEach((columnEl) => {
                const status = columnEl.dataset.sortableColumn;
                const count = columnEl.querySelectorAll(':scope > [data-task-id]').length;

                const badge = this.$el.querySelector(`[data-column-count="${status}"]`);
                if (badge) {
                    badge.textContent = String(count);
                }

                const empty = this.$el.querySelector(`[data-column-empty="${status}"]`);
                if (empty) {
                    empty.classList.toggle('hidden', count > 0);
                }
            });
        },

        onDragEnd(evt) {
            this.syncColumnMeta();

            const fromStatus = evt.from?.dataset?.sortableColumn;
            const toStatus = evt.to?.dataset?.sortableColumn;

            if (!fromStatus || !toStatus) {
                return;
            }

            if (fromStatus === toStatus && evt.oldIndex === evt.newIndex) {
                return;
            }

            const toIds = this.collectTaskIds(evt.to);
            this.$wire.reorderBoard(toStatus, toIds);

            if (fromStatus !== toStatus) {
                const fromIds = this.collectTaskIds(evt.from);
                this.$wire.reorderBoard(fromStatus, fromIds);
            }
        },
    }));
});

document.addEventListener('livewire:navigated', () => {
    queueMicrotask(() => rebindAllPlannerBoards());
});

document.addEventListener('livewire:init', () => {
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            queueMicrotask(() => rebindAllPlannerBoards());
        });
    });
});
