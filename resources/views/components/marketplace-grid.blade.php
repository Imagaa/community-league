@props(['items'])

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
    @foreach ($items as $item)
        <div class="flex h-full flex-col gap-1 rounded-2xl border border-zinc-200 bg-white p-4 lg:gap-1.5 lg:p-6 dark:border-navy-800 dark:bg-navy-900">
            <a href="{{ route('marketplace.show', $item) }}" wire:navigate>
                @if ($item->image_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->temporaryUrl($item->image_path, now()->addDay()) }}" alt="{{ $item->name }}" class="mb-3 aspect-video w-full rounded-lg object-cover lg:mb-4" />
                @endif
                <p class="font-semibold text-zinc-900 lg:text-lg dark:text-white">{{ $item->name }}</p>
            </a>
            @if ($item->description)
                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $item->description }}</p>
            @endif
            <p class="font-bold text-red-600 lg:text-lg dark:text-orange-400">{{ $item->point_cost }} {{ __('poin') }}</p>

            <div class="mt-auto pt-3">
                <livewire:pages::marketplace.redeem-widget :item="$item" :key="'redeem-'.$item->id" />
            </div>
        </div>
    @endforeach
</div>
