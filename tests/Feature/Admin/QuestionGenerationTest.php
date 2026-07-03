<?php

namespace Tests\Feature\Admin;

use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Admin\Questions\Generate;
use App\Models\Material;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class QuestionGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.openai.key' => 'test-openai-key',
            'services.openai.model' => 'gpt-4o',
        ]);
    }

    public function test_admin_can_access_generate_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.questions.generate'))
            ->assertOk()
            ->assertSee('Generate Soal AI');
    }

    public function test_peserta_cannot_access_generate_page(): void
    {
        $peserta = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($peserta)
            ->get(route('admin.questions.generate'))
            ->assertForbidden();
    }

    public function test_generate_twk_questions_and_approve_saves_to_database(): void
    {
        [$twkSubject, $material] = $this->createTwkSubjectAndMaterial();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->fakeOpenAiResponse($this->twkQuestionPayload());

        Livewire::actingAs($admin)
            ->test(Generate::class)
            ->set('subject_id', $twkSubject->id)
            ->set('material_id', $material->id)
            ->set('difficulty', 'medium')
            ->set('questionCount', 1)
            ->call('generate')
            ->assertSet('generatedQuestions.0.content', 'Apa ibu kota Indonesia?')
            ->call('approve', 0);

        $this->assertDatabaseCount('questions', 1);
        $this->assertDatabaseHas('questions', [
            'subject_id' => $twkSubject->id,
            'material_id' => $material->id,
            'difficulty' => 'medium',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $question = Question::query()->first();
        $this->assertNotNull($question);
        $this->assertStringContainsString('Jakarta', $question->explanation ?? '');
        $this->assertDatabaseHas('question_options', [
            'question_id' => $question->id,
            'label' => 'A',
            'is_correct' => true,
        ]);
        $this->assertDatabaseHas('question_options', [
            'question_id' => $question->id,
            'label' => 'B',
            'is_correct' => false,
        ]);
    }

    public function test_tkp_approve_rejects_invalid_score_weights(): void
    {
        [$tkpSubject, $material] = $this->createTkpSubjectAndMaterial();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->fakeOpenAiResponse($this->tkpQuestionPayload());

        Livewire::actingAs($admin)
            ->test(Generate::class)
            ->set('subject_id', $tkpSubject->id)
            ->set('material_id', $material->id)
            ->set('questionCount', 1)
            ->call('generate')
            ->set('generatedQuestions.0.options.0.score_weight', 5)
            ->set('generatedQuestions.0.options.1.score_weight', 5)
            ->call('approve', 0)
            ->assertSet('generatedQuestions.0.validation_error', 'Pada soal TKP, bobot setiap opsi tidak boleh duplikat.');

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_tkp_approve_saves_weighted_options(): void
    {
        [$tkpSubject, $material] = $this->createTkpSubjectAndMaterial();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->fakeOpenAiResponse($this->tkpQuestionPayload());

        Livewire::actingAs($admin)
            ->test(Generate::class)
            ->set('subject_id', $tkpSubject->id)
            ->set('material_id', $material->id)
            ->set('questionCount', 1)
            ->call('generate')
            ->call('approve', 0);

        $question = Question::query()->with('options')->first();
        $this->assertNotNull($question);
        $this->assertSame([5, 4, 3, 2, 1], $question->options->sortBy('sort_order')->pluck('score_weight')->all());
        $this->assertTrue($question->options->every(fn ($option) => $option->is_correct === false));
    }

    public function test_regenerate_replaces_single_question(): void
    {
        [$twkSubject, $material] = $this->createTwkSubjectAndMaterial();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push($this->openAiApiResponse($this->twkQuestionPayload('Soal pertama')))
                ->push($this->openAiApiResponse($this->twkQuestionPayload('Soal regenerated'))),
        ]);

        Livewire::actingAs($admin)
            ->test(Generate::class)
            ->set('subject_id', $twkSubject->id)
            ->set('material_id', $material->id)
            ->set('questionCount', 1)
            ->call('generate')
            ->assertSet('generatedQuestions.0.content', 'Soal pertama')
            ->call('regenerate', 0)
            ->assertSet('generatedQuestions.0.content', 'Soal regenerated');
    }

    /**
     * @return array{0: Subject, 1: Material}
     */
    private function createTwkSubjectAndMaterial(): array
    {
        $subject = Subject::query()->create([
            'code' => SubjectCode::Twk,
            'name' => 'TWK',
            'slug' => 'twk',
            'sort_order' => 1,
        ]);

        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => 'nasionalisme',
            'name' => 'Nasionalisme',
            'sort_order' => 1,
        ]);

        return [$subject, $material];
    }

    /**
     * @return array{0: Subject, 1: Material}
     */
    private function createTkpSubjectAndMaterial(): array
    {
        $subject = Subject::query()->create([
            'code' => SubjectCode::Tkp,
            'name' => 'TKP',
            'slug' => 'tkp',
            'sort_order' => 3,
        ]);

        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => 'pelayanan-publik',
            'name' => 'Pelayanan Publik',
            'sort_order' => 1,
        ]);

        return [$subject, $material];
    }

    /**
     * @param  array<string, mixed>  $questionsPayload
     */
    private function fakeOpenAiResponse(array $questionsPayload): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response($this->openAiApiResponse($questionsPayload)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $questionsPayload
     * @return array<string, mixed>
     */
    private function openAiApiResponse(array $questionsPayload): array
    {
        return [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode(['questions' => [$questionsPayload]]),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function twkQuestionPayload(string $content = 'Apa ibu kota Indonesia?'): array
    {
        return [
            'content' => $content,
            'explanation' => 'Jakarta adalah ibu kota Indonesia sejak tahun 1949.',
            'options' => [
                ['label' => 'A', 'content' => 'Jakarta', 'is_correct' => true, 'score_weight' => null],
                ['label' => 'B', 'content' => 'Bandung', 'is_correct' => false, 'score_weight' => null],
                ['label' => 'C', 'content' => 'Surabaya', 'is_correct' => false, 'score_weight' => null],
                ['label' => 'D', 'content' => 'Medan', 'is_correct' => false, 'score_weight' => null],
                ['label' => 'E', 'content' => 'Makassar', 'is_correct' => false, 'score_weight' => null],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tkpQuestionPayload(): array
    {
        return [
            'content' => 'Bagaimana sikap Anda saat melayani pengguna layanan yang marah?',
            'explanation' => 'Respons terbaik adalah tetap tenang dan empati.',
            'options' => [
                ['label' => 'A', 'content' => 'Tetap tenang dan mendengarkan', 'is_correct' => false, 'score_weight' => 5],
                ['label' => 'B', 'content' => 'Membalas dengan nada setinggi', 'is_correct' => false, 'score_weight' => 1],
                ['label' => 'C', 'content' => 'Mengabaikan keluhan', 'is_correct' => false, 'score_weight' => 2],
                ['label' => 'D', 'content' => 'Menyuruh menunggu tanpa alasan', 'is_correct' => false, 'score_weight' => 3],
                ['label' => 'E', 'content' => 'Menyalahkan atasan', 'is_correct' => false, 'score_weight' => 4],
            ],
        ];
    }
}
