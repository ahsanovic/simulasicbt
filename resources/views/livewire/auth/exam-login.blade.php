<div class="space-y-5">
    <div>
        <h2 class="text-xl font-bold text-slate-900">Masuk Ujian</h2>
        <p class="mt-1 text-sm text-slate-500">Masuk menggunakan NIK / username dan password yang diberikan panitia.</p>
    </div>

    <x-ui.flash-toast />

    {{-- With every participant logging in at the same second, the button stays
         on "Memproses..." from the click until the dashboard opens (no second
         click while the server is busy). Re-enabled only when the server answers
         with an error, or after 30 s as a safety net. --}}
    <form wire:submit="authenticate" class="space-y-5"
          x-data="{ busy: false, timer: null }"
          x-on:submit="busy = true; clearTimeout(timer); timer = setTimeout(() => busy = false, 30000)"
          x-on:login-failed.window="busy = false; clearTimeout(timer)">
        <div>
            <label for="login" class="ui-label">NIK / Username</label>
            <input id="login" type="text" wire:model="login" autocomplete="username" class="ui-input" placeholder="NIK atau username">
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

        <button type="submit" x-bind:disabled="busy" class="ui-btn-primary w-full py-3">
            <svg x-show="busy" x-cloak class="h-4 w-4 shrink-0 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span x-text="busy ? 'Memproses...' : 'Masuk Ujian'">Masuk Ujian</span>
        </button>
        <p x-show="busy" x-cloak class="text-center text-xs text-slate-500">Mohon tunggu, jangan muat ulang halaman.</p>
    </form>
</div>
