<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Login;
use App\Models\Instansi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validRegistrationPayload(Instansi $instansi): array
    {
        return [
            'registerName' => 'Budi Santoso',
            'registerEmail' => 'budi.santoso@example.com',
            'registerPassword' => 'password',
            'registerPasswordConfirmation' => 'password',
            'registerNip' => '198501012010011234',
            'registerInstansiId' => $instansi->id,
            'registerInstansiSearch' => $instansi->nama,
        ];
    }

    public function test_pegawai_can_register_with_valid_data(): void
    {
        $instansi = Instansi::query()->create(['id' => 1, 'nama' => 'Dinas Pendidikan']);

        Livewire::test(Login::class)
            ->set($this->validRegistrationPayload($instansi))
            ->call('registerPegawai')
            ->assertHasNoErrors()
            ->assertSet('showRegisterModal', false);

        $this->assertDatabaseHas('users', [
            'email' => 'budi.santoso@example.com',
            'nip' => '198501012010011234',
            'instansi_id' => $instansi->id,
            'is_pegawai' => true,
        ]);
    }

    public function test_registration_rejects_nip_shorter_than_18_digits(): void
    {
        $instansi = Instansi::query()->create(['id' => 1, 'nama' => 'Dinas Pendidikan']);

        Livewire::test(Login::class)
            ->set([
                ...$this->validRegistrationPayload($instansi),
                'registerNip' => '1234567890123456',
            ])
            ->call('registerPegawai')
            ->assertHasErrors(['registerNip' => 'nip harus minimal 18 digit.']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_nip_with_repeated_digits(): void
    {
        $instansi = Instansi::query()->create(['id' => 1, 'nama' => 'Dinas Pendidikan']);

        Livewire::test(Login::class)
            ->set([
                ...$this->validRegistrationPayload($instansi),
                'registerNip' => '000000000000000000',
            ])
            ->call('registerPegawai')
            ->assertHasErrors(['registerNip' => 'nip tidak valid.']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_non_numeric_nip(): void
    {
        $instansi = Instansi::query()->create(['id' => 1, 'nama' => 'Dinas Pendidikan']);

        Livewire::test(Login::class)
            ->set([
                ...$this->validRegistrationPayload($instansi),
                'registerNip' => '19850101201001123-',
            ])
            ->call('registerPegawai')
            ->assertHasErrors(['registerNip']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_invalid_email_format(): void
    {
        $instansi = Instansi::query()->create(['id' => 1, 'nama' => 'Dinas Pendidikan']);

        Livewire::test(Login::class)
            ->set([
                ...$this->validRegistrationPayload($instansi),
                'registerEmail' => 'bukan-email',
            ])
            ->call('registerPegawai')
            ->assertHasErrors(['registerEmail']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $instansi = Instansi::query()->create(['id' => 1, 'nama' => 'Dinas Pendidikan']);
        User::factory()->create(['email' => 'budi.santoso@example.com']);

        Livewire::test(Login::class)
            ->set($this->validRegistrationPayload($instansi))
            ->call('registerPegawai')
            ->assertHasErrors(['registerEmail' => 'email sudah terdaftar']);
    }
}
