<?php

namespace Tests\Unit;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamAttemptType;
use App\Enums\ExamStatus;
use App\Enums\HelpItem;
use App\Enums\UserRole;
use App\Models\CoinTransaction;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Models\UserHelpItem;
use App\Services\CoinService;
use App\Services\HelpItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CoinAndHelpItemServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_awards_coins_when_attempt_passes_threshold(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $attempt = $this->createAttempt($user, [
            'score_twk' => 70,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 325,
        ]);

        $transaction = app(CoinService::class)->awardExamAttemptCoins($attempt, $user);

        $this->assertNotNull($transaction);
        $this->assertSame(CoinService::EXAM_PASS_COIN_REWARD, $transaction->amount);
        $this->assertSame(CoinService::EXAM_PASS_COIN_REWARD, app(CoinService::class)->balance($user));
    }

    public function test_awards_fail_coins_when_attempt_does_not_pass(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);
        $attempt = $this->createAttempt($user, [
            'score_twk' => 50,
            'score_tiu' => 85,
            'score_tkp' => 170,
            'total_score' => 305,
        ]);

        $transaction = app(CoinService::class)->awardExamAttemptCoins($attempt, $user);

        $this->assertNotNull($transaction);
        $this->assertSame(CoinService::EXAM_FAIL_COIN_REWARD, $transaction->amount);
    }

    public function test_does_not_award_coins_for_duel_or_remedial_attempts(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $duelAttempt = $this->createAttempt($user, ['duel_session_id' => 1]);
        $remedialAttempt = $this->createAttempt($user, ['attempt_type' => ExamAttemptType::Remedial]);

        $this->assertNull(app(CoinService::class)->awardExamAttemptCoins($duelAttempt, $user));
        $this->assertNull(app(CoinService::class)->awardExamAttemptCoins($remedialAttempt, $user));
        $this->assertSame(0, CoinTransaction::query()->count());
    }

    public function test_purchase_adds_help_item_and_spends_coins(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        app(CoinService::class)->award($user, 'test_reward', 1, 500, 'Bonus uji coba');

        $inventory = app(HelpItemService::class)->purchase($user, HelpItem::SkipTracker);

        $this->assertSame(1, $inventory->quantity);
        $this->assertSame(300, app(CoinService::class)->balance($user));
        $this->assertDatabaseHas('user_help_items', [
            'user_id' => $user->id,
            'item' => HelpItem::SkipTracker->value,
            'quantity' => 1,
        ]);
    }

    public function test_purchase_fails_when_balance_is_insufficient(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->expectException(ValidationException::class);

        app(HelpItemService::class)->purchase($user, HelpItem::FiftyFifty);
    }

    public function test_consume_reduces_inventory_quantity(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        UserHelpItem::query()->create([
            'user_id' => $user->id,
            'item' => HelpItem::SkipTracker->value,
            'quantity' => 2,
        ]);

        app(HelpItemService::class)->consume($user, HelpItem::SkipTracker);

        $this->assertSame(1, app(HelpItemService::class)->quantity($user, HelpItem::SkipTracker));
    }

    /** @param array<string, mixed> $overrides */
    private function createAttempt(User $user, array $overrides = []): ExamAttempt
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $exam = Exam::query()->create([
            'title' => 'Simulasi Test',
            'slug' => 'simulasi-test',
            'duration_minutes' => 100,
            'status' => ExamStatus::Published,
            'settings' => ['difficulty' => 'all'],
            'created_by' => $admin->id,
        ]);

        return ExamAttempt::query()->create(array_merge([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'expires_at' => now()->addHour(),
            'status' => ExamAttemptStatus::Submitted,
            'score_twk' => 0,
            'score_tiu' => 0,
            'score_tkp' => 0,
            'total_score' => 0,
        ], $overrides));
    }
}
