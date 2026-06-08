@if ($importJob)
    @php
        $isActive = $importJob->status->isActive();
        $percent = $importJob->progressPercent();
        $statusLabel = $importJob->status->label();
    @endphp

    <div
        class="ui-card mb-5 overflow-hidden"
        @if ($isActive) wire:poll.2s="refreshImportProgress" @endif
    >
        <div class="border-b border-slate-100 px-4 py-3 sm:px-5">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold text-slate-800">Import Soal Background</h3>
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-amber-100 text-amber-800' => $importJob->status === \App\Enums\QuestionImportStatus::Pending,
                            'bg-blue-100 text-blue-800' => $importJob->status === \App\Enums\QuestionImportStatus::Processing,
                            'bg-emerald-100 text-emerald-800' => $importJob->status === \App\Enums\QuestionImportStatus::Completed,
                            'bg-red-100 text-red-800' => $importJob->status === \App\Enums\QuestionImportStatus::Failed,
                        ])>
                            {{ $statusLabel }}
                        </span>
                    </div>

                    @if ($importJob->status === \App\Enums\QuestionImportStatus::Pending)
                        <p class="mt-1 text-sm text-slate-600">
                            Menunggu queue worker memproses {{ number_format($importJob->total_rows) }} soal.
                        </p>
                        @if ($importJob->isStale())
                            <p class="mt-1 text-sm font-medium text-amber-700">
                                Proses belum dimulai. Pastikan queue worker berjalan:
                                <code class="rounded bg-amber-50 px-1.5 py-0.5 text-xs">php artisan queue:work</code>
                            </p>
                        @endif
                    @elseif ($importJob->status === \App\Enums\QuestionImportStatus::Processing)
                        <p class="mt-1 text-sm text-slate-600">
                            Mengimpor {{ number_format($importJob->processed_rows) }} dari {{ number_format($importJob->total_rows) }} soal...
                        </p>
                    @elseif ($importJob->status === \App\Enums\QuestionImportStatus::Completed)
                        <p class="mt-1 text-sm text-emerald-700">
                            {{ number_format($importJob->total_rows) }} soal berhasil diimpor. Daftar soal telah diperbarui.
                        </p>
                    @else
                        <p class="mt-1 text-sm text-red-700">
                            {{ $importJob->error_message ?: 'Import gagal diproses.' }}
                        </p>
                    @endif
                </div>

                @unless ($isActive)
                    <button
                        type="button"
                        wire:click="dismissImportProgress({{ $importJob->id }})"
                        class="shrink-0 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Tutup notifikasi import"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endunless
            </div>
        </div>

        <div class="px-4 py-4 sm:px-5">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-medium text-slate-700">Progress</span>
                <span @class([
                    'font-bold',
                    'text-primary-600' => $isActive,
                    'text-emerald-600' => $importJob->status === \App\Enums\QuestionImportStatus::Completed,
                    'text-red-600' => $importJob->status === \App\Enums\QuestionImportStatus::Failed,
                    'text-amber-600' => $importJob->status === \App\Enums\QuestionImportStatus::Pending,
                ])>
                    {{ $percent }}%
                </span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                <div
                    @class([
                        'h-full rounded-full transition-all duration-500 ease-out',
                        'bg-gradient-to-r from-primary-500 to-indigo-500' => $isActive,
                        'bg-emerald-500' => $importJob->status === \App\Enums\QuestionImportStatus::Completed,
                        'bg-red-500' => $importJob->status === \App\Enums\QuestionImportStatus::Failed,
                        'bg-amber-400' => $importJob->status === \App\Enums\QuestionImportStatus::Pending,
                    ])
                    style="width: {{ $percent }}%"
                ></div>
            </div>

            @if ($isActive)
                <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                    <svg class="h-3.5 w-3.5 animate-spin text-primary-500" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Memperbarui otomatis setiap 2 detik
                </p>
            @endif
        </div>
    </div>
@endif
