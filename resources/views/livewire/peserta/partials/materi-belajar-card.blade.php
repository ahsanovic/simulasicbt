@php
    $isPublished = $material->cheatSheet?->isPublished() ?? false;
@endphp

@if ($isPublished)
    <a
        href="{{ route('peserta.materi.show', ['subjectCode' => $material->subject->code->value, 'materialSlug' => $material->slug]) }}"
        wire:navigate
        class="ui-card group flex flex-col gap-3 p-4 transition hover:ring-2 hover:ring-emerald-200"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Siap dibaca</span>
        </div>
        <div>
            <p class="font-bold text-slate-900 group-hover:text-emerald-700">{{ $material->name }}</p>
            <p class="mt-1 text-xs text-slate-500">Baca ringkasan &lt; 2 menit</p>
        </div>
    </a>
@else
    <div class="ui-card flex flex-col gap-3 p-4 opacity-70">
        <div class="flex items-start justify-between gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Segera hadir</span>
        </div>
        <div>
            <p class="font-bold text-slate-700">{{ $material->name }}</p>
            <p class="mt-1 text-xs text-slate-400">Materi sedang disiapkan</p>
        </div>
    </div>
@endif
