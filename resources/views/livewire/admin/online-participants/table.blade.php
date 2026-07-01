<div class="ui-card overflow-hidden p-0">
    <div class="border-b border-slate-100 bg-slate-50/80 px-5 py-3">
        <p class="text-xs text-slate-500">Pembaruan otomatis setiap 10 detik · Skor dihitung dari jawaban yang sudah diisi</p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 bg-slate-50/80">
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Peserta</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ujian</th>
                    <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TWK</th>
                    <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TIU</th>
                    <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TKP</th>
                    <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Total</th>
                    <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Mulai</th>
                    <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Sisa Waktu</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($this->activeAttempts as $attempt)
                    @php
                        $scores = $attempt->calculateScores();
                        $passes = exam_attempt_passes(
                            $scores['twk'],
                            $scores['tiu'],
                            $scores['tkp'],
                            $scores['total'],
                        );
                    @endphp
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
                        <td class="px-5 py-4 text-center">
                            <x-exam-score-cell :value="$scores['twk']" :threshold="$passingGrades['twk']" color="blue" />
                        </td>
                        <td class="px-5 py-4 text-center">
                            <x-exam-score-cell :value="$scores['tiu']" :threshold="$passingGrades['tiu']" color="amber" />
                        </td>
                        <td class="px-5 py-4 text-center">
                            <x-exam-score-cell :value="$scores['tkp']" :threshold="$passingGrades['tkp']" color="violet" />
                        </td>
                        <td class="px-5 py-4 text-center">
                            <x-exam-score-cell :value="$scores['total']" :threshold="$passingGrades['total']" color="primary" />
                        </td>
                        <td class="px-5 py-4 text-center">
                            <span @class([
                                'ui-badge inline-flex items-center gap-1',
                                'bg-emerald-100 text-emerald-700' => $passes,
                                'bg-red-100 text-red-700' => ! $passes,
                            ])>
                                @if ($passes)
                                    Lulus
                                @else
                                    Belum Lulus
                                @endif
                            </span>
                        </td>
                        <td class="px-5 py-4 text-slate-500">{{ $attempt->started_at?->format('d M Y, H:i') }}</td>
                        <td class="px-5 py-4">
                            <span class="ui-badge bg-amber-50 text-amber-700">
                                {{ format_exam_remaining_time($attempt->remainingSeconds()) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-5 py-12 text-center text-slate-500">
                            Tidak ada peserta yang sedang ujian saat ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
