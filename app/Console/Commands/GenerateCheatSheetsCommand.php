<?php

namespace App\Console\Commands;

use App\Enums\SubjectCode;
use App\Jobs\GenerateCheatSheetJob;
use App\Models\Material;
use App\Services\CheatSheetGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class GenerateCheatSheetsCommand extends Command
{
    protected $signature = 'cheat-sheets:generate
                            {--material= : Slug materi tertentu}
                            {--subject= : Kode subject (twk, tiu, tkp)}
                            {--force : Regenerasi meski sudah ada}
                            {--dry-run : Tampilkan prompt tanpa memanggil API}
                            {--sync : Jalankan tanpa queue}';

    protected $description = 'Generate cheat-sheet materi belajar CPNS via OpenAI (strategi seed & save)';

    public function handle(CheatSheetGenerationService $generationService): int
    {
        if (! $this->option('dry-run') && ! $generationService->isConfigured()) {
            $this->error('API key OpenAI belum dikonfigurasi. Tambahkan OPENAI_API_KEY di file .env.');

            return self::FAILURE;
        }

        $materials = $this->resolveMaterials();

        if ($materials->isEmpty()) {
            $this->warn('Tidak ada materi yang cocok dengan filter.');

            return self::SUCCESS;
        }

        $this->info("Menargetkan {$materials->count()} materi...");

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');
        $sync = (bool) $this->option('sync');

        foreach ($materials as $material) {
            $label = "{$material->subject->code->label()} — {$material->displayName()}";

            if ($dryRun) {
                $this->newLine();
                $this->line("<fg=cyan>{$label}</>");
                $this->line($generationService->buildPrompt($material));

                continue;
            }

            if ($sync) {
                try {
                    $generationService->generateForMaterial($material, $force);
                    $this->info("✓ {$label}");
                } catch (\Throwable $exception) {
                    $this->error("✗ {$label}: {$exception->getMessage()}");
                }

                continue;
            }

            GenerateCheatSheetJob::dispatch($material->id, $force);
            $this->line("→ Antrian: {$label}");
        }

        if (! $dryRun && ! $sync) {
            $this->newLine();
            $this->comment('Semua job telah dimasukkan ke antrian. Pastikan queue worker berjalan.');
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Material>
     */
    private function resolveMaterials()
    {
        $query = Material::query()
            ->with(['subject', 'materialGroup', 'cheatSheet'])
            ->orderedForSelect();

        if ($subjectCode = $this->option('subject')) {
            $code = SubjectCode::tryFrom(strtolower((string) $subjectCode));

            if ($code === null) {
                $this->error('Subject tidak valid. Gunakan: twk, tiu, atau tkp.');

                return collect();
            }

            $query->whereHas('subject', fn ($builder) => $builder->where('code', $code));
        }

        if ($materialSlug = $this->option('material')) {
            $query->where('materials.slug', $materialSlug);
        }

        $materials = $query->get();

        if (! $this->option('force')) {
            $materials = $materials->filter(
                fn (Material $material) => ! $material->cheatSheet?->isPublished(),
            );
        }

        return $materials->values();
    }
}
