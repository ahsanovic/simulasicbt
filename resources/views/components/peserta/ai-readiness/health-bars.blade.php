@props([
    'materials' => [],
    'sectionId' => 'readiness-section-health',
    'columns' => 1,
])

@if (! empty($materials))
    <div id="{{ $sectionId }}" @class([
        'scroll-mt-4 space-y-2.5',
        'sm:grid sm:grid-cols-2 sm:gap-x-6 sm:gap-y-3 sm:space-y-0' => $columns === 2,
    ])>
        <p @class([
            'text-[11px] font-bold uppercase tracking-wider text-slate-500',
            'sm:col-span-2' => $columns === 2,
        ])>Health Bar Sub-Materi</p>
        @foreach ($materials as $material)
            <div wire:key="material-{{ md5($material['display_name']) }}" class="space-y-1">
                <div class="flex items-center justify-between gap-2 text-[11px]">
                    <span class="min-w-0 truncate font-medium text-slate-700" title="{{ $material['display_name'] }}">
                        {{ $material['display_name'] }}
                    </span>
                    <span @class([
                        'shrink-0 font-bold tabular-nums',
                        'text-emerald-600' => $material['status'] === 'aman',
                        'text-amber-600' => $material['status'] === 'cukup',
                        'text-red-600' => $material['status'] === 'kritis',
                    ])>{{ $material['percentage'] }}%</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                    <div @class([
                        'h-full rounded-full transition-all duration-500',
                        'bg-emerald-500' => $material['status'] === 'aman',
                        'bg-amber-400' => $material['status'] === 'cukup',
                        'bg-red-500' => $material['status'] === 'kritis',
                    ]) style="width: {{ $material['percentage'] }}%"></div>
                </div>
                <p @class([
                    'text-[10px] font-medium',
                    'text-emerald-600' => $material['status'] === 'aman',
                    'text-amber-600' => $material['status'] === 'cukup',
                    'text-red-600' => $material['status'] === 'kritis',
                ])>{{ $material['status_label'] }}</p>
            </div>
        @endforeach
    </div>
@endif
