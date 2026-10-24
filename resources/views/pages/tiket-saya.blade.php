<x-layouts::public :title="__('Tiket Saya')">
    <div class="flex flex-col gap-4">
        <x-akun-page-header :title="__('Tiket Saya')" />

        @forelse ($bookings as $booking)
            <a href="{{ route('booking.show', $booking) }}" wire:navigate
               class="flex flex-col gap-1 rounded-2xl border border-zinc-200 bg-white p-4 hover:border-red-300 dark:border-navy-800 dark:bg-navy-900 dark:hover:border-orange-700">
                <div class="flex items-center justify-between">
                    <span class="font-semibold text-zinc-900 dark:text-white">{{ $booking->booking_code }}</span>
                    <flux:badge :color="match ($booking->status) {
                        'paid' => 'emerald',
                        'pending_payment' => 'amber',
                        default => 'zinc',
                    }">
                        {{ match ($booking->status) {
                            'paid' => __('Lunas'),
                            'pending_payment' => __('Menunggu Pembayaran'),
                            'expired' => __('Kedaluwarsa'),
                            'cancelled' => __('Dibatalkan'),
                            default => $booking->status,
                        } }}
                    </flux:badge>
                </div>
                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $booking->match->title }} — {{ $booking->match->venue->name }}</p>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $booking->quantity }} {{ __('slot') }} · {{ \App\Support\Money::rupiah($booking->paidAmount()) }}</p>
            </a>
        @empty
            <p class="text-center text-zinc-500 dark:text-zinc-400">{{ __('Belum ada tiket.') }}</p>
        @endforelse
    </div>
</x-layouts::public>
