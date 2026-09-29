<div class="ui-card mb-5 p-4 sm:p-5">
    <x-ui.filter-toolbar>
        <div class="relative min-w-0 w-full sm:max-w-md sm:flex-1">
            <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari isi soal..." class="ui-input pl-10">
        </div>
    </x-ui.filter-toolbar>
</div>

<div class="ui-table-wrap">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50/80">
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Soal</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kesulitan</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($questions as $question)
                    <tr wire:key="skb-question-{{ $question->id }}" class="transition hover:bg-slate-50/50">
                        <td class="px-5 py-4">
                            <button wire:click="openPreviewModal({{ $question->id }})" class="line-clamp-2 max-w-md text-left text-sm text-slate-800 hover:text-primary-700">
                                {{ Str::limit(strip_tags($question->content), 120) }}
                            </button>
                        </td>
                        <td class="px-5 py-4">
                            <span class="ui-badge bg-slate-100 text-slate-700">{{ ucfirst($question->difficulty) }}</span>
                        </td>
                        <td class="px-5 py-4">
                            @if ($question->is_active)
                                <span class="ui-badge bg-emerald-50 text-emerald-700">Aktif</span>
                            @else
                                <span class="ui-badge bg-slate-100 text-slate-500">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <button wire:click="openEditModal({{ $question->id }})" class="ui-btn-ghost px-3 py-1.5">Edit</button>
                            <button
                                wire:click="delete({{ $question->id }})"
                                wire:confirm="Hapus soal ini?"
                                class="ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center text-slate-500">
                            Belum ada soal SKB untuk jabatan ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($questions->hasPages())
        <div class="border-t border-slate-100 px-5 py-3">{{ $questions->links() }}</div>
    @endif
</div>
