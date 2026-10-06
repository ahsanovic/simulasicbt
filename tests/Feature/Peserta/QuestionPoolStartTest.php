<?php

namespace Tests\Feature\Peserta;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamStatus;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Livewire\Peserta\ModeUjian\Dashboard;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\JabatanSkb;
use App\Models\Material;
use App\Models\Question;
use App\Models\SkbQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamQuestionGeneratorService;
use App\Services\ExamService;
use App\Services\SkbExamService;
use App\Support\ExamQuestionCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pressing "Mulai" draws questions from a shared cached pool instead of an
 * ORDER BY RAND() over the bank per participant, with the same composition
 * rules, and falls back to the database whenever the pool cannot be trusted.
 */
class QuestionPoolStartTest extends TestCase
{
    use RefreshDatabase;

    private const EXTRA_PER_SUBJECT = 5;

    public function test_skd_start_keeps_the_composition_without_order_by_rand(): void
    {
        $exam = $this->seedSkdBank();
        app(ExamService::class)->startAttempt($exam, $this->peserta()); // fills the pools

        DB::enableQueryLog();
        $attempt = app(ExamService::class)->startAttempt($exam, $this->peserta());
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertFalse($queries->contains(fn (string $sql) => preg_match('/rand(om)?\(\)/i', $sql) === 1), 'No ORDER BY RAND() once the pool is cached.');

        $answers = $attempt->answers()->with('question.subject')->orderBy('sort_order')->get();
        $this->assertCount(110, $answers);
        $this->assertSame(110, $answers->pluck('question_id')->unique()->count());
        $this->assertTrue($answers->every(fn ($a) => $a->question->is_active));
        $this->assertSame(range(1, 110), $answers->pluck('sort_order')->all());

        foreach ([[0, 30, SubjectCode::Twk], [30, 35, SubjectCode::Tiu], [65, 45, SubjectCode::Tkp]] as [$offset, $length, $code]) {
            $this->assertTrue($answers->slice($offset, $length)->every(fn ($a) => $a->question->subject->code === $code), "{$code->value} block");
        }
    }

    public function test_two_participants_get_different_random_sets(): void
    {
        $exam = $this->seedSkdBank(extra: 40);

        $a = app(ExamService::class)->startAttempt($exam, $this->peserta())->answers()->pluck('question_id')->all();
        $b = app(ExamService::class)->startAttempt($exam, $this->peserta())->answers()->pluck('question_id')->all();

        $this->assertNotSame($a, $b);
    }

    public function test_a_question_deactivated_in_the_admin_is_never_drawn_again(): void
    {
        $exam = $this->seedSkdBank();
        app(ExamService::class)->startAttempt($exam, $this->peserta());

        $removed = Question::query()->latest('id')->limit(self::EXTRA_PER_SUBJECT)->get();
        $removed->each->update(['is_active' => false]); // model events drop the pools

        foreach (range(1, 5) as $i) {
            $ids = app(ExamService::class)->startAttempt($exam, $this->peserta())->answers()->pluck('question_id');
            $this->assertEmpty($ids->intersect($removed->pluck('id')));
        }
    }

    public function test_a_bulk_deactivation_without_model_events_falls_back_to_the_database(): void
    {
        $exam = $this->seedSkdBank();
        app(ExamService::class)->startAttempt($exam, $this->peserta()); // pools cached

        // Every TWK question but the required 30 disappears behind the pool's back.
        $twk = Question::query()->whereHas('subject', fn ($q) => $q->where('code', SubjectCode::Twk))->pluck('id');
        $hidden = $twk->take(self::EXTRA_PER_SUBJECT);
        Question::query()->whereKey($hidden)->update(['is_active' => false]);

        foreach (range(1, 5) as $i) {
            $ids = app(ExamService::class)->startAttempt($exam, $this->peserta())->answers()->pluck('question_id');
            $this->assertEmpty($ids->intersect($hidden), 'A stale pool must never put an inactive question in an exam.');
        }
    }

    public function test_too_small_bank_still_reports_the_exact_shortage(): void
    {
        $exam = $this->seedSkdBank(extra: 0);
        Question::query()->whereHas('subject', fn ($q) => $q->where('code', SubjectCode::Tiu))->first()->update(['is_active' => false]);

        try {
            app(ExamService::class)->startAttempt($exam, $this->peserta());
            $this->fail('Expected the bank to be too small.');
        } catch (ValidationException $e) {
            $this->assertSame('Bank soal tidak cukup untuk memulai ujian. Hubungi admin.', $e->errors()['exam'][0]);
        }
    }

    public function test_switching_the_pool_off_uses_the_database_query(): void
    {
        config(['exam.question_pool' => false]);
        $exam = $this->seedSkdBank();

        DB::enableQueryLog();
        $attempt = app(ExamService::class)->startAttempt($exam, $this->peserta());
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertTrue($queries->contains(fn (string $sql) => preg_match('/rand(om)?\(\)/i', $sql) === 1));
        $this->assertSame(110, $attempt->answers()->count());
    }

    public function test_an_unreachable_cache_falls_back_to_the_database(): void
    {
        $exam = $this->seedSkdBank();
        config(['cache.default' => 'redis', 'database.redis.client' => 'predis', 'database.redis.cache.port' => 1, 'database.redis.default.port' => 1]);

        $attempt = app(ExamService::class)->startAttempt($exam, $this->peserta());

        $this->assertSame(110, $attempt->answers()->count());
    }

    public function test_skb_start_draws_active_questions_of_the_jabatan_from_the_pool(): void
    {
        [$event, $participant, $other] = $this->seedSkbEvent(questions: 12, count: 8);
        app(SkbExamService::class)->startAttempt($event, $participant); // fills the pool

        $removed = SkbQuestion::query()->where('jabatan_skb_id', $participant->jabatan_skb_id)->first();
        $removed->update(['is_active' => false]);
        $second = $this->participantFor($event, $participant->jabatan_skb_id);

        DB::enableQueryLog();
        $attempt = app(SkbExamService::class)->startAttempt($event, $second);
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $ids = $attempt->answers()->pluck('skb_question_id');
        $jabatanIds = SkbQuestion::query()->where('jabatan_skb_id', $participant->jabatan_skb_id)->where('is_active', true)->pluck('id');

        $this->assertCount(8, $ids);
        $this->assertSame(8, $ids->unique()->count());
        $this->assertEmpty($ids->diff($jabatanIds), 'Only active questions of the participant jabatan.');
        $this->assertNotContains($removed->id, $ids);
        $this->assertNotContains($other->id, $ids);
        $this->assertSame(range(1, 8), $attempt->answers()->orderBy('sort_order')->pluck('sort_order')->all());

        // The pool was rebuilt once after the edit; the next start needs no RAND either.
        app(SkbExamService::class)->startAttempt($event, $this->participantFor($event, $participant->jabatan_skb_id));
        $this->assertFalse($queries->contains(fn (string $sql) => preg_match('/rand(om)?\(\)/i', $sql) === 1));
    }

    public function test_dashboard_start_sets_the_display_name_and_reports_a_wrong_pin(): void
    {
        $exam = $this->seedSkdBank();
        $user = $this->peserta();
        $event = Event::query()->create([
            'name' => 'Event Pool', 'exam_id' => $exam->id, 'status' => EventStatus::Active,
            'created_by' => $exam->created_by, 'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skd,
        ]);
        $session = EventSession::query()->create([
            'event_id' => $event->id, 'name' => 'Sesi 1', 'code' => EventSession::generateUniqueCode(),
            'skd_pin' => '1234', 'status' => EventStatus::Active,
        ]);
        EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $user->id,
            'name' => 'Nama Di Kartu', 'nik' => '3201010101019999', 'jabatan_label' => '-',
        ]);

        $page = Livewire::actingAs($user)->test(Dashboard::class)->call('openPinModal', 'skd');

        $page->set('pinInput', '0000')->call('submitPin')->assertDispatched('pin-failed')->assertSet('pinError', 'PIN sesi salah.');
        $page->set('pinInput', '1234')->call('submitPin')->assertNotDispatched('pin-failed')->assertRedirect(route('peserta.exam.room', $exam->id));

        $this->assertSame('Nama Di Kartu', ExamAttempt::query()->where('user_id', $user->id)->sole()->display_name);
    }

    public function test_warm_loads_every_active_question_into_the_cache(): void
    {
        $this->seedSkdBank();
        [, $participant] = $this->seedSkbEvent(questions: 4, count: 3);
        $inactive = Question::query()->first();
        $inactive->update(['is_active' => false]);

        $this->artisan('exam:warm')->expectsOutputToContain('Cache soal siap')->assertSuccessful();

        DB::enableQueryLog();
        $skd = ExamQuestionCache::skd(Question::query()->where('is_active', true)->value('id'));
        $skb = ExamQuestionCache::skb(SkbQuestion::query()->where('jabatan_skb_id', $participant->jabatan_skb_id)->value('id'));
        $count = count(DB::getQueryLog()) - 2; // the two value() lookups above
        DB::disableQueryLog();

        $this->assertSame(0, $count, 'Warm questions come from the cache.');
        $this->assertNotNull($skd->subject);
        $this->assertCount(2, $skd->options);
        $this->assertCount(2, $skb->options);
    }

    private function peserta(): User
    {
        return User::factory()->create(['role' => UserRole::Peserta]);
    }

    private function seedSkdBank(int $extra = self::EXTRA_PER_SUBJECT): Exam
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach (SubjectCode::cases() as $code) {
            $subject = Subject::query()->create(['code' => $code, 'name' => $code->label(), 'slug' => $code->value, 'sort_order' => 1]);
            $material = Material::query()->create(['subject_id' => $subject->id, 'slug' => 'materi-'.$code->value, 'name' => 'Materi', 'sort_order' => 1]);

            for ($i = 0; $i < ExamQuestionGeneratorService::COUNTS_BY_SUBJECT[$code->value] + $extra; $i++) {
                $question = Question::query()->create([
                    'subject_id' => $subject->id, 'material_id' => $material->id,
                    'content' => "Soal {$code->value} {$i}", 'difficulty' => 'medium', 'is_active' => true,
                ]);
                $question->options()->createMany([
                    ['label' => 'A', 'content' => 'A', 'is_correct' => true, 'score_weight' => 5, 'sort_order' => 1],
                    ['label' => 'B', 'content' => 'B', 'is_correct' => false, 'score_weight' => 1, 'sort_order' => 2],
                ]);
            }
        }

        return Exam::query()->create([
            'title' => 'Ujian Pool', 'slug' => 'ujian-pool', 'duration_minutes' => 100,
            'status' => ExamStatus::Published, 'settings' => ['difficulty' => 'all'], 'created_by' => $admin->id,
        ]);
    }

    /** @return array{0: Event, 1: EventParticipant, 2: SkbQuestion} */
    private function seedSkbEvent(int $questions, int $count): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $jabatan = JabatanSkb::query()->create(['name' => 'Analis', 'slug' => 'analis-'.uniqid(), 'is_active' => true]);
        $otherJabatan = JabatanSkb::query()->create(['name' => 'Lain', 'slug' => 'lain-'.uniqid(), 'is_active' => true]);

        $make = fn (int $jabatanId, int $i) => tap(SkbQuestion::query()->create([
            'jabatan_skb_id' => $jabatanId, 'content' => "<p>Soal {$i}</p>", 'difficulty' => 'medium', 'is_active' => true, 'created_by' => $admin->id,
        ]), fn (SkbQuestion $q) => $q->options()->createMany([
            ['label' => 'A', 'content_type' => 'text', 'content' => 'A', 'is_correct' => true, 'sort_order' => 1],
            ['label' => 'B', 'content_type' => 'text', 'content' => 'B', 'is_correct' => false, 'sort_order' => 2],
        ]));

        foreach (range(1, $questions) as $i) {
            $make($jabatan->id, $i);
        }
        $other = $make($otherJabatan->id, 99);

        $event = Event::query()->create([
            'name' => 'Event SKB Pool', 'exam_id' => null, 'status' => EventStatus::Active, 'created_by' => $admin->id,
            'is_mode_ujian' => true, 'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => $count, 'skb_correct_score' => 5, 'skb_duration_minutes' => 60,
        ]);

        return [$event, $this->participantFor($event, $jabatan->id), $other];
    }

    private function participantFor(Event $event, int $jabatanId): EventParticipant
    {
        $session = $event->sessions()->first() ?? EventSession::query()->create([
            'event_id' => $event->id, 'name' => 'Sesi 1', 'code' => EventSession::generateUniqueCode(), 'skb_pin' => '5678', 'status' => EventStatus::Active,
        ]);

        return EventParticipant::query()->create([
            'event_id' => $event->id, 'event_session_id' => $session->id, 'user_id' => $this->peserta()->id,
            'name' => 'Peserta', 'nik' => (string) random_int(1000000000000000, 9999999999999999), 'jabatan_label' => 'Analis', 'jabatan_skb_id' => $jabatanId,
        ]);
    }
}
