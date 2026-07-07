<?php

namespace App\Services;

use App\Enums\TestimonialFeatureTag;
use App\Enums\TestimonialReactionType;
use App\Models\Testimonial;
use App\Models\TestimonialReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestimonialService
{
    public const PER_PAGE = 12;

    public const MAX_PER_PAGE = 60;

    public function __construct(
        private readonly GamificationService $gamificationService,
    ) {}

    /** @return Collection<int, Testimonial> */
    public function featuredTestimonials(int $limit = self::PER_PAGE): Collection
    {
        $limit = max(1, min($limit, self::MAX_PER_PAGE));

        return Testimonial::query()
            ->with(['user.instansi', 'reactions' => fn ($query) => $query->where('user_id', auth()->id())])
            ->orderByRaw('(hearts_count + fires_count) DESC')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function featuredTestimonialsCount(): int
    {
        return Testimonial::query()->count();
    }

    public function averageRating(): ?float
    {
        $average = Testimonial::query()
            ->whereNotNull('rating')
            ->avg('rating');

        return $average !== null ? round((float) $average, 1) : null;
    }

    /** @return array<int, int> */
    public function ratingDistribution(): array
    {
        $counts = Testimonial::query()
            ->whereNotNull('rating')
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->all();

        return collect(range(5, 1))
            ->mapWithKeys(fn (int $star) => [$star => (int) ($counts[$star] ?? 0)])
            ->all();
    }

    public function userTestimonial(User $user): ?Testimonial
    {
        return Testimonial::query()
            ->where('user_id', $user->id)
            ->first();
    }

    public function submit(User $user, array $data): Testimonial
    {
        return DB::transaction(function () use ($user, $data) {
            $targetInstansi = sanitize_testimonial_text($data['target_instansi'] ?? '');
            $story = sanitize_testimonial_text($data['story'] ?? '', multiline: true);
            $turningPoint = filled($data['turning_point'] ?? null)
                ? sanitize_testimonial_text($data['turning_point'], multiline: true)
                : null;
            $rating = max(1, min(5, (int) ($data['rating'] ?? 0)));
            $featureTags = $this->normalizeFeatureTags($data['feature_tags'] ?? []);

            if ($targetInstansi === '' || $story === '' || $featureTags === []) {
                throw new \InvalidArgumentException('Data testimoni tidak valid.');
            }

            $testimonial = Testimonial::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'target_instansi' => $targetInstansi,
                    'story' => $story,
                    'rating' => $rating,
                    'turning_point' => $turningPoint !== '' ? $turningPoint : null,
                    'feature_tags' => $featureTags,
                    'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
                ],
            );

            if ($testimonial->wasRecentlyCreated) {
                $this->gamificationService->awardXp(
                    $user,
                    Testimonial::class,
                    $testimonial->id,
                    GamificationService::TESTIMONIAL_XP_REWARD,
                );
            }

            return $testimonial->fresh(['user.instansi']);
        });
    }

    public function toggleReaction(User $user, Testimonial $testimonial, TestimonialReactionType $type): void
    {
        if ($testimonial->user_id === $user->id) {
            return;
        }

        DB::transaction(function () use ($user, $testimonial, $type) {
            $existing = TestimonialReaction::query()
                ->where('testimonial_id', $testimonial->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existing?->type === $type) {
                $existing->delete();
                $this->decrementReactionCount($testimonial, $type);

                return;
            }

            if ($existing) {
                $this->decrementReactionCount($testimonial, $existing->type);
                $existing->update(['type' => $type]);
            } else {
                TestimonialReaction::query()->create([
                    'testimonial_id' => $testimonial->id,
                    'user_id' => $user->id,
                    'type' => $type,
                ]);
            }

            $this->incrementReactionCount($testimonial, $type);
        });
    }

    public function displayName(Testimonial $testimonial): string
    {
        if (! $testimonial->is_anonymous) {
            return $testimonial->user->name;
        }

        return $this->anonymousName($testimonial);
    }

    public function displaySubtitle(Testimonial $testimonial): string
    {
        return $testimonial->target_instansi;
    }

    public function avatarInitials(Testimonial $testimonial): string
    {
        if ($testimonial->is_anonymous) {
            return 'PK';
        }

        return $testimonial->user->initials();
    }

    public function userReaction(Testimonial $testimonial, User $user): ?TestimonialReactionType
    {
        $reaction = $testimonial->reactions
            ->firstWhere('user_id', $user->id);

        return $reaction?->type;
    }

    /** @param list<string> $tags */
    private function normalizeFeatureTags(array $tags): array
    {
        $allowed = collect(TestimonialFeatureTag::cases())
            ->map(fn (TestimonialFeatureTag $tag) => $tag->value)
            ->all();

        return collect($tags)
            ->filter(fn ($tag) => in_array($tag, $allowed, true))
            ->unique()
            ->values()
            ->all();
    }

    private function anonymousName(Testimonial $testimonial): string
    {
        $target = Str::lower($testimonial->target_instansi);

        if (str_contains($target, 'jawa timur')) {
            return 'Pejuang CPNS Jatim';
        }

        if (preg_match('/pemprov\s+([a-z\s]+)/i', $testimonial->target_instansi, $matches)) {
            return 'Pejuang Pemprov '.Str::title(Str::limit(trim($matches[1]), 40, ''));
        }

        // if ($testimonial->user->instansi) {
        //     return 'Pejuang '.$testimonial->user->instansi->nama;
        // }

        return 'Pejuang CPNS';
    }

    private function incrementReactionCount(Testimonial $testimonial, TestimonialReactionType $type): void
    {
        $column = $type === TestimonialReactionType::Heart ? 'hearts_count' : 'fires_count';
        $testimonial->increment($column);
    }

    private function decrementReactionCount(Testimonial $testimonial, TestimonialReactionType $type): void
    {
        $column = $type === TestimonialReactionType::Heart ? 'hearts_count' : 'fires_count';

        if ($testimonial->{$column} > 0) {
            $testimonial->decrement($column);
        }
    }
}
