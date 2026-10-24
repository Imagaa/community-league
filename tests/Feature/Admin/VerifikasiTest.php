<?php

use App\Actions\Booking\VerifyPayment;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingPaidNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\PaymentProofReceivedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function bookingMenunggu(array $attrs = []): Booking
{
    return Booking::factory()->create($attrs + [
        'status' => 'awaiting_verification',
        'unique_code' => 83,
        'payment_proof_path' => 'bukti-bayar/contoh.webp',
        'proof_uploaded_at' => now(),
        'hold_expires_at' => null,
    ]);
}

it('menampilkan antrean verifikasi ke admin', function () {
    $booking = bookingMenunggu(['total' => 75000]);

    $this->actingAs(buatAdmin())->get(adminUrl('/verifikasi'))
        ->assertOk()
        ->assertSee($booking->user->name)
        ->assertSee('Rp 75.083');
});

it('menyetujui pembayaran, menandai lunas, dan mengirim e-ticket', function () {
    Notification::fake();
    $admin = buatAdmin();
    $booking = bookingMenunggu();

    Livewire::actingAs($admin)->test('pages::admin.verifikasi')->call('approve', $booking->id)->assertHasNoErrors();

    expect($booking->fresh())
        ->status->toBe('paid')
        ->paid_at->not->toBeNull()
        ->verified_by->toBe($admin->id);
    Notification::assertSentTo($booking->user, BookingPaidNotification::class);
});

it('tidak memproses dua kali (dobel klik / dua admin)', function () {
    Notification::fake();
    $admin = buatAdmin();
    $booking = bookingMenunggu();
    $verify = app(VerifyPayment::class);

    $verify->approve($booking, $admin);

    expect(fn () => $verify->approve($booking, $admin))->toThrow(BookingException::class);
    expect(fn () => $verify->reject($booking, $admin, 'x'))->toThrow(BookingException::class);
    Notification::assertSentToTimes($booking->user, BookingPaidNotification::class, 1);
});

it('menolak dengan alasan wajib dan melepas slot', function () {
    Notification::fake();
    $admin = buatAdmin();
    $booking = bookingMenunggu(['user_id' => null, 'guest_name' => 'Budi', 'guest_email' => 'budi@example.com', 'guest_phone' => '081234567890']);

    expect(fn () => app(VerifyPayment::class)->reject($booking, $admin, '  '))->toThrow(BookingException::class);

    Livewire::actingAs($admin)->test('pages::admin.verifikasi')->call('reject', $booking->id, 'Nominal tidak sesuai');

    expect($booking->fresh())->status->toBe('rejected')->rejection_reason->toBe('Nominal tidak sesuai');
    expect($booking->match->fresh()->remainingSlots())->toBe($booking->match->capacity);
    Notification::assertSentOnDemand(BookingRejectedNotification::class);
});

it('bukti bayar hanya bisa dilihat admin', function () {
    Storage::fake('proofs');
    Storage::disk('proofs')->put('bukti-bayar/contoh.webp', 'gambar');
    $booking = bookingMenunggu();

    $this->get(adminUrl('/bukti/'.$booking->id))->assertRedirect(adminUrl('/masuk'));
    $this->actingAs(User::factory()->create())->get(adminUrl('/bukti/'.$booking->id))->assertForbidden();
    $this->actingAs(buatAdmin())->get(adminUrl('/bukti/'.$booking->id))->assertOk();
});

it('email bukti diterima dan ditolak memuat info penting dan tombol WhatsApp admin', function () {
    $booking = bookingMenunggu(['total' => 75000, 'rejection_reason' => 'Dana belum masuk']);

    $diterima = (string) (new PaymentProofReceivedNotification($booking))->toMail($booking->user)->render();
    $ditolak = (string) (new BookingRejectedNotification($booking))->toMail($booking->user)->render();

    expect($diterima)->toContain($booking->booking_code)->toContain('Rp 75.083')->toContain('wa.me/6285713438267')
        ->and($ditolak)->toContain('Dana belum masuk')->toContain('wa.me/6285713438267');
});
