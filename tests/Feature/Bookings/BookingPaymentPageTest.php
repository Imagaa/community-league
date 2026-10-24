<?php

use App\Models\Booking;
use App\Models\PaymentSettings;
use App\Models\User;
use App\Notifications\PaymentProofReceivedNotification;
use App\Support\Qris;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function pasangQris(): void
{
    $body = '00020101021126310017ID.CO.EXAMPLE.WWW01061234565204581453033605802ID5916COMMUNITY LEAGUE6009SURAKARTA6304';
    PaymentSettings::current()->update(['qris_payload' => $body.Qris::crc16($body), 'qris_merchant_name' => 'COMMUNITY LEAGUE']);
}

function gambarBukti(): UploadedFile
{
    return UploadedFile::fake()->image('bukti.png', 400, 800);
}

it('menampilkan QRIS dinamis dengan nominal plus kode unik', function () {
    pasangQris();
    $user = User::factory()->create();
    $booking = Booking::factory()->create(['user_id' => $user->id, 'total' => 75000]);

    $this->actingAs($user)->get(route('booking.payment', $booking))
        ->assertOk()
        ->assertSee('data:image/png;base64', false)
        ->assertSee('COMMUNITY LEAGUE')
        ->assertDontSee('Bayar otomatis');

    $booking->refresh();
    expect($booking->unique_code)->toBeBetween(1, 999)
        ->and($booking->amountToPay())->toBe(75000 + $booking->unique_code);

    // Kode tidak berubah saat halaman dibuka ulang.
    $kode = $booking->unique_code;
    $this->actingAs($user)->get(route('booking.payment', $booking));
    expect($booking->fresh()->unique_code)->toBe($kode);
});

it('memberi kode unik berbeda untuk booking lain dengan harga sama', function () {
    $kode = collect(range(1, 30))->map(fn () => Booking::factory()->create(['total' => 50000])->ensureUniqueCode());

    expect($kode->unique()->count())->toBe(30);
});

it('menampilkan tombol Sumopod hanya kalau diaktifkan dan key terisi', function () {
    pasangQris();
    $user = User::factory()->create();
    $booking = Booking::factory()->create(['user_id' => $user->id]);

    PaymentSettings::current()->update(['sumopod_enabled' => true, 'sumopod_api_key' => 'key-123']);

    $this->actingAs($user)->get(route('booking.payment', $booking))->assertSee('Bayar otomatis');
});

it('membuat link Sumopod saat tombol bayar otomatis ditekan', function () {
    Http::fake(['api-pay*.sumopod.com/*' => Http::response(['payment_id' => 'pay-xyz', 'payment_link_url' => 'https://pay.sumopod.com/pay/xyz'])]);
    PaymentSettings::current()->update(['sumopod_enabled' => true, 'sumopod_api_key' => 'key-123', 'sumopod_mode' => 'live']);
    $user = User::factory()->create();
    $booking = Booking::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('booking.payment.sumopod', $booking))
        ->assertRedirect('https://pay.sumopod.com/pay/xyz');

    Http::assertSent(fn ($request) => $request->url() === PaymentSettings::SUMOPOD_LIVE_URL.'/payments'
        && $request->hasHeader('X-Api-Key', 'key-123'));
});

it('menolak bayar otomatis saat Sumopod nonaktif', function () {
    $user = User::factory()->create();
    $booking = Booking::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('booking.payment.sumopod', $booking))->assertNotFound();
});

it('unggah bukti mengubah status jadi menunggu verifikasi, menyimpan privat, dan mengirim email', function () {
    Storage::fake('proofs');
    Notification::fake();
    pasangQris();
    $booking = Booking::factory()->create(['user_id' => null, 'guest_name' => 'Budi', 'guest_email' => 'budi@example.test', 'guest_phone' => '081234567890']);

    $this->post($booking->urlFor('booking.proof'), ['bukti' => gambarBukti()])
        ->assertRedirect($booking->urlFor('booking.payment'));

    $booking->refresh();
    expect($booking->status)->toBe('awaiting_verification')
        ->and($booking->hold_expires_at)->toBeNull()
        ->and($booking->payment_proof_path)->toEndWith('.webp');
    Storage::disk('proofs')->assertExists($booking->payment_proof_path);
    Notification::assertSentOnDemand(PaymentProofReceivedNotification::class);

    $this->get($booking->urlFor('booking.payment'))
        ->assertSee('Pembayaran sedang diverifikasi')
        ->assertSee('wa.me/6285713438267', false);
});

it('meminta nomor WhatsApp user login yang belum punya lalu menyimpannya', function () {
    Storage::fake('proofs');
    Notification::fake();
    pasangQris();
    $user = User::factory()->create(['phone' => null]);
    $booking = Booking::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('booking.payment', $booking))->assertSee('Nomor WhatsApp');
    $this->actingAs($user)->post(route('booking.proof', $booking), ['bukti' => gambarBukti()])->assertSessionHasErrors('phone');
    $this->actingAs($user)->post(route('booking.proof', $booking), ['bukti' => gambarBukti(), 'phone' => '0812 3456 7890']);

    expect($user->fresh()->phone)->toBe('081234567890')
        ->and($booking->fresh()->status)->toBe('awaiting_verification');
});

it('menolak file bukan gambar dan unggahan untuk booking kedaluwarsa', function () {
    Storage::fake('proofs');
    $user = User::factory()->create(['phone' => '081234567890']);
    $booking = Booking::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->post(route('booking.proof', $booking), ['bukti' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('bukti');

    $booking->update(['status' => 'expired']);
    $this->actingAs($user)->post(route('booking.proof', $booking), ['bukti' => gambarBukti()])
        ->assertSessionHasErrors('bukti');
    expect($booking->fresh()->status)->toBe('expired');
});

it('tamu tanpa tanda tangan dan user lain tidak bisa mengunggah', function () {
    $booking = Booking::factory()->create(['user_id' => null, 'guest_email' => 'x@example.test']);
    $this->post(route('booking.proof', $booking), ['bukti' => gambarBukti()])->assertForbidden();

    $milikOrang = Booking::factory()->create();
    $this->actingAs(User::factory()->create())->post(route('booking.proof', $milikOrang), ['bukti' => gambarBukti()])->assertForbidden();
});

it('menampilkan alasan penolakan', function () {
    $user = User::factory()->create();
    $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'rejected', 'rejection_reason' => 'Nominal tidak sesuai']);

    $this->actingAs($user)->get(route('booking.payment', $booking))
        ->assertSee('Pembayaran ditolak')->assertSee('Nominal tidak sesuai');
    $this->actingAs($user)->get(route('booking.show', $booking))->assertRedirect(route('booking.payment', $booking));
});

it('redirect ke e-ticket kalau sudah lunas dan menolak user lain', function () {
    $user = User::factory()->create();
    $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'paid']);

    $this->actingAs($user)->get(route('booking.payment', $booking))->assertRedirect(route('booking.show', $booking));
    $this->actingAs(User::factory()->create())->get(route('booking.payment', $booking))->assertForbidden();
});

it('tombol bypass sandbox memakai url bertanda tangan untuk tamu', function () {
    $source = file_get_contents(resource_path('views/pages/bookings/payment.blade.php'));

    expect($source)->toContain("action=\"{{ \$booking->urlFor('booking.payment.bypass') }}\"");
});

it('link Sumopod dibuat berlaku 1 jam dan hold booking ikut diperpanjang', function () {
    Http::fake(['api-pay*.sumopod.com/*' => Http::response([
        'payment_id' => 'pay-1', 'payment_link_url' => 'https://pay.sumopod.com/pay/1', 'expires_at' => now()->addHour()->toIso8601String(),
    ])]);
    PaymentSettings::current()->update(['sumopod_enabled' => true, 'sumopod_api_key' => 'key-123']);
    $user = User::factory()->create();
    $booking = Booking::factory()->create(['user_id' => $user->id, 'hold_expires_at' => now()->addMinutes(30)]);

    $this->actingAs($user)->post(route('booking.payment.sumopod', $booking));

    Http::assertSent(fn ($request) => $request['expires_in_hours'] === 1);
    expect($booking->fresh()->hold_expires_at->gt(now()->addMinutes(55)))->toBeTrue();
});
