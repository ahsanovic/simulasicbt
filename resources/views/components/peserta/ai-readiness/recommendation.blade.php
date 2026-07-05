@props([
    'recommendation' => null,
    'sectionId' => 'readiness-section-recommendation',
    'size' => 'sm',
    'showHeading' => true,
])

<div id="{{ $sectionId }}" @class([
    'scroll-mt-4 rounded-xl border border-primary-100 bg-white/80',
    'p-3.5' => $size === 'sm',
    'p-6 sm:p-8' => $size === 'lg',
])>
    @if ($showHeading)
        <p @class([
            'mb-2 font-bold uppercase tracking-wider text-primary-600',
            'text-[11px]' => $size === 'sm',
            'text-xs' => $size === 'lg',
        ])>Rekomendasi AI</p>
    @endif
    <div @class([
        'prose max-w-none leading-relaxed text-slate-700 whitespace-pre-line',
        'prose-sm text-[13px]' => $size === 'sm',
        'prose-base sm:prose-lg text-base' => $size === 'lg',
    ])>{{ format_ai_recommendation($recommendation) }}</div>
</div>
