<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            InstansiSeeder::class,
            SubjectSeeder::class,
            DemoExamSeeder::class,
            FormationSeeder::class,
        ]);

        User::query()->updateOrCreate(
            ['email' => 'admin@simulasicbt.test'],
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $instansi = \App\Models\Instansi::query()->first();

        User::query()->updateOrCreate(
            ['email' => 'peserta@simulasicbt.test'],
            [
                'name' => 'Peserta Demo (Pegawai)',
                'username' => 'peserta',
                'password' => Hash::make('password'),
                'nip' => '198001012006011001',
                'instansi_id' => $instansi?->id,
                'is_pegawai' => true,
                'role' => UserRole::Peserta,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        Setting::setValue('app_name', 'Simulasi CBT', 'general');
        Setting::setValue('institution_name', 'Instansi Demo', 'general');
        Setting::setValue('default_exam_duration', '100', 'exam', 'integer');
    }
}
