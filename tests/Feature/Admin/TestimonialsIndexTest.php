<?php

namespace Tests\Feature\Admin;

use App\Enums\TestimonialFeatureTag;
use App\Enums\UserRole;
use App\Livewire\Admin\Testimonials\Index;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\TestimonialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TestimonialsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_testimonials_page(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.testimonials.index'))
            ->assertOk()
            ->assertSee('Hasil Testimoni');
    }

    public function test_testimonials_are_sorted_by_newest_submission(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $service = app(TestimonialService::class);

        $olderUser = User::factory()->create(['role' => UserRole::Peserta, 'name' => 'Peserta Lama']);
        $newerUser = User::factory()->create(['role' => UserRole::Peserta, 'name' => 'Peserta Baru']);

        $service->submit($olderUser, [
            'target_instansi' => 'Calon Auditor Pemprov Jatim',
            'story' => 'Cerita lama yang cukup panjang untuk memenuhi validasi minimal karakter.',
            'turning_point' => null,
            'feature_tags' => [TestimonialFeatureTag::AudioMode->value],
            'is_anonymous' => false,
            'rating' => 3,
        ]);

        Testimonial::query()
            ->where('user_id', $olderUser->id)
            ->update(['created_at' => now()->subDay()]);

        $service->submit($newerUser, [
            'target_instansi' => 'Calon PNS Kemenkumham',
            'story' => 'Cerita baru yang cukup panjang untuk memenuhi validasi minimal karakter.',
            'turning_point' => null,
            'feature_tags' => [TestimonialFeatureTag::SimulasiCBT->value],
            'is_anonymous' => false,
            'rating' => 5,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->assertSeeInOrder(['Peserta Baru', 'Peserta Lama']);
    }

    public function test_testimonials_can_be_filtered_by_rating(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $service = app(TestimonialService::class);

        $fiveStarUser = User::factory()->create(['role' => UserRole::Peserta, 'name' => 'Rating Lima']);
        $threeStarUser = User::factory()->create(['role' => UserRole::Peserta, 'name' => 'Rating Tiga']);

        $service->submit($fiveStarUser, [
            'target_instansi' => 'Calon Auditor Pemprov Jatim',
            'story' => 'Cerita rating lima yang cukup panjang untuk memenuhi validasi minimal karakter.',
            'turning_point' => null,
            'feature_tags' => [TestimonialFeatureTag::AudioMode->value],
            'is_anonymous' => false,
            'rating' => 5,
        ]);

        $service->submit($threeStarUser, [
            'target_instansi' => 'Calon PNS Kemenkumham',
            'story' => 'Cerita rating tiga yang cukup panjang untuk memenuhi validasi minimal karakter.',
            'turning_point' => null,
            'feature_tags' => [TestimonialFeatureTag::SimulasiCBT->value],
            'is_anonymous' => false,
            'rating' => 3,
        ]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('ratingFilter', '5')
            ->assertSee('Rating Lima')
            ->assertDontSee('Rating Tiga');
    }

    public function test_reset_filters_clears_search_and_rating_filter(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->set('search', 'cari sesuatu')
            ->set('ratingFilter', '5')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('ratingFilter', '');
    }
}
