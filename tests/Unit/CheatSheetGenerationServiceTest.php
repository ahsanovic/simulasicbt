<?php

namespace Tests\Unit;

use App\Enums\SubjectCode;
use App\Models\Material;
use App\Models\MaterialCheatSheet;
use App\Models\Subject;
use App\Services\CheatSheetGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheatSheetGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_for_material_skips_when_already_published(): void
    {
        config(['services.openai.key' => 'test-key']);

        $material = $this->createMaterial();
        $existing = MaterialCheatSheet::query()->create([
            'material_id' => $material->id,
            'content' => '## Sudah ada',
            'status' => MaterialCheatSheet::STATUS_COMPLETED,
            'generated_at' => now(),
        ]);

        Http::fake();

        $result = app(CheatSheetGenerationService::class)->generateForMaterial($material);

        $this->assertSame($existing->id, $result->id);
        Http::assertNothingSent();
    }

    public function test_generate_for_material_calls_openai_and_stores_markdown(): void
    {
        config(['services.openai.key' => 'test-key']);

        $material = $this->createMaterial();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "```markdown\n## Konsep Inti\n\nIsi materi.\n```",
                        ],
                    ],
                ],
            ]),
        ]);

        $cheatSheet = app(CheatSheetGenerationService::class)->generateForMaterial($material);

        $this->assertTrue($cheatSheet->isPublished());
        $this->assertStringContainsString('## Konsep Inti', $cheatSheet->content);
        $this->assertStringNotContainsString('```', $cheatSheet->content);
    }

    public function test_build_prompt_includes_subject_and_material_name(): void
    {
        $material = $this->createMaterial();
        $prompt = app(CheatSheetGenerationService::class)->buildPrompt($material);

        $this->assertStringContainsString('TWK', $prompt);
        $this->assertStringContainsString('Integritas', $prompt);
    }

    private function createMaterial(): Material
    {
        $subject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'Tes Wawasan Kebangsaan',
            'slug' => 'twk',
            'sort_order' => 1,
        ]);

        return Material::query()->create([
            'subject_id' => $subject->id,
            'name' => 'Integritas',
            'slug' => 'integritas',
            'sort_order' => 1,
        ]);
    }
}
