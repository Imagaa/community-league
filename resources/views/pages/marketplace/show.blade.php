<x-layouts::public :title="$item->name">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:gap-6">
        @if ($item->image_path)
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white md:w-2/5 md:shrink-0 dark:border-navy-800 dark:bg-navy-900">
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->temporaryUrl($item->image_path, now()->addDay()) }}"
                     alt="{{ $item->name }}" class="h-auto w-full object-cover" />
            </div>
        @endif

        <div class="flex flex-1 flex-col gap-4">
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $item->name }}</h1>

                @if ($item->description)
                    <p class="mt-3 border-t border-zinc-100 pt-3 text-sm text-zinc-600 dark:border-navy-800 dark:text-zinc-300">{{ $item->description }}</p>
                @endif

                <div class="mt-3 flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-navy-800">
                    <div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Harga tukar') }}</p>
                        <p class="text-lg font-bold text-red-600 dark:text-orange-400">{{ $item->point_cost }} {{ __('poin') }}</p>
                    </div>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Poin Anda:') }} <strong class="text-zinc-900 dark:text-white">{{ auth()->user()->pointsBalance() }}</strong>
                    </p>
                </div>
            </section>

            <section class="rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
                <livewire:pages::marketplace.redeem-widget :item="$item" />
            </section>
        </div>
    </div>
</x-layouts::public>
