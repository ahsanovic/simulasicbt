<?php

namespace Tests\Feature\Peserta;

use App\Enums\TestimonialFeatureTag;
use App\Enums\TestimonialReactionType;
use App\Enums\UserRole;
use App\Livewire\Peserta\Testimonials;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\XpReward;
use App\Services\GamificationService;
use App\Services\TestimonialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TestimonialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_peserta_can_view_testimonials_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $this->actingAs($user)
            ->get(route('peserta.testimonials.index'))
            ->assertOk()
            ->assertSee('Wall of Love')
            ->assertSee('Cerita Pejuang CPNS');
    }

    public function test_peserta_can_submit_testimonial_and_receive_xp(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Livewire::actingAs($user)
            ->test(Testimonials::class)
            ->set('targetInstansi', 'Calon Auditor — Pemprov Jatim')
            ->set('story', 'Aplikasi ini sangat membantu saya belajar secara konsisten setiap hari setelah pulang kerja.')
            ->set('turningPoint', 'Skor Try Out naik dari 280 menjadi 410 dalam 2 bulan.')
            ->set('selectedTags', [TestimonialFeatureTag::AudioMode->value])
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('testimonials', [
            'user_id' => $user->id,
            'target_instansi' => 'Calon Auditor — Pemprov Jatim',
            'is_anonymous' => false,
        ]);

        $testimonial = Testimonial::query()->where('user_id', $user->id)->first();

        $this->assertDatabaseHas('xp_rewards', [
            'user_id' => $user->id,
            'source_type' => Testimonial::class,
            'source_id' => $testimonial->id,
            'amount' => GamificationService::TESTIMONIAL_XP_REWARD,
        ]);

        $this->assertSame(
            GamificationService::TESTIMONIAL_XP_REWARD,
            app(GamificationService::class)->totalXp($user),
        );
    }

    public function test_anonymous_testimonial_uses_pejuang_display_name(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Peserta,
            'name' => 'Budi Santoso',
        ]);

        $testimonial = app(TestimonialService::class)->submit($user, [
            'target_instansi' => 'Calon Auditor Pemprov Jatim',
            'story' => 'Cerita panjang tentang perjalanan belajar saya yang sangat membantu sekali.',
            'turning_point' => null,
            'feature_tags' => [TestimonialFeatureTag::Duel1v1->value],
            'is_anonymous' => true,
        ]);

        $this->assertSame(
            'Pejuang Pemprov Jatim',
            app(TestimonialService::class)->displayName($testimonial),
        );
    }

    public function test_user_can_toggle_reaction_on_testimonial(): void
    {
        $author = User::factory()->create(['role' => UserRole::Peserta]);
        $reactor = User::factory()->create(['role' => UserRole::Peserta]);

        $testimonial = app(TestimonialService::class)->submit($author, [
            'target_instansi' => 'Calon PNS Kemenkumham',
            'story' => 'Platform ini membantu saya fokus belajar meski sibuk bekerja setiap hari.',
            'turning_point' => null,
            'feature_tags' => [TestimonialFeatureTag::SimulasiCBT->value],
            'is_anonymous' => false,
        ]);

        app(TestimonialService::class)->toggleReaction(
            $reactor,
            $testimonial,
            TestimonialReactionType::Heart,
        );

        $testimonial->refresh();
        $this->assertSame(1, $testimonial->hearts_count);

        app(TestimonialService::class)->toggleReaction(
            $reactor,
            $testimonial,
            TestimonialReactionType::Heart,
        );

        $testimonial->refresh();
        $this->assertSame(0, $testimonial->hearts_count);
    }

    public function test_xp_reward_is_not_duplicated_on_testimonial_update(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $service = app(TestimonialService::class);

        $service->submit($user, [
            'target_instansi' => 'Calon Auditor Pemprov Jatim',
            'story' => 'Cerita pertama yang cukup panjang untuk memenuhi validasi minimal karakter.',
            'turning_point' => null,
            'feature_tags' => [TestimonialFeatureTag::AudioMode->value],
            'is_anonymous' => false,
        ]);

        $service->submit($user, [
            'target_instansi' => 'Calon Auditor Pemprov Jatim — Updated',
            'story' => 'Cerita kedua yang sudah diperbarui dengan konten yang lebih panjang dan detail.',
            'turning_point' => 'Skor naik signifikan.',
            'feature_tags' => [TestimonialFeatureTag::Duel1v1->value],
            'is_anonymous' => true,
        ]);

        $this->assertSame(1, XpReward::query()->where('user_id', $user->id)->count());
        $this->assertSame(
            GamificationService::TESTIMONIAL_XP_REWARD,
            app(GamificationService::class)->totalXp($user),
        );
    }
}
