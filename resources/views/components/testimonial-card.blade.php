@props([
    'testimonial',
    'featured' => false,
])

@php
    $testimonialService = app(\App\Services\TestimonialService::class);
    $displayName = $testimonialService->displayName($testimonial);
    $displaySubtitle = $testimonialService->displaySubtitle($testimonial);
    $avatarInitials = $testimonialService->avatarInitials($testimonial);
    $devotionBadge = $testimonialService->devotionBadge($testimonial);
    $userReaction = auth()->check()
        ? $testimonialService->userReaction($testimonial, auth()->user())
        : null;
    $isOwn = auth()->id() === $testimonial->user_id;
@endphp

<article @class([
    'mb-4 break-inside-avoid ui-card overflow-hidden transition hover:shadow-md',
    'ring-2 ring-rose-400/60' => $featured,
])>
    @if ($featured)
        <div class="bg-gradient-to-r from-rose-400 to-pink-500 px-4 py-1.5 text-center text-xs font-bold uppercase tracking-wider text-white">
            ❤️ Featured Testimonial
        </div>
    @endif

    <div class="p-5">
        <div class="flex items-start gap-3">
            <div @class([
                'flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                'bg-gradient-to-br from-rose-500 to-pink-600 text-white' => ! $testimonial->is_anonymous,
                'bg-gradient-to-br from-slate-400 to-slate-600 text-white' => $testimonial->is_anonymous,
            ])>
                {{ $avatarInitials }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <p class="font-bold text-slate-900">{{ $displayName }}</p>
                    <x-devotion-badge :badge="$devotionBadge" />
                    <span class="text-slate-300">·</span>
                    <time class="text-xs text-slate-400" datetime="{{ $testimonial->created_at->toIso8601String() }}">
                        {{ $testimonial->created_at->diffForHumans() }}
                    </time>
                </div>
                <p class="text-sm text-rose-600">{{ $displaySubtitle }}</p>
                @if ($testimonial->rating)
                    <x-star-rating :rating="$testimonial->rating" size="sm" class="mt-1" />
                @endif
            </div>
        </div>

        <div class="mt-4 space-y-3">
            <p class="text-sm leading-relaxed text-slate-700">{{ $testimonial->story }}</p>

            @if ($testimonial->turning_point)
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/80 px-4 py-3">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">My Turning Point</p>
                    <p class="mt-1 text-sm leading-relaxed text-emerald-900">{{ $testimonial->turning_point }}</p>
                </div>
            @endif
        </div>

        @if (count($testimonial->resolvedFeatureTags()) > 0)
            <div class="mt-4 flex flex-wrap gap-1.5">
                @foreach ($testimonial->resolvedFeatureTags() as $tag)
                    <span class="ui-badge bg-rose-50 text-rose-700">{{ $tag->hashtag() }}</span>
                @endforeach
            </div>
        @endif

        <div class="mt-4 flex items-center gap-4 border-t border-slate-100 pt-4">
            <button
                type="button"
                wire:click="react({{ $testimonial->id }}, 'heart')"
                @disabled($isOwn)
                @class([
                    'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-semibold transition',
                    'bg-rose-50 text-rose-600 ring-1 ring-rose-200' => $userReaction?->value === 'heart',
                    'text-slate-500 hover:bg-rose-50 hover:text-rose-600' => $userReaction?->value !== 'heart',
                    'cursor-not-allowed opacity-50' => $isOwn,
                ])
                title="{{ $isOwn ? 'Tidak bisa reaksi testimoni sendiri' : 'Kirim Energi Positif' }}"
            >
                <span>❤️</span>
                <span>{{ number_format($testimonial->hearts_count) }}</span>
            </button>

            <button
                type="button"
                wire:click="react({{ $testimonial->id }}, 'fire')"
                @disabled($isOwn)
                @class([
                    'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm font-semibold transition',
                    'bg-orange-50 text-orange-600 ring-1 ring-orange-200' => $userReaction?->value === 'fire',
                    'text-slate-500 hover:bg-orange-50 hover:text-orange-600' => $userReaction?->value !== 'fire',
                    'cursor-not-allowed opacity-50' => $isOwn,
                ])
                title="{{ $isOwn ? 'Tidak bisa reaksi testimoni sendiri' : 'Semangat!' }}"
            >
                <span>🔥</span>
                <span>{{ number_format($testimonial->fires_count) }}</span>
            </button>
        </div>
    </div>
</article>
