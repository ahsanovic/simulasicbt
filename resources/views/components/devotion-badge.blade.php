@props([
    'badge' => null,
])

@if (filled($badge))
    @php
        $theme = match ($badge['tooltip_theme'] ?? 'indigo') {
            'emerald' => [
                'header' => 'from-emerald-600 to-teal-600',
                'border' => 'border-emerald-300',
                'accent' => 'text-emerald-700',
            ],
            'amber' => [
                'header' => 'from-amber-500 via-amber-600 to-orange-600',
                'border' => 'border-amber-300',
                'accent' => 'text-amber-800',
            ],
            default => [
                'header' => 'from-indigo-600 to-violet-600',
                'border' => 'border-indigo-300',
                'accent' => 'text-indigo-700',
            ],
        };
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
                'shrink-0 cursor-help rounded-md px-1.5 py-px text-[9px] font-bold leading-tight tracking-wide transition',
                'hover:brightness-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1',
                'focus-visible:ring-emerald-400' => ($badge['tooltip_theme'] ?? '') === 'emerald',
                'focus-visible:ring-amber-400' => ($badge['tooltip_theme'] ?? '') === 'amber',
                'focus-visible:ring-indigo-400' => ($badge['tooltip_theme'] ?? 'indigo') === 'indigo',
                $badge['classes'] ?? '',
            ])
        >
            {{ $badge['label'] }}
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
                        'flex items-start gap-2 bg-gradient-to-r px-3 py-2.5',
                        $theme['header'],
                    ])>
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-white text-emerald-600 ring-1 ring-white">
                            <svg class="h-3.5 w-3.5 text-amber-500" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 2l2.4 5.2L20 8l-4 3.9.9 5.5L12 15.4 7.1 17.4 8 11.9 4 8l5.6-.8L12 2z"/>
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[11px] font-bold leading-tight text-white drop-shadow-sm">{{ $badge['label'] }}</span>
                            <span class="mt-0.5 block text-[10px] font-semibold tabular-nums text-white">
                                {{ number_format($badge['min_xp'] ?? 0) }}+ XP
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
