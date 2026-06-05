<div class="ui-table-wrap">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50/80">
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Pengguna</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kontak</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Role</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($users as $user)
                    <tr wire:key="user-{{ $user->id }}" class="transition hover:bg-slate-50/50">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700">
                                    {{ $user->initials() }}
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $user->username ?? '—' }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4">
                            <p class="text-slate-600">{{ $user->email }}</p>
                            @if ($user->is_pegawai)
                                <p class="mt-0.5 text-xs text-slate-500">NIP: {{ $user->nip }}</p>
                                @if ($user->instansi)
                                    <p class="text-xs text-slate-500">{{ $user->instansi->nama }}</p>
                                @endif
                            @elseif ($user->google_id)
                                <p class="mt-0.5 text-xs text-slate-500">Peserta umum · Google</p>
                            @endif
                        </td>
                        <td class="px-5 py-4">
                            <span @class([
                                'ui-badge',
                                'bg-violet-100 text-violet-700' => $user->role->value === 'admin',
                                'bg-primary-100 text-primary-700' => $user->role->value === 'peserta',
                            ])>{{ $user->role->label() }}</span>
                        </td>
                        <td class="px-5 py-4">
                            <span @class([
                                'ui-badge',
                                'bg-emerald-100 text-emerald-700' => $user->is_active,
                                'bg-rose-100 text-rose-700' => ! $user->is_active,
                            ])>{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <button wire:click="openEditModal({{ $user->id }})" class="ui-btn-ghost px-3 py-1.5">Edit</button>
                            <button wire:click="delete({{ $user->id }})" wire:confirm="Hapus pengguna ini?" class="ui-btn-ghost px-3 py-1.5 text-rose-600 hover:bg-rose-50 hover:text-rose-700">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center text-slate-500">Belum ada data pengguna.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($users->hasPages())
        <div class="border-t border-slate-100 px-5 py-3">{{ $users->links() }}</div>
    @endif
</div>
