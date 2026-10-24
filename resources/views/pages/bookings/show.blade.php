<x-layouts::public :title="__('Detail Tiket')">
    <div class="flex flex-col gap-4">
        @if (auth()->check() && auth()->id() === $booking->user_id && \Illuminate\Support\Facades\Route::has('tiket-saya'))
            <div class="flex items-center gap-3">
                <a href="{{ route('tiket-saya') }}" wire:navigate
                   class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-navy-800 dark:text-zinc-300 dark:hover:bg-navy-700"
                   aria-label="{{ __('Kembali ke Tiket Saya') }}">
                    <flux:icon name="arrow-left" class="size-5" />
                </a>
                <h1 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Detail Tiket') }}</h1>
            </div>
        @endif

        @if ($booking->status === 'paid')
            {{-- #ticket-card sengaja TIDAK ikut dark mode -- ini tiket yang ditunjukkan ke panitia
                 dan di-capture apa adanya oleh html2canvas (backgroundColor putih di-hardcode di
                 script download di bawah), jadi harus tetap terang & konsisten di kedua mode. --}}
            <div id="ticket-card" class="relative overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm">
                <x-sandbox-ribbon />
                <div class="flex flex-col items-center gap-1 px-6 pt-6 pb-4">
                    <img src="{{ asset('images/primary-logo-landscape.webp') }}" alt="{{ config('app.name') }}" class="h-7 w-auto object-contain">
                    <p class="text-[10px] font-semibold tracking-[0.2em] text-zinc-400">{{ __('E-TICKET') }}</p>
                </div>

                <div class="bg-red-600 px-6 py-4">
                    <p class="text-base font-bold text-white">{{ $booking->match->title }}</p>
                    <p class="flex items-center gap-1 text-xs text-red-100">
                        <flux:icon name="map-pin" class="size-3.5" />
                        {{ $booking->match->venue->name }}
                    </p>
                </div>

                <div class="relative">
                    <div class="absolute -left-3 -top-3 size-6 rounded-full bg-zinc-50"></div>
                    <div class="absolute -right-3 -top-3 size-6 rounded-full bg-zinc-50"></div>
                    <div class="border-t border-dashed border-zinc-300"></div>
                </div>

                <div class="flex flex-col gap-3 px-6 py-5 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">{{ __('Kode booking') }}</span>
                        <span class="font-mono text-base font-bold tracking-wider text-zinc-900">{{ $booking->booking_code }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5 text-zinc-500">
                            <flux:icon name="calendar-days" class="size-4" />
                            {{ __('Jadwal') }}
                        </span>
                        <span class="font-medium text-zinc-900">
                            {{ $booking->match->starts_at->timezone(config('app.display_timezone'))->translatedFormat('d M Y, H:i') }} WIB
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">{{ __('Jumlah slot') }}</span>
                        <span class="font-medium text-zinc-900">{{ $booking->quantity }}</span>
                    </div>

                    @if ($booking->isGuest())
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">{{ __('Atas nama') }}</span>
                            <span class="font-medium text-zinc-900">{{ $booking->guest_name }}</span>
                        </div>
                    @endif

                    <div class="flex items-center justify-between border-t border-zinc-100 pt-3">
                        <span class="text-zinc-500">{{ __('Total') }}</span>
                        <span class="font-bold text-red-600">{{ \App\Support\Money::rupiah($booking->paidAmount()) }}</span>
                    </div>
                </div>

                <div class="relative">
                    <div class="absolute -left-3 -top-3 size-6 rounded-full bg-zinc-50"></div>
                    <div class="absolute -right-3 -top-3 size-6 rounded-full bg-zinc-50"></div>
                    <div class="border-t border-dashed border-zinc-300"></div>
                </div>

                <div class="flex flex-col items-center gap-2 px-6 py-6">
                    <img src="{{ $qrDataUri }}" alt="{{ __('QR check-in') }}" class="size-44 rounded-xl border border-zinc-100 p-2">
                    <p class="max-w-64 text-center text-xs text-zinc-500">
                        {{ \App\Models\MembershipSettings::enabled() ? __('Tunjukkan QR ini ke panitia untuk check-in dan klaim poin di venue.') : __('Tunjukkan QR ini ke panitia di venue.') }}
                    </p>
                </div>
            </div>

            <button type="button" id="download-tiket"
                    class="flex items-center justify-center gap-2 rounded-lg bg-red-600 py-2.5 text-sm font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700">
                <flux:icon name="arrow-down-tray" class="size-4" />
                {{ __('Download Tiket') }}
            </button>

            @if ($booking->match->drive_folder_url)
                <a href="{{ $booking->match->drive_folder_url }}" target="_blank" rel="noopener"
                   class="flex items-center justify-center gap-2 rounded-lg border border-red-600 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 dark:border-orange-500 dark:text-orange-400 dark:hover:bg-navy-800">
                    <flux:icon name="photo" class="size-4" />
                    {{ __('Lihat & unduh foto dokumentasi') }}
                </a>
            @endif

            <a href="{{ $booking->match->venue->mapsUrl() }}" target="_blank" rel="noopener"
               class="flex items-center justify-center gap-2 rounded-lg border border-zinc-300 py-2.5 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-navy-700 dark:text-zinc-200 dark:hover:bg-navy-800">
                <flux:icon name="map" class="size-4" />
                {{ __('Buka lokasi di Google Maps') }}
            </a>

            @vite('resources/js/ticket-download.js')
            <script>
                document.getElementById('download-tiket')?.addEventListener('click', async (event) => {
                    const button = event.currentTarget;
                    const card = document.getElementById('ticket-card');
                    if (! card) return;

                    button.disabled = true;

                    try {
                        if (! window.html2canvas) {
                            await new Promise((resolve) => window.addEventListener('html2canvas:ready', resolve, { once: true }));
                        }

                        const canvas = await window.html2canvas(card, { backgroundColor: '#ffffff', scale: 2 });

                        const link = document.createElement('a');
                        link.download = 'tiket-{{ $booking->booking_code }}.png';
                        link.href = canvas.toDataURL('image/png');
                        link.click();
                    } catch (error) {
                        console.error('Gagal membuat gambar tiket:', error);
                    } finally {
                        button.disabled = false;
                    }
                });
            </script>
        @elseif (in_array($booking->status, ['expired', 'cancelled'], true))
            <div class="flex flex-col items-center gap-2 rounded-2xl border border-zinc-200 bg-white p-6 text-center dark:border-navy-800 dark:bg-navy-900">
                <flux:icon name="clock" class="size-10 text-zinc-400 dark:text-zinc-500" />
                <p class="text-zinc-600 dark:text-zinc-300">{{ __('Booking ini sudah tidak berlaku.') }}</p>
            </div>
        @endif
    </div>
</x-layouts::public>
