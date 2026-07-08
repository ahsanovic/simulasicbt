<div class="min-h-screen bg-gradient-to-b from-slate-50 to-emerald-50/30">
    <main class="mx-auto max-w-screen-2xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
        <x-ui.flash-toast />

        <div class="mb-8 rounded-2xl bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-600 p-6 text-white shadow-xl shadow-emerald-500/20 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-emerald-200">Cheat-Sheet Kilat</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight">Materi Belajar CPNS</h1>
                    <p class="mt-2 max-w-2xl text-sm text-emerald-100">
                        Ringkasan super padat per sub-materi TWK, TIU, dan TKP. Baca tuntas kurang dari 2 menit per topik.
                    </p>
                </div>
                <div class="rounded-xl bg-white/15 px-4 py-2 text-sm font-semibold ring-1 ring-white/20">
                    {{ $publishedCount }} materi tersedia
                </div>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap gap-2">
            @foreach ($subjects as $subject)
                <button
                    type="button"
                    wire:click="setSubject('{{ $subject->code->value }}')"
                    @class([
                        'rounded-xl px-4 py-2 text-sm font-semibold transition',
                        'bg-emerald-600 text-white shadow-sm' => $activeSubject?->id === $subject->id,
                        'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' => $activeSubject?->id !== $subject->id,
                    ])
                >
                    {{ $subject->code->label() }}
                </button>
            @endforeach
        </div>

        @if ($activeSubject)
            <div class="space-y-6">
                <div class="ui-card p-5">
                    <h2 class="text-lg font-bold text-slate-900">{{ $activeSubject->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Pilih sub-materi untuk membaca ringkasan kilat.</p>
                </div>

                @if ($activeSubject->materialGroups->isNotEmpty())
                    @foreach ($activeSubject->materialGroups as $group)
                        <section class="space-y-3">
                            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">{{ $group->name }}</h3>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($group->materials as $material)
                                    @include('livewire.peserta.partials.materi-belajar-card', ['material' => $material])
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @else
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($activeSubject->materials as $material)
                            @include('livewire.peserta.partials.materi-belajar-card', ['material' => $material])
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </main>
</div>
