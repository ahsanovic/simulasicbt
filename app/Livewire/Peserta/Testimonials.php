<?php

namespace App\Livewire\Peserta;

use App\Enums\TestimonialFeatureTag;
use App\Enums\TestimonialReactionType;
use App\Models\Testimonial;
use App\Services\GamificationService;
use App\Services\TestimonialService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'testimonials', 'showNav' => true])]
#[Title('Wall of Love')]
class Testimonials extends Component
{
    public bool $showForm = false;

    public string $targetInstansi = '';

    public string $story = '';

    public string $turningPoint = '';

    /** @var list<string> */
    public array $selectedTags = [];

    public bool $isAnonymous = false;

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
        $this->validate([
            'targetInstansi' => ['required', 'string', 'min:3', 'max:120'],
            'story' => ['required', 'string', 'min:20', 'max:2000'],
            'turningPoint' => ['nullable', 'string', 'max:1000'],
            'selectedTags' => ['required', 'array', 'min:1'],
            'selectedTags.*' => ['string'],
            'isAnonymous' => ['boolean'],
        ], [
            'targetInstansi.required' => 'Ceritakan formasi dan instansi target Anda.',
            'story.required' => 'Cerita pengalaman belajar wajib diisi.',
            'story.min' => 'Cerita minimal 20 karakter agar lebih bermakna.',
            'selectedTags.required' => 'Pilih minimal satu fitur andalan.',
        ]);

        $testimonialService->submit(auth()->user(), [
            'target_instansi' => $this->targetInstansi,
            'story' => $this->story,
            'turning_point' => $this->turningPoint,
            'feature_tags' => $this->selectedTags,
            'is_anonymous' => $this->isAnonymous,
        ]);

        $this->showForm = false;
        session()->flash('success', 'Testimoni berhasil dikirim! +'.GamificationService::TESTIMONIAL_XP_REWARD.' XP telah ditambahkan.');
    }

    public function react(int $testimonialId, string $type, TestimonialService $testimonialService): void
    {
        $reactionType = TestimonialReactionType::tryFrom($type);

        if ($reactionType === null) {
            return;
        }

        $testimonial = Testimonial::query()->findOrFail($testimonialId);
        $testimonialService->toggleReaction(auth()->user(), $testimonial, $reactionType);
    }

    public function loadMore(TestimonialService $testimonialService): void
    {
        if ($this->perPage >= $testimonialService->featuredTestimonialsCount()) {
            return;
        }

        $this->perPage += TestimonialService::PER_PAGE;
    }

    public function render(TestimonialService $testimonialService, GamificationService $gamificationService)
    {
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
}
