<?php

use App\Models\RewardRedemption;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Riwayat Poin')] class extends Component {
    use WithPagination;

    public function getEarnedProperty()
    {
        return Auth::user()->pointsTransactions()
            ->where('amount', '>', 0)
            ->latest()
            ->paginate(15, pageName: 'earnedPage');
    }

    public function getRedemptionsProperty()
    {
        return Auth::user()->rewardRedemptions()
            ->with('rewardItem')
            ->latest('redeemed_at')
            ->paginate(15, pageName: 'redemptionsPage');
    }
}; ?>

@php
    // Kelas literal lengkap (bukan bg-{{ $color }}-100 hasil interpolasi) -- Tailwind cuma
    // mendeteksi nama class yang benar-benar tertulis utuh di source, class yang dirakit dari
    // potongan string dinamis akan ke-purge dari build produksi dan jadi tidak ada gaya sama sekali.
    $statusBadgeClass = [
        'pending' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-500/10 dark:text-zinc-400',
        'processing' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
        'fulfilled' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
        'rejected' => 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
    ];
@endphp

<div class="flex flex-col gap-4">
    <div class="flex items-center gap-3">
        <a href="{{ route('membership') }}" wire:navigate class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-navy-800 dark:text-zinc-300 dark:hover:bg-navy-700" aria-label="{{ __('Kembali ke Membership') }}">
            <flux:icon name="arrow-left" class="size-5" />
        </a>
        <h1 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Riwayat Poin') }}</h1>
    </div>

    <h2 class="text-sm font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Perolehan Poin') }}</h2>
    <div class="flex flex-col gap-3">
        @forelse ($this->earned as $transaction)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
                <div class="flex items-start justify-between gap-2">
                    <p class="min-w-0 text-sm text-zinc-700 dark:text-zinc-300">{{ $transaction->description ?? __('Poin masuk') }}</p>
                    <span class="shrink-0 text-sm font-semibold text-emerald-600 dark:text-emerald-400">+{{ $transaction->amount }} {{ __('poin') }}</span>
                </div>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $transaction->created_at->timezone(config('app.display_timezone'))->translatedFormat('d M Y, H:i') }} WIB
                </p>
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-white p-8 text-center text-sm text-zinc-500 dark:border-navy-800 dark:bg-navy-900 dark:text-zinc-400">
                {{ __('Belum ada poin yang diperoleh.') }}
            </div>
        @endforelse
    </div>
    {{ $this->earned->links() }}

    <h2 class="mt-2 text-sm font-semibold text-zinc-500 dark:text-zinc-400">{{ __('Penukaran Poin') }}</h2>
    <div class="flex flex-col gap-3">
        @forelse ($this->redemptions as $redemption)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="font-semibold text-zinc-900 dark:text-white">{{ $redemption->rewardItem->name }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $redemption->redeemed_at->timezone(config('app.display_timezone'))->translatedFormat('d M Y, H:i') }} WIB
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $statusBadgeClass[$redemption->status] ?? $statusBadgeClass['pending'] }}">
                        {{ \App\Models\RewardRedemption::STATUS_LABELS[$redemption->status] ?? $redemption->status }}
                    </span>
                </div>

                <p class="mt-2 text-sm font-semibold text-red-600 dark:text-orange-400">-{{ $redemption->points_spent }} {{ __('poin') }}</p>

                @if ($redemption->admin_note)
                    <p class="mt-2 rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-600 dark:bg-navy-800 dark:text-zinc-300">
                        <span class="font-semibold">{{ __('Catatan admin:') }}</span> {{ $redemption->admin_note }}
                    </p>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 bg-white p-8 text-center text-sm text-zinc-500 dark:border-navy-800 dark:bg-navy-900 dark:text-zinc-400">
                {{ __('Belum ada penukaran poin.') }}
            </div>
        @endforelse
    </div>

    {{ $this->redemptions->links() }}
</div>
