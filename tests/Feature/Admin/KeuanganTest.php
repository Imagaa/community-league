<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\PaymentSettings;
use App\Models\User;
use App\Support\FinanceReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function bookingLunas(array $attrs = []): Booking
{
    return Booking::factory()->create($attrs + [
        'status' => 'paid',
        'paid_at' => now()->subHour(),
        'total' => 40000,
        'admin_fee' => 700,
    ]);
}

it('menghitung uang masuk, biaya admin, kode unik, potongan Sumopod, dan pendapatan bersih', function () {
    $match = GameMatch::factory()->create(['capacity' => 10]);
    bookingLunas(['match_id' => $match->id, 'unique_code' => 12]);                       // QRIS manual: 40.712
    bookingLunas(['match_id' => $match->id, 'unique_code' => 99, 'sumopod_payment_id' => 'p1']); // Sumopod: 40.700 (kode unik tidak ikut)
    bookingLunas(['match_id' => $match->id, 'paid_at' => now()->subDays(40)]);            // di luar 30 hari
    Booking::factory()->create(['match_id' => $match->id, 'status' => 'awaiting_verification', 'total' => 40000, 'admin_fee' => 700, 'unique_code' => 5]);
    Booking::factory()->create(['match_id' => $match->id, 'status' => 'expired']);

    $r = new FinanceReport('30-hari');
    $s = $r->summary();

    // Potongan Sumopod: ceil(40.700 x 0,7%) + 300 = 285 + 300 = 585
    expect($s)->toMatchArray([
        'transaksi' => 2,
        'slot' => 2,
        'tiket' => 80000,
        'biaya_admin' => 1400,
        'kode_unik' => 12,
        'uang_masuk' => 81412,
        'potongan_sumopod' => 585,
        'bersih' => 80827,
        'sumopod_transaksi' => 1,
        'sumopod_masuk' => 40700,
        'manual_masuk' => 40712,
    ]);
    expect($r->pending())->toBe(['jumlah' => 1, 'nominal' => 40705])
        ->and($r->conversion())->toMatchArray(['dibuat' => 5, 'lunas' => 3, 'kedaluwarsa' => 1])
        ->and($r->perMatch()->first())->slot->toEqual(2)
        ->and((new FinanceReport('semua'))->summary()['transaksi'])->toBe(3);
});

it('menandai pembayaran lunas dari match batal sebagai perlu refund', function () {
    bookingLunas(['match_id' => GameMatch::factory()->create(['status' => 'cancelled'])->id]);

    expect((new FinanceReport('30-hari'))->refundNeeded())->toBe(['jumlah' => 1, 'nominal' => 40700]);
});

it('grafik harian mengisi hari kosong dan menjumlah per hari WIB', function () {
    bookingLunas();

    $chart = (new FinanceReport('7-hari'))->chart();

    expect($chart)->toHaveCount(7)
        ->and($chart->sum('nominal'))->toBe(40700);
});

it('halaman keuangan dan CSV hanya untuk admin', function () {
    bookingLunas(['booking_code' => 'KEU12345']);

    $this->get(adminUrl('/keuangan'))->assertRedirect(adminUrl('/masuk'));
    $this->actingAs(buatAdmin())->get(adminUrl('/keuangan?periode=7-hari'))
        ->assertOk()->assertSee('Uang masuk')->assertSee('Rp 40.700');

    $csv = $this->actingAs(buatAdmin())->get(adminUrl('/keuangan/csv?periode=semua'))->assertOk()->streamedContent();
    expect($csv)->toContain('"Total dibayar"')->toContain('KEU12345')->toContain(';40700');
});

it('transaksi sandbox tidak dihitung di keuangan tapi dicatat sebagai catatan', function () {
    bookingLunas(['is_sandbox' => true, 'sumopod_payment_id' => 'sbx']);
    bookingLunas();

    $r = new FinanceReport('30-hari');

    expect($r->summary()['transaksi'])->toBe(1)
        ->and($r->summary()['uang_masuk'])->toBe(40700)
        ->and($r->sandboxCount())->toBe(1);

    $this->actingAs(buatAdmin())->get(adminUrl('/keuangan'))->assertSee('1 transaksi sandbox (uji coba) tidak dihitung');
});

it('menandai booking sandbox saat link Sumopod dibuat di mode sandbox, dan melepasnya bila akhirnya bayar QRIS manual', function () {
    Http::fake(['api-pay*.sumopod.com/*' => Http::response(['payment_id' => 'p', 'payment_link_url' => 'https://sumo.sandbox.pymnt.global/pay/p'])]);
    Storage::fake('proofs');
    PaymentSettings::current()->update(['sumopod_enabled' => true, 'sumopod_api_key' => 'k', 'sumopod_mode' => 'sandbox']);
    $user = User::factory()->create(['phone' => '081234567890']);
    $booking = Booking::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('booking.payment.sumopod', $booking));
    expect($booking->fresh()->is_sandbox)->toBeTrue();

    $this->actingAs($user)->post(route('booking.proof', $booking), ['bukti' => UploadedFile::fake()->image('b.png')]);
    expect($booking->fresh()->is_sandbox)->toBeFalse();
});
