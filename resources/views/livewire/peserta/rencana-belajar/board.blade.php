@php
    use App\Enums\LearningPlanTaskStatus;
    $readOnly = $readOnly ?? false;
    $columns = [
        LearningPlanTaskStatus::Todo,
        LearningPlanTaskStatus::InProgress,
        LearningPlanTaskStatus::Done,
    ];
@endphp

<div
    wire:key="planner-board-{{ $selectedPlan->id }}"
    @if (! $readOnly)
        data-planner-kanban
        x-data="plannerKanban()"
        x-init="init()"
    @endif
    class="grid gap-4 lg:grid-cols-3"
>
    @foreach ($columns as $column)
        @php
            $key = $column->value;
            $tasks = $board[$key] ?? collect();
        @endphp
        <div class="flex min-h-[420px] flex-col rounded-2xl border border-slate-200/80 bg-slate-50/80">
            <div class="flex items-center justify-between gap-2 border-b border-slate-200/70 px-3 py-3">
                <div class="flex items-center gap-2">
                    <span @class(['inline-flex items-center rounded-lg border px-2.5 py-1 text-xs font-bold', $column->columnAccent()])>
                        {{ $column->columnTitle() }}
                    </span>
                    <span
                        data-column-count="{{ $column->value }}"
                        class="rounded-full bg-white px-2 py-0.5 text-xs font-bold tabular-nums text-slate-500 ring-1 ring-slate-200"
                    >
                        {{ $tasks->count() }}
                    </span>
                </div>
                @if (! $readOnly && ($column === LearningPlanTaskStatus::Todo || $column === LearningPlanTaskStatus::InProgress))
                    <button
                        type="button"
                        wire:click="openCreateTaskModal('{{ $column->value }}')"
                        class="no-drag rounded-lg p-1.5 text-slate-400 transition hover:bg-white hover:text-blue-600"
                        title="Tambah tugas"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </button>
                @endif
            </div>

            <div class="relative flex flex-1 flex-col p-2.5" style="min-height: 320px;">
                <div
                    data-column-empty="{{ $column->value }}"
                    @class([
                        'pointer-events-none absolute inset-2.5 z-0 flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-white/50 px-4 text-center',
                        'hidden' => $tasks->isNotEmpty(),
                    ])
                >
                    <p class="text-xs font-semibold text-slate-400">Seret tugas ke sini</p>
                    <p class="mt-1 text-[11px] text-slate-400">atau klik + untuk menambah</p>
                </div>

                <div
                    data-sortable-column="{{ $column->value }}"
                    class="planner-column-drop relative z-10 flex flex-1 flex-col gap-2.5"
                    style="min-height: 280px;"
                >
                    @foreach ($tasks as $task)
                        @php $progress = $task->subtaskProgress(); @endphp
                        <article
                            wire:key="task-card-{{ $task->id }}"
                            data-task-id="{{ $task->id }}"
                            class="group {{ $readOnly ? '' : 'cursor-grab touch-manipulation active:cursor-grabbing' }} rounded-2xl border border-slate-200/90 bg-white p-3 shadow-sm shadow-slate-200/40 transition {{ $readOnly ? '' : 'hover:border-blue-200 hover:shadow-md hover:shadow-blue-600/10' }}"
                        >
                            <div class="flex items-start gap-2">
                                @if (! $readOnly)
                                <div
                                    data-drag-handle
                                    class="mt-0.5 inline-flex shrink-0 cursor-grab rounded-md p-1 text-slate-300 transition group-hover:text-slate-500 active:cursor-grabbing"
                                    aria-hidden="true"
                                    title="Seret untuk memindahkan"
                                >
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M7 4a1 1 0 11-2 0 1 1 0 012 0zm0 6a1 1 0 11-2 0 1 1 0 012 0zm0 6a1 1 0 11-2 0 1 1 0 012 0zm8-12a1 1 0 11-2 0 1 1 0 012 0zm0 6a1 1 0 11-2 0 1 1 0 012 0zm0 6a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                                </div>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span @class(['ui-badge !px-2 !py-0.5', $task->category->colorClasses()])>
                                            {{ $task->category->emoji() }} {{ $task->category->label() }}
                                        </span>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $task->priority->dotClass() }}"></span>
                                            {{ $task->priority->label() }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm font-bold leading-snug text-slate-900">
                                        {{ $task->title }}
                                    </p>

                                    @if ($task->scheduled_at)
                                        <p class="mt-1.5 flex items-center gap-1 text-[11px] font-semibold text-slate-500">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            {{ $task->scheduled_at->translatedFormat('d M Y') }}
                                        </p>
                                    @endif

                                    @if ($progress['total'] > 0)
                                        <div class="mt-3">
                                            <div class="mb-1 flex items-center justify-between text-[11px] font-semibold text-slate-500">
                                                <span>Sub-tugas</span>
                                                <span>{{ $progress['done'] }}/{{ $progress['total'] }}</span>
                                            </div>
                                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                                <div class="h-full rounded-full bg-blue-500 transition-all" style="width: {{ $progress['percent'] }}%"></div>
                                            </div>
                                            <ul class="mt-2 space-y-1">
                                                @foreach ($task->subtasks as $sub)
                                                    <li class="flex items-center gap-2">
                                                        @if ($readOnly)
                                                            <span
                                                                @class([
                                                                    'flex h-4 w-4 shrink-0 items-center justify-center rounded border',
                                                                    'border-emerald-500 bg-emerald-500 text-white' => $sub->status->value === 'done',
                                                                    'border-slate-300 bg-slate-50' => $sub->status->value !== 'done',
                                                                ])
                                                                aria-hidden="true"
                                                            >
                                                                @if ($sub->status->value === 'done')
                                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                                @endif
                                                            </span>
                                                        @else
                                                        <button
                                                            type="button"
                                                            wire:click="toggleSubtask({{ $sub->id }})"
                                                            @class([
                                                                'no-drag flex h-4 w-4 shrink-0 items-center justify-center rounded border transition',
                                                                'border-emerald-500 bg-emerald-500 text-white' => $sub->status->value === 'done',
                                                                'border-slate-300 bg-white hover:border-blue-400' => $sub->status->value !== 'done',
                                                            ])
                                                        >
                                                            @if ($sub->status->value === 'done')
                                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                            @endif
                                                        </button>
                                                        @endif
                                                        <span @class([
                                                            'truncate text-xs',
                                                            'text-slate-400 line-through' => $sub->status->value === 'done',
                                                            'text-slate-600' => $sub->status->value !== 'done',
                                                        ])>{{ $sub->title }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif

                                    @if (! $readOnly)
                                    <div class="no-drag mt-3 flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-1">
                                            @if ($task->category->routeName())
                                                <a
                                                    href="{{ route($task->category->routeName()) }}"
                                                    wire:navigate
                                                    class="no-drag rounded-lg px-2 py-1 text-[11px] font-bold text-blue-600 hover:bg-blue-50"
                                                >
                                                    Buka →
                                                </a>
                                            @endif
                                            <button type="button" wire:click="openCreateTaskModal('todo', {{ $task->id }})" class="no-drag rounded-lg px-2 py-1 text-[11px] font-bold text-slate-500 hover:bg-slate-100">
                                                + Sub
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <button
                                                type="button"
                                                wire:click="openEditTaskModal({{ $task->id }})"
                                                class="no-drag rounded-lg px-2 py-1 text-[11px] font-bold text-blue-600 hover:bg-blue-50"
                                            >Edit</button>
                                            <button
                                                type="button"
                                                wire:click="deleteTask({{ $task->id }})"
                                                wire:confirm="Hapus tugas ini?"
                                                class="no-drag rounded-lg px-2 py-1 text-[11px] font-bold text-rose-500 hover:bg-rose-50"
                                            >Hapus</button>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
