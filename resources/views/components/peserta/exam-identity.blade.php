{{-- Who is sitting the exam (Mode Ujian rooms): name and full NIK, so the
     proctor can check them against the identity card. Renders nothing when
     neither is given (simulasi). --}}
@props(['name' => null, 'nik' => null])

@if (filled($name) || filled($nik))
    <span class="inline-flex min-w-0 items-center gap-1.5 font-medium text-slate-700">
        <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"/></svg>
        @if (filled($name))
            <span class="truncate">{{ $name }}</span>
        @endif
        @if (filled($nik))
            <span class="text-slate-300">·</span>
            <span class="whitespace-nowrap tabular-nums">NIK {{ $nik }}</span>
        @endif
    </span>
    <span class="text-slate-300" aria-hidden="true">·</span>
@endif
