<x-layouts::public :title="__('Membership')">
    <div class="rounded-2xl bg-gradient-to-br from-red-600 to-red-800 p-6 text-white shadow-lg dark:from-navy-800 dark:to-navy-950">
        <div class="flex items-stretch gap-4">
            <div class="min-w-0 flex-1">
                <p class="text-xs uppercase tracking-wide text-red-100 dark:text-orange-300">{{ __('Community League Membership') }}</p>
                <p class="mt-2 text-xl font-bold">
                    {{ auth()->user()->name }}
                    <x-membership-badge class="ml-1 h-5 w-5" />
                </p>
                <p class="mt-1 font-mono text-sm text-red-100 dark:text-orange-300">{{ $membership->memberNumber() }}</p>
                <p class="mt-3 text-sm text-red-100 dark:text-white/70">
                    {{ __('Berlaku sampai') }} {{ $membership->expires_at->timezone(config('app.display_timezone'))->translatedFormat('d M Y') }}
                </p>
            </div>
            <a
                href="{{ route('membership.redemptions') }}"
                wire:navigate
                class="flex shrink-0 flex-col items-center justify-center rounded-xl border border-white/25 bg-white/10 px-5 py-4 text-center shadow-lg backdrop-blur-md transition hover:bg-white/20"
            >
                <p class="text-xs text-red-100 dark:text-white/70">{{ __('Poin terkumpul') }}</p>
                <p class="text-2xl font-bold text-white">{{ $pointsBalance }}</p>
            </a>
        </div>
    </div>

    @if ($membership->card_printed_at && ! $membership->cardActive())
        <a href="{{ route('membership.card.activate') }}" class="mt-3 flex items-center gap-3 rounded-xl border border-orange-300 bg-orange-50 px-4 py-3 text-sm text-orange-900 dark:border-orange-800 dark:bg-orange-950 dark:text-orange-200">
            <flux:icon name="credit-card" class="size-5 shrink-0" />
            <span class="flex-1"><strong>{{ __('Aktifkan kartu fisikmu') }}</strong> · {{ __('scan QR kartu atau tap NFC') }}</span>
            <flux:icon name="chevron-right" class="size-4 shrink-0" />
        </a>
    @endif

    <x-member-qr class="mt-3" />

    {{-- PNG kartu (sisi depan, tanpa QR) untuk disimpan di galeri. --}}
    <a
        href="{{ route('membership.card', ['download' => 1]) }}"
        class="mt-3 flex items-center justify-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-3 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-50 dark:border-navy-800 dark:bg-navy-900 dark:text-white dark:hover:bg-navy-800"
    >
        <flux:icon name="arrow-down-tray" class="size-5 text-red-600 dark:text-orange-500" />
        {{ __('Download kartu member') }}
    </a>

    <details class="group mt-4 rounded-2xl border border-zinc-200 bg-white dark:border-navy-800 dark:bg-navy-900">
        <summary class="flex cursor-pointer list-none items-center justify-between p-4 text-sm font-semibold text-zinc-900 dark:text-white">
            {{ __('Manfaat yang Anda terima sebagai membership') }}
            <flux:icon name="chevron-down" class="size-4 shrink-0 text-zinc-400 transition-transform group-open:rotate-180 dark:text-zinc-500" />
        </summary>
        <ul class="flex flex-col gap-3 border-t border-zinc-100 p-4 dark:border-navy-800">
            @foreach ($benefits as $benefit)
                <li class="flex items-start gap-3">
                    <flux:icon :name="$benefit->icon" class="mt-1 size-5 shrink-0 text-red-600 dark:text-orange-500" />
                    <span>{{ $benefit->text }}</span>
                </li>
            @endforeach
        </ul>
    </details>

    <div id="marketplace" class="mt-8 scroll-mt-4">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Marketplace Poin') }}</h2>
        <div class="mt-3">
            <x-marketplace-grid :items="$items" />
        </div>
    </div>
</x-layouts::public>
