<div class="ui-table-wrap">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50/80">
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Jenis</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Materi</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Soal</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($questions as $question)
                    <tr wire:key="question-{{ $question->id }}" class="transition hover:bg-slate-50/50">
                        <td class="px-5 py-4">
                            <span @class([
                                'ui-badge',
                                'bg-blue-100 text-blue-700' => $question->subject->code->value === 'twk',
                                'bg-amber-100 text-amber-700' => $question->subject->code->value === 'tiu',
                                'bg-violet-100 text-violet-700' => $question->subject->code->value === 'tkp',
                            ])>{{ $question->subject->code->label() }}</span>
                        </td>
                        <td class="px-5 py-4 font-medium text-slate-700">{{ $question->material->display_name }}</td>
                        <td class="max-w-xs truncate px-5 py-4 text-slate-600">{{ Str::limit(strip_tags($question->content), 70) }}</td>
                        <td class="px-5 py-4">
                            <span @class(['ui-badge', 'bg-emerald-100 text-emerald-700' => $question->is_active, 'bg-slate-100 text-slate-600' => ! $question->is_active])>
                                {{ $question->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <button wire:click="openPreviewModal({{ $question->id }})" class="ui-btn-ghost px-3 py-1.5">Pratinjau</button>
                            <button wire:click="openEditModal({{ $question->id }})" class="ui-btn-ghost px-3 py-1.5">Edit</button>
                            <button wire:click="delete({{ $question->id }})" wire:confirm="Hapus soal ini?" class="ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Belum ada soal. Tambahkan atau import dari Excel.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($questions->hasPages())
        <div class="border-t border-slate-100 px-5 py-3">{{ $questions->links() }}</div>
    @endif
</div>
