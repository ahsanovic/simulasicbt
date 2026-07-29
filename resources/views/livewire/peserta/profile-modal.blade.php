<div>
    @if ($showModal && $user)
        <div
            class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center"
            wire:keydown.escape.window="closeModal"
        >
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeModal"></div>

            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="profile-modal-title"
                class="relative flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl shadow-slate-900/20"
            >
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white/95 px-6 py-4 backdrop-blur">
                    <h2 id="profile-modal-title" class="text-lg font-bold text-slate-900">Profil Saya</h2>
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    >
                        <span class="sr-only">Tutup</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="overflow-y-auto p-6">
                    @if (session('profile-success'))
                        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                            {{ session('profile-success') }}
                        </div>
                    @endif

                    <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-primary-100 text-2xl font-bold uppercase text-primary-700 ring-4 ring-primary-50">
                            {{ $user->initials() }}
                        </div>

                        <div class="min-w-0">
                            <p class="truncate text-xl font-bold text-slate-900">{{ $user->name }}</p>
                            <p class="mt-1 truncate text-sm text-slate-500">{{ $user->email }}</p>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-amber-200/70 bg-gradient-to-br from-amber-50 to-orange-50 p-5">
                            <div class="flex items-center gap-2 text-amber-700">
                                <x-ui.coin-icon class="h-5 w-5 shrink-0" />
                                <span class="text-sm font-semibold">Koin</span>
                            </div>
                            <p class="mt-3 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($coinBalance) }}</p>
                        </div>

                        <div class="rounded-2xl border border-indigo-200/70 bg-gradient-to-br from-indigo-50 to-violet-50 p-5">
                            <div class="flex items-center gap-2 text-indigo-700">
                                <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M13 2L3 14h8l-1 8 10-12h-8l1-8z"/>
                                </svg>
                                <span class="text-sm font-semibold">XP</span>
                            </div>
                            <p class="mt-3 text-2xl font-bold tabular-nums text-slate-900">{{ number_format($totalXp) }}</p>
                        </div>

                        <div class="rounded-2xl border border-emerald-200/70 bg-gradient-to-br from-emerald-50 to-teal-50 p-5">
                            <div class="flex items-center gap-2 text-emerald-700">
                                <svg class="h-5 w-5 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2l2.4 5.2L20 8l-4 3.9.9 5.5L12 15.4 7.1 17.4 8 11.9 4 8l5.6-.8L12 2z"/>
                                </svg>
                                <span class="text-sm font-semibold">Pangkat</span>
                            </div>
                            <div class="mt-3">
                                <x-devotion-badge :badge="$currentBadge" size="md" />
                            </div>
                        </div>
                    </div>

                    @if ($canEditName)
                        <div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50/80 p-5">
                            <h3 class="text-sm font-bold text-slate-900">Ganti Nama Tampilan</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Nama ini akan tampil di seluruh platform. Nama di ujian event tetap dikonfirmasi terpisah saat mengerjakan ujian.
                            </p>

                            <form wire:submit="updateName" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                                <div class="min-w-0 flex-1">
                                    <label for="profile-name-input" class="sr-only">Nama tampilan</label>
                                    <input
                                        id="profile-name-input"
                                        type="text"
                                        wire:model="name"
                                        class="ui-input w-full"
                                        placeholder="Masukkan nama tampilan"
                                        autocomplete="name"
                                    >
                                    @error('name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <button type="submit" class="ui-btn-primary shrink-0 px-5 py-2.5">
                                    Simpan
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
