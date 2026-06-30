<?php

namespace App\Livewire\Auth;

use App\Enums\UserRole;
use App\Models\Instansi;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Masuk')]
class Login extends Component
{
    public string $login = '';

    public string $password = '';

    public bool $remember = false;

    public bool $showRegisterModal = false;

    public string $registerStep = 'choose';

    public string $registerName = '';

    public string $registerEmail = '';

    public string $registerPassword = '';

    public string $registerPasswordConfirmation = '';

    public string $registerNip = '';

    public ?int $registerInstansiId = null;

    public string $registerInstansiSearch = '';

    public function authenticate(): void
    {
        $this->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => 'email atau nip harus diisi',
            'login.string' => 'email atau nip harus berupa string',
            'password.required' => 'password harus diisi',
            'password.string' => 'password harus berupa string',
        ]);

        $this->ensureIsNotRateLimited();

        $field = filter_var($this->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'nip';

        if (! Auth::attempt([$field => $this->login, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'login' => 'kredensial tidak valid atau akun nonaktif.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $this->redirectAfterLogin();
    }

    public function openRegisterModal(): void
    {
        $this->resetRegisterForm();
        $this->showRegisterModal = true;
        $this->registerStep = 'choose';
    }

    public function selectRegisterPegawai(): void
    {
        $this->registerStep = 'pegawai';
    }

    public function backToRegisterChoice(): void
    {
        $this->registerStep = 'choose';
        $this->resetValidation();
    }

    public function closeRegisterModal(): void
    {
        $this->showRegisterModal = false;
        $this->resetRegisterForm();
    }

    public function updatedRegisterInstansiSearch(): void
    {
        if ($this->registerInstansiSearch === '') {
            $this->registerInstansiId = null;

            return;
        }

        if ($this->registerInstansiId !== null) {
            $nama = Instansi::query()->whereKey($this->registerInstansiId)->value('nama');

            if ($nama !== $this->registerInstansiSearch) {
                $this->registerInstansiId = null;
            }
        }
    }

    public function selectRegisterInstansi(int $id): void
    {
        $instansi = Instansi::query()->find($id);

        if ($instansi === null) {
            return;
        }

        $this->registerInstansiId = $instansi->id;
        $this->registerInstansiSearch = $instansi->nama;
    }

    public function registerPegawai(): void
    {
        $validated = $this->validate([
            'registerName' => ['required', 'string', 'max:255'],
            'registerEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'registerPassword' => ['required', 'string', 'min:8', 'same:registerPasswordConfirmation'],
            'registerNip' => ['required', 'string', 'max:50', 'unique:users,nip'],
            'registerInstansiId' => ['required', 'integer', 'exists:instansis,id'],
        ], [
            'registerName.required' => 'nama harus diisi',
            'registerEmail.required' => 'email harus diisi',
            'registerEmail.email' => 'email tidak valid',
            'registerEmail.unique' => 'email sudah terdaftar',
            'registerPassword.required' => 'password harus diisi',
            'registerPassword.min' => 'password harus minimal 8 karakter',
            'registerPassword.same' => 'password tidak sama',
            'registerNip.required' => 'nip harus diisi',
            'registerNip.max' => 'nip maksimal 50 karakter',
            'registerNip.unique' => 'nip sudah terdaftar',
            'registerInstansiId.required' => 'instansi harus dipilih',
            'registerInstansiId.integer' => 'instansi harus berupa angka',
            'registerInstansiId.exists' => 'instansi tidak valid',
        ], [
            'registerName' => 'nama',
            'registerEmail' => 'email',
            'registerPassword' => 'password',
            'registerPasswordConfirmation' => 'konfirmasi password',
            'registerNip' => 'nip',
            'registerInstansiId' => 'instansi',
        ]);

        User::query()->create([
            'name' => $validated['registerName'],
            'email' => $validated['registerEmail'],
            'password' => Hash::make($validated['registerPassword']),
            'nip' => $validated['registerNip'],
            'instansi_id' => $validated['registerInstansiId'],
            'is_pegawai' => true,
            'role' => UserRole::Peserta,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->closeRegisterModal();
        session()->flash('success', 'Pendaftaran berhasil. Silakan masuk dengan email dan password Anda.');
    }

    protected function redirectAfterLogin(): void
    {
        $user = Auth::user();

        $this->redirect(match ($user->role) {
            UserRole::Admin => route('admin.dashboard'),
            UserRole::Peserta => route('peserta.dashboard'),
        }, navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->login).'|'.request()->ip());
    }

    private function resetRegisterForm(): void
    {
        $this->reset([
            'registerStep',
            'registerName',
            'registerEmail',
            'registerPassword',
            'registerPasswordConfirmation',
            'registerNip',
            'registerInstansiId',
            'registerInstansiSearch',
        ]);
        $this->registerStep = 'choose';
        $this->resetValidation();
    }

    public function render()
    {
        $instansiSuggestions = collect();

        if ($this->showRegisterModal && $this->registerStep === 'pegawai' && $this->registerInstansiSearch !== '') {
            $instansiSuggestions = Instansi::query()
                ->where('nama', 'like', '%'.$this->registerInstansiSearch.'%')
                ->orderBy('nama')
                ->limit(15)
                ->get();
        }

        return view('livewire.auth.login', compact('instansiSuggestions'));
    }
}
