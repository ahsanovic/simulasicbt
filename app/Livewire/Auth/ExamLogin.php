<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\AuthenticatesCbtUsers;
use App\Support\ExamLockdown;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Login used while Mode Sedang Ujian closes the simulasi: only admins and
 * participants of a running Mode Ujian event get in.
 */
#[Layout('layouts.ujian')]
#[Title('Masuk Ujian')]
class ExamLogin extends Component
{
    use AuthenticatesCbtUsers;

    public string $login = '';

    public string $password = '';

    public function mount(): void
    {
        if (! ExamLockdown::active()) {
            $this->redirect(route('login'));
        }
    }

    public function authenticate(): void
    {
        if (! ExamLockdown::active()) {
            $this->redirect(route('login'));

            return;
        }

        $this->validateCredentials();

        $user = $this->resolveUserFromCredentials();

        if (! $user->isAdmin() && ! $user->isActiveModeUjianParticipant()) {
            throw ValidationException::withMessages([
                'login' => 'Akun tidak terdaftar sebagai peserta ujian yang sedang berlangsung.',
            ]);
        }

        $this->completeLogin($user);
    }

    public function render()
    {
        return view('livewire.auth.exam-login');
    }
}
