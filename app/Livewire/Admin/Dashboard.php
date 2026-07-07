<?php

namespace App\Livewire\Admin;

use App\Models\Exam;
use App\Models\ExamAttempt;
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
            ],
        ]);
    }
}
