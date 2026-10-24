<x-layouts::public :title="__('Marketplace Poin')">
    <h1 class="font-display text-2xl font-semibold tracking-wide text-zinc-900 lg:text-3xl dark:text-white">{{ __('Marketplace Poin') }}</h1>
    <p class="mt-2 text-zinc-600 dark:text-zinc-300">{{ __('Poin Anda:') }} <strong>{{ $pointsBalance }}</strong></p>

    <div class="mt-6">
        <x-marketplace-grid :items="$items" />
    </div>
</x-layouts::public>
