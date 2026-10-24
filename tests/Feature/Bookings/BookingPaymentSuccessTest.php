<?php

namespace Tests\Feature\Bookings;

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\MembershipSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingPaymentSuccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_menampilkan_animasi_sukses_kalau_sudah_lunas(): void
    {
        MembershipSettings::current()->update(['enabled' => true]);
        $user = User::factory()->create();
        $match = GameMatch::factory()->create(['points_reward' => 5, 'title' => 'Match Animasi']);
        $booking = Booking::factory()->create(['user_id' => $user->id, 'match_id' => $match->id, 'quantity' => 2, 'status' => 'paid']);

        $this->actingAs($user)
            ->get(route('booking.payment.success', $booking))
            ->assertOk()
            ->assertSee('10 Poin')
            ->assertSee('Match Animasi');
    }

    public function test_konten_pembelian_berhasil_ada_di_halaman_yang_sama_tanpa_redirect(): void
    {
        // Animasi poin + sukses dan konten "Pembelian Berhasil" digabung jadi satu halaman --
        // tidak ada lagi redirect JS ke booking.show setelah animasi selesai.
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'paid']);

        $response = $this->actingAs($user)->get(route('booking.payment.success', $booking));

        $response->assertOk()
            ->assertSee('Pembelian Berhasil')
            ->assertSee($booking->booking_code)
            ->assertDontSee('window.location.href', false);
    }

    public function test_halaman_animasi_tidak_menampilkan_tombol_kembali(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'paid']);

        $this->actingAs($user)
            ->get(route('booking.payment.success', $booking))
            ->assertOk()
            ->assertDontSee('history.back()', false);
    }

    public function test_menampilkan_status_menunggu_kalau_belum_lunas(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'pending_payment']);

        $this->actingAs($user)
            ->get(route('booking.payment.success', $booking))
            ->assertOk()
            ->assertSee('Menunggu konfirmasi pembayaran');
    }

    public function test_user_lain_tidak_bisa_akses(): void
    {
        $pemilik = User::factory()->create();
        $userLain = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $pemilik->id, 'status' => 'paid']);

        $this->actingAs($userLain)
            ->get(route('booking.payment.success', $booking))
            ->assertForbidden();
    }

    public function test_init_animasi_tidak_bergantung_pada_domcontentloaded(): void
    {
        // Regresi ditemukan lewat testing manual di staging: animasi tidak pernah play kalau
        // status booking masih pending_payment saat halaman pertama dimuat, lalu berubah jadi
        // paid lewat morph wire:poll -- DOMContentLoaded cuma sekali per page-load sungguhan,
        // tidak pernah nyala ulang saat Livewire memorph konten @else masuk. Init lottie harus
        // lewat x-init (jalan ulang tiap Alpine.initTree kena elemen baru), bukan listener
        // DOMContentLoaded, dan script CDN-nya harus tanpa syarat supaya sudah siap sebelum
        // konten paid dirender.
        $source = file_get_contents(resource_path('views/pages/bookings/⚡payment-success.blade.php'));

        expect($source)->not->toContain("addEventListener('DOMContentLoaded'")
            ->and($source)->toContain('init()')
            ->and($source)->toContain('lottie.loadAnimation');
    }

    public function test_tombol_kembali_ke_home_muncul_setelah_selesai(): void
    {
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'paid']);

        $this->actingAs($user)
            ->get(route('booking.payment.success', $booking))
            ->assertOk()
            ->assertSee('Kembali ke Home')
            ->assertSee(route('home'), false);
    }

    public function test_animasi_sukses_dimuat_dan_diputar_saat_terlihat_bukan_di_awal(): void
    {
        // Regresi ditemukan lewat testing manual: animasi centang "langsung freeze dari awal"
        // karena lottie.loadAnimation() dipanggil sejak x-init/mount padahal containernya masih
        // disembunyikan di balik tahap "poin" -- jam animasinya jalan di background lalu sudah di
        // frame akhir begitu terlihat. Perbaikannya: playSukses() (yang memuat & memutar animasi
        // ini) baru dipanggil saat tahap benar-benar pindah ke 'sukses', dan transisi ke tahap
        // berikutnya ('selesai') dipicu event 'complete' dari lottie, bukan setTimeout buta yang
        // cuma menebak durasi.
        $source = file_get_contents(resource_path('views/pages/bookings/⚡payment-success.blade.php'));

        expect($source)->toContain('playSukses()')
            ->and($source)->toContain("addEventListener('complete'")
            ->and($source)->toContain('showDetail');
    }

    public function test_tahap_poin_dilewati_untuk_booking_tamu(): void
    {
        // Tamu (tanpa akun) tidak pernah dapat poin -- animasi & teks "+N Poin" harus dilewati
        // sama sekali, langsung ke animasi sukses.
        $match = GameMatch::factory()->create(['points_reward' => 5]);
        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'tamu@example.test',
            'match_id' => $match->id,
            'status' => 'paid',
        ]);

        $this->get($booking->urlFor('booking.payment.success'))
            ->assertOk()
            ->assertSee('skipPoin: true', false);
    }

    public function test_tahap_poin_tidak_dilewati_untuk_user_dengan_poin(): void
    {
        MembershipSettings::current()->update(['enabled' => true]);
        $user = User::factory()->create();
        $match = GameMatch::factory()->create(['points_reward' => 5]);
        $booking = Booking::factory()->create(['user_id' => $user->id, 'match_id' => $match->id, 'status' => 'paid']);

        $this->actingAs($user)
            ->get(route('booking.payment.success', $booking))
            ->assertOk()
            ->assertSee('skipPoin: false', false);
    }

    public function test_animasi_dilewati_kalau_booking_sudah_paid_sebelum_halaman_dimuat(): void
    {
        // Regresi ditemukan user: refresh/kunjungan ulang ke halaman ini setelah booking sudah
        // lunas mengulang animasi dari awal, harusnya langsung tampil hasil akhir.
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'paid']);

        $this->actingAs($user)
            ->get(route('booking.payment.success', $booking))
            ->assertOk()
            ->assertSee('skipAnimation: true', false)
            ->assertSee('showDetail: true', false);
    }

    public function test_already_paid_on_load_tetap_false_setelah_transisi_live_lewat_poll(): void
    {
        // alreadyPaidOnLoad HARUS ditentukan sekali di mount(), tidak boleh ikut berubah saat
        // pollStatus() me-refresh booking dari pending_payment jadi paid -- kalau ikut berubah,
        // transisi live juga akan langsung lompat ke tahap akhir tanpa animasi (regresi baru).
        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'pending_payment']);

        $component = Livewire::actingAs($user)->test('pages::bookings.payment-success', ['booking' => $booking]);
        $component->assertSet('alreadyPaidOnLoad', false);

        $booking->update(['status' => 'paid']);
        $component->call('pollStatus');

        $component->assertSet('alreadyPaidOnLoad', false);
    }
}
