<?php

use App\Actions\Marketplace\RedeemReward;
use App\Exceptions\RedemptionException;
use App\Models\RewardItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    public RewardItem $item;

    public bool $redeemed = false;

    public bool $showConfirm = false;

    public function confirmRedeem(): void
    {
        $this->showConfirm = true;
    }

    public function redeem(RedeemReward $redeemReward): void
    {
        $this->showConfirm = false;
        $this->resetErrorBag();

        try {
            $redeemReward->handle(Auth::user(), $this->item);
        } catch (RedemptionException $e) {
            $this->addError('redeem', $e->getMessage());

            return;
        }

        $this->item->refresh();
        $this->redeemed = true;
    }
}; ?>

<div wire:key="redeem-widget-{{ $item->id }}">
    @error('redeem')
        <p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600 dark:bg-red-950 dark:text-red-400">{{ $message }}</p>
    @enderror

    @if ($redeemed)
        <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">
            {{ __('Berhasil ditukar! Penukaran Anda sedang diproses.') }}
        </p>
    @else
        @php
            $balance = Auth::user()->pointsBalance();
            $kurang = $item->point_cost - $balance;
            $tersedia = $item->isAvailable();
        @endphp

        @if (! $tersedia)
            <p class="mb-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Stok habis.') }}</p>
        @elseif ($kurang > 0)
            <p class="mb-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Butuh :n poin lagi.', ['n' => $kurang]) }}</p>
        @endif

        <button
            type="button"
            wire:click="confirmRedeem"
            wire:loading.attr="disabled"
            wire:target="redeem"
            @disabled(! $tersedia || $kurang > 0)
            class="w-full rounded-lg bg-red-600 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:bg-zinc-300 disabled:text-zinc-500 dark:bg-orange-600 dark:hover:bg-orange-700 dark:disabled:bg-navy-800 dark:disabled:text-zinc-500"
        >
            {{ __('Tukar') }}
        </button>

        <flux:modal wire:model="showConfirm" class="max-w-sm">
            <div class="space-y-4">
                <p class="text-sm text-zinc-700 dark:text-zinc-300">
                    {{ __('Tukar :cost poin untuk :name?', ['cost' => $item->point_cost, 'name' => $item->name]) }}
                </p>
                <div class="flex flex-col gap-2">
                    <flux:button variant="primary" wire:click="redeem" wire:loading.attr="disabled" wire:target="redeem">{{ __('Ya, Tukar') }}</flux:button>
                    <flux:button variant="outline" wire:click="$set('showConfirm', false)">{{ __('Batal') }}</flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
