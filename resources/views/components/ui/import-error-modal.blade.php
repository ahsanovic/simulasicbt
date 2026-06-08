@props([
    'show' => false,
    'report' => [],
])

@php
    $report = is_array($report) ? $report : [];
    $errors = $report['errors'] ?? [];
@endphp

@if ($show && $errors !== [])
    <div
        wire:ignore
        class="fixed inset-0 z-[70] flex items-end justify-center p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="import-error-title"
    >
        <div
            class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm"
            wire:click="closeImportErrorModal"
        ></div>

        <div class="relative flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-900/25">
            <div class="border-b border-rose-100 bg-gradient-to-r from-rose-50 to-orange-50 px-6 py-5">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-100 text-rose-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 id="import-error-title" class="text-lg font-bold text-slate-900">
                            {{ $report['title'] ?? 'Import Gagal' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $report['summary'] ?? 'Perbaiki file Excel lalu impor ulang.' }}
                        </p>
                        <div class="mt-3 inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-200">
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                            {{ $report['total'] ?? count($errors) }} kesalahan ditemukan
                        </div>
                    </div>
                    <button
                        type="button"
                        wire:click="closeImportErrorModal"
                        class="rounded-xl p-2 text-slate-400 transition hover:bg-white/70 hover:text-slate-600"
                        aria-label="Tutup"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-auto p-6">
                <div class="ui-table-wrap overflow-hidden">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="w-20 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Baris</th>
                                <th scope="col" class="w-40 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Kolom</th>
                                <th scope="col" class="w-48 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Nilai</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach ($errors as $error)
                                <tr class="transition hover:bg-rose-50/40">
                                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-700">
                                        {{ filled($error['row'] ?? null) ? $error['row'] : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-600">
                                        {{ $error['column'] ?? '—' }}
                                    </td>
                                    <td class="max-w-[12rem] truncate px-4 py-3 font-mono text-xs text-slate-500" title="{{ $error['value'] ?? '' }}">
                                        {{ filled($error['value'] ?? null) ? $error['value'] : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-800">
                                        {{ $error['message'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/80 px-6 py-4 sm:flex-row sm:justify-end">
                <button type="button" wire:click="closeImportErrorModal" class="ui-btn-secondary">
                    Tutup
                </button>
                <button type="button" wire:click="reopenImportModal" class="ui-btn-primary">
                    Impor Ulang
                </button>
            </div>
        </div>
    </div>
@endif
