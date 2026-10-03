<?php

namespace App\Livewire\Auth;

use App\Enums\UserRole;
use App\Livewire\Concerns\AuthenticatesCbtUsers;
use App\Models\Instansi;
use App\Models\User;
use App\Rules\ValidNip;
use App\Support\ExamLockdown;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Email;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.auth')]
#[Title('Masuk')]
class Login extends Component
{
    use AuthenticatesCbtUsers;

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
        // Livewire calls skip the route middleware: re-check the closure here.
        if ($this->closedForExam()) {
            return;
        }

        $this->validateCredentials();

        $this->completeLogin($this->resolveUserFromCredentials(), $this->remember);
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
        if ($this->closedForExam()) {
            return;
        }

        $this->ensureRegisterIsNotRateLimited();

        $validated = $this->validate([
            'registerName' => ['required', 'string', 'max:255'],
            'registerEmail' => ['required', 'string', 'lowercase', 'max:255', Email::default(), 'unique:users,email'],
            'registerPassword' => ['required', 'string', Password::defaults(), 'same:registerPasswordConfirmation'],
            'registerPasswordConfirmation' => ['required', 'string'],
            'registerNip' => ['required', 'string', 'max:50', 'unique:users,nip', new ValidNip],
            'registerInstansiId' => ['required', 'integer', 'exists:instansis,id'],
        ], [
            'registerName.required' => 'nama harus diisi',
            'registerEmail.required' => 'email harus diisi',
            'registerEmail.email' => 'format email tidak valid',
            'registerEmail.unique' => 'email sudah terdaftar',
            'registerPassword.required' => 'password harus diisi',
            'registerPassword.min' => 'password harus minimal 8 karakter',
            'registerPassword.same' => 'password tidak sama',
            'registerPasswordConfirmation.required' => 'konfirmasi password harus diisi',
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
            'name' => trim($validated['registerName']),
            'email' => $validated['registerEmail'],
            'password' => Hash::make($validated['registerPassword']),
            'nip' => $validated['registerNip'],
            'instansi_id' => $validated['registerInstansiId'],
            'is_pegawai' => true,
            'role' => UserRole::Peserta,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        RateLimiter::clear($this->registerThrottleKey());

        $this->closeRegisterModal();
        session()->flash('success', 'Pendaftaran berhasil. Silakan masuk dengan username atau NIP dan password Anda.');
    }

    /** Mode Sedang Ujian closes this page; send any late form post back to the notice. */
    private function closedForExam(): bool
    {
        if (! ExamLockdown::active()) {
            return false;
        }

        $this->redirect(route('login'));

        return true;
    }

    protected function ensureRegisterIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->registerThrottleKey(), 5)) {
            RateLimiter::hit($this->registerThrottleKey(), 3600);

            return;
        }

        $seconds = RateLimiter::availableIn($this->registerThrottleKey());

        throw ValidationException::withMessages([
            'registerEmail' => "Terlalu banyak percobaan pendaftaran. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    protected function registerThrottleKey(): string
    {
        return 'register|'.request()->ip();
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
