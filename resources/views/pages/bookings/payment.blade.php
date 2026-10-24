@php
    $tz = config('app.display_timezone');
    $waText = 'Halo admin, saya butuh bantuan untuk booking '.$booking->booking_code.' ('.$booking->match->title.').';
@endphp
<x-layouts::public :title="__('Pembayaran')">
    <div class="mx-auto flex max-w-lg flex-col gap-4">
        @if ($booking->status === 'awaiting_verification')
            <section class="flex flex-col items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-6 text-center dark:border-navy-800 dark:bg-navy-900">
                <div class="flex size-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950">
                    <flux:icon name="clock" class="size-9 text-amber-600 dark:text-amber-400" />
                </div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Pembayaran sedang diverifikasi') }}</h1>
                <p class="text-zinc-600 dark:text-zinc-300">
                    {{ __('Bukti pembayaran Anda sudah kami terima. Halaman ini boleh ditutup -- e-ticket dikirim ke') }}
                    <strong class="text-zinc-900 dark:text-white">{{ $booking->buyerEmail() }}</strong>
                    {{ __('begitu admin menyetujui.') }}
                </p>
                <dl class="w-full divide-y divide-zinc-100 rounded-xl bg-zinc-50 text-start dark:divide-navy-800 dark:bg-navy-950">
                    <div class="flex justify-between px-4 py-2"><dt class="text-zinc-500">{{ __('Kode booking') }}</dt><dd class="font-mono font-bold text-zinc-900 dark:text-white">{{ $booking->booking_code }}</dd></div>
                    <div class="flex justify-between gap-3 px-4 py-2"><dt class="text-zinc-500">{{ __('Match') }}</dt><dd class="text-end text-zinc-900 dark:text-white">{{ $booking->match->title }}</dd></div>
                    <div class="flex justify-between px-4 py-2"><dt class="text-zinc-500">{{ __('Nominal') }}</dt><dd class="font-bold text-zinc-900 dark:text-white">{{ \App\Support\Money::rupiah($booking->amountToPay()) }}</dd></div>
                </dl>
            </section>

            <a href="{{ $settings->whatsappUrl($waText) }}" target="_blank" rel="noopener"
               class="flex items-center justify-center gap-2 rounded-lg border border-emerald-600 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-950">
                <flux:icon name="chat-bubble-left-right" class="size-5" />
                {{ __('Mengalami kendala? Hubungi admin') }}
            </a>

            {{-- Cek ulang tiap 30 detik; begitu disetujui controller mengalihkan ke e-ticket. --}}
            <script>setTimeout(() => window.location.reload(), 30000);</script>
        @elseif ($booking->status === 'rejected')
            <section class="flex flex-col items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-6 text-center dark:border-navy-800 dark:bg-navy-900">
                <flux:icon name="x-circle" class="size-14 text-red-600 dark:text-orange-500" />
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Pembayaran ditolak') }}</h1>
                <p class="text-zinc-600 dark:text-zinc-300">{{ __('Alasan:') }} {{ $booking->rejection_reason }}</p>
                <p class="text-xs text-zinc-500">{{ __('Slot Anda sudah dilepas. Kalau Anda sudah membayar dengan benar, hubungi admin.') }}</p>
            </section>
            <a href="{{ $settings->whatsappUrl($waText) }}" target="_blank" rel="noopener"
               class="flex items-center justify-center gap-2 rounded-lg bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                <flux:icon name="chat-bubble-left-right" class="size-5" /> {{ __('Hubungi admin') }}
            </a>
            <a href="{{ route('match.show', $booking->match) }}" wire:navigate class="text-center text-sm text-zinc-500 underline">{{ __('Pesan ulang') }}</a>
        @else
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Selesaikan Pembayaran') }}</h1>

            <section class="relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
                <x-sandbox-ribbon />
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Kode booking') }}</p>
                <p class="font-mono text-lg font-bold text-zinc-900 dark:text-white">{{ $booking->booking_code }}</p>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $booking->match->title }} — {{ $booking->match->venue->name }}</p>

                <dl class="mt-3 space-y-1 border-t border-zinc-100 pt-3 text-sm dark:border-navy-800">
                    <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Harga slot') }}</dt><dd class="text-zinc-900 dark:text-white">{{ \App\Support\Money::rupiah($booking->total) }}</dd></div>
                    @if ($booking->admin_fee > 0)
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Biaya admin') }}</dt><dd class="text-zinc-900 dark:text-white">{{ \App\Support\Money::rupiah($booking->admin_fee) }}</dd></div>
                    @endif
                    @if ($qrisDataUri && $booking->unique_code)
                        <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Kode unik') }}</dt><dd class="text-zinc-900 dark:text-white">{{ \App\Support\Money::rupiah($booking->unique_code) }}</dd></div>
                    @endif
                    <div class="flex justify-between border-t border-zinc-100 pt-1 font-bold dark:border-navy-800">
                        <dt class="text-zinc-700 dark:text-zinc-200">{{ __('Total') }}</dt>
                        <dd class="text-red-600 dark:text-orange-400">{{ \App\Support\Money::rupiah($qrisDataUri ? $booking->amountToPay() : $booking->payableAmount()) }}</dd>
                    </div>
                </dl>
                @if ($booking->hold_expires_at)
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Unggah bukti sebelum') }}
                        <strong>{{ $booking->hold_expires_at->timezone($tz)->translatedFormat('H:i') }} WIB</strong>
                        {{ __('atau slot dilepas otomatis.') }}
                    </p>
                @endif
            </section>

            @if ($qrisDataUri)
                <section class="flex flex-col items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-5 text-center dark:border-navy-800 dark:bg-navy-900"
                         x-data="{ copied: false }">
                    <p class="text-xs font-semibold tracking-[0.2em] text-zinc-400">QRIS</p>
                    {{-- QR selalu di atas putih supaya terbaca kamera/aplikasi bank di mode gelap. --}}
                    <img src="{{ $qrisDataUri }}" alt="{{ __('QRIS pembayaran') }}" class="size-64 rounded-xl bg-white p-2">
                    @if ($settings->qris_merchant_name)
                        <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $settings->qris_merchant_name }}</p>
                    @endif

                    <div>
                        <p class="text-xs text-zinc-500">{{ __('Total transfer') }}</p>
                        <button type="button" class="flex items-center gap-2 text-3xl font-bold text-red-600 dark:text-orange-400"
                                @click="navigator.clipboard.writeText('{{ $booking->amountToPay() }}'); copied = true; setTimeout(() => copied = false, 2000)">
                            {{ \App\Support\Money::rupiah($booking->amountToPay()) }}
                            <flux:icon name="clipboard-document" class="size-5 text-zinc-400" />
                        </button>
                        <p class="text-xs text-emerald-600" x-show="copied" x-cloak>{{ __('Nominal disalin') }}</p>
                    </div>
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        {{ __('Nominal sudah terisi otomatis saat scan. Pastikan yang dibayar persis') }}
                        <strong>{{ \App\Support\Money::rupiah($booking->amountToPay()) }}</strong>
                        {{ __('(termasuk kode unik :code) supaya cepat diverifikasi.', ['code' => str_pad((string) $booking->unique_code, 3, '0', STR_PAD_LEFT)]) }}
                    </p>
                    <a href="{{ $qrisDataUri }}" download="qris-{{ $booking->booking_code }}.png" class="text-xs font-semibold text-red-600 underline dark:text-orange-400">{{ __('Simpan gambar QRIS') }}</a>
                </section>

                <form method="POST" action="{{ $booking->urlFor('booking.proof') }}" enctype="multipart/form-data"
                      class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900"
                      x-data="{ preview: null, sending: false }" @submit="sending = true">
                    @csrf
                    <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Sudah bayar? Unggah bukti') }}</h2>

                    <label class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 p-5 text-center text-zinc-500 hover:border-red-400 dark:border-navy-700 dark:hover:border-orange-500">
                        <template x-if="preview"><img :src="preview" alt="" class="max-h-56 rounded-lg"></template>
                        <template x-if="! preview">
                            <span class="flex flex-col items-center gap-1">
                                <flux:icon name="arrow-up-tray" class="size-8" />
                                <span class="text-sm">{{ __('Ketuk untuk pilih screenshot/foto bukti') }}</span>
                                <span class="text-xs">JPG, PNG, WEBP · maks 8 MB</span>
                            </span>
                        </template>
                        <input type="file" name="bukti" accept="image/jpeg,image/png,image/webp" required class="sr-only"
                               @change="const f = $event.target.files[0]; preview = f ? URL.createObjectURL(f) : null">
                    </label>
                    @error('bukti') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

                    @if ($needsPhone)
                        <label class="flex flex-col gap-1 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ __('Nomor WhatsApp') }}
                            <input type="tel" name="phone" value="{{ old('phone') }}" required inputmode="tel" placeholder="08xxxxxxxxxx"
                                   class="rounded-lg border border-zinc-300 px-3 py-2 text-sm text-zinc-900 dark:border-navy-700 dark:bg-navy-950 dark:text-white">
                            <span class="text-xs text-zinc-500">{{ __('Disimpan ke akun Anda, dipakai admin kalau ada kendala.') }}</span>
                        </label>
                        @error('phone') <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    @endif

                    <button type="submit" :disabled="sending" class="rounded-lg bg-red-600 py-2.5 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60 dark:bg-orange-600 dark:hover:bg-orange-700">
                        <span x-show="! sending">{{ __('Kirim bukti pembayaran') }}</span>
                        <span x-show="sending" x-cloak>{{ __('Mengirim...') }}</span>
                    </button>
                </form>
            @endif

            @if ($settings->sumopodAvailable())
                @if ($qrisDataUri)
                    <p class="text-center text-xs text-zinc-400">{{ __('atau') }}</p>
                @endif
                <form method="POST" action="{{ $booking->urlFor('booking.payment.sumopod') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg border border-zinc-300 bg-white py-2.5 text-sm font-semibold text-zinc-800 hover:bg-zinc-50 dark:border-navy-700 dark:bg-navy-900 dark:text-white dark:hover:bg-navy-800">
                        <flux:icon name="bolt" class="size-5" /> {{ __('Bayar otomatis (tanpa unggah bukti)') }}
                    </button>
                </form>
            @endif

            @if (! $qrisDataUri && ! $settings->sumopodAvailable())
                <p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                    {{ __('Metode pembayaran belum tersedia. Silakan hubungi admin.') }}
                </p>
            @endif

            <a href="{{ $settings->whatsappUrl($waText) }}" target="_blank" rel="noopener" class="text-center text-sm text-zinc-500 underline">
                {{ __('Mengalami kendala? Hubungi admin') }}
            </a>

            @if (config('services.sumopod.sandbox_bypass'))
                <form method="POST" action="{{ $booking->urlFor('booking.payment.bypass') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-lg border border-amber-300 bg-amber-50 py-2.5 text-center text-sm font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        {{ __('Saya Sudah Bayar (Sandbox)') }}
                    </button>
                </form>
            @endif
        @endif
    </div>
</x-layouts::public>
