<?php

namespace Tests\Feature\Peserta;

use App\Enums\EventStatus;
use App\Enums\ExamAttemptStatus;
use App\Services\ModeUjianRoom\DeviceToken;
use App\Support\ExamLockdown;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Peserta\Concerns\CreatesExamAttempts;
use Tests\TestCase;

/**
 * The browser exam room's JSON calls: "jawaban" (background save) and
 * "selesai" (submit / time up).
 */
class ModeUjianRoomEndpointsTest extends TestCase
{
    use CreatesExamAttempts;
    use RefreshDatabase;

    private function skd(): array
    {
        $ctx = $this->createSkdAttempt(expiresInMinutes: 60, modeUjian: true);
        $ctx['token'] = DeviceToken::issue($ctx['attempt']);
        $ctx['answer'] = $ctx['attempt']->answers()->orderBy('sort_order')->first();

        return $ctx;
    }

    private function body(array $ctx, array $extra = []): array
    {
        return [
            'kind' => 'skd',
            'attempt_id' => $ctx['attempt']->id,
            'device_token' => $ctx['token'],
            'answers' => [['id' => $ctx['answer']->id, 'option_id' => $ctx['firstOptionId'], 'is_marked' => false, 'version' => 3]],
            ...$extra,
        ];
    }

    public function test_answers_are_saved_and_the_remaining_time_is_returned(): void
    {
        $ctx = $this->skd();

        $this->actingAs($ctx['user'])
            ->postJson(route('peserta.mode-ujian.answers'), $this->body($ctx))
            ->assertOk()
            ->assertJson(['saved' => [$ctx['answer']->id], 'stale' => []])
            ->assertJsonPath('remaining', fn (int $seconds) => $seconds > 3500);

        $this->assertSame($ctx['firstOptionId'], $ctx['answer']->fresh()->selected_option_id);
    }

    public function test_another_users_attempt_is_not_found(): void
    {
        $ctx = $this->skd();
        [$intruder] = $this->startSkbAttempt();

        $this->actingAs($intruder)->postJson(route('peserta.mode-ujian.answers'), $this->body($ctx))->assertNotFound();
    }

    public function test_a_closed_attempt_and_an_old_device_get_409(): void
    {
        $ctx = $this->skd();
        $oldToken = $ctx['token'];
        $ctx['token'] = DeviceToken::issue($ctx['attempt']);

        $this->actingAs($ctx['user'])
            ->postJson(route('peserta.mode-ujian.answers'), $this->body($ctx, ['device_token' => $oldToken]))
            ->assertStatus(409)->assertJson(['error' => 'device']);

        $ctx['attempt']->update(['status' => ExamAttemptStatus::Submitted]);

        $this->actingAs($ctx['user'])
            ->postJson(route('peserta.mode-ujian.answers'), $this->body($ctx))
            ->assertStatus(409)->assertJson(['error' => 'closed']);
    }

    public function test_malformed_answers_get_422(): void
    {
        $ctx = $this->skd();

        $this->actingAs($ctx['user'])
            ->postJson(route('peserta.mode-ujian.answers'), $this->body($ctx, ['answers' => [['id' => 'x']]]))
            ->assertUnprocessable();
    }

    public function test_an_expired_login_gets_401_json_not_a_redirect(): void
    {
        $ctx = $this->skd();

        $this->postJson(route('peserta.mode-ujian.answers'), $this->body($ctx))->assertUnauthorized();
    }

    public function test_the_answers_route_is_rate_limited(): void
    {
        $this->assertContains('throttle:mode-ujian-answers', Route::getRoutes()->getByName('peserta.mode-ujian.answers')->gatherMiddleware());
    }

    public function test_finish_submits_and_returns_the_result_page(): void
    {
        $ctx = $this->skd();

        $this->actingAs($ctx['user'])
            ->postJson(route('peserta.mode-ujian.finish'), $this->body($ctx, ['reason' => 'manual']))
            ->assertOk()
            ->assertJson(['result_url' => route('peserta.mode-ujian.skd-result', $ctx['attempt'])]);

        $this->assertSame(ExamAttemptStatus::Submitted, $ctx['attempt']->fresh()->status);
    }

    public function test_time_up_finish_after_added_time_returns_the_new_remaining_time(): void
    {
        $ctx = $this->skd();

        $this->actingAs($ctx['user'])
            ->postJson(route('peserta.mode-ujian.finish'), $this->body($ctx, ['reason' => 'time_up']))
            ->assertOk()
            ->assertJsonPath('remaining', fn (int $seconds) => $seconds > 3500)
            ->assertJsonMissingPath('result_url');
    }

    public function test_a_running_attempt_can_still_save_and_finish_while_lockdown_is_on_and_the_event_was_closed(): void
    {
        $ctx = $this->skd();
        ExamLockdown::save(true, null, null);
        $ctx['attempt']->event()->update(['status' => EventStatus::Closed]);

        $this->actingAs($ctx['user'])
            ->postJson(route('peserta.mode-ujian.answers'), $this->body($ctx))
            ->assertOk()->assertJson(['saved' => [$ctx['answer']->id]]);

        $this->actingAs($ctx['user'])
            ->postJson(route('peserta.mode-ujian.finish'), $this->body($ctx, ['reason' => 'manual']))
            ->assertOk()->assertJsonStructure(['result_url']);
    }
}
