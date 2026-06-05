<?php

namespace Database\Seeders;

use App\Enums\SubjectCode;
use App\Models\Material;
use App\Models\MaterialGroup;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            SubjectCode::Twk->value => [
                'name' => 'Tes Wawasan Kebangsaan',
                'materials' => [
                    'nasionalisme' => 'Nasionalisme',
                    'integritas' => 'Integritas',
                    'bela-negara' => 'Bela Negara',
                    'pilar-negara' => 'Pilar Negara',
                    'bahasa-negara' => 'Bahasa Negara',
                ],
            ],
            SubjectCode::Tiu->value => [
                'name' => 'Tes Intelegensia Umum',
                'groups' => [
                    'kemampuan-verbal' => [
                        'name' => 'Kemampuan Verbal',
                        'materials' => [
                            'analogi-verbal' => 'Analogi',
                            'silogisme' => 'Silogisme',
                            'analitis' => 'Analitis',
                        ],
                    ],
                    'kemampuan-numerik' => [
                        'name' => 'Kemampuan Numerik',
                        'materials' => [
                            'berhitung' => 'Berhitung',
                            'deret-angka' => 'Deret Angka',
                            'perbandingan-kuantitatif' => 'Perbandingan Kuantitatif',
                            'soal-cerita' => 'Soal Cerita',
                        ],
                    ],
                    'kemampuan-figural' => [
                        'name' => 'Kemampuan Figural',
                        'materials' => [
                            'analogi-figural' => 'Analogi',
                            'ketidaksamaan' => 'Ketidaksamaan',
                            'serial' => 'Serial',
                        ],
                    ],
                ],
            ],
            SubjectCode::Tkp->value => [
                'name' => 'Tes Karakteristik Pribadi',
                'materials' => [
                    'pelayanan-publik' => 'Pelayanan Publik',
                    'jejaring-kerja' => 'Jejaring Kerja',
                    'sosial-budaya' => 'Sosial Budaya',
                    'teknologi-informasi' => 'Teknologi Informasi',
                    'profesionalisme' => 'Profesionalisme',
                    'anti-radikalisme' => 'Anti Radikalisme',
                ],
            ],
        ];

        $sortOrder = 1;

        foreach ($subjects as $code => $config) {
            $subject = Subject::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $config['name'],
                    'slug' => Str::slug($config['name']),
                    'sort_order' => $sortOrder++,
                ],
            );

            if (isset($config['materials'])) {
                $this->seedMaterials($subject, $config['materials']);
            }

            if (isset($config['groups'])) {
                $groupOrder = 1;

                foreach ($config['groups'] as $groupSlug => $groupConfig) {
                    $group = MaterialGroup::query()->updateOrCreate(
                        [
                            'subject_id' => $subject->id,
                            'slug' => $groupSlug,
                        ],
                        [
                            'name' => $groupConfig['name'],
                            'sort_order' => $groupOrder++,
                        ],
                    );

                    $this->seedMaterials($subject, $groupConfig['materials'], $group->id);
                }
            }
        }
    }

    private function seedMaterials(Subject $subject, array $materials, ?int $groupId = null): void
    {
        $order = 1;

        foreach ($materials as $slug => $name) {
            Material::query()->updateOrCreate(
                [
                    'subject_id' => $subject->id,
                    'slug' => $slug,
                ],
                [
                    'material_group_id' => $groupId,
                    'name' => $name,
                    'sort_order' => $order++,
                ],
            );
        }
    }
}
