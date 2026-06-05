@php
    $toasts = array_filter([
        'success' => session('success'),
        'error' => session('error'),
        'info' => session('info'),
        'warning' => session('warning'),
    ], fn ($message) => filled($message));
@endphp

@if ($toasts !== [])
    <div
        data-flash-toasts='@json($toasts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
        class="hidden"
        aria-hidden="true"
    ></div>
@endif
