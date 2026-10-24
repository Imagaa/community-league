<?php

use App\Models\Booking;
use App\Models\MembershipSettings;
use Illuminate\Http\Request;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.auth', ['showBack' => false, 'topAlign' => true])] #[Title('Pembayaran Berhasil')] class extends Component {
    public Booking $booking;

    /**
     * True kalau booking SUDAH 'paid' saat halaman ini pertama dimuat (termasuk refresh) --
     * beda dengan transisi live pending->paid lewat wire:poll (pollStatus() di bawah, yang
     * TIDAK mengubah properti ini). Dipakai buat lompat langsung ke tahap akhir tanpa animasi
     * kalau ini cuma refresh/kunjungan ulang, bukan momen pembayaran baru saja berhasil.
     */
    public bool $alreadyPaidOnLoad = false;

    public function mount(Request $request, Booking $booking): void
    {
        $pemilikSah = $request->user() && $request->user()->id === $booking->user_id;
        $tandaTanganSah = $booking->isGuest() && $request->hasValidSignature();

        abort_unless($pemilikSah || $tandaTanganSah, 403);

        $this->booking = $booking->load('match.venue');
        $this->alreadyPaidOnLoad = $this->booking->status === 'paid';
    }

    public function pollStatus(): void
    {
        $this->booking->refresh();
    }

    #[Computed]
    public function poinTotal(): int
    {
        // Tamu (tanpa akun) tidak pernah dapat poin -- tidak ada akun untuk dikreditkan.
        if ($this->booking->isGuest() || ! MembershipSettings::enabled()) {
            return 0;
        }

        return $this->booking->match->points_reward * $this->booking->quantity;
    }
}; ?>

<div @if ($booking->status === 'pending_payment') wire:poll.5s="pollStatus" @endif>
    {{--
        Dimuat tanpa syarat (bukan di dalam @else) supaya lottie sudah siap SEBELUM tahap "paid"
        dirender -- baik lewat page-load penuh (status sudah paid saat mount) maupun lewat morph
        wire:poll (status baru berubah jadi paid). DOMContentLoaded tidak reliable di sini karena
        transisi pending->paid terjadi lewat morph AJAX Livewire, bukan navigasi baru -- makanya
        init animasi dipindah ke x-init (jalan ulang otomatis tiap Alpine.initTree pada elemen
        yang baru dimorph, terbukti jalan di passkey-verify.blade.php).
    --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>

    @if ($booking->status !== 'paid')
        <div class="flex flex-col items-center gap-4 text-center">
            <flux:icon.loading class="size-10 text-red-600 dark:text-orange-500" />
            <p class="text-zinc-600 dark:text-zinc-300">{{ __('Menunggu konfirmasi pembayaran...') }}</p>
        </div>
    @else
        {{--
            Satu halaman, tiga tahap (bukan lagi animasi terpisah -> redirect ke booking.show):
            1. "poin"    -- animasi koin + teks poin (DILEWATI kalau poinTotal 0, mis. tamu tanpa
               akun -- tidak ada gunanya nunggu animasi kosong).
            2. "sukses"  -- animasi centang main penuh, di tengah. Lottie BARU dimuat/diputar saat
               tahap ini benar-benar terlihat (bukan sejak x-init) -- kalau dimuat lebih awal saat
               masih disembunyikan di balik tahap "poin", jam animasinya tetap jalan di background
               (rAF tidak berhenti walau container display:none) sehingga begitu terlihat dia sudah
               di frame akhir alias "langsung freeze". Transisi ke tahap berikut dipicu event
               'complete' dari lottie, bukan setTimeout tebak-tebakan -- supaya benar-benar
               menunggu animasinya SELESAI diputar dulu baru membeku & mengecil.
            3. "selesai" -- centang yang SAMA mengecil & bergeser ke atas (transisi CSS). Judul
               "Pembelian Berhasil" muncul bareng transisi ini (persis seperti teks poin yang
               muncul bareng animasi koinnya), kartu detail (kode booking dkk) baru muncul
               belakangan lewat showDetail. Tidak ada redirect JS -- ini kondisi akhir halaman.
        --}}
        <div
            x-data="{
                skipAnimation: {{ $alreadyPaidOnLoad ? 'true' : 'false' }},
                stage: {{ $alreadyPaidOnLoad ? "'selesai'" : "'poin'" }},
                skipPoin: {{ $this->poinTotal > 0 ? 'false' : 'true' }},
                showDetail: {{ $alreadyPaidOnLoad ? 'true' : 'false' }},
                playSukses() {
                    // $nextTick, bukan langsung -- kalau dipanggil sinkron tepat setelah
                    // this.stage jadi sukses (jalur skipPoin, lihat init()), Alpine belum
                    // sempat menghapus display:none dari container x-show. lottie.loadAnimation
                    // yang jalan di container tersembunyi gagal render apa-apa & event complete
                    // tidak pernah nyala -- makanya judul selesai & kartu detail tidak pernah
                    // muncul (khusus booking tamu/poin 0 yang lewat jalur skipPoin ini).
                    this.$nextTick(() => {
                        const anim = lottie.loadAnimation({
                            container: this.$refs.lottieSukses,
                            path: '{{ asset('animations/success-state.json') }}',
                            renderer: 'svg',
                            loop: false,
                            autoplay: true,
                        });
                        anim.addEventListener('complete', () => {
                            setTimeout(() => { this.stage = 'selesai'; }, 400);
                            setTimeout(() => { this.showDetail = true; }, 1300);
                        });
                    });
                },
                init() {
                    if (this.skipAnimation) {
                        return;
                    }

                    if (this.skipPoin) {
                        this.stage = 'sukses';
                        this.playSukses();

                        return;
                    }

                    const anim = lottie.loadAnimation({
                        container: this.$refs.lottiePoin,
                        path: '{{ asset('animations/reward-coin.json') }}',
                        renderer: 'svg',
                        loop: false,
                        autoplay: true,
                    });
                    anim.addEventListener('complete', () => {
                        setTimeout(() => {
                            this.stage = 'sukses';
                            this.playSukses();
                        }, 400);
                    });
                },
            }"
            class="flex flex-col items-center gap-4 text-center"
        >
            <div x-show="stage === 'poin'">
                <div x-ref="lottiePoin" class="mx-auto size-40"></div>
                <p class="text-2xl font-bold text-red-600 dark:text-orange-400">+{{ $this->poinTotal }} Poin</p>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Anda berhak atas poin ini untuk match') }} <strong>{{ $booking->match->title }}</strong>
                    {{ __('kalau Anda hadir (poin masuk otomatis saat check-in di lokasi).') }}
                </p>
            </div>

            <div x-show="stage !== 'poin'" x-cloak class="flex w-full flex-col items-center gap-4">
                <div
                    x-ref="lottieSukses"
                    class="flex items-center justify-center transition-all duration-700 ease-in-out"
                    :class="stage === 'sukses' ? 'size-40' : 'size-14'"
                >
                    @if ($alreadyPaidOnLoad)
                        <flux:icon name="check-circle" class="size-full text-red-600 dark:text-orange-500" />
                    @endif
                </div>

                <div x-show="stage === 'selesai'" x-transition.opacity.duration.500ms x-cloak>
                    <h1 class="font-display text-xl font-bold tracking-wide text-zinc-900 lg:text-2xl dark:text-white">{{ __('Pembelian Berhasil') }}</h1>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Silakan cek email Anda') }}
                        @if ($booking->user_id)
                            {{ __('atau lihat di') }}
                            <a href="{{ route('tiket-saya') }}" wire:navigate class="font-semibold text-red-600 underline dark:text-orange-400">{{ __('Tiket Saya') }}</a>.
                        @else
                            .
                        @endif
                    </p>
                </div>

                <div x-show="showDetail" x-transition.opacity.duration.500ms x-cloak class="flex w-full flex-col gap-4">
                    <section class="w-full rounded-2xl border border-zinc-200 bg-white p-4 text-start dark:border-navy-800 dark:bg-navy-900">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Kode booking') }}</p>
                        <p class="text-lg font-bold text-zinc-900 dark:text-white">{{ $booking->booking_code }}</p>

                        <div class="mt-2 border-t border-zinc-100 pt-2 text-sm text-zinc-600 dark:border-navy-800 dark:text-zinc-300">
                            <p>{{ $booking->match->title }} — {{ $booking->match->venue->name }}</p>
                            <p>{{ __('Jumlah slot:') }} {{ $booking->quantity }}</p>
                            <p class="font-bold text-red-600 dark:text-orange-400">{{ __('Total:') }} {{ \App\Support\Money::rupiah($booking->paidAmount()) }}</p>
                        </div>
                    </section>

                    <a href="{{ route('home') }}" wire:navigate class="block rounded-lg bg-red-600 py-2.5 text-center text-sm font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700">
                        {{ __('Kembali ke Home') }}
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
