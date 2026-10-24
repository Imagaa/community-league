<?php

use App\Http\Middleware\EnsureAdmin;
use App\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Berita')] class extends Component {
    public string $notice = '';

    /** Jalan di render awal dan SETIAP aksi Livewire (endpoint update tidak lewat middleware rute). */
    public function boot(): void
    {
        EnsureAdmin::authorize();
    }

    /** @return Collection<int, Post> Urutan sama persis dengan carousel homepage. */
    #[Computed]
    public function posts(): Collection
    {
        return Post::query()->carouselOrder()->get();
    }

    /** @return list<int> id post yang saat ini benar-benar tampil di carousel. */
    #[Computed]
    public function inCarousel(): array
    {
        return Post::query()->carouselOrder()->published()->limit(Post::CAROUSEL_LIMIT)->pluck('id')->all();
    }

    /** Geser satu posisi (-1 naik, 1 turun) lalu tulis ulang sort_order berurutan. */
    public function move(int $id, int $direction): void
    {
        $ids = $this->posts->pluck('id')->all();
        $from = array_search($id, $ids, true);
        $to = $from === false ? false : $from + ($direction < 0 ? -1 : 1);

        if ($from === false || $to < 0 || $to >= count($ids)) {
            return;
        }

        [$ids[$from], $ids[$to]] = [$ids[$to], $ids[$from]];

        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $postId) {
                Post::whereKey($postId)->update(['sort_order' => $position + 1]);
            }
        });

        unset($this->posts, $this->inCarousel);
    }

    public function toggle(int $id): void
    {
        $post = Post::findOrFail($id);
        $post->update(['is_active' => ! $post->is_active]);
        $this->notice = $post->is_active ? "\"{$post->title}\" diaktifkan." : "\"{$post->title}\" dinonaktifkan.";

        unset($this->posts, $this->inCarousel);
    }

    public function delete(int $id): void
    {
        $post = Post::findOrFail($id);

        if ($post->image_path) {
            Storage::disk('public')->delete($post->image_path);
        }

        $post->delete();
        $this->notice = "\"{$post->title}\" dihapus.";

        unset($this->posts, $this->inCarousel);
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-white">Berita</h1>
            <p class="text-xs text-zinc-400">Isi hero carousel homepage. {{ \App\Models\Post::CAROUSEL_LIMIT }} teratas yang aktif ditampilkan.</p>
        </div>
        <a href="{{ route('admin.berita.create') }}" wire:navigate class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-orange-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-orange-700">
            <flux:icon name="plus" variant="micro" /> Berita baru
        </a>
    </div>

    @if ($notice)
        <p class="rounded-xl border border-emerald-800 bg-emerald-950 px-4 py-3 text-emerald-300">{{ $notice }}</p>
    @endif

    <div class="space-y-2">
        @forelse ($this->posts as $post)
            @php
                $tampil = in_array($post->id, $this->inCarousel, true);
                $terjadwal = $post->is_active && $post->published_at?->isFuture();
            @endphp
            <article wire:key="p-{{ $post->id }}" x-data="{ confirming: false }"
                     @class(['rounded-2xl border bg-navy-900 p-3', 'border-orange-600/60' => $tampil, 'border-navy-800' => ! $tampil])>
                <div class="flex gap-3">
                    <div class="relative h-16 w-28 shrink-0 overflow-hidden rounded-lg bg-navy-800">
                        @if ($post->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->temporaryUrl($post->image_path, now()->addDay()) }}"
                                 alt="" loading="lazy" @class(['size-full object-cover', 'opacity-40' => ! $post->is_active])>
                        @else
                            <flux:icon name="photo" class="absolute inset-0 m-auto size-6 text-zinc-600" />
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold text-white">{{ $post->title }}</p>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px] font-semibold">
                            <span class="rounded-full bg-navy-800 px-2 py-0.5 text-zinc-300">{{ $post->isNews() ? 'Berita' : 'Promo' }}</span>
                            @if ($tampil)
                                <span class="rounded-full bg-orange-600 px-2 py-0.5 text-white">Tampil di carousel</span>
                            @elseif (! $post->is_active)
                                <span class="rounded-full bg-navy-800 px-2 py-0.5 text-zinc-500">Nonaktif</span>
                            @elseif ($terjadwal)
                                <span class="rounded-full bg-amber-950 px-2 py-0.5 text-amber-300">Terbit {{ $post->published_at->timezone(config('app.display_timezone'))->translatedFormat('d M H.i') }}</span>
                            @else
                                <span class="rounded-full bg-navy-800 px-2 py-0.5 text-zinc-400">Di luar {{ \App\Models\Post::CAROUSEL_LIMIT }} teratas</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-1 border-t border-navy-800 pt-2">
                    <button type="button" wire:click="move({{ $post->id }}, -1)" @disabled($loop->first) class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white disabled:opacity-30" title="Naikkan">
                        <flux:icon name="arrow-up" class="size-4" />
                    </button>
                    <button type="button" wire:click="move({{ $post->id }}, 1)" @disabled($loop->last) class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white disabled:opacity-30" title="Turunkan">
                        <flux:icon name="arrow-down" class="size-4" />
                    </button>
                    <button type="button" wire:click="toggle({{ $post->id }})" class="ml-1 rounded-lg px-2 py-1.5 text-xs text-zinc-400 hover:bg-navy-800 hover:text-white">
                        {{ $post->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                    <span class="flex-1"></span>
                    <a href="{{ route('admin.berita.edit', $post) }}" wire:navigate class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-white" title="Ubah">
                        <flux:icon name="pencil-square" class="size-4" />
                    </a>
                    <button type="button" @click="confirming = true" class="rounded-lg p-2 text-zinc-400 hover:bg-navy-800 hover:text-red-400" title="Hapus">
                        <flux:icon name="trash" class="size-4" />
                    </button>
                </div>

                <div x-show="confirming" x-cloak class="mt-2 flex flex-wrap items-center gap-2 rounded-xl border border-red-900 bg-red-950/50 p-3 text-xs text-red-200">
                    <span class="flex-1">Hapus permanen beserta gambarnya?</span>
                    <button type="button" wire:click="delete({{ $post->id }})" class="rounded-lg bg-red-600 px-3 py-1.5 font-bold text-white">Ya, hapus</button>
                    <button type="button" @click="confirming = false" class="rounded-lg border border-navy-700 px-3 py-1.5 text-zinc-300">Batal</button>
                </div>
            </article>
        @empty
            <div class="flex flex-col items-center gap-2 rounded-2xl border border-dashed border-navy-800 p-10 text-center text-zinc-500">
                <flux:icon name="newspaper" class="size-10" />
                Belum ada berita. Carousel homepage menampilkan keadaan kosong.
            </div>
        @endforelse
    </div>
</div>
