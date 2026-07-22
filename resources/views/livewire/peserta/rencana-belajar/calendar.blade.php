@php
    $monthLabel = \Illuminate\Support\Carbon::create($calendarYear, $calendarMonth, 1)->translatedFormat('F Y');
    $weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <button type="button" wire:click="previousMonth" class="ui-btn-ghost !px-2.5 !py-2" aria-label="Bulan sebelumnya">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <h3 class="min-w-[10rem] text-center text-base font-bold text-slate-900">{{ $monthLabel }}</h3>
            <button type="button" wire:click="nextMonth" class="ui-btn-ghost !px-2.5 !py-2" aria-label="Bulan berikutnya">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
        <button type="button" wire:click="goToToday" class="ui-btn-secondary !py-2 text-xs">Hari ini</button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50">
            @foreach ($weekdays as $day)
                <div class="px-2 py-2.5 text-center text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $day }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 auto-rows-[minmax(100px,1fr)]">
            @foreach ($calendarDays as $day)
                @php
                    $key = $day['date']->format('Y-m-d');
                    $dayTasks = $calendarTasks->get($key, collect());
                @endphp
                <div
                    @class([
                        'min-h-[100px] border-b border-r border-slate-100 p-1.5 transition',
                        'bg-white' => $day['isCurrentMonth'] && ! $day['isToday'],
                        'bg-slate-50/70' => ! $day['isCurrentMonth'],
                        'bg-blue-50/40' => $day['isToday'],
                    ])
                >
                    <div class="mb-1 flex items-center justify-between px-0.5">
                        <span @class([
                            'inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold',
                            'bg-blue-600 text-white' => $day['isToday'],
                            'text-slate-700' => $day['isCurrentMonth'] && ! $day['isToday'],
                            'text-slate-300' => ! $day['isCurrentMonth'],
                        ])>{{ $day['date']->day }}</span>
                    </div>

                    <div class="space-y-1">
                        @foreach ($dayTasks->take(3) as $task)
                            <button
                                type="button"
                                wire:click="openEditTaskModal({{ $task->id }})"
                                @class([
                                    'block w-full truncate rounded-md px-1.5 py-0.5 text-left text-[10px] font-bold leading-tight transition',
                                    $task->category->colorClasses(),
                                    'opacity-60 line-through' => $task->status->value === 'done',
                                ])
                                title="{{ $task->title }}"
                            >
                                {{ $task->category->emoji() }} {{ $task->title }}
                            </button>
                        @endforeach
                        @if ($dayTasks->count() > 3)
                            <p class="px-1 text-[10px] font-semibold text-slate-400">+{{ $dayTasks->count() - 3 }} lagi</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <p class="text-center text-xs text-slate-500">
        Tugas dengan tanggal jadwal akan muncul di kalender. Atur jadwal saat membuat/mengedit tugas.
    </p>
</div>
