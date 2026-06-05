<div class="ui-table-wrap">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50/80">
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ujian</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Durasi</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Soal</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Percobaan</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($exams as $exam)
                    <tr wire:key="exam-{{ $exam->id }}" class="transition hover:bg-slate-50/50">
                        <td class="px-5 py-4">
                            <p class="font-semibold text-slate-900">{{ $exam->title }}</p>
                            @if($exam->description)
                                <p class="mt-0.5 max-w-xs truncate text-xs text-slate-500">{{ $exam->description }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-4 text-slate-600">{{ $exam->duration_minutes }} mnt</td>
                        <td class="px-5 py-4"><span class="ui-badge bg-primary-100 text-primary-700">{{ $exam->questions_count }} soal</span></td>
                        <td class="px-5 py-4"><span class="ui-badge bg-slate-100 text-slate-700">{{ $exam->attempts_count }}×</span></td>
                        <td class="px-5 py-4">
                            @php
                                $statusColor = match($exam->status->value) {
                                    'published' => 'bg-emerald-100 text-emerald-700',
                                    'draft' => 'bg-amber-100 text-amber-700',
                                    default => 'bg-slate-100 text-slate-600',
                                };
                            @endphp
                            <span class="ui-badge {{ $statusColor }}">{{ $exam->status->label() }}</span>
                        </td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <button wire:click="openEditModal({{ $exam->id }})" class="ui-btn-ghost px-3 py-1.5">Edit</button>
                            <button wire:click="delete({{ $exam->id }})" wire:confirm="Hapus ujian ini?" class="ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">Belum ada ujian dibuat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($exams->hasPages())
        <div class="border-t border-slate-100 px-5 py-3">{{ $exams->links() }}</div>
    @endif
</div>
