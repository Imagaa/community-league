<?php

namespace Tests\Feature\Matches;

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_max_quantity_mengikuti_sisa_slot(): void
    {
        $match = GameMatch::factory()->create(['status' => 'open', 'capacity' => 2]);

        Livewire::test('pages::matches.booking-widget', ['match' => $match])
            ->assertSet('maxQuantity', 1);
    }

    public function test_tidak_ada_lagi_card_pesan_slot_dan_stepper_jumlah(): void
    {
        $match = GameMatch::factory()->create(['status' => 'open', 'capacity' => 5]);

        Livewire::test('pages::matches.booking-widget', ['match' => $match])
            ->assertDontSee('Pesan Slot')
            ->assertDontSee('Jumlah slot')
            ->assertSee('Masuk untuk beli')
            ->assertSee('Beli tanpa login')
            ->assertDontSee('Nama lengkap');

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::matches.booking-widget', ['match' => $match])
            ->assertDontSee('Pesan Slot')
            ->assertDontSee('Jumlah slot')
            ->assertSee('Beli');
    }

    public function test_user_login_beli_langsung_redirect_ke_pembayaran(): void
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create(['status' => 'open', 'capacity' => 5]);

        Livewire::actingAs($user)
            ->test('pages::matches.booking-widget', ['match' => $match])
            ->set('guest_phone', '081234567890')
            ->call('buyAuthenticated')
            ->assertRedirect();

        expect($user->bookings()->count())->toBe(1)
            ->and($user->bookings()->first()->quantity)->toBe(1);
    }

    public function test_login_untuk_beli_menyimpan_niat_dan_redirect_ke_register(): void
    {
        $match = GameMatch::factory()->create(['status' => 'open', 'capacity' => 5]);

        Livewire::test('pages::matches.booking-widget', ['match' => $match])
            ->call('loginToBuy')
            ->assertRedirect(route('login'));

        expect(session('purchase_intent'))->toBe(['match_id' => $match->id])
            ->and(session('url.intended'))->toBe(route('match.show', $match).'#beli');
    }

    public function test_beli_tanpa_login_membuat_booking_tamu(): void
    {
        $match = GameMatch::factory()->create(['status' => 'open', 'capacity' => 5]);

        Livewire::test('pages::matches.booking-widget', ['match' => $match])
            ->set('guest_name', 'Budi Tamu')
            ->set('guest_email', 'budi@example.test')
            ->set('guest_phone', '081234567890')
            ->set('terms', true)
            ->call('buyAsGuest')
            ->assertRedirect();

        expect(Booking::where('guest_email', 'budi@example.test')->exists())->toBeTrue();
    }

    public function test_error_booking_ganda_menampilkan_tautan_tiket_saya_untuk_user_login(): void
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create(['status' => 'open', 'capacity' => 5]);
        Booking::factory()->create(['user_id' => $user->id, 'match_id' => $match->id, 'status' => 'paid']);

        Livewire::actingAs($user)
            ->test('pages::matches.booking-widget', ['match' => $match])
            ->set('guest_phone', '081234567890')
            ->call('buyAuthenticated')
            ->assertSee('Anda sudah punya booking untuk match ini.')
            ->assertSee('Lihat di sini')
            ->assertSeeHtml(route('tiket-saya'));
    }

    public function test_error_bukan_booking_ganda_tidak_menampilkan_tautan_tiket_saya(): void
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create(['status' => 'draft']);

        Livewire::actingAs($user)
            ->test('pages::matches.booking-widget', ['match' => $match])
            ->set('guest_phone', '081234567890')
            ->call('buyAuthenticated')
            ->assertDontSee('Lihat di sini');
    }

    public function test_error_booking_ganda_tamu_tidak_menampilkan_tautan_tiket_saya(): void
    {
        $match = GameMatch::factory()->create(['status' => 'open', 'capacity' => 5]);
        Booking::factory()->create(['user_id' => null, 'guest_email' => 'budi@example.test', 'match_id' => $match->id, 'status' => 'paid']);

        Livewire::test('pages::matches.booking-widget', ['match' => $match])
            ->set('guest_name', 'Budi Tamu')
            ->set('guest_email', 'budi@example.test')
            ->set('guest_phone', '081234567890')
            ->set('terms', true)
            ->call('buyAsGuest')
            ->assertSee('Anda sudah punya booking untuk match ini.')
            ->assertDontSee('Lihat di sini');
    }

    public function test_user_login_wajib_isi_whatsapp_sekali_lalu_tersimpan_ke_akun(): void
    {
        $user = User::factory()->create(['name' => 'Nama Lama', 'phone' => null]);
        $match = GameMatch::factory()->create(['capacity' => 5]);

        Livewire::actingAs($user)
            ->test('pages::matches.booking-widget', ['match' => $match])
            ->assertSet('guest_name', 'Nama Lama')
            ->call('buyAuthenticated')
            ->assertHasErrors('guest_phone')
            ->set('guest_name', 'Nama Baru')
            ->set('guest_phone', '+62 812-3456-7890')
            ->call('buyAuthenticated')
            ->assertRedirect();

        expect($user->fresh())->name->toBe('Nama Baru')->phone->toBe('081234567890');
    }

    public function test_tamu_harus_konfirmasi_email_dulu(): void
    {
        $match = GameMatch::factory()->create(['capacity' => 5]);

        Livewire::test('pages::matches.booking-widget', ['match' => $match])
            ->call('startGuest')
            ->assertSee('Nama lengkap')
            ->set('guest_name', 'Budi Tamu')
            ->set('guest_email', ' budi@example.test ')
            ->set('guest_phone', '081234567890')
            ->set('terms', true)
            ->call('confirmGuest')
            ->assertSet('showEmailConfirm', true)
            ->assertSee('budi@example.test');

        expect(Booking::count())->toBe(0);
    }

    public function test_email_tamu_yang_sama_dengan_akun_dihitung_orang_yang_sama(): void
    {
        $user = User::factory()->create(['email' => 'rina@example.test', 'phone' => '081234567890']);
        $match = GameMatch::factory()->create(['capacity' => 5]);
        Booking::factory()->create(['user_id' => $user->id, 'match_id' => $match->id, 'status' => 'paid']);

        Livewire::test('pages::matches.booking-widget', ['match' => $match])
            ->set('guest_name', 'Rina')
            ->set('guest_email', 'RINA@example.test')
            ->set('guest_phone', '081234567890')
            ->set('terms', true)
            ->call('buyAsGuest')
            ->assertSee('Anda sudah punya booking untuk match ini.');

        Booking::factory()->create(['user_id' => null, 'guest_email' => 'tamu@example.test', 'match_id' => $match->id, 'status' => 'awaiting_verification']);
        $akunTamu = User::factory()->create(['email' => 'tamu@example.test', 'phone' => '081234567890']);

        Livewire::actingAs($akunTamu)
            ->test('pages::matches.booking-widget', ['match' => $match])
            ->call('buyAuthenticated')
            ->assertSee('Anda sudah punya booking untuk match ini.');
    }
}
