@props([
    'suggestions',
    'search' => '',
    'searchWireModel' => 'formationSearch',
    'selectAction' => 'selectFormation',
    'notFoundMessage' => 'Jabatan tidak ditemukan / belum tersedia',
    'placeholder' => 'Ketik nama jabatan...',
])

<div x-data="{ open: false }" @click.away="open = false" class="relative">
    <input
        type="text"
        wire:model.live.debounce.300ms="{{ $searchWireModel }}"
        @focus="open = true"
        @keydown.escape="open = false"
        placeholder="{{ $placeholder }}"
        class="ui-input"
        autocomplete="off"
    />

    @if (strlen($search) >= 1)
        <ul
            x-show="open"
            x-cloak
            class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
        >
            @forelse ($suggestions as $formation)
                <li>
                    <button
                        type="button"
                        wire:click="{{ $selectAction }}({{ $formation->id }})"
                        @click="open = false"
                        class="w-full px-4 py-2.5 text-left hover:bg-primary-50"
                    >
                        <span class="block text-sm font-medium text-slate-800">{{ $formation->name }}</span>
                        <span class="block text-xs text-slate-500">{{ $formation->group }}</span>
                    </button>
                </li>
            @empty
                <li class="px-4 py-2.5 text-sm text-slate-500">{{ $notFoundMessage }}</li>
            @endforelse
        </ul>
    @endif
</div>
