@props([
    'passingGrades' => null,
    'skdTarget' => null,
    'showAllProfiles' => false,
])

@php
    use App\Enums\SkdTarget;

    $profiles = $showAllProfiles
        ? SkdTarget::cases()
        : [($skdTarget instanceof SkdTarget ? $skdTarget : ($skdTarget ? SkdTarget::tryFrom($skdTarget) : null)) ?? SkdTarget::default()];
@endphp

<div {{ $attributes->merge(['class' => 'ui-card border-primary-100 bg-gradient-to-r from-primary-50/80 to-indigo-50/50 p-4 sm:p-5']) }}>
    <div class="flex flex-col gap-4">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-slate-900">Nilai Ambang Batas (Passing Grade)</p>
                <p class="mt-0.5 text-xs text-slate-500">
                    @if ($showAllProfiles)
                        Ambang batas mengikuti jenis simulasi. Semua komponen harus memenuhi ambang agar dinyatakan lulus.
                    @else
                        Standar {{ $profiles[0]->label() }} — semua komponen harus memenuhi ambang batas agar dinyatakan lulus.
                    @endif
                </p>
            </div>
        </div>

        <div @class([
            'grid gap-3',
            'sm:grid-cols-2' => count($profiles) > 1,
        ])>
            @foreach ($profiles as $profile)
                @php $grades = $passingGrades ?? exam_passing_grades($profile); @endphp
                <div @class([
                    'rounded-xl border border-white/80 bg-white/70 p-3',
                    'sm:p-4' => count($profiles) > 1,
                ])>
                    @if ($showAllProfiles || count($profiles) > 1)
                        <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-500">{{ $profile->label() }}</p>
                    @endif
                    <div class="flex flex-wrap gap-2">
                        <span class="ui-badge bg-blue-50 text-blue-700">TWK ≥ {{ $grades['twk'] }}</span>
                        <span class="ui-badge bg-amber-50 text-amber-700">TIU ≥ {{ $grades['tiu'] }}</span>
                        <span class="ui-badge bg-violet-50 text-violet-700">TKP ≥ {{ $grades['tkp'] }}</span>
                        <span class="ui-badge bg-primary-100 text-primary-800">Total ≥ {{ $grades['total'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
