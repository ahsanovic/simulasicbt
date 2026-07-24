@php $readOnly = $readOnly ?? false; @endphp
<div class="overflow-x-auto rounded-2xl border border-slate-200">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50">
            <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                <th class="px-4 py-3">Tugas</th>
                <th class="px-4 py-3">Kategori</th>
                <th class="px-4 py-3">Prioritas</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Jadwal</th>
                <th class="px-4 py-3">Progress</th>
                @if (! $readOnly)
                <th class="px-4 py-3 text-right">Aksi</th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
            @forelse ($tableTasks as $task)
                @php $progress = $task->subtaskProgress(); @endphp
                <tr class="align-top transition hover:bg-slate-50/80" wire:key="table-task-{{ $task->id }}">
                    <td class="px-4 py-3">
                        @if ($readOnly)
                            <p class="font-bold text-slate-900">{{ $task->title }}</p>
                        @else
                        <button type="button" wire:click="openEditTaskModal({{ $task->id }})" class="text-left font-bold text-slate-900 hover:text-blue-700">
                            {{ $task->title }}
                        </button>
                        @endif
                        @if ($task->subtasks->isNotEmpty())
                            <ul class="mt-2 space-y-1 border-l-2 border-slate-100 pl-3">
                                @foreach ($task->subtasks as $sub)
                                    <li class="flex items-center gap-2 text-xs text-slate-600">
                                        @if ($readOnly)
                                            <span class="inline-flex h-3.5 w-3.5 shrink-0 items-center justify-center" aria-hidden="true">
                                                @if ($sub->status->value === 'done')
                                                    <svg class="h-3.5 w-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                @else
                                                    <span class="inline-block h-3.5 w-3.5 rounded border border-slate-300 bg-slate-50"></span>
                                                @endif
                                            </span>
                                        @else
                                        <button type="button" wire:click="toggleSubtask({{ $sub->id }})" class="text-slate-400 hover:text-blue-600">
                                            @if ($sub->status->value === 'done')
                                                <svg class="h-3.5 w-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            @else
                                                <span class="inline-block h-3.5 w-3.5 rounded border border-slate-300"></span>
                                            @endif
                                        </button>
                                        @endif
                                        <span @class(['line-through text-slate-400' => $sub->status->value === 'done'])>{{ $sub->title }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span @class(['ui-badge', $task->category->colorClasses()])>
                            {{ $task->category->emoji() }} {{ $task->category->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span @class(['ui-badge', $task->priority->colorClasses()])>{{ $task->priority->label() }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span @class(['inline-flex rounded-lg border px-2 py-1 text-xs font-bold', $task->status->columnAccent()])>
                            {{ $task->status->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs font-semibold text-slate-600">
                        {{ $task->scheduled_at?->translatedFormat('d M Y') ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        @if ($progress['total'] > 0)
                            <div class="w-28">
                                <div class="mb-1 text-[11px] font-semibold text-slate-500">{{ $progress['done'] }}/{{ $progress['total'] }}</div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-blue-500" style="width: {{ $progress['percent'] }}%"></div>
                                </div>
                            </div>
                        @else
                            <span class="text-xs text-slate-400">—</span>
                        @endif
                    </td>
                    @if (! $readOnly)
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-1">
                            <button type="button" wire:click="openCreateTaskModal('todo', {{ $task->id }})" class="rounded-lg px-2 py-1 text-xs font-bold text-slate-500 hover:bg-slate-100">+ Sub</button>
                            <button type="button" wire:click="openEditTaskModal({{ $task->id }})" class="rounded-lg px-2 py-1 text-xs font-bold text-blue-600 hover:bg-blue-50">Edit</button>
                            <button type="button" wire:click="deleteTask({{ $task->id }})" wire:confirm="Hapus tugas ini?" class="rounded-lg px-2 py-1 text-xs font-bold text-rose-500 hover:bg-rose-50">Hapus</button>
                        </div>
                    </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $readOnly ? 6 : 7 }}" class="px-4 py-12 text-center text-sm text-slate-500">
                        Belum ada tugas. Klik <strong>Buat Tugas</strong> untuk memulai.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
