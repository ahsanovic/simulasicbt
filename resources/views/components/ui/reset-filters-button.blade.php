@props(['action' => 'resetFilters'])

<button
    type="button"
    wire:click="{{ $action }}"
    wire:loading.attr="disabled"
    wire:target="{{ $action }}"
    {{ $attributes->class(['ui-btn-reset-filter']) }}
>
    <svg class="h-4 w-4 shrink-0" wire:loading.remove wire:target="{{ $action }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
    </svg>
    <svg class="h-4 w-4 shrink-0 animate-spin" wire:loading wire:target="{{ $action }}" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
    </svg>
    <span wire:loading.remove wire:target="{{ $action }}">Reset</span>
    <span wire:loading wire:target="{{ $action }}">...</span>
</button>
