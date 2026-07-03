@if ($showRegisterModal)
    <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeRegisterModal"></div>
        <div class="relative max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
            <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-100 bg-white px-6 py-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">
                        @if ($registerStep === 'pegawai')
                            Daftar Pegawai Pemprov Jatim
                        @else
                            Pilih Jenis Pendaftaran
                        @endif
                    </h2>
                    @if ($registerStep === 'choose')
                        <p class="mt-0.5 text-sm text-slate-500">Pilih sesuai status Anda sebagai peserta</p>
                    @endif
                </div>
                <button type="button" wire:click="closeRegisterModal" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            @if ($registerStep === 'choose')
                <div class="space-y-3 p-6">
                    {{-- <a href="{{ route('auth.google.redirect') }}"
                       class="flex items-start gap-4 rounded-2xl border-2 border-slate-200 p-4 transition hover:border-primary-300 hover:bg-primary-50/40">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700">
                            <x-ui.icon name="users" class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 text-left">
                            <p class="font-bold text-slate-900">Peserta Umum</p>
                            <p class="mt-1 text-sm text-slate-500">Daftar dan masuk menggunakan akun Google</p>
                        </div>
                    </a> --}}

                    <button type="button"
                            wire:click="selectRegisterPegawai"
                            class="flex w-full items-start gap-4 rounded-2xl border-2 border-slate-200 p-4 text-left transition hover:border-emerald-300 hover:bg-emerald-50/40">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                            <x-ui.icon name="office" class="h-6 w-6" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900">Pegawai Pemprov Jatim</p>
                            <p class="mt-1 text-sm text-slate-500">Daftar dengan NIP dan pilih instansi tempat bekerja</p>
                        </div>
                    </button>
                </div>
            @else
                <form wire:submit="registerPegawai" class="space-y-4 p-6">
                    <button type="button" wire:click="backToRegisterChoice" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                        ← Kembali ke pilihan
                    </button>

                    <div>
                        <label class="ui-label">Nama Lengkap</label>
                        <input type="text" wire:model="registerName" class="ui-input" maxlength="255" autocomplete="name" required>
                        @error('registerName') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label">Email</label>
                        <input type="email" wire:model="registerEmail" class="ui-input" maxlength="255" autocomplete="email" inputmode="email" required>
                        @error('registerEmail') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label">NIP (PPPK / PPPK Paruh Waktu / Non ASN)</label>
                        <input type="text" wire:model="registerNip" class="ui-input" placeholder="Nomor Induk Pegawai tanpa spasi atau tanda -" inputmode="numeric" pattern="[0-9]*" maxlength="50" autocomplete="off" required>
                        @error('registerNip') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label">Instansi</label>
                        <x-ui.instansi-autocomplete
                            :suggestions="$instansiSuggestions"
                            :search="$registerInstansiSearch"
                            search-wire-model="registerInstansiSearch"
                            select-action="selectRegisterInstansi"
                            error-field="registerInstansiId"
                        />
                    </div>
                    <div>
                        <label class="ui-label">Password</label>
                        <input type="password" wire:model="registerPassword" class="ui-input" autocomplete="new-password" minlength="8" required>
                        @error('registerPassword') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="ui-label">Konfirmasi Password</label>
                        <input type="password" wire:model="registerPasswordConfirmation" class="ui-input" autocomplete="new-password" minlength="8" required>
                        @error('registerPasswordConfirmation') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeRegisterModal" class="ui-btn-secondary">Batal</button>
                        <button type="submit" class="ui-btn-primary" wire:loading.attr="disabled" wire:target="registerPegawai">Daftar</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endif
