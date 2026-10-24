<x-layouts::public :title="__('Home')">
    {{-- Filter lengkap (kota/jenis/venue/tanggal) sengaja HANYA di /match (lihat matches/index.blade.php)
         -- Home cuma teaser 5 match terdekat, bukan katalog, jadi tidak butuh filter penuh. --}}
    <div class="flex flex-col gap-6">
        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-navy-800 dark:bg-navy-900">
            @if ($posts->isEmpty())
                <div class="flex aspect-[21/9] items-center justify-center bg-zinc-100 dark:bg-navy-950">
                    <flux:icon name="newspaper" class="size-10 text-zinc-300 lg:size-14 dark:text-navy-700" />
                </div>
                <div class="p-4">
                    <span class="rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white dark:bg-orange-600">
                        {{ __('Berita') }}
                    </span>
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Belum ada berita saat ini') }}</p>
                </div>
            @else
                @php $postCount = $posts->count(); @endphp
                {{--
                    Carousel di-drive Alpine sepenuhnya lewat transform: translateX (BUKAN scroll-
                    snap native lagi) -- native scroll-snap tetap bisa "fling" lewat lebih dari 1
                    slide kalau swipe/scroll-nya cepat/jauh (browser cuma settle ke snap point
                    TERDEKAT setelah momentum habis, bukan membatasi ke 1 slide per gesture).
                    Di sini setiap gesture (swipe touch ATAU wheel/trackpad) SELALU maju/mundur
                    PERSIS 1 index, berapa pun jauh/cepatnya -- deteksi threshold jarak/delta,
                    lalu index-nya sendiri yang diubah, bukan posisi scroll mentah. wheelCooldown
                    perlu karena satu gesture trackpad memicu puluhan event wheel beruntun; tanpa
                    debounce itu akan kebaca sebagai puluhan "gesture" terpisah.
                    Wraparound: index dibungkus modulo count, jadi dari slide terakhir maju lagi
                    balik ke slide pertama (dan sebaliknya) -- bukan berhenti/clamp di ujung.
                --}}
                <div
                    x-data="{
                        active: 0,
                        count: {{ $postCount }},
                        touchStartX: 0,
                        wheelCooldown: false,
                        next() { this.active = (this.active + 1) % this.count; },
                        prev() { this.active = (this.active - 1 + this.count) % this.count; },
                        onTouchStart(e) { this.touchStartX = e.touches[0].clientX; },
                        onTouchEnd(e) {
                            const deltaX = e.changedTouches[0].clientX - this.touchStartX;
                            if (Math.abs(deltaX) > 40) { deltaX < 0 ? this.next() : this.prev(); }
                        },
                        onWheel(e) {
                            if (this.wheelCooldown || Math.abs(e.deltaX) < 12) return;
                            this.wheelCooldown = true;
                            e.deltaX > 0 ? this.next() : this.prev();
                            setTimeout(() => { this.wheelCooldown = false; }, 500);
                        },
                    }"
                    class="relative overflow-hidden"
                >
                    <div
                        class="flex touch-pan-y transition-transform duration-300 ease-out"
                        x-on:touchstart="onTouchStart"
                        x-on:touchend="onTouchEnd"
                        x-on:wheel="onWheel"
                        :style="`width: ${count * 100}%; transform: translateX(-${(active * 100) / count}%)`"
                    >
                        @foreach ($posts as $post)
                            <a
                                href="{{ $post->isNews() ? route('berita.show', $post) : $post->link_url }}"
                                @if ($post->isNews()) wire:navigate @else target="_blank" rel="noopener" @endif
                                style="width: {{ 100 / $postCount }}%"
                                class="relative flex aspect-[21/9] shrink-0 items-end overflow-hidden bg-zinc-200 dark:bg-navy-950"
                            >
                                @if ($post->image_path)
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->temporaryUrl($post->image_path, now()->addDay()) }}"
                                        alt="{{ $post->title }}"
                                        class="absolute inset-0 h-full w-full object-cover"
                                    />
                                @endif

                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent"></div>

                                <span class="absolute left-3 top-3 rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white dark:bg-orange-600">
                                    {{ $post->isNews() ? __('Berita') : __('Promo') }}
                                </span>

                                <div class="relative z-10 flex min-w-0 w-full flex-col gap-0.5 p-4 text-white">
                                    {{-- min-w-0 di sini WAJIB -- <a> ini flex-row, div ini satu-satunya
                                         child normal-flow-nya (yang lain absolute), jadi tanpa min-w-0
                                         div ini ikut aturan default flex item (min-width:auto) dan
                                         melebar sesuai lebar TEKS asli yang belum di-truncate (nowrap =
                                         min-content sama dengan max-content), lebih lebar dari card.
                                         truncate di <p> di bawah jadi cuma "hard clip" oleh overflow-
                                         hidden milik <a> di posisi yang salah (buntutnya keluar box),
                                         bukan ellipsis yang benar di tepi box. --}}
                                    <p class="min-w-0 truncate font-display text-lg font-bold tracking-wide">{{ $post->title }}</p>
                                    @if ($post->excerpt)
                                        <p class="line-clamp-2 text-xs text-white/80">{{ $post->excerpt }}</p>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>

                    @if ($postCount > 1)
                        {{-- Panah cuma di desktop -- mobile sudah punya swipe/geser langsung di
                             touchscreen, panah di sana cuma nambah elemen tanpa guna. Keduanya
                             pakai next()/prev() yang sama dengan swipe -- wraparound konsisten
                             di semua cara navigasi, bukan cuma di swipe. --}}
                        <button
                            type="button"
                            @click="prev()"
                            class="absolute left-2 top-1/2 hidden size-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white hover:bg-black/60 md:flex"
                            aria-label="{{ __('Berita sebelumnya') }}"
                        >
                            <flux:icon name="chevron-left" class="size-5" />
                        </button>
                        <button
                            type="button"
                            @click="next()"
                            class="absolute right-2 top-1/2 hidden size-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white hover:bg-black/60 md:flex"
                            aria-label="{{ __('Berita berikutnya') }}"
                        >
                            <flux:icon name="chevron-right" class="size-5" />
                        </button>

                        <div class="absolute inset-x-0 top-3 flex justify-center gap-1.5">
                            @foreach ($posts as $i => $post)
                                <button
                                    type="button"
                                    @click="active = {{ $i }}"
                                    :class="active === {{ $i }} ? 'w-4 bg-white' : 'w-1.5 bg-white/50'"
                                    class="h-1.5 rounded-full transition-all"
                                    aria-label="{{ __('Slide') }} {{ $i + 1 }}"
                                ></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </section>

        <section class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-navy-800">
            <div class="flex items-center justify-between gap-2 bg-red-600 px-4 py-3 dark:bg-navy-900">
                <h2 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-white">
                    <flux:icon name="calendar-days" class="size-4 shrink-0" />
                    {{ __('Jadwal Permainan') }}
                </h2>

                <form method="GET" action="{{ route('home') }}">
                    <x-filter-select
                        name="city"
                        :options="$cities->pluck('name', 'id')"
                        :selected="request('city')"
                        placeholder="{{ __('Semua Kota') }}"
                        auto-submit
                        compact
                    />
                </form>
            </div>

            <ul class="flex flex-col gap-3 bg-zinc-50 p-3 md:grid md:grid-cols-2 md:gap-4 md:space-y-0 lg:grid-cols-3 [&>li:nth-child(6)]:hidden md:[&>li:nth-child(6)]:flex dark:bg-navy-950">
                {{-- Query ambil 6 match, tapi mobile (list satu kolom) cuma tampilkan 5 -- item
                     ke-6 disembunyikan via nth-child supaya desktop (grid 3x2) pas penuh tanpa
                     bikin mobile query terpisah. md:flex (bukan md:block) karena root <li> di
                     <x-match-card> sendiri butuh display:flex buat layout flex-col internalnya
                     (mt-auto push tombol ke bawah) -- kalau di-override jadi block, mt-auto rusak. --}}
                @forelse ($matches as $match)
                    <x-match-card :match="$match" :button-label="__('Ambil Slot Main')" />
                @empty
                    <li class="text-zinc-500 dark:text-zinc-400">{{ __('Belum ada match yang dibuka.') }}</li>
                @endforelse
            </ul>

            <div class="bg-zinc-50 pb-4 text-center dark:bg-navy-950">
                <a href="{{ route('match', request()->query()) }}" wire:navigate
                   class="inline-block rounded-full bg-red-600 px-6 py-2 text-sm font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700">
                    {{ __('Lihat Lebih Banyak') }}
                </a>
            </div>
        </section>

        @if (\App\Models\MembershipSettings::enabled())
        <section class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-navy-800">
            <div class="bg-red-600 px-4 py-3 dark:bg-navy-900">
                <h2 class="flex items-center gap-1.5 text-sm font-bold uppercase tracking-wide text-white">
                    <flux:icon name="gift" class="size-4 shrink-0" />
                    {{ __('Penukaran Poin Member') }}
                </h2>
            </div>

            @if ($rewardItems->isNotEmpty())
                <ul class="flex flex-col gap-3 bg-zinc-50 p-3 md:grid md:grid-cols-3 md:gap-4 md:space-y-0 dark:bg-navy-950">
                    @foreach ($rewardItems as $item)
                        <li class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm lg:gap-4 lg:p-5 dark:border-navy-800 dark:bg-navy-900">
                            @if ($item->image_path)
                                <img
                                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->temporaryUrl($item->image_path, now()->addDay()) }}"
                                    alt="{{ $item->name }}"
                                    class="h-12 w-12 shrink-0 rounded-lg object-cover lg:h-16 lg:w-16"
                                />
                            @else
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-zinc-100 lg:h-16 lg:w-16 dark:bg-navy-950">
                                    <flux:icon name="gift" class="size-5 text-zinc-400 lg:size-6 dark:text-navy-700" />
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="font-semibold text-zinc-900 lg:text-lg dark:text-white">{{ $item->name }}</p>
                                <p class="mt-1 font-bold text-red-600 dark:text-orange-400">{{ $item->point_cost }} {{ __('poin') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="bg-zinc-50 pb-4 text-center dark:bg-navy-950">
                    <a href="{{ route('marketplace') }}" wire:navigate
                       class="inline-block rounded-full bg-red-600 px-6 py-2 text-sm font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700">
                        {{ __('Lihat Semua') }}
                    </a>
                </div>
            @else
                <div class="flex items-center gap-3 bg-zinc-50 p-4 dark:bg-navy-950">
                    <flux:icon name="gift" class="size-8 text-zinc-400 dark:text-navy-700" />
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Belum ada reward yang tersedia saat ini.') }}</p>
                </div>
            @endif
        </section>
        @endif
    </div>
</x-layouts::public>
