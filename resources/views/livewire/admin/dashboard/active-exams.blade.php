<div class="ui-card overflow-hidden p-0">
    <div class="flex flex-col gap-3 border-b border-slate-100 bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-200 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-white"></span>
                </span>
                <h2 class="text-base font-bold text-white">Peserta Sedang Ujian</h2>
            </div>
            <p class="mt-1 text-sm text-emerald-100">Pembaruan otomatis setiap 10 detik</p>
        </div>
        <span class="inline-flex w-fit items-center rounded-full bg-white/20 px-3 py-1 text-sm font-semibold text-white">
            {{ $this->activeAttempts->count() }} online
        </span>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50/80">
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peserta</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ujian</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Mulai</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sisa Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->activeAttempts as $attempt)
                    <tr wire:key="active-attempt-{{ $attempt->id }}" class="transition hover:bg-slate-50/50">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700">
                                    {{ $attempt->user->initials() }}
                                </div>
                                <div>
                                    <p class="font-semibold text-slate-900">{{ $attempt->user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $attempt->user->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 font-medium text-slate-700">{{ $attempt->exam->title }}</td>
                        <td class="px-5 py-4 text-slate-500">{{ $attempt->started_at?->format('d M Y, H:i') }}</td>
                        <td class="px-5 py-4">
                            <span class="ui-badge bg-amber-50 text-amber-700">
                                {{ max(0, (int) $attempt->expires_at->diffInMinutes(now())) }} menit
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-slate-500">
                            Tidak ada peserta yang sedang ujian saat ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
