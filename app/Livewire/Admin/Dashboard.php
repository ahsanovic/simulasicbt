<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Formation;
use App\Models\Question;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\TestimonialService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Dashboard Admin')]
class Dashboard extends Component
{
    public function render(TestimonialService $testimonialService)
    {
        $pesertaQuery = User::query()->where('role', UserRole::Peserta);

        $formationRecap = Formation::query()
            ->whereHas('users', fn ($query) => $query->where('role', UserRole::Peserta))
            ->withCount(['users as peserta_count' => fn ($query) => $query->where('role', UserRole::Peserta)])
            ->orderBy('group')
            ->orderBy('name')
            ->get()
            ->groupBy('group');

        return view('livewire.admin.dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'questions' => Question::query()->count(),
                'exams' => Exam::query()->count(),
                'attempts' => ExamAttempt::query()->whereNotNull('submitted_at')->count(),
                'testimonials' => Testimonial::query()->count(),
                'testimonial_avg_rating' => $testimonialService->averageRating(),
                'testimonial_rating_distribution' => $testimonialService->ratingDistribution(),
                'recent_testimonials' => Testimonial::query()
                    ->with('user')
                    ->whereNotNull('rating')
                    ->latest()
                    ->limit(5)
                    ->get(),
                'formation_recap' => [
                    'selected_count' => (clone $pesertaQuery)->whereNotNull('formation_id')->count(),
                    'unselected_count' => (clone $pesertaQuery)->whereNull('formation_id')->count(),
                    'by_group' => $formationRecap,
                    'max_count' => $formationRecap->flatten()->max('peserta_count') ?: 1,
                ],
            ],
        ]);
    }
}
