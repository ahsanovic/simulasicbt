@props([
    'badge' => null,
    'size' => 'sm',
])

@if (filled($badge))
    @php
        $theme = match ($badge['tooltip_theme'] ?? 'indigo') {
            'emerald' => [
                'header' => 'from-emerald-600 to-teal-600',
                'border' => 'border-emerald-300',
                'accent' => 'text-emerald-700',
                'icon_bg' => 'bg-emerald-100 text-emerald-600',
            ],
            'sky' => [
                'header' => 'from-sky-500 to-indigo-600',
                'border' => 'border-sky-300',
                'accent' => 'text-sky-700',
                'icon_bg' => 'bg-sky-100 text-sky-600',
            ],
            'violet' => [
                'header' => 'from-violet-500 to-purple-600',
                'border' => 'border-violet-300',
                'accent' => 'text-violet-700',
                'icon_bg' => 'bg-violet-100 text-violet-600',
            ],
            'teal' => [
                'header' => 'from-teal-500 to-cyan-600',
                'border' => 'border-teal-300',
                'accent' => 'text-teal-700',
                'icon_bg' => 'bg-teal-100 text-teal-600',
            ],
            'amber' => [
                'header' => 'from-amber-500 via-amber-600 to-orange-600',
                'border' => 'border-amber-300',
                'accent' => 'text-amber-800',
                'icon_bg' => 'bg-amber-100 text-amber-600',
            ],
            default => [
                'header' => 'from-indigo-600 to-violet-600',
                'border' => 'border-indigo-300',
                'accent' => 'text-indigo-700',
                'icon_bg' => 'bg-indigo-100 text-indigo-600',
            ],
        };

        $isMd = $size === 'md';
        $iconName = $badge['icon'] ?? 'shield';
    @endphp

    <span
        x-data="{
            open: false,
            tipStyle: '',
            place() {
                const rect = this.$refs.trigger.getBoundingClientRect();
                this.tipStyle = `left:${rect.left + rect.width / 2}px;top:${rect.top - 8}px;`;
            },
            show() {
                this.open = true;
                this.$nextTick(() => this.place());
            },
            hide() {
                this.open = false;
            },
        }"
        class="relative inline-flex"
        @mouseenter="show()"
        @mouseleave="hide()"
        @focusin="show()"
        @focusout="hide()"
    >
        <span
            x-ref="trigger"
            tabindex="0"
            role="button"
            aria-describedby="devotion-tip-{{ $badge['value'] }}"
            @class([
                'group/devotion-badge inline-flex shrink-0 cursor-help items-center ring-1 transition hover:brightness-[0.97] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1',
                'gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-bold leading-tight tracking-wide' => ! $isMd,
                'gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold leading-tight tracking-wide' => $isMd,
                'focus-visible:ring-emerald-400' => ($badge['tooltip_theme'] ?? '') === 'emerald',
                'focus-visible:ring-sky-400' => ($badge['tooltip_theme'] ?? '') === 'sky',
                'focus-visible:ring-violet-400' => ($badge['tooltip_theme'] ?? '') === 'violet',
                'focus-visible:ring-teal-400' => ($badge['tooltip_theme'] ?? '') === 'teal',
                'focus-visible:ring-amber-400' => ($badge['tooltip_theme'] ?? '') === 'amber',
                'focus-visible:ring-indigo-400' => ($badge['tooltip_theme'] ?? 'indigo') === 'indigo',
                $badge['classes'] ?? '',
            ])
        >
            <span @class([
                'flex shrink-0 items-center justify-center rounded-full ring-1 ring-white/60',
                'h-3.5 w-3.5' => ! $isMd,
                'h-4 w-4' => $isMd,
                $theme['icon_bg'],
            ])>
                @switch($iconName)
                    @case('shield')
                        <svg @class(['shrink-0', 'h-2 w-2' => ! $isMd, 'h-2.5 w-2.5' => $isMd]) fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill-rule="evenodd" d="M12.516 2.17a.75.75 0 00-1.032 0 11.209 11.209 0 01-7.877 3.08.75.75 0 00-.722.515A12.74 12.74 0 002.25 9.75c0 5.942 4.064 10.933 9.563 12.348a.75.75 0 00.374 0c5.499-1.415 9.563-6.406 9.563-12.348 0-1.39-.223-2.73-.635-3.985a.75.75 0 00-.722-.516 11.209 11.209 0 01-7.877-3.08z" clip-rule="evenodd"/>
                        </svg>
                        @break
                    @case('star')
                        <svg @class(['shrink-0', 'h-2 w-2' => ! $isMd, 'h-2.5 w-2.5' => $isMd]) fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005z" clip-rule="evenodd"/>
                        </svg>
                        @break
                    @case('heart')
                        <svg @class(['shrink-0', 'h-2 w-2' => ! $isMd, 'h-2.5 w-2.5' => $isMd]) fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                        </svg>
                        @break
                    @case('bolt')
                        <svg @class(['shrink-0', 'h-2 w-2' => ! $isMd, 'h-2.5 w-2.5' => $isMd]) fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill-rule="evenodd" d="M14.615 1.595a.75.75 0 01.359.852L12.982 9.75h7.268a.75.75 0 01.548 1.262l-10.5 11.25a.75.75 0 01-1.272-.71l1.992-7.302H3.75a.75.75 0 01-.548-1.262l10.5-11.25a.75.75 0 01.913-.143z" clip-rule="evenodd"/>
                        </svg>
                        @break
                    @case('sparkles')
                        <svg @class(['shrink-0', 'h-2 w-2' => ! $isMd, 'h-2.5 w-2.5' => $isMd]) fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813a3.75 3.75 0 002.576-2.576l.813-2.846A.75.75 0 019 4.5zM18 1.5a.75.75 0 01.728.568l.258 1.036c.108.434.425.751.86.86l1.036.258a.75.75 0 010 1.456l-1.036.258c-.434.108-.752.425-.86.86l-.258 1.036a.75.75 0 01-1.456 0l-.258-1.036a1.125 1.125 0 00-.86-.86l-1.036-.258a.75.75 0 010-1.456l1.036-.258a1.125 1.125 0 00.86-.86l.258-1.036A.75.75 0 0118 1.5z" clip-rule="evenodd"/>
                        </svg>
                        @break
                    @default
                        <svg @class(['shrink-0', 'h-2 w-2' => ! $isMd, 'h-2.5 w-2.5' => $isMd]) fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2l2.4 5.2L20 8l-4 3.9.9 5.5L12 15.4 7.1 17.4 8 11.9 4 8l5.6-.8L12 2z"/>
                        </svg>
                @endswitch
            </span>

            <span class="truncate">{{ $badge['label'] }}</span>
        </span>

        <template x-teleport="body">
            <div
                id="devotion-tip-{{ $badge['value'] }}"
                role="tooltip"
                x-show="open"
                :style="tipStyle"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="translate-y-1 scale-95"
                x-transition:enter-end="translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="translate-y-0 scale-100"
                x-transition:leave-end="translate-y-1 scale-95"
                x-cloak
                class="pointer-events-none fixed z-[200] w-[13.5rem] -translate-x-1/2 -translate-y-full sm:w-60"
            >
                <div @class([
                    'overflow-hidden rounded-xl border-2 bg-white shadow-[0_12px_40px_-8px_rgba(15,23,42,0.45)]',
                    $theme['border'],
                ])>
                    <div @class([
                        'flex items-start gap-2.5 bg-gradient-to-r px-3 py-2.5',
                        $theme['header'],
                    ])>
                        <span @class([
                            'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg ring-1 ring-white/30',
                            $theme['icon_bg'],
                        ])>
                            @switch($iconName)
                                @case('shield')
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M12.516 2.17a.75.75 0 00-1.032 0 11.209 11.209 0 01-7.877 3.08.75.75 0 00-.722.515A12.74 12.74 0 002.25 9.75c0 5.942 4.064 10.933 9.563 12.348a.75.75 0 00.374 0c5.499-1.415 9.563-6.406 9.563-12.348 0-1.39-.223-2.73-.635-3.985a.75.75 0 00-.722-.516 11.209 11.209 0 01-7.877-3.08z" clip-rule="evenodd"/>
                                    </svg>
                                    @break
                                @case('star')
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005z" clip-rule="evenodd"/>
                                    </svg>
                                    @break
                                @case('heart')
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/>
                                    </svg>
                                    @break
                                @case('bolt')
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M14.615 1.595a.75.75 0 01.359.852L12.982 9.75h7.268a.75.75 0 01.548 1.262l-10.5 11.25a.75.75 0 01-1.272-.71l1.992-7.302H3.75a.75.75 0 01-.548-1.262l10.5-11.25a.75.75 0 01.913-.143z" clip-rule="evenodd"/>
                                    </svg>
                                    @break
                                @case('sparkles')
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M9 4.5a.75.75 0 01.721.544l.813 2.846a3.75 3.75 0 002.576 2.576l2.846.813a.75.75 0 010 1.442l-2.846.813a3.75 3.75 0 00-2.576 2.576l-.813 2.846a.75.75 0 01-1.442 0l-.813-2.846a3.75 3.75 0 00-2.576-2.576l-2.846-.813a.75.75 0 010-1.442l2.846-.813a3.75 3.75 0 002.576-2.576l.813-2.846A.75.75 0 019 4.5zM18 1.5a.75.75 0 01.728.568l.258 1.036c.108.434.425.751.86.86l1.036.258a.75.75 0 010 1.456l-1.036.258c-.434.108-.752.425-.86.86l-.258 1.036a.75.75 0 01-1.456 0l-.258-1.036a1.125 1.125 0 00-.86-.86l-1.036-.258a.75.75 0 010-1.456l1.036-.258a1.125 1.125 0 00.86-.86l.258-1.036A.75.75 0 0118 1.5z" clip-rule="evenodd"/>
                                    </svg>
                                    @break
                                @default
                                    <svg class="h-4 w-4 text-amber-500" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M12 2l2.4 5.2L20 8l-4 3.9.9 5.5L12 15.4 7.1 17.4 8 11.9 4 8l5.6-.8L12 2z"/>
                                    </svg>
                            @endswitch
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[11px] font-bold leading-tight text-white drop-shadow-sm">{{ $badge['label'] }}</span>
                            <span class="mt-0.5 flex items-center gap-1.5 text-[10px] font-semibold text-white/90">
                                <span class="tabular-nums">{{ number_format($badge['min_xp'] ?? 0) }}+ XP</span>
                                @if (filled($badge['tier'] ?? null))
                                    <span class="opacity-60">·</span>
                                    <span>Tingkat {{ $badge['tier'] }}</span>
                                @endif
                            </span>
                        </span>
                    </div>

                    <div class="bg-white px-3 py-2.5">
                        <p class="mb-1.5 flex items-center gap-1 text-[9px] font-bold uppercase tracking-widest {{ $theme['accent'] }}">
                            <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            Lencana Pengabdian
                        </p>
                        <p class="text-[11px] font-medium leading-relaxed text-slate-900">
                            {{ $badge['description'] ?? '' }}
                        </p>
                    </div>
                </div>

                <div class="absolute left-1/2 top-full -translate-x-1/2">
                    <div class="h-0 w-0 border-x-[9px] border-t-[9px] border-x-transparent border-t-white"
                         style="filter: drop-shadow(0 2px 2px rgba(15,23,42,0.15));"></div>
                </div>
            </div>
        </template>
    </span>
@endif
