<x-layouts::public :title="$post->title" :meta="array_filter([
    'title' => $post->title,
    'description' => $post->excerpt,
    'image' => $post->image_path ? \Illuminate\Support\Facades\Storage::disk('public')->temporaryUrl($post->image_path, now()->addDays(7)) : null,
    'image_width' => $post->image_path ? 1600 : null,
    'image_height' => $post->image_path ? 686 : null,
])">
    <div class="flex flex-col gap-4">
        <a href="{{ route('home') }}" wire:navigate class="inline-flex w-fit items-center gap-1.5 text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
            <flux:icon name="arrow-left" class="size-4" />
            {{ __('Kembali ke Home') }}
        </a>

        <article class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-navy-800 dark:bg-navy-900">
            @if ($post->image_path)
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->temporaryUrl($post->image_path, now()->addDay()) }}"
                    alt="{{ $post->title }}"
                    class="h-56 w-full object-cover lg:h-80"
                />
            @endif

            <div class="flex flex-col gap-3 p-4 lg:p-6">
                <span class="w-fit rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white dark:bg-orange-600">
                    {{ __('Berita') }}
                </span>

                <h1 class="font-display text-2xl font-semibold tracking-wide text-zinc-900 lg:text-3xl dark:text-white">
                    {{ $post->title }}
                </h1>

                @if ($post->published_at)
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $post->published_at->timezone(config('app.display_timezone'))->translatedFormat('d M Y, H:i') }} WIB
                    </p>
                @endif

                @if ($post->body)
                    {{-- Isi dari RichEditor Filament, sumbernya admin terpercaya (bukan input user
                         publik) -- aman dirender langsung tanpa sanitasi tambahan. Styling manual
                         via arbitrary selector, sama seperti layouts/legal.blade.php (proyek ini
                         tidak pasang plugin @tailwindcss/typography). --}}
                    <div class="mt-2 flex flex-col gap-3 border-t border-zinc-100 pt-4 text-sm leading-relaxed text-zinc-700 [&_a]:underline [&_h2]:mt-2 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-zinc-900 [&_ol]:flex [&_ol]:list-decimal [&_ol]:flex-col [&_ol]:gap-1 [&_ol]:pl-6 [&_p]:leading-relaxed [&_strong]:font-semibold [&_ul]:flex [&_ul]:list-disc [&_ul]:flex-col [&_ul]:gap-1 [&_ul]:pl-6 dark:border-navy-800 dark:text-zinc-300 dark:[&_h2]:text-white">
                        {!! $post->body !!}
                    </div>
                @endif
            </div>
        </article>
    </div>
</x-layouts::public>
