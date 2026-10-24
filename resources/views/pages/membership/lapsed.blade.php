<x-layouts::public :title="__('Membership')">
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-900 dark:bg-amber-950">
        <h1 class="text-xl font-bold text-amber-900 dark:text-amber-300">{{ __('Perpanjang durasi manfaatmu') }}</h1>
        <p class="mt-2 text-sm text-amber-800 dark:text-amber-400">
            {{ __('Membership Anda sudah berakhir. Jika tidak diperpanjang sebelum') }}
            {{ $forfeitAt->timezone(config('app.display_timezone'))->translatedFormat('d M Y') }},
            {{ __('Anda akan kehilangan seluruh poin.') }}
        </p>
        <p class="mt-2 text-sm text-amber-800 dark:text-amber-400">
            {{ __('Poin Anda saat ini:') }} <strong>{{ $pointsBalance }}</strong>
        </p>
    </div>

    <x-member-qr class="mt-4" />

    <a href="{{ route('membership.redemptions') }}" wire:navigate class="mt-3 inline-block text-sm font-semibold text-red-600 underline dark:text-orange-400">
        {{ __('Riwayat penukaran') }}
    </a>

    <div class="mt-4">
        @unless (auth()->user()->hasVerifiedEmail())
            <div class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                <flux:icon name="exclamation-triangle" class="size-5 shrink-0" />
                <span class="flex-1">{{ __('Verifikasi email Anda dulu sebelum memperpanjang.') }}</span>
                <flux:link :href="route('verification.notice')" wire:navigate class="shrink-0 font-medium">
                    {{ __('Verifikasi sekarang') }}
                </flux:link>
            </div>
        @else
            <form method="POST" action="{{ route('membership.purchase') }}">
                @csrf
                <flux:button type="submit">{{ __('Perpanjang Sekarang') }}</flux:button>
            </form>
        @endunless
    </div>
</x-layouts::public>
