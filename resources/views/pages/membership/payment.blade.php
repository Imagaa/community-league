<x-layouts::public :title="__('Pembayaran Membership')">
    <div class="flex flex-col gap-4">
        <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Selesaikan Pembayaran Membership') }}</h1>

        <section class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
            <x-sandbox-ribbon />
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Kode pembelian') }}</p>
            <p class="text-lg font-bold text-zinc-900 dark:text-white">{{ $purchase->purchase_code }}</p>
            <p class="mt-3 font-bold text-red-600 dark:text-orange-400">{{ __('Total:') }} {{ \App\Support\Money::rupiah($purchase->amount) }}</p>
        </section>

        <a href="{{ $purchase->sumopod_payment_link_url }}" target="_blank"
           class="block rounded-lg bg-red-600 py-2.5 text-center text-sm font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700">
            {{ __('Bayar Sekarang') }}
        </a>

        @if (config('services.sumopod.sandbox_bypass'))
            <form method="POST" action="{{ route('membership.payment.bypass', $purchase) }}">
                @csrf
                <button type="submit" class="block w-full rounded-lg border border-amber-300 bg-amber-50 py-2.5 text-center text-sm font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300">
                    {{ __('Saya Sudah Bayar (Sandbox)') }}
                </button>
            </form>
        @endif
    </div>
</x-layouts::public>
