<?php

namespace Tests\Feature\Peserta\Concerns;

use App\Enums\EventExamMode;
use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionOptionContentType;
use App\Enums\SubjectCode;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\EventSession;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\JabatanSkb;
use App\Models\Material;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\SkbExamAnswer;
use App\Models\SkbExamAttempt;
use App\Models\SkbQuestion;
use App\Models\Subject;
use App\Models\User;
use App\Services\SkbExamService;

/**
 * SKD (two questions) and SKB (three questions) attempts for exam room tests.
 */
trait CreatesExamAttempts
{
    /**
     * @return array{0: User, 1: SkbExamAttempt}
     */
    protected function startSkbAttempt(): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $jabatan = JabatanSkb::query()->create([
            'name' => 'Analis SKB Deadline',
            'slug' => 'analis-skb-deadline-'.uniqid(),
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $question = SkbQuestion::query()->create([
                'jabatan_skb_id' => $jabatan->id,
                'content' => "<p>Soal {$i}</p>",
                'difficulty' => 'medium',
                'is_active' => true,
                'created_by' => $admin->id,
            ]);

            foreach (['A', 'B', 'C', 'D'] as $order => $label) {
                $question->options()->create([
                    'label' => $label,
                    'content_type' => QuestionOptionContentType::Text,
                    'content' => "Opsi {$label}",
                    'is_correct' => $label === 'A',
                    'sort_order' => $order + 1,
                ]);
            }
        }

        $event = Event::query()->create([
            'name' => 'Mode Ujian SKB Deadline Test',
            'exam_id' => null,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
            'is_mode_ujian' => true,
            'exam_mode' => EventExamMode::Skb,
            'skb_question_count' => 3,
            'skb_correct_score' => 5,
            'skb_duration_minutes' => 60,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => EventSession::generateUniqueCode(),
            'skb_pin' => '5678',
            'status' => EventStatus::Active,
        ]);

        $participant = EventParticipant::query()->create([
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'name' => 'Peserta SKB',
            'nik' => '3201010101018888',
            'jabatan_label' => $jabatan->name,
            'jabatan_skb_id' => $jabatan->id,
        ]);

        $attempt = app(SkbExamService::class)->startAttempt($event, $participant);

        return [$user, $attempt];
    }

    protected function skbAnswerAt(SkbExamAttempt $attempt, int $sortOrder): SkbExamAnswer
    {
        return SkbExamAnswer::query()
            ->where('skb_exam_attempt_id', $attempt->id)
            ->where('sort_order', $sortOrder)
            ->with('question.options')
            ->firstOrFail();
    }

    /**
     * @return array{user: User, exam: Exam, attempt: ExamAttempt, firstOptionId: int}
     */
    protected function createSkdAttempt(int $expiresInMinutes, bool $modeUjian = false, SubjectCode $subject = SubjectCode::Twk): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Deadline',
            'slug' => 'simulasi-deadline',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        $event = Event::query()->create([
            'name' => 'Tryout Deadline Test',
            'exam_id' => $exam->id,
            'status' => EventStatus::Active,
            'created_by' => $admin->id,
            'is_mode_ujian' => $modeUjian,
            'exam_mode' => EventExamMode::Skd,
        ]);

        $session = EventSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Sesi 1',
            'code' => 'DLN123',
            'status' => EventStatus::Active,
        ]);

        $attempt = ExamAttempt::query()->create([
            'exam_id' => $exam->id,
            'event_id' => $event->id,
            'event_session_id' => $session->id,
            'user_id' => $user->id,
            'display_name' => 'Peserta Deadline',
            'started_at' => now()->subMinutes(5),
            'expires_at' => now()->addMinutes($expiresInMinutes)->startOfSecond(),
            'status' => ExamAttemptStatus::InProgress,
        ]);

        $subjectCode = $subject;
        $subject = Subject::query()->create([
            'code' => $subjectCode,
            'name' => strtoupper($subjectCode->value),
            'slug' => $subjectCode->value,
            'sort_order' => 1,
        ]);

        $material = Material::query()->create([
            'subject_id' => $subject->id,
            'slug' => $subjectCode->value.'-materi',
            'name' => 'Materi '.strtoupper($subjectCode->value),
            'sort_order' => 1,
        ]);

        $firstOptionId = null;

        foreach ([1, 2] as $sortOrder) {
            $question = Question::query()->create([
                'subject_id' => $subject->id,
                'material_id' => $material->id,
                'content' => "Soal nomor {$sortOrder}?",
                'explanation' => 'Pembahasan.',
                'difficulty' => 'easy',
                'is_active' => true,
            ]);

            $correct = QuestionOption::query()->create([
                'question_id' => $question->id,
                'label' => 'A',
                'content' => 'Jawaban benar',
                'is_correct' => true,
                'sort_order' => 1,
            ]);

            QuestionOption::query()->create([
                'question_id' => $question->id,
                'label' => 'B',
                'content' => 'Jawaban salah',
                'is_correct' => false,
                'sort_order' => 2,
            ]);

            ExamAnswer::query()->create([
                'exam_attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'sort_order' => $sortOrder,
            ]);

            if ($sortOrder === 1) {
                $firstOptionId = $correct->id;
            }
        }

        return [
            'user' => $user,
            'exam' => $exam,
            'attempt' => $attempt,
            'firstOptionId' => $firstOptionId,
        ];
    }
}
