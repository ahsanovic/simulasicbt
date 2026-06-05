@props(['type' => 'success'])

@if (filled($slot))
    <div
        data-flash-toasts='@json([$type => (string) $slot], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
        class="hidden"
        aria-hidden="true"
    ></div>
@endif
