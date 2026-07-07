<?php

namespace App\Livewire\Admin\Testimonials;

use App\Models\Testimonial;
use App\Services\TestimonialService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Hasil Testimoni')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $viewingId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function viewDetail(int $id): void
    {
        $this->viewingId = $id;
    }

    public function closeDetail(): void
    {
        $this->viewingId = null;
    }

    public function render(TestimonialService $testimonialService)
    {
        $testimonials = Testimonial::query()
            ->with(['user.instansi'])
            ->when($this->search, function (Builder $query) {
                $search = $this->search;

                $query->where(function (Builder $inner) use ($search) {
                    $inner->where('target_instansi', 'like', "%{$search}%")
                        ->orWhere('story', 'like', "%{$search}%")
                        ->orWhere('turning_point', 'like', "%{$search}%")
                        ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByRaw('(hearts_count + fires_count) DESC')
            ->orderByDesc('created_at')
            ->paginate(15);

        $allTestimonials = Testimonial::query()->get(['hearts_count', 'fires_count', 'is_anonymous', 'turning_point']);

        $stats = [
            'total' => $allTestimonials->count(),
            'reactions' => $allTestimonials->sum(fn (Testimonial $item) => $item->reactionsScore()),
            'anonymous' => $allTestimonials->where('is_anonymous', true)->count(),
            'with_turning_point' => $allTestimonials->filter(fn (Testimonial $item) => filled($item->turning_point))->count(),
        ];

        $viewingTestimonial = $this->viewingId
            ? Testimonial::query()->with(['user.instansi'])->find($this->viewingId)
            : null;

        return view('livewire.admin.testimonials.index', [
            'testimonials' => $testimonials,
            'stats' => $stats,
            'viewingTestimonial' => $viewingTestimonial,
            'testimonialService' => $testimonialService,
        ]);
    }
}
