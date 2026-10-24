<?php

use App\Actions\Booking\CreateBooking;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\PaymentSettings;
use App\Models\User;
use Livewire\Livewire;

it('menghitung biaya admin 1% + Rp300 dibulatkan ke atas ke Rp100', function () {
    $s = PaymentSettings::current();

    expect($s->adminFeeFor(40000))->toBe(700)   // 400 + 300
        ->and($s->adminFeeFor(45500))->toBe(800) // 755 -> 800
        ->and($s->adminFeeFor(50000))->toBe(800)
        ->and($s->adminFeeFor(0))->toBe(0);
});

it('booking baru menyimpan biaya admin dan tagihan memakainya', function () {
    $match = GameMatch::factory()->create(['price_regular' => 40000]);
    $user = User::factory()->create();

    $booking = app(CreateBooking::class)->handle($match, ['user_id' => $user->id, 'quantity' => 1, 'terms_version' => User::TERMS_VERSION]);
    $booking->update(['unique_code' => 12]);

    expect($booking->admin_fee)->toBe(700)
        ->and($booking->payableAmount())->toBe(40700)      // Sumopod: tanpa kode unik
        ->and($booking->amountToPay())->toBe(40712);        // QRIS manual: + kode unik

    // Setelan diubah belakangan tidak mengubah tagihan yang sudah ada.
    PaymentSettings::current()->update(['admin_fee_percent' => 2]);
    expect($booking->fresh()->admin_fee)->toBe(700);
});

it('halaman bayar menampilkan rincian biaya admin tanpa menyebut persen', function () {
    $user = User::factory()->create();
    $booking = Booking::factory()->create(['user_id' => $user->id, 'total' => 40000, 'admin_fee' => 700]);

    $this->actingAs($user)->get(route('booking.payment', $booking))
        ->assertSee('Biaya admin')->assertSee('Rp 700')
        ->assertDontSeeText('1%');
});

it('admin bisa mengubah persen dan biaya tetap', function () {
    Livewire::actingAs(buatAdmin())->test('pages::admin.setelan')
        ->assertSee('biaya admin Rp 800')
        ->call('edit', 'fee')
        ->assertSet('admin_fee_percent', '1')
        ->set('admin_fee_percent', '1,5')
        ->set('admin_fee_flat', 500)
        ->assertSee('Rp 1.300')   // 50.000 x 1,5% + 500 = 1.250 -> 1.300
        ->call('saveFee')
        ->assertHasNoErrors()
        ->assertSee('1,5%');

    expect(PaymentSettings::current())
        ->admin_fee_percent->toBe('1.50')
        ->admin_fee_flat->toBe(500);
});
