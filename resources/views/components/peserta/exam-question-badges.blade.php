@props(['question'])

@php
    $code = $question->subject->code->value;
    $material = $question->material;
@endphp

<span @class([
    'ui-badge',
    'bg-blue-100 text-blue-700' => $code === 'twk',
    'bg-amber-100 text-amber-700' => $code === 'tiu',
    'bg-violet-100 text-violet-700' => $code === 'tkp',
])>{{ $question->subject->code->label() }}</span>

@if ($material)
    @if ($material->materialGroup)
        <span @class([
            'ui-badge',
            'bg-blue-50/80 text-blue-800 ring-1 ring-blue-100' => $code === 'twk',
            'bg-amber-50/80 text-amber-800 ring-1 ring-amber-100' => $code === 'tiu',
            'bg-violet-50/80 text-violet-800 ring-1 ring-violet-100' => $code === 'tkp',
        ])>{{ $material->materialGroup->name }}</span>

        <span class="ui-badge bg-slate-100 text-slate-700">{{ $material->name }}</span>
    @else
        <span @class([
            'ui-badge',
            'bg-blue-50 text-blue-800 ring-1 ring-blue-100' => $code === 'twk',
            'bg-amber-50 text-amber-800 ring-1 ring-amber-100' => $code === 'tiu',
            'bg-violet-50 text-violet-800 ring-1 ring-violet-100' => $code === 'tkp',
        ])>{{ $material->name }}</span>
    @endif
@endif
