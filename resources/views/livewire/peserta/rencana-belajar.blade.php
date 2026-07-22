<div class="min-h-screen bg-gradient-to-b from-slate-50 via-blue-50/40 to-slate-50">
    <main class="mx-auto max-w-screen-2xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
        <x-ui.flash-toast />

        {{-- Hero header --}}
        <div class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-br from-blue-700 via-blue-600 to-sky-500 p-6 text-white shadow-xl shadow-blue-600/25 sm:p-8">
            <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-2xl"></div>
            <div class="pointer-events-none absolute -bottom-20 left-1/3 h-48 w-48 rounded-full bg-sky-300/25 blur-3xl"></div>

            <div class="relative flex flex-wrap items-start justify-between gap-5">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-blue-100">Planner Belajar</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Rencana Belajar</h1>
                    <p class="mt-2 text-sm text-blue-50/90 sm:text-base">
                        Jadwalkan tugas, atur prioritas, dan pantau progres — Board, Tabel, atau Kalender sesuai gaya belajarmu.
                    </p>
                </div>

                <div class="flex flex-wrap items-stretch gap-3">
                    <div class="rounded-2xl bg-white/15 px-4 py-3 ring-1 ring-white/25 backdrop-blur">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-blue-100">Selesai hari ini</p>
                        <p class="text-2xl font-bold tabular-nums">{{ $completedToday }}</p>
                    </div>
                    <div class="rounded-2xl bg-white/15 px-4 py-3 ring-1 ring-white/25 backdrop-blur">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-blue-100">Rencana aktif</p>
                        <p class="text-2xl font-bold tabular-nums">{{ $activeCount }} <span class="text-sm font-semibold text-blue-100">/ {{ $maxPlans }}</span></p>
                    </div>
                    <button
                        type="button"
                        wire:click="openCreatePlanModal"
                        @disabled($activeCount >= $maxPlans)
                        class="inline-flex items-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-bold text-blue-700 shadow-lg shadow-slate-900/10 transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Buat Rencana
                    </button>
                    @if ($aiGeneration['status'] === 'available')
                        <button
                            type="button"
                            wire:click="generateFromEvaluation"
                            wire:loading.attr="disabled"
                            @disabled($activeCount >= $maxPlans)
                            title="Membuat rencana belajar 7 hari otomatis berdasarkan hasil evaluasi kesiapan"
                            class="inline-flex max-w-xs flex-col items-start gap-0.5 rounded-2xl bg-white/15 px-4 py-3 text-left ring-1 ring-white/30 backdrop-blur transition hover:bg-white/25 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <span class="inline-flex items-center gap-2 text-sm font-bold text-white">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                <span wire:loading.remove wire:target="generateFromEvaluation">Buat Rencana Otomatis dari Hasil Evaluasi</span>
                                <span wire:loading wire:target="generateFromEvaluation">Membuat rencana...</span>
                            </span>
                            <span wire:loading.remove wire:target="generateFromEvaluation" class="pl-6 text-[11px] font-medium leading-snug text-blue-100/90">
                                {{ $aiGeneration['message'] }}
                            </span>
                        </button>
                    @elseif ($aiGeneration['status'] === 'already_generated' && $aiGeneration['existing_plan'])
                        <a
                            href="{{ route('peserta.rencana-belajar.index', ['plan' => $aiGeneration['existing_plan']->id]) }}"
                            wire:navigate
                            class="inline-flex max-w-xs flex-col items-start gap-0.5 rounded-2xl bg-white px-4 py-3 text-left shadow-lg shadow-slate-900/10 transition hover:bg-blue-50"
                        >
                            <span class="inline-flex items-center gap-2 text-sm font-bold text-blue-700">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                Buka Rencana dari Hasil Evaluasi
                            </span>
                            <span class="pl-6 text-[11px] font-medium leading-snug text-blue-600/80">
                                {{ $aiGeneration['message'] }}
                            </span>
                        </a>
                    @elseif ($aiGeneration['status'] === 'no_simulation')
                        <div class="inline-flex max-w-xs flex-col items-start gap-0.5 rounded-2xl bg-white/10 px-4 py-3 text-left ring-1 ring-white/20">
                            <span class="text-sm font-bold text-blue-100">Buat Rencana Otomatis dari Hasil Evaluasi</span>
                            <span class="text-[11px] font-medium leading-snug text-blue-100/80">{{ $aiGeneration['message'] }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(240px,280px)_minmax(0,1fr)]">
            {{-- Plans sidebar --}}
            <aside class="space-y-3">
                <div class="inline-flex w-full rounded-xl bg-slate-100 p-1">
                    <button
                        type="button"
                        wire:click="setSidebarTab('active')"
                        @class([
                            'flex-1 rounded-lg px-3 py-2 text-xs font-bold transition sm:text-sm',
                            'bg-white text-blue-700 shadow-sm' => $sidebarTab === 'active',
                            'text-slate-600 hover:text-slate-900' => $sidebarTab !== 'active',
                        ])
                    >
                        Rencana
                        <span class="ml-1 tabular-nums text-slate-400">{{ $plans->count() }}</span>
                    </button>
                    <button
                        type="button"
                        wire:click="setSidebarTab('archive')"
                        @class([
                            'flex-1 rounded-lg px-3 py-2 text-xs font-bold transition sm:text-sm',
                            'bg-white text-blue-700 shadow-sm' => $sidebarTab === 'archive',
                            'text-slate-600 hover:text-slate-900' => $sidebarTab !== 'archive',
                        ])
                    >
                        Arsip
                        @if ($archivedCount > 0)
                            <span class="ml-1 tabular-nums text-slate-400">{{ $archivedCount }}</span>
                        @endif
                    </button>
                </div>

                @if ($sidebarTab === 'active')
                <div class="flex items-center justify-between px-1">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Rencana Aktif</h2>
                </div>

                @forelse ($plans as $plan)
                    @php $colors = $plan->colorClasses(); @endphp
                    <button
                        type="button"
                        wire:click="selectPlan({{ $plan->id }})"
                        @class([
                            'group w-full rounded-2xl border p-4 text-left transition',
                            'border-transparent bg-white shadow-md shadow-blue-600/10 ring-2 ring-blue-500/40' => $selectedPlanId === $plan->id,
                            'border-slate-200/80 bg-white/80 hover:border-blue-200 hover:shadow-sm' => $selectedPlanId !== $plan->id,
                        ])
                    >
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 h-3 w-3 shrink-0 rounded-full {{ $colors['bg'] }} ring-4 {{ $colors['ring'] }}"></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="truncate text-sm font-bold text-slate-900">{{ $plan->title }}</p>
                                    @if ($plan->status->value === 'completed')
                                        <span class="ui-badge bg-emerald-100 text-emerald-700">Selesai</span>
                                    @endif
                                </div>
                                @if ($plan->description)
                                    <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $plan->description }}</p>
                                @endif
                                <div class="mt-3">
                                    @php $pct = $plan->progressPercent(); @endphp
                                    <div class="mb-1 flex items-center justify-between text-[11px] font-semibold text-slate-500">
                                        <span>{{ $plan->priority->label() }}</span>
                                        <span>{{ $pct }}%</span>
                                    </div>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full {{ $colors['bg'] }} transition-all duration-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-6 text-center">
                        <p class="text-sm font-semibold text-slate-700">Belum ada rencana</p>
                        <p class="mt-1 text-xs text-slate-500">Buat hingga {{ $maxPlans }} rencana belajar sesuai prioritasmu.</p>
                        <button type="button" wire:click="openCreatePlanModal" class="ui-btn-primary mt-4 w-full !bg-blue-600 !shadow-blue-600/20 hover:!bg-blue-700">
                            Buat Rencana Pertama
                        </button>
                    </div>
                @endforelse
                @else
                <div class="px-1">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Rencana Diarsipkan</h2>
                    <p class="mt-1 text-[11px] leading-relaxed text-slate-500">
                        Rencana arsip tidak memakai slot aktif. Memulihkan kembali akan memakai 1 slot (maks. {{ $maxPlans }}).
                    </p>
                </div>

                @forelse ($archivedPlans as $plan)
                    @php $colors = $plan->colorClasses(); @endphp
                    <div
                        @class([
                            'w-full rounded-2xl border p-4 transition',
                            'border-transparent bg-white shadow-md shadow-slate-300/20 ring-2 ring-slate-300/60' => $selectedPlanId === $plan->id,
                            'border-slate-200/80 bg-white/80' => $selectedPlanId !== $plan->id,
                        ])
                    >
                        <button
                            type="button"
                            wire:click="selectPlan({{ $plan->id }})"
                            class="w-full text-left"
                        >
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 h-3 w-3 shrink-0 rounded-full {{ $colors['bg'] }} opacity-60 ring-4 {{ $colors['ring'] }}"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="truncate text-sm font-bold text-slate-700">{{ $plan->title }}</p>
                                        <span class="ui-badge bg-slate-100 text-slate-600">Arsip</span>
                                    </div>
                                    @if ($plan->description)
                                        <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ $plan->description }}</p>
                                    @endif
                                    <p class="mt-2 text-[11px] text-slate-400">
                                        Diarsipkan {{ $plan->updated_at->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        </button>

                        <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                            <button
                                type="button"
                                wire:click="restorePlan({{ $plan->id }})"
                                @disabled(! $canRestorePlan)
                                title="{{ $canRestorePlan ? 'Pulihkan ke daftar rencana aktif' : 'Slot rencana aktif penuh ('.$maxPlans.')' }}"
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Pulihkan
                            </button>
                            <button
                                type="button"
                                wire:click="deletePlan({{ $plan->id }})"
                                wire:confirm="Hapus permanen rencana arsip ini beserta semua tugasnya?"
                                class="inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-bold text-rose-600 transition hover:bg-rose-50"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white/60 p-6 text-center">
                        <p class="text-sm font-semibold text-slate-700">Belum ada arsip</p>
                        <p class="mt-1 text-xs text-slate-500">Rencana yang diarsipkan akan muncul di sini.</p>
                    </div>
                @endforelse
                @endif
            </aside>

            {{-- Main workspace --}}
            <section class="min-w-0 space-y-4">
                @if ($selectedPlan)
                    @php $selectedPlanColors = $selectedPlan->colorClasses(); @endphp

                    @if ($isArchivedView)
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            <span class="font-bold text-slate-800">Mode arsip — hanya baca.</span>
                            Pulihkan rencana ini untuk mengedit tugas. Memulihkan memakai 1 slot rencana aktif
                            @if (! $canRestorePlan)
                                <span class="font-semibold text-amber-700">(slot penuh: {{ $activeCount }}/{{ $maxPlans }})</span>
                            @endif
                        </div>
                    @endif

                    <div class="ui-card overflow-hidden">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="h-2.5 w-2.5 rounded-full {{ $selectedPlanColors['bg'] }} {{ $isArchivedView ? 'opacity-60' : '' }}"></span>
                                    <h2 class="truncate text-base font-bold text-slate-900 sm:text-lg">{{ $selectedPlan->title }}</h2>
                                    <span @class(['ui-badge', $selectedPlan->priority->colorClasses()])>{{ $selectedPlan->priority->label() }}</span>
                                    @if ($isArchivedView)
                                        <span class="ui-badge bg-slate-100 text-slate-600">Arsip</span>
                                    @endif
                                </div>
                                @if ($selectedPlan->starts_at || $selectedPlan->ends_at)
                                    <p class="mt-1 text-xs text-slate-500">
                                        @if ($selectedPlan->starts_at){{ $selectedPlan->starts_at->translatedFormat('d M Y') }}@endif
                                        @if ($selectedPlan->starts_at && $selectedPlan->ends_at) — @endif
                                        @if ($selectedPlan->ends_at){{ $selectedPlan->ends_at->translatedFormat('d M Y') }}@endif
                                    </p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <div class="inline-flex rounded-xl bg-slate-100 p-1">
                                    @foreach ([
                                        'board' => 'Board',
                                        'table' => 'Tabel',
                                        'calendar' => 'Kalender',
                                    ] as $mode => $label)
                                        <button
                                            type="button"
                                            wire:click="setViewMode('{{ $mode }}')"
                                            @class([
                                                'rounded-lg px-3 py-1.5 text-xs font-bold transition sm:text-sm',
                                                'bg-white text-blue-700 shadow-sm' => $viewMode === $mode,
                                                'text-slate-600 hover:text-slate-900' => $viewMode !== $mode,
                                            ])
                                        >{{ $label }}</button>
                                    @endforeach
                                </div>

                                @if ($isArchivedView)
                                    <button
                                        type="button"
                                        wire:click="restorePlan({{ $selectedPlan->id }})"
                                        @disabled(! $canRestorePlan)
                                        class="ui-btn-primary !bg-blue-600 !px-3 !py-2 !shadow-blue-600/20 text-xs hover:!bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 sm:text-sm"
                                    >
                                        Pulihkan
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="deletePlan({{ $selectedPlan->id }})"
                                        wire:confirm="Hapus permanen rencana arsip ini beserta semua tugasnya?"
                                        class="ui-btn-ghost !px-3 !py-2 text-xs text-rose-600 hover:bg-rose-50 sm:text-sm"
                                    >
                                        Hapus
                                    </button>
                                @else
                                <button type="button" wire:click="openCreateTaskModal('todo')" class="ui-btn-primary !bg-blue-600 !px-3 !py-2 !shadow-blue-600/20 text-xs hover:!bg-blue-700 sm:text-sm">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    Buat Tugas
                                </button>

                                <div x-data="{ open: false }" class="relative">
                                    <button type="button" @click="open = !open" class="ui-btn-ghost !px-2.5 !py-2" aria-label="Opsi rencana">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                                    </button>
                                    <div
                                        x-show="open"
                                        x-cloak
                                        @click.away="open = false"
                                        class="absolute right-0 z-20 mt-1 w-48 origin-top-right rounded-xl border border-slate-200 bg-white p-1 shadow-lg"
                                    >
                                        <button type="button" wire:click="openEditPlanModal({{ $selectedPlan->id }})" @click="open = false" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit rencana</button>
                                        @if ($selectedPlan->status->value !== 'completed')
                                            <button type="button" wire:click="completePlan({{ $selectedPlan->id }})" @click="open = false" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Tandai selesai</button>
                                        @endif
                                        <button type="button" wire:click="archivePlan({{ $selectedPlan->id }})" @click="open = false" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-50">Arsipkan</button>
                                        <button
                                            type="button"
                                            wire:click="deletePlan({{ $selectedPlan->id }})"
                                            wire:confirm="Hapus rencana ini beserta semua tugasnya?"
                                            @click="open = false"
                                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-rose-600 hover:bg-rose-50"
                                        >Hapus</button>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <div class="p-4 sm:p-5">
                            @if ($viewMode === 'board')
                                @include('livewire.peserta.rencana-belajar.board', ['readOnly' => $isArchivedView])
                            @elseif ($viewMode === 'table')
                                @include('livewire.peserta.rencana-belajar.table', ['readOnly' => $isArchivedView])
                            @else
                                @include('livewire.peserta.rencana-belajar.calendar', ['readOnly' => $isArchivedView])
                            @endif
                        </div>
                    </div>
                @else
                    <div class="ui-card flex min-h-[420px] flex-col items-center justify-center p-10 text-center">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-100 text-3xl">{{ $sidebarTab === 'archive' ? '📦' : '📋' }}</div>
                        <h2 class="mt-4 text-lg font-bold text-slate-900">
                            {{ $sidebarTab === 'archive' ? 'Belum ada rencana di arsip' : 'Mulai rencanakan belajarmu' }}
                        </h2>
                        <p class="mt-2 max-w-md text-sm text-slate-500">
                            @if ($sidebarTab === 'archive')
                                Rencana yang diarsipkan akan tersimpan di sini. Anda bisa memulihkan atau menghapusnya kapan saja.
                            @else
                                Buat rencana belajar, pecah jadi tugas & sub-tugas, lalu seret antar kolom Board untuk melacak progres.
                            @endif
                        </p>
                        @if ($sidebarTab !== 'archive')
                        <button type="button" wire:click="openCreatePlanModal" class="ui-btn-primary mt-6 !bg-blue-600 !shadow-blue-600/20 hover:!bg-blue-700">
                            Buat Rencana Belajar
                        </button>
                        @endif
                    </div>
                @endif
            </section>
        </div>
    </main>

    {{-- Plan modal --}}
    @if ($showPlanModal)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showPlanModal', false)"></div>
            <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl shadow-slate-900/20">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white/95 px-6 py-4 backdrop-blur">
                    <h2 class="text-lg font-bold text-slate-900">{{ $editingPlanId ? 'Edit Rencana' : 'Buat Rencana Belajar' }}</h2>
                    <button type="button" wire:click="$set('showPlanModal', false)" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form wire:submit="savePlan" class="space-y-4 p-6">
                    <div>
                        <label class="ui-label" for="planTitle">Judul rencana</label>
                        <input id="planTitle" type="text" wire:model="planTitle" class="ui-input" placeholder="Contoh: Persiapan SKD 30 Hari" maxlength="120">
                        @error('planTitle') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label" for="planDescription">Deskripsi (opsional)</label>
                        <textarea id="planDescription" wire:model="planDescription" rows="3" class="ui-input" placeholder="Fokus materi & target skor..."></textarea>
                        @error('planDescription') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="ui-label" for="planPriority">Prioritas</label>
                            <select id="planPriority" wire:model="planPriority" class="ui-select">
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Warna</label>
                            <div class="flex flex-wrap gap-2 pt-1">
                                @foreach ($planColors as $colorKey => $colorMeta)
                                    <button
                                        type="button"
                                        wire:click="$set('planColor', '{{ $colorKey }}')"
                                        @class([
                                            'h-8 w-8 rounded-full transition ring-offset-2',
                                            $colorMeta['bg'],
                                            'ring-2 ring-slate-900 scale-110' => $planColor === $colorKey,
                                            'hover:scale-105' => $planColor !== $colorKey,
                                        ])
                                        aria-label="Warna {{ $colorKey }}"
                                    ></button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="ui-label" for="planStartsAt">Mulai</label>
                            <input id="planStartsAt" type="date" wire:model="planStartsAt" class="ui-input">
                            @error('planStartsAt') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="ui-label" for="planEndsAt">Selesai</label>
                            <input id="planEndsAt" type="date" wire:model="planEndsAt" class="ui-input">
                            @error('planEndsAt') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showPlanModal', false)" class="ui-btn-secondary">Batal</button>
                        <button type="submit" class="ui-btn-primary !bg-blue-600 !shadow-blue-600/20 hover:!bg-blue-700">{{ $editingPlanId ? 'Simpan' : 'Buat Rencana' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Task modal --}}
    @if ($showTaskModal)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showTaskModal', false)"></div>
            <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-white shadow-2xl shadow-slate-900/20">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white/95 px-6 py-4 backdrop-blur">
                    <h2 class="text-lg font-bold text-slate-900">
                        @if ($editingTaskId)
                            Edit {{ $parentTaskId ? 'Sub-tugas' : 'Tugas' }}
                        @else
                            {{ $parentTaskId ? 'Tambah Sub-tugas' : 'Buat Tugas' }}
                        @endif
                    </h2>
                    <button type="button" wire:click="$set('showTaskModal', false)" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form wire:submit="saveTask" class="space-y-4 p-6">
                    <div>
                        <label class="ui-label" for="taskTitle">Judul</label>
                        <input id="taskTitle" type="text" wire:model="taskTitle" class="ui-input" placeholder="Contoh: Drill 20 soal TIU" maxlength="160" autofocus>
                        @error('taskTitle') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    @if (! $parentTaskId)
                        <div>
                            <label class="ui-label">Kategori</label>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ($categories as $category)
                                    <button
                                        type="button"
                                        wire:click="$set('taskCategory', '{{ $category->value }}')"
                                        @class([
                                            'flex items-center gap-2 rounded-xl border px-3 py-2 text-left text-xs font-semibold transition',
                                            'border-blue-400 bg-blue-50 text-blue-800 ring-2 ring-blue-200' => $taskCategory === $category->value,
                                            'border-slate-200 bg-white text-slate-700 hover:border-blue-200' => $taskCategory !== $category->value,
                                        ])
                                    >
                                        <span>{{ $category->emoji() }}</span>
                                        <span class="truncate">{{ $category->label() }}</span>
                                    </button>
                                @endforeach
                            </div>
                            @error('taskCategory') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="ui-label" for="taskPriority">Prioritas</label>
                            <select id="taskPriority" wire:model="taskPriority" class="ui-select">
                                @foreach ($priorities as $priority)
                                    <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if (! $parentTaskId)
                            <div>
                                <label class="ui-label" for="taskStatus">Status</label>
                                <select id="taskStatus" wire:model="taskStatus" class="ui-select">
                                    @foreach ($taskStatuses as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="{{ $parentTaskId ? '' : 'sm:col-span-2' }}">
                            <label class="ui-label" for="taskScheduledAt">Jadwal</label>
                            <input id="taskScheduledAt" type="date" wire:model="taskScheduledAt" class="ui-input">
                        </div>
                    </div>

                    <div>
                        <label class="ui-label" for="taskNotes">Catatan (opsional)</label>
                        <textarea id="taskNotes" wire:model="taskNotes" rows="3" class="ui-input" placeholder="Detail tambahan..."></textarea>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" wire:click="$set('showTaskModal', false)" class="ui-btn-secondary">Batal</button>
                        <button type="submit" class="ui-btn-primary !bg-blue-600 !shadow-blue-600/20 hover:!bg-blue-700">{{ $editingTaskId ? 'Simpan' : 'Tambah' }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
