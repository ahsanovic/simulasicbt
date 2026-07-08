<?php

namespace App\Jobs;

use App\Models\Material;
use App\Services\CheatSheetGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateCheatSheetJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $materialId,
        public bool $force = false,
    ) {}

    public function handle(CheatSheetGenerationService $generationService): void
    {
        $material = Material::query()
            ->with(['subject', 'materialGroup'])
            ->find($this->materialId);

        if ($material === null) {
            return;
        }

        if (! $generationService->isConfigured()) {
            return;
        }

        try {
            $generationService->generateForMaterial($material, $this->force);
        } catch (\Throwable $exception) {
            Log::warning('Gagal membuat cheat-sheet materi.', [
                'material_id' => $this->materialId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
