<?php

namespace App\Livewire\Admin\Settings;

use App\Models\Setting;
use App\Support\ExamLockdown;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Pengaturan')]
class Index extends Component
{
    public bool $showModal = false;

    public string $app_name = '';

    public string $institution_name = '';

    public int $default_exam_duration = 100;

    /** Mode Sedang Ujian: closes the simulasi while an official exam runs. */
    public bool $lockdownActive = false;

    /** datetime-local value, shown to visitors as "dapat digunakan kembali pada". */
    public string $lockdownReopensAt = '';

    public string $lockdownMessage = '';

    public function mount(): void
    {
        $this->app_name = Setting::getValue('app_name', 'Simulasi CBT');
        $this->institution_name = Setting::getValue('institution_name', '');
        $this->default_exam_duration = (int) Setting::getValue('default_exam_duration', 100);

        $this->lockdownActive = ExamLockdown::active();
        $this->lockdownReopensAt = ExamLockdown::reopensAt()?->format('Y-m-d\TH:i') ?? '';
        $this->lockdownMessage = ExamLockdown::message() ?? '';
    }

    public function openModal(): void
    {
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'app_name' => ['required', 'string', 'max:255'],
            'institution_name' => ['required', 'string', 'max:255'],
            'default_exam_duration' => ['required', 'integer', 'min:1'],
        ]);

        Setting::setValue('app_name', $validated['app_name']);
        Setting::setValue('institution_name', $validated['institution_name']);
        Setting::setValue('default_exam_duration', (string) $validated['default_exam_duration'], 'exam', 'integer');

        $this->showModal = false;
        session()->flash('success', 'Pengaturan berhasil disimpan.');
    }

    public function saveLockdown(): void
    {
        $validated = $this->validate([
            'lockdownActive' => ['boolean'],
            'lockdownReopensAt' => ['nullable', 'date'],
            'lockdownMessage' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'lockdownReopensAt' => 'waktu dibuka kembali',
            'lockdownMessage' => 'pesan',
        ]);

        ExamLockdown::save(
            $validated['lockdownActive'],
            filled($validated['lockdownReopensAt']) ? $validated['lockdownReopensAt'] : null,
            filled($validated['lockdownMessage']) ? trim($validated['lockdownMessage']) : null,
        );

        session()->flash('success', $validated['lockdownActive']
            ? 'Mode Sedang Ujian aktif. Login simulasi ditutup.'
            : 'Mode Sedang Ujian nonaktif. Login simulasi dibuka kembali.');

        $this->redirect(route('admin.settings.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.settings.index');
    }
}
