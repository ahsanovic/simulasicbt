<?php

namespace App\Livewire\Admin\Settings;

use App\Enums\SkdTarget;
use App\Models\Setting;
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

    public int $cpns_twk = 65;

    public int $cpns_tiu = 80;

    public int $cpns_tkp = 166;

    public int $cpns_total = 311;

    public int $kedinasan_twk = 65;

    public int $kedinasan_tiu = 80;

    public int $kedinasan_tkp = 156;

    public int $kedinasan_total = 301;

    public function mount(): void
    {
        $this->app_name = Setting::getValue('app_name', 'Simulasi CBT');
        $this->institution_name = Setting::getValue('institution_name', '');
        $this->default_exam_duration = (int) Setting::getValue('default_exam_duration', 100);

        $profiles = exam_passing_grades_profiles();
        $cpns = $profiles[SkdTarget::Cpns->value];
        $kedinasan = $profiles[SkdTarget::SekolahKedinasan->value];

        $this->cpns_twk = (int) $cpns['twk'];
        $this->cpns_tiu = (int) $cpns['tiu'];
        $this->cpns_tkp = (int) $cpns['tkp'];
        $this->cpns_total = (int) $cpns['total'];
        $this->kedinasan_twk = (int) $kedinasan['twk'];
        $this->kedinasan_tiu = (int) $kedinasan['tiu'];
        $this->kedinasan_tkp = (int) $kedinasan['tkp'];
        $this->kedinasan_total = (int) $kedinasan['total'];
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
            'cpns_twk' => ['required', 'integer', 'min:0'],
            'cpns_tiu' => ['required', 'integer', 'min:0'],
            'cpns_tkp' => ['required', 'integer', 'min:0'],
            'cpns_total' => ['required', 'integer', 'min:0'],
            'kedinasan_twk' => ['required', 'integer', 'min:0'],
            'kedinasan_tiu' => ['required', 'integer', 'min:0'],
            'kedinasan_tkp' => ['required', 'integer', 'min:0'],
            'kedinasan_total' => ['required', 'integer', 'min:0'],
        ]);

        Setting::setValue('app_name', $validated['app_name']);
        Setting::setValue('institution_name', $validated['institution_name']);
        Setting::setValue('default_exam_duration', (string) $validated['default_exam_duration'], 'exam', 'integer');

        Setting::setValue(
            'exam_passing_grades',
            json_encode([
                SkdTarget::Cpns->value => [
                    'twk' => $validated['cpns_twk'],
                    'tiu' => $validated['cpns_tiu'],
                    'tkp' => $validated['cpns_tkp'],
                    'total' => $validated['cpns_total'],
                ],
                SkdTarget::SekolahKedinasan->value => [
                    'twk' => $validated['kedinasan_twk'],
                    'tiu' => $validated['kedinasan_tiu'],
                    'tkp' => $validated['kedinasan_tkp'],
                    'total' => $validated['kedinasan_total'],
                ],
            ]),
            'exam',
            'json',
        );

        $this->showModal = false;
        session()->flash('success', 'Pengaturan berhasil disimpan.');
    }

    public function render()
    {
        $profiles = exam_passing_grades_profiles();

        return view('livewire.admin.settings.index', [
            'passingGradeProfiles' => [
                SkdTarget::Cpns->label() => $profiles[SkdTarget::Cpns->value],
                SkdTarget::SekolahKedinasan->label() => $profiles[SkdTarget::SekolahKedinasan->value],
            ],
        ]);
    }
}
