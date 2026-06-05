@props(['name' => null, 'path' => null])

@php
    $iconPath = $path ?? ($name ? config('admin-icons.'.$name) : null);
@endphp

@if ($iconPath)
    <svg {{ $attributes->merge(['class' => 'h-5 w-5']) }} fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $iconPath }}"/>
    </svg>
@endif
