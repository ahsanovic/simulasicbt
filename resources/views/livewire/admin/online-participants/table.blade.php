<div class="ui-card overflow-hidden p-0">
    <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3">
        <p class="text-xs text-slate-500">Pembaruan otomatis setiap 10 detik</p>
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
                                {{ format_exam_remaining_time($attempt->remainingSeconds()) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-5 py-12 text-center text-slate-500">
                            Tidak ada peserta yang sedang ujian saat ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
