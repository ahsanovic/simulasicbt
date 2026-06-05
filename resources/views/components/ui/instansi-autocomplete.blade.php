@props([
    'suggestions',
    'search' => '',
    'searchWireModel' => 'instansiSearch',
    'selectAction' => 'selectInstansi',
    'errorField' => 'instansi_id',
    'placeholder' => 'Ketik nama instansi...',
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
            class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-lg"
        >
            @forelse ($suggestions as $instansi)
                <li>
                    <button
                        type="button"
                        wire:click="{{ $selectAction }}({{ $instansi->id }})"
                        @click="open = false"
                        class="w-full px-4 py-2.5 text-left text-sm text-slate-700 hover:bg-primary-50 hover:text-primary-700"
                    >
                        {{ $instansi->nama }}
                    </button>
                </li>
            @empty
                <li class="px-4 py-2.5 text-sm text-slate-500">Instansi tidak ditemukan</li>
            @endforelse
        </ul>
    @endif

    @error($errorField)
        <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>
    @enderror
</div>
