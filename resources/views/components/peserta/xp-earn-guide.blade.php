@props([
    'variant' => 'compact',
])

@php
    use App\Services\GamificationService;
@endphp

@if ($variant === 'compact')
    <div {{ $attributes }}>
        <p class="text-[10px] font-bold uppercase tracking-wider text-violet-600">Cara dapat XP</p>
        <div class="mt-2 flex flex-wrap gap-2">
            <a href="{{ route('peserta.audio.index') }}"
               wire:navigate
               class="inline-flex items-center rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-semibold text-violet-800 ring-1 ring-violet-200 transition hover:bg-violet-50">
                Audio Mode <span class="ml-1 font-medium text-violet-500">+XP/soal</span>
            </a>
            <a href="{{ route('peserta.kartu-sakti.index') }}"
               wire:navigate
               class="inline-flex items-center rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-semibold text-violet-800 ring-1 ring-violet-200 transition hover:bg-violet-50">
                Kartu Sakti <span class="ml-1 font-medium text-violet-500">+XP/kartu</span>
            </a>
            <a href="{{ route('peserta.dashboard') }}"
               wire:navigate
               class="inline-flex items-center rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-semibold text-violet-800 ring-1 ring-violet-200 transition hover:bg-violet-50">
                Simulasi <span class="ml-1 font-medium text-violet-500">+{{ GamificationService::EXAM_PASS_XP_REWARD }}/+{{ GamificationService::EXAM_FAIL_XP_REWARD }}</span>
            </a>
            <a href="{{ route('peserta.duel.index') }}"
               wire:navigate
               class="inline-flex items-center rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-semibold text-violet-800 ring-1 ring-violet-200 transition hover:bg-violet-50">
                Duel <span class="ml-1 font-medium text-violet-500">+{{ GamificationService::DUEL_WIN_XP_REWARD }}/+{{ GamificationService::DUEL_LOSE_XP_REWARD }}</span>
            </a>
            <a href="{{ route('peserta.testimonials.index') }}"
               wire:navigate
               class="inline-flex items-center rounded-lg bg-white px-2.5 py-1.5 text-[11px] font-semibold text-violet-800 ring-1 ring-violet-200 transition hover:bg-violet-50">
                Testimoni <span class="ml-1 font-medium text-violet-500">+{{ GamificationService::TESTIMONIAL_XP_REWARD }}</span>
            </a>
        </div>
        <p class="mt-2 text-[10px] leading-relaxed text-violet-700/80">
            🔥 Streak konsistensi harian mengalikan XP Audio Mode, Kartu Sakti, dan Duel hingga <strong>1.5x</strong>.
        </p>
    </div>
@else
    <div {{ $attributes->class(['rounded-xl border border-indigo-100 bg-indigo-50/50 px-3 py-2.5']) }}>
        <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-600">Cara Naik XP</p>
        <ul class="mt-1.5 space-y-1 text-[11px] leading-relaxed text-indigo-900/80">
            <li class="flex items-start gap-1.5">
                <span class="shrink-0 text-indigo-400">•</span>
                <span>Selesaikan sesi <a href="{{ route('peserta.audio.index') }}" wire:navigate class="font-semibold text-indigo-700 underline-offset-2 hover:underline">Audio Mode</a> (+XP per soal)</span>
            </li>
            <li class="flex items-start gap-1.5">
                <span class="shrink-0 text-indigo-400">•</span>
                <span>Review <a href="{{ route('peserta.kartu-sakti.index') }}" wire:navigate class="font-semibold text-indigo-700 underline-offset-2 hover:underline">Kartu Sakti</a> (+XP per kartu)</span>
            </li>
            <li class="flex items-start gap-1.5">
                <span class="shrink-0 text-indigo-400">•</span>
                <span>Selesaikan <a href="{{ route('peserta.dashboard') }}" wire:navigate class="font-semibold text-indigo-700 underline-offset-2 hover:underline">tes simulasi</a> (+{{ GamificationService::EXAM_PASS_XP_REWARD }} XP lulus, +{{ GamificationService::EXAM_FAIL_XP_REWARD }} XP belum lulus)</span>
            </li>
            <li class="flex items-start gap-1.5">
                <span class="shrink-0 text-indigo-400">•</span>
                <span>Menang <a href="{{ route('peserta.duel.index') }}" wire:navigate class="font-semibold text-indigo-700 underline-offset-2 hover:underline">duel 1v1</a> (+{{ GamificationService::DUEL_WIN_XP_REWARD }} XP), kalah (+{{ GamificationService::DUEL_LOSE_XP_REWARD }} XP)</span>
            </li>
            <li class="flex items-start gap-1.5">
                <span class="shrink-0 text-indigo-400">•</span>
                <span>Remedial sempurna (+{{ GamificationService::REMEDIAL_PERFECT_XP_REWARD }} XP)</span>
            </li>
            <li class="flex items-start gap-1.5">
                <span class="shrink-0 text-indigo-400">•</span>
                <span>Kirim <a href="{{ route('peserta.testimonials.index') }}" wire:navigate class="font-semibold text-indigo-700 underline-offset-2 hover:underline">testimoni pertama</a> (+{{ GamificationService::TESTIMONIAL_XP_REWARD }} XP)</span>
            </li>
            <li class="flex items-start gap-1.5">
                <span class="shrink-0 text-orange-500">🔥</span>
                <span>
                    <strong>Streak konsistensi:</strong> hari 1–3 = 1x XP, hari 4–7 = 1.2x, hari 8+ = 1.5x (maks).
                    Bolos 1 hari = reset ke 1x. Baca <a href="{{ route('peserta.materi.index') }}" wire:navigate class="font-semibold text-indigo-700 underline-offset-2 hover:underline">materi cheat-sheet</a> untuk mempertahankan streak.
                </span>
            </li>
        </ul>
    </div>
@endif
