<?php

namespace App\Livewire\Peserta;

use App\Enums\HelpItem;
use App\Services\CoinService;
use App\Services\ExamService;
use App\Services\HelpItemService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.peserta', ['activeNav' => 'shop', 'showNav' => true])]
#[Title('Toko Bantuan')]
class Shop extends Component
{
    public function purchase(string $item): void
    {
        try {
            $helpItem = HelpItem::from($item);
            app(HelpItemService::class)->purchase(auth()->user(), $helpItem);
            session()->flash('success', $helpItem->label().' berhasil dibeli.');
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            session()->flash('error', $message ?? 'Pembelian gagal.');
        }
    }

    public function render(CoinService $coinService, HelpItemService $helpItemService, ExamService $examService)
    {
        return view('livewire.peserta.shop', [
            'balance' => $coinService->balance(auth()->user()),
            'items' => HelpItem::cases(),
            'inventory' => $helpItemService->inventory(auth()->user()),
            'activeAttempt' => $examService->findActiveFullAttempt(auth()->user()),
        ]);
    }
}
