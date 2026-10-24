<?php

use App\Support\Money;
use App\Support\UnifiedHistoryEntry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Riwayat')] class extends Component {
    use WithPagination;

    public function getEntriesProperty(): LengthAwarePaginator
    {
        $user = Auth::user();

        $bookings = $user->bookings()->with('match')->get()
            ->filter(fn ($b) => $b->paid_at !== null)
            ->map(fn ($b) => new UnifiedHistoryEntry(
                date: $b->paid_at,
                title: __('Booking: :title', ['title' => $b->match->title]),
                amountLabel: Money::rupiah($b->total),
                statusLabel: $b->status,
                icon: 'ticket',
            ));

        $points = $user->pointsTransactions()->get()
            ->map(fn ($p) => new UnifiedHistoryEntry(
                date: $p->created_at,
                title: $p->description ?? __('Mutasi poin'),
                amountLabel: ($p->amount >= 0 ? '+' : '').$p->amount.' '.__('poin'),
                statusLabel: $p->type,
                icon: 'star',
            ));

        $memberships = $user->membershipPurchases()->get()
            ->filter(fn ($m) => $m->paid_at !== null)
            ->map(fn ($m) => new UnifiedHistoryEntry(
                date: $m->paid_at,
                title: __('Pembayaran Membership'),
                amountLabel: Money::rupiah($m->amount),
                statusLabel: $m->status,
                icon: 'identification',
            ));

        $redemptions = $user->rewardRedemptions()->with('rewardItem')->get()
            ->map(fn ($r) => new UnifiedHistoryEntry(
                date: $r->redeemed_at,
                title: __('Tukar Poin: :item', ['item' => $r->rewardItem->name]),
                amountLabel: '-'.$r->points_spent.' '.__('poin'),
                statusLabel: $r->status,
                icon: 'gift',
            ));

        $all = $bookings->concat($points)->concat($memberships)->concat($redemptions)
            ->sortByDesc('date')
            ->values();

        $perPage = 15;
        $page = $this->getPage();

        return new LengthAwarePaginator(
            $all->forPage($page, $perPage),
            $all->count(),
            $perPage,
            $page,
            ['path' => request()->url()],
        );
    }
}; ?>

<div class="flex flex-col gap-4">
    <x-akun-page-header :title="__('Riwayat')" />

    <ul class="flex flex-col gap-3">
        @forelse ($this->entries as $entry)
            <li class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
                <flux:icon name="{{ $entry->icon }}" class="size-5 shrink-0 text-zinc-400 dark:text-zinc-500" />
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ $entry->title }}</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $entry->date->timezone(config('app.display_timezone'))->translatedFormat('d M Y, H:i') }}</p>
                </div>
                <span class="shrink-0 text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ $entry->amountLabel }}</span>
            </li>
        @empty
            <li class="rounded-xl border border-zinc-200 bg-white p-8 text-center text-sm text-zinc-500 dark:border-navy-800 dark:bg-navy-900 dark:text-zinc-400">
                {{ __('Belum ada riwayat transaksi.') }}
            </li>
        @endforelse
    </ul>

    {{ $this->entries->links() }}
</div>
