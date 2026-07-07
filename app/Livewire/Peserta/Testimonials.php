<?php

namespace App\Livewire\Peserta;

use App\Enums\TestimonialFeatureTag;
use App\Enums\TestimonialReactionType;
use App\Models\Testimonial;
use App\Services\GamificationService;
use App\Services\TestimonialService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'testimonials', 'showNav' => true])]
#[Title('Wall of Love')]
class Testimonials extends Component
{
    private const SUBMIT_RATE_LIMIT = 5;

    private const REACT_RATE_LIMIT = 30;

    public bool $showForm = false;

    public string $targetInstansi = '';

    public string $story = '';

    public string $turningPoint = '';

    /** @var list<string> */
    public array $selectedTags = [];

    public bool $isAnonymous = false;

    public int $rating = 0;

    public int $perPage = 12;

    public function mount(TestimonialService $testimonialService): void
    {
        $existing = $testimonialService->userTestimonial(auth()->user());

        if ($existing) {
            $this->targetInstansi = $existing->target_instansi;
            $this->story = $existing->story;
            $this->turningPoint = $existing->turning_point ?? '';
            $this->selectedTags = $existing->feature_tags ?? [];
            $this->isAnonymous = $existing->is_anonymous;
            $this->rating = (int) ($existing->rating ?? 0);
        }
    }

    public function openForm(): void
    {
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function toggleTag(string $tag): void
    {
        if (TestimonialFeatureTag::tryFrom($tag) === null) {
            return;
        }

        if (in_array($tag, $this->selectedTags, true)) {
            $this->selectedTags = array_values(array_filter(
                $this->selectedTags,
                fn (string $value) => $value !== $tag,
            ));
        } else {
            $this->selectedTags[] = $tag;
        }
    }

    public function submit(TestimonialService $testimonialService): void
    {
        $this->ensureWithinRateLimit('testimonial-submit', self::SUBMIT_RATE_LIMIT);

        $this->targetInstansi = sanitize_testimonial_text($this->targetInstansi);
        $this->story = sanitize_testimonial_text($this->story, multiline: true);
        $this->turningPoint = sanitize_testimonial_text($this->turningPoint, multiline: true);
        $this->selectedTags = array_values(array_filter(
            $this->selectedTags,
            fn (string $tag) => TestimonialFeatureTag::tryFrom($tag) !== null,
        ));

        $this->validate([
            'targetInstansi' => ['required', 'string', 'min:3', 'max:120'],
            'story' => ['required', 'string', 'min:20', 'max:2000'],
            'rating' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'turningPoint' => ['nullable', 'string', 'max:1000'],
            'selectedTags' => ['required', 'array', 'min:1', 'max:8'],
            'selectedTags.*' => ['string', Rule::enum(TestimonialFeatureTag::class)],
            'isAnonymous' => ['boolean'],
        ], [
            'targetInstansi.required' => 'Ceritakan formasi dan instansi target Anda.',
            'story.required' => 'Cerita pengalaman belajar wajib diisi.',
            'story.min' => 'Cerita minimal 20 karakter agar lebih bermakna.',
            'rating.required' => 'Berikan rating 1–5 bintang untuk pengalaman Anda.',
            'selectedTags.required' => 'Pilih minimal satu fitur andalan.',
        ]);

        $isEdit = $testimonialService->userTestimonial(auth()->user()) !== null;

        $testimonialService->submit(auth()->user(), [
            'target_instansi' => $this->targetInstansi,
            'story' => $this->story,
            'rating' => $this->rating,
            'turning_point' => $this->turningPoint,
            'feature_tags' => $this->selectedTags,
            'is_anonymous' => $this->isAnonymous,
        ]);

        $this->showForm = false;
        session()->flash(
            'success',
            $isEdit
                ? 'Testimoni berhasil diperbarui.'
                : 'Testimoni berhasil dikirim! +'.GamificationService::TESTIMONIAL_XP_REWARD.' XP telah ditambahkan.',
        );
    }

    public function react(int $testimonialId, string $type, TestimonialService $testimonialService): void
    {
        $this->ensureWithinRateLimit('testimonial-react', self::REACT_RATE_LIMIT, silent: true);

        $reactionType = TestimonialReactionType::tryFrom($type);

        if ($reactionType === null) {
            return;
        }

        if ($testimonialId < 1) {
            return;
        }

        $testimonial = Testimonial::query()->find($testimonialId);

        if ($testimonial === null) {
            return;
        }

        $testimonialService->toggleReaction(auth()->user(), $testimonial, $reactionType);
    }

    public function loadMore(TestimonialService $testimonialService): void
    {
        if ($this->perPage >= $testimonialService->featuredTestimonialsCount()) {
            return;
        }

        $this->perPage = min(
            $this->perPage + TestimonialService::PER_PAGE,
            TestimonialService::MAX_PER_PAGE,
        );
    }

    public function render(TestimonialService $testimonialService, GamificationService $gamificationService)
    {
        $this->perPage = max(1, min($this->perPage, TestimonialService::MAX_PER_PAGE));

        $testimonials = $testimonialService->featuredTestimonials($this->perPage);
        $hasMorePages = $testimonialService->featuredTestimonialsCount() > $testimonials->count();

        return view('livewire.peserta.testimonials', [
            'testimonials' => $testimonials,
            'hasMorePages' => $hasMorePages,
            'userTestimonial' => $testimonialService->userTestimonial(auth()->user()),
            'featureTagOptions' => TestimonialFeatureTag::cases(),
            'totalXp' => $gamificationService->totalXp(auth()->user()),
        ]);
    }

    private function ensureWithinRateLimit(string $action, int $maxAttempts, bool $silent = false): void
    {
        $key = $action.':'.auth()->id();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            if ($silent) {
                return;
            }

            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'story' => 'Terlalu banyak percobaan. Coba lagi dalam '.$seconds.' detik.',
            ]);
        }

        RateLimiter::hit($key, 60);
    }
}
