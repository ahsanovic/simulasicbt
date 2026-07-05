@props([
    'weaknessStats' => [],
    'size' => 'sm',
    'sectionId' => 'readiness-section-chart',
])

@php
    $maxWidth = match ($size) {
        'lg' => 'max-w-md',
        'md' => 'max-w-sm',
        default => 'max-w-[240px]',
    };
    $canvasHeight = match ($size) {
        'lg' => 320,
        'md' => 280,
        default => 220,
    };
@endphp

<div id="{{ $sectionId }}" class="scroll-mt-4">
    @if ($size !== 'sm')
        <p class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500">Grafik Kesiapan Pilar</p>
    @endif
    <div data-readiness-chart data-stats='@json($weaknessStats)' @class(['mx-auto shrink-0', $maxWidth])>
        <canvas height="{{ $canvasHeight }}"></canvas>
    </div>
</div>
