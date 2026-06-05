@props(['show' => false, 'title', 'maxWidth' => 'lg'])

@php
    $widthClass = match($maxWidth) {
        'sm' => 'max-w-md',
        'md' => 'max-w-lg',
        'xl' => 'max-w-4xl',
        '2xl' => 'max-w-5xl',
        default => 'max-w-lg',
    };
@endphp

@if ($show)
    <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$parent.closeModal ?? $dispatch('close-modal')"></div>

        <div {{ $attributes->merge(['class' => "relative w-full {$widthClass} max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl shadow-slate-900/20"]) }}>
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white/95 px-6 py-4 backdrop-blur">
                <h2 class="text-lg font-bold text-slate-900">{{ $title }}</h2>
                <button type="button" wire:click="$parent.closeModal ?? $set('showModal', false)" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif
