<x-layouts::public :title="$match->title" :meta="[
    'title' => $match->title.' · '.$match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('D, d M Y H.i').' WIB',
    'description' => $match->venue->name.', '.$match->venue->city->name.' · '.\App\Support\Money::rupiah($match->price_regular).'/slot · '.($match->remainingSlots() > 0 ? 'Sisa '.$match->remainingSlots().' slot' : 'Slot penuh').'. Pesan di Community League.',
    'image' => route('match.og', ['match' => $match, 'v' => $match->updated_at?->timestamp]),
    'image_width' => 1200,
    'image_height' => 630,
]">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:gap-6">
        @if ($match->poster_path)
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white md:w-2/5 md:shrink-0 dark:border-navy-800 dark:bg-navy-900">
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->temporaryUrl($match->poster_path, now()->addDay()) }}"
                     alt="Poster {{ $match->title }}" class="h-auto w-full object-cover" />
            </div>
        @endif

        @php
            // Sama persis dengan badge di x-match-card (katalog/home) -- supaya kategori & status
            // match tampil konsisten di mana pun, bukan cuma di sini.
            $computedStatus = $match->computedStatus();
            $isFootball = $match->type === 'football';
            $statusBadge = match (true) {
                $computedStatus === 'finished' => ['label' => __('Selesai'), 'class' => 'bg-slate-500 text-white'],
                $computedStatus === 'ongoing' => ['label' => __('Berlangsung'), 'class' => 'bg-amber-500 text-white'],
                $computedStatus === 'published' => ['label' => __('Segera Dibuka'), 'class' => 'bg-blue-600 text-white'],
                $computedStatus === 'open' && $match->isFull() => ['label' => __('Penuh'), 'class' => 'bg-red-600 text-white dark:bg-orange-600'],
                default => null,
            };
        @endphp

        <div class="flex flex-1 flex-col gap-4">
            <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-navy-800 dark:bg-navy-900">
            <div class="flex flex-col gap-3 p-4">
                <span class="w-fit rounded-full px-2 py-0.5 text-[10px] font-bold uppercase text-white {{ $isFootball ? 'bg-zinc-900 dark:bg-white/10' : 'bg-teal-600 dark:bg-teal-500' }}">
                    {{ $isFootball ? __('Sepak Bola') : __('Mini Soccer') }}
                </span>

                <div class="flex items-start justify-between gap-3">
                    <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ $match->title }}</h1>
                    <x-share-button class="shrink-0" :url="route('match.show', $match)" :title="$match->title"
                                    :text="$match->title.' · '.$match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('D, d M Y H.i').' WIB · '.$match->venue->name.'. Pesan slotmu:'" />
                </div>

                <div class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <flux:icon name="map-pin" class="mt-0.5 size-4 shrink-0 text-red-600 dark:text-orange-500" />
                    <div class="flex flex-col gap-1.5">
                        <span>
                            <span class="font-medium text-zinc-900 dark:text-white">{{ $match->venue->name }}</span>, {{ $match->venue->city->name }}
                            @if ($match->venue->address)
                                <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ $match->venue->address }}</span>
                            @endif
                        </span>
                        <a href="{{ $match->venue->mapsUrl() }}" target="_blank" rel="noopener"
                           class="inline-flex w-fit items-center gap-1 rounded-full border border-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-navy-700 dark:text-zinc-200 dark:hover:bg-navy-800">
                            <flux:icon name="map" variant="micro" />
                            {{ __('Buka di Google Maps') }}
                        </a>
                    </div>
                </div>

                <div class="flex items-start gap-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <flux:icon name="clock" class="mt-0.5 size-4 shrink-0 text-red-600 dark:text-orange-500" />
                    <span>{{ $match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('l, d M Y, H:i') }} WIB</span>
                </div>

                @if ($match->description)
                    <p class="border-t border-zinc-100 pt-3 text-sm text-zinc-600 dark:border-navy-800 dark:text-zinc-300">{{ $match->description }}</p>
                @endif

                <div class="flex items-center justify-between border-t border-zinc-100 pt-3 dark:border-navy-800">
                    <div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Harga per slot') }}</p>
                        <p class="text-lg font-bold text-red-600 dark:text-orange-400">{{ \App\Support\Money::rupiah($match->price_regular) }}</p>
                        @php $biayaAdmin = \App\Models\PaymentSettings::current()->adminFeeFor($match->price_regular); @endphp
                        @if ($biayaAdmin > 0)
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('+ biaya admin :fee', ['fee' => \App\Support\Money::rupiah($biayaAdmin)]) }}</p>
                        @endif
                    </div>

                    <div class="text-right">
                        @if ($statusBadge)
                            <span class="inline-block rounded-full px-3 py-1 text-xs font-bold {{ $statusBadge['class'] }}">
                                {{ $statusBadge['label'] }}
                            </span>
                        @else
                            <p class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Sisa slot:') }} {{ $match->remainingSlots() }}</p>
                        @endif
                    </div>
                </div>

                @if ($match->points_reward > 0 && \App\Models\MembershipSettings::enabled())
                    <span class="w-fit rounded-full bg-amber-400 px-2 py-0.5 text-[10px] font-bold text-zinc-900">
                        +{{ $match->points_reward }} Pts
                    </span>
                @endif
            </div>
        </section>

        @php
            $inMemberWindow = $match->hasEarlyMemberWindow()
                && $computedStatus === 'published'
                && now()->between($match->member_opens_at, $match->public_opens_at);
        @endphp

        @if ($inMemberWindow)
            <section class="flex items-center gap-2 rounded-2xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                @if (auth()->user()?->membership?->isActive())
                    <x-membership-badge class="h-5 w-5 shrink-0" />
                    <span>
                        {{ __('Sudah dibuka untuk Anda berkat Membership. Publik baru dibuka') }}
                        {{ $match->public_opens_at->timezone(config('app.display_timezone'))->translatedFormat('d M Y, H:i') }} WIB.
                    </span>
                @else
                    <flux:icon name="star" class="size-5 shrink-0" />
                    <span>
                        {{ __('Sedang dibuka khusus member. Publik mulai') }}
                        {{ $match->public_opens_at->timezone(config('app.display_timezone'))->translatedFormat('d M Y, H:i') }} WIB.
                    </span>
                @endif
            </section>
        @endif

        @if (! $match->isBookableBy(auth()->user()))
            <section class="rounded-2xl border border-zinc-200 bg-white p-4 text-sm text-zinc-500 dark:border-navy-800 dark:bg-navy-900 dark:text-zinc-400">
                @if ($computedStatus === 'cancelled')
                    {{ __('Match ini telah dibatalkan.') }}
                @elseif ($computedStatus === 'finished')
                    {{ __('Match ini sudah selesai.') }}
                @elseif ($computedStatus === 'ongoing')
                    {{ __('Match sedang berlangsung.') }}
                @elseif ($computedStatus === 'published')
                    {{ __('Dibuka untuk umum pada') }}
                    {{ $match->public_opens_at->timezone(config('app.display_timezone'))->translatedFormat('d M Y, H:i') }} WIB.
                    {{-- Tombol "Ingatkan Saya" dimatikan dulu sampai email reminder diaktifkan lagi. --}}
                @else
                    {{ __('Match ini belum dibuka untuk pemesanan.') }}
                @endif
            </section>
        @elseif ($match->isFull())
            {{-- Tombol booking tidak ditampilkan saat penuh --}}
        @else
            <livewire:pages::matches.booking-widget :match="$match" />
        @endif

        @if ($match->drive_photos || $match->drive_folder_url)
            @php
                // Link unduh semua foto hanya untuk pembeli lunas match ini (dikirim juga lewat email).
                $isBuyer = auth()->check() && $match->bookings()->where('user_id', auth()->id())->where('status', 'paid')->exists();
            @endphp
            <section class="space-y-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900" x-data="{ zoom: null }">
                <h2 class="flex items-center gap-1.5 font-semibold text-zinc-900 dark:text-white">
                    <flux:icon name="photo" class="size-5 text-red-600 dark:text-orange-500" />
                    {{ __('Dokumentasi') }}
                </h2>

                @if ($match->drive_photos)
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($match->drive_photos as $photo)
                            @php $src = \Illuminate\Support\Facades\Storage::disk('public')->temporaryUrl($photo, now()->addDay()); @endphp
                            <button type="button" @click="zoom = @js($src)" class="aspect-[4/3] overflow-hidden rounded-xl bg-zinc-100 dark:bg-navy-950">
                                <img src="{{ $src }}" alt="{{ __('Foto dokumentasi :title', ['title' => $match->title]) }}" loading="lazy" class="size-full object-cover transition hover:scale-105">
                            </button>
                        @endforeach
                    </div>
                @endif

                @if ($isBuyer)
                    <a href="{{ $match->drive_folder_url }}" target="_blank" rel="noopener"
                       class="flex items-center justify-center gap-2 rounded-lg bg-red-600 py-2.5 text-sm font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700">
                        <flux:icon name="arrow-down-tray" class="size-4" />
                        {{ __('Lihat & unduh semua foto') }}
                    </a>
                @endif

                <template x-teleport="body">
                    <div x-show="zoom" x-cloak x-transition.opacity @click="zoom = null" @keydown.escape.window="zoom = null"
                         class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4">
                        <img :src="zoom" alt="" class="max-h-full max-w-full rounded-lg">
                    </div>
                </template>
            </section>
        @endif
        </div>
    </div>
</x-layouts::public>
