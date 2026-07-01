<div>
    <div class="mb-5">
        <a href="{{ route('admin.users.index') }}" wire:navigate class="ui-btn-ghost mb-4 inline-flex items-center gap-2 px-0 text-slate-600 hover:text-slate-900">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Kembali ke Manajemen Pengguna
        </a>

        <x-ui.page-header
            title="Riwayat Tes"
            :description="'Hasil simulasi untuk '.$user->name"
        />
    </div>

    <div class="ui-card mb-5 p-4 sm:p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700">
                    {{ $user->initials() }}
                </div>
                <div>
                    <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                    <p class="text-sm text-slate-500">{{ $user->email }}</p>
                    @if ($user->is_pegawai && $user->instansi)
                        <p class="text-xs text-slate-500">{{ $user->instansi->nama }}</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <div class="rounded-xl border border-slate-100 bg-slate-50 px-4 py-2 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Simulasi</p>
                    <p class="text-xl font-bold text-slate-900">{{ number_format($stats['total']) }}</p>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-2 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Lulus</p>
                    <p class="text-xl font-bold text-emerald-700">{{ number_format($stats['passed']) }}</p>
                </div>
            </div>
        </div>
    </div>

    <x-exam-passing-grades-banner :passing-grades="$passingGrades" class="mb-5" />

    <div class="ui-table-wrap">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/80">
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Ujian</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TWK</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TIU</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">TKP</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Total</th>
                        <th class="px-5 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                        <th class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Selesai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($attempts as $attempt)
                        @php
                            $passes = exam_attempt_passes(
                                $attempt->score_twk,
                                $attempt->score_tiu,
                                $attempt->score_tkp,
                                $attempt->total_score,
                            );
                        @endphp
                        <tr wire:key="attempt-{{ $attempt->id }}" class="transition hover:bg-slate-50/50">
                            <td class="px-5 py-4 font-medium text-slate-900">{{ $attempt->exam->title }}</td>
                            <td class="px-5 py-4 text-center">
                                <x-exam-score-cell :value="$attempt->score_twk" :threshold="$passingGrades['twk']" color="blue" />
                            </td>
                            <td class="px-5 py-4 text-center">
                                <x-exam-score-cell :value="$attempt->score_tiu" :threshold="$passingGrades['tiu']" color="amber" />
                            </td>
                            <td class="px-5 py-4 text-center">
                                <x-exam-score-cell :value="$attempt->score_tkp" :threshold="$passingGrades['tkp']" color="violet" />
                            </td>
                            <td class="px-5 py-4 text-center">
                                <x-exam-score-cell :value="$attempt->total_score" :threshold="$passingGrades['total']" color="primary" />
                            </td>
                            <td class="px-5 py-4 text-center">
                                <span @class([
                                    'ui-badge inline-flex items-center gap-1',
                                    'bg-emerald-100 text-emerald-700' => $passes,
                                    'bg-red-100 text-red-700' => ! $passes,
                                ])>
                                    @if ($passes)
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Lulus
                                    @else
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Belum Lulus
                                    @endif
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-500">
                                {{ $attempt->submitted_at?->format('d M Y, H:i') ?? $attempt->created_at->format('d M Y, H:i') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-500">Peserta ini belum memiliki riwayat tes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($attempts->hasPages())
            <div class="border-t border-slate-100 px-5 py-3">{{ $attempts->links() }}</div>
        @endif
    </div>
</div>
