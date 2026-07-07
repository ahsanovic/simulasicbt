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
            ->set('rating', 5)
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('testimonials', [
            'user_id' => $user->id,
            'target_instansi' => 'Calon Auditor — Pemprov Jatim',
            'is_anonymous' => false,
            'rating' => 5,
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
            'rating' => 4,
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
            'rating' => 5,
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
            'rating' => 3,
        ]);

        $service->submit($user, [
            'target_instansi' => 'Calon Auditor Pemprov Jatim — Updated',
            'story' => 'Cerita kedua yang sudah diperbarui dengan konten yang lebih panjang dan detail.',
            'turning_point' => 'Skor naik signifikan.',
            'feature_tags' => [TestimonialFeatureTag::Duel1v1->value],
            'is_anonymous' => true,
            'rating' => 5,
        ]);

        $this->assertSame(1, XpReward::query()->where('user_id', $user->id)->count());
        $this->assertSame(
            GamificationService::TESTIMONIAL_XP_REWARD,
            app(GamificationService::class)->totalXp($user),
        );
    }

    public function test_submit_strips_html_from_testimonial_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Livewire::actingAs($user)
            ->test(Testimonials::class)
            ->set('targetInstansi', '<script>alert(1)</script>Calon Auditor')
            ->set('story', '<b>Platform</b> ini sangat membantu saya belajar secara konsisten setiap hari setelah pulang kerja.')
            ->set('turningPoint', '<img src=x onerror=alert(1)>Skor naik drastis')
            ->set('selectedTags', [TestimonialFeatureTag::AudioMode->value])
            ->set('rating', 5)
            ->call('submit')
            ->assertHasNoErrors();

        $testimonial = Testimonial::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertStringNotContainsString('<script>', $testimonial->target_instansi);
        $this->assertStringNotContainsString('<b>', $testimonial->story);
        $this->assertStringNotContainsString('<img', $testimonial->turning_point);
        $this->assertSame('Calon Auditor', $testimonial->target_instansi);
    }

    public function test_submit_rejects_invalid_feature_tags(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        Livewire::actingAs($user)
            ->test(Testimonials::class)
            ->set('targetInstansi', 'Calon Auditor Pemprov Jatim')
            ->set('story', 'Cerita panjang tentang perjalanan belajar saya yang sangat membantu sekali.')
            ->set('selectedTags', ['invalid_tag', TestimonialFeatureTag::AudioMode->value])
            ->set('rating', 5)
            ->call('submit')
            ->assertHasNoErrors();

        $testimonial = Testimonial::query()->where('user_id', $user->id)->firstOrFail();

        $this->assertSame([TestimonialFeatureTag::AudioMode->value], $testimonial->feature_tags);
    }

    public function test_reaction_counts_cannot_be_mass_assigned(): void
    {
        $user = User::factory()->create(['role' => UserRole::Peserta]);

        $testimonial = Testimonial::query()->create([
            'user_id' => $user->id,
            'target_instansi' => 'Calon Auditor',
            'story' => 'Cerita yang cukup panjang untuk lolos validasi minimal karakter.',
            'rating' => 5,
            'feature_tags' => [TestimonialFeatureTag::AudioMode->value],
            'is_anonymous' => false,
            'hearts_count' => 999,
            'fires_count' => 999,
        ]);

        $this->assertSame(0, $testimonial->fresh()->hearts_count);
        $this->assertSame(0, $testimonial->fresh()->fires_count);
    }
}
