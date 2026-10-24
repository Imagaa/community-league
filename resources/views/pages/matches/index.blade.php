<x-layouts::public :title="__('Katalog Match')">
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <h1 class="font-display text-2xl font-semibold tracking-wide text-zinc-900 lg:text-3xl dark:text-white">{{ __('Katalog Match') }}</h1>

            <details class="relative">
                <summary class="flex size-9 cursor-pointer list-none items-center justify-center rounded-full border border-zinc-200 bg-white text-zinc-600 shadow-sm dark:border-navy-800 dark:bg-navy-900 dark:text-zinc-300">
                    <flux:icon name="funnel" class="size-5" />
                </summary>

                <form method="GET" class="absolute right-0 z-10 mt-2 flex w-72 flex-col gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-lg dark:border-navy-800 dark:bg-navy-900">
                    <input type="text" name="q" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="{{ __('Cari match atau venue') }}"
                           class="rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-navy-700 dark:bg-navy-950 dark:text-white" />

                    <x-filter-select name="city" :options="$cities->pluck('name', 'id')" :selected="request('city')" placeholder="{{ __('Semua Kota') }}" />

                    <x-filter-select name="type" :options="['minisoccer' => 'Mini Soccer', 'football' => 'Sepak Bola']" :selected="request('type')" placeholder="{{ __('Semua Jenis') }}" />

                    <x-filter-select name="venue" :options="$venues->pluck('name', 'id')" :selected="request('venue')" placeholder="{{ __('Semua Venue') }}" />

                    <div class="flex gap-2">
                        <input type="date" name="date_from" value="{{ is_string(request('date_from')) ? request('date_from') : '' }}"
                               class="w-1/2 rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-navy-700 dark:bg-navy-950 dark:text-white" />
                        <input type="date" name="date_to" value="{{ is_string(request('date_to')) ? request('date_to') : '' }}"
                               class="w-1/2 rounded-lg border border-zinc-300 px-3 py-2 text-sm dark:border-navy-700 dark:bg-navy-950 dark:text-white" />
                    </div>

                    <flux:checkbox name="available_only" value="1" :checked="request('available_only')" :label="__('Hanya yang masih ada slot')" />

                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700">
                        {{ __('Terapkan Filter') }}
                    </button>
                </form>
            </details>
        </div>

        <ul class="flex flex-col gap-3 md:grid md:grid-cols-2 md:gap-4 md:space-y-0 lg:grid-cols-3">
            @forelse ($matches as $match)
                <x-match-card :match="$match" />
            @empty
                <li class="text-zinc-500 dark:text-zinc-400">{{ __('Belum ada match yang dibuka.') }}</li>
            @endforelse
        </ul>

        {{ $matches->links() }}
    </div>
</x-layouts::public>
