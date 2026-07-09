<?php

namespace App\Services;

use App\Enums\HelpItem;
use App\Enums\SubjectCode;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Models\UserHelpItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HelpItemService
{
    public function __construct(
        private readonly CoinService $coinService,
    ) {}

    /** @return array<string, int> */
    public function inventory(User $user): array
    {
        return UserHelpItem::query()
            ->where('user_id', $user->id)
            ->where('quantity', '>', 0)
            ->pluck('quantity', 'item')
            ->map(fn ($quantity) => (int) $quantity)
            ->all();
    }

    public function quantity(User $user, HelpItem $item): int
    {
        return (int) UserHelpItem::query()
            ->where('user_id', $user->id)
            ->where('item', $item->value)
            ->value('quantity');
    }

    public function purchase(User $user, HelpItem $item): UserHelpItem
    {
        return DB::transaction(function () use ($user, $item) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $inventory = UserHelpItem::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'item' => $item->value,
                ],
                [
                    'quantity' => 0,
                ],
            );

            $this->coinService->spend(
                $user,
                $item->price(),
                'Beli '.$item->label(),
                'help_item_purchase',
                $inventory->id * 1000 + $inventory->quantity + 1,
            );

            $inventory->increment('quantity');

            return $inventory->fresh();
        });
    }

    public function consume(User $user, HelpItem $item): void
    {
        DB::transaction(function () use ($user, $item) {
            $inventory = UserHelpItem::query()
                ->where('user_id', $user->id)
                ->where('item', $item->value)
                ->lockForUpdate()
                ->first();

            if ($inventory === null || $inventory->quantity < 1) {
                throw ValidationException::withMessages([
                    'item' => 'Stok '.$item->label().' habis.',
                ]);
            }

            $inventory->decrement('quantity');
        });
    }

    /**
     * @return list<int>
     */
    public function eliminateWrongOptions(ExamAttempt $attempt, Question $question): array
    {
        $wrongOptions = $question->options
            ->where('is_correct', false)
            ->sortBy('id')
            ->values();

        if ($wrongOptions->count() < 2) {
            throw ValidationException::withMessages([
                'item' => 'Soal ini tidak memiliki cukup pilihan salah untuk 50:50.',
            ]);
        }

        $hash = crc32($attempt->id.'-'.$question->id);
        $firstIndex = $hash % $wrongOptions->count();
        $secondIndex = intdiv($hash, $wrongOptions->count()) % $wrongOptions->count();

        if ($secondIndex === $firstIndex) {
            $secondIndex = ($secondIndex + 1) % $wrongOptions->count();
        }

        return [
            (int) $wrongOptions[$firstIndex]->id,
            (int) $wrongOptions[$secondIndex]->id,
        ];
    }

    public function canUseFiftyFifty(Question $question): bool
    {
        $subject = $question->subject?->code;

        if (! $subject instanceof SubjectCode) {
            return false;
        }

        return in_array($subject, [SubjectCode::Twk, SubjectCode::Tiu], true);
    }

    /** @return array{skip_tracker_active: bool, fifty_fifty: array<string, list<int>>} */
    public function defaultHelpItemsState(): array
    {
        return [
            'skip_tracker_active' => false,
            'fifty_fifty' => [],
        ];
    }
}
