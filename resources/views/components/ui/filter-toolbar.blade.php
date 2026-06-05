@props([
    'action' => 'resetFilters',
    'grid' => false,
])

<div class="ui-filter-toolbar">
    <div @class([
        'ui-filter-toolbar__fields',
        'ui-filter-toolbar__fields--grid' => $grid,
    ])>
        {{ $slot }}
        <x-ui.reset-filters-button :action="$action" />
    </div>
</div>
