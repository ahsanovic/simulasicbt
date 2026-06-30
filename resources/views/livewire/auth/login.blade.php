<div class="space-y-5">
    <div class="mb-2 hidden lg:block">
        <h2 class="text-xl font-bold text-slate-900">Selamat datang</h2>
        <p class="mt-1 text-sm text-slate-500">Masuk dengan email atau NIP Anda</p>
    </div>

    <x-ui.flash-toast />

    <div>
        <label for="login" class="ui-label">Email atau NIP</label>
        <input id="login" type="text" wire:model="login" autocomplete="username" class="ui-input" placeholder="email atau NIP">
        @error('login') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div x-data="{ showPassword: false }">
        <label for="password" class="ui-label">Password</label>
        <div class="relative">
            <input id="password" :type="showPassword ? 'text' : 'password'" wire:model="password" autocomplete="current-password" class="ui-input pr-11" placeholder="••••••••">
            <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 flex items-center px-3.5 text-slate-400 transition hover:text-slate-600"
                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
            >
                <svg x-show="!showPassword" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <svg x-show="showPassword" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
            </button>
        </div>
        @error('password') <p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p> @enderror
    </div>

    <label class="flex cursor-pointer items-center gap-2.5 text-sm font-medium text-slate-600">
        <input type="checkbox" wire:model="remember" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500/20">
        Ingat saya
    </label>

    <button type="button" wire:click="authenticate" wire:loading.attr="disabled" class="ui-btn-primary w-full py-3">
        <svg class="h-4 w-4 shrink-0 animate-spin" wire:loading wire:target="authenticate" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        <span wire:loading.remove wire:target="authenticate">Masuk</span>
        <span wire:loading wire:target="authenticate">Memproses...</span>
    </button>

    <div class="relative py-1">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-slate-200"></div></div>
        <div class="relative flex justify-center text-xs font-medium uppercase tracking-wider text-slate-400"><span class="bg-white px-3">atau</span></div>
    </div>

    <a href="{{ route('auth.google.redirect') }}"
       class="flex w-full items-center justify-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
        <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/>
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
        </svg>
        Masuk / Daftar dengan Google
    </a>
    <p class="text-center text-xs text-slate-500">Untuk peserta umum (non-pegawai Pemprov Jatim)</p>

    <p class="text-center text-sm text-slate-600">
        Anda pegawai Pemprov Jatim?
        <button type="button" wire:click="openRegisterModal" class="font-semibold text-primary-600 hover:text-primary-700">Daftar sekarang</button>
    </p>

    @include('livewire.auth.register-modal')
</div>
