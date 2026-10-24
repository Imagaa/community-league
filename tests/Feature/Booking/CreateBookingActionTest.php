<?php

use App\Actions\Booking\CreateBooking;
use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

// Akses awal member hanya berlaku saat saklar Membership aktif.
beforeEach(fn () => MembershipSettings::current()->update(['enabled' => true]));

it('membuat booking pending_payment dengan hold 30 menit', function () {
    $match = GameMatch::factory()->create(['capacity' => 10, 'price_regular' => 35000]);
    $user = User::factory()->create();

    $booking = (new CreateBooking)->handle($match, [
        'user_id' => $user->id,
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ]);

    expect($booking->status)->toBe('pending_payment')
        ->and($booking->quantity)->toBe(1)
        ->and($booking->unit_price)->toBe(35000)
        ->and($booking->total)->toBe(35000)
        ->and($booking->hold_expires_at)->not->toBeNull()
        ->and(abs($booking->hold_expires_at->diffInMinutes(now())))->toBeGreaterThanOrEqual(29)
        ->and(abs($booking->hold_expires_at->diffInMinutes(now())))->toBeLessThanOrEqual(30)
        ->and($booking->booking_code)->toHaveLength(8);
});

it('user login mendapat notifikasi in-app menunggu pembayaran', function () {
    $match = GameMatch::factory()->create(['title' => 'Liga Malam']);
    $user = User::factory()->create();

    $booking = (new CreateBooking)->handle($match, [
        'user_id' => $user->id,
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ]);

    $notification = $user->notifications()->latest()->firstOrFail();
    expect($notification->data['title'])->toBe('Selesaikan pembayaran')
        ->and($notification->data['body'])->toContain('Liga Malam')
        ->and($notification->data['url'])->toBe($booking->urlFor('booking.payment'));
});

it('tamu tidak dapat notifikasi in-app (tidak ada akun)', function () {
    $match = GameMatch::factory()->create();

    (new CreateBooking)->handle($match, [
        'guest_name' => 'Budi',
        'guest_email' => 'budi@example.test',
        'guest_phone' => '081234567890',
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ]);

    expect(DatabaseNotification::count())->toBe(0);
});

it('menolak kuantitas nol', function () {
    $match = GameMatch::factory()->create();
    $user = User::factory()->create();

    (new CreateBooking)->handle($match, [
        'user_id' => $user->id,
        'quantity' => 0,
        'terms_version' => User::TERMS_VERSION,
    ]);
})->throws(BookingException::class, 'Jumlah tiket tidak valid');

it('menolak kuantitas negatif', function () {
    $match = GameMatch::factory()->create();
    $user = User::factory()->create();

    (new CreateBooking)->handle($match, [
        'user_id' => $user->id,
        'quantity' => -1,
        'terms_version' => User::TERMS_VERSION,
    ]);
})->throws(BookingException::class, 'Jumlah tiket tidak valid');

it('menolak booking guest tanpa data diri', function () {
    $match = GameMatch::factory()->create();

    (new CreateBooking)->handle($match, [
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ]);
})->throws(BookingException::class, 'Data tamu wajib diisi');

it('membuat booking guest lengkap dengan data diri', function () {
    $match = GameMatch::factory()->create();

    $booking = (new CreateBooking)->handle($match, [
        'guest_name' => 'Budi',
        'guest_email' => 'budi@example.test',
        'guest_phone' => '081234567890',
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ]);

    expect($booking->isGuest())->toBeTrue()
        ->and($booking->guest_name)->toBe('Budi');
});

it('menolak kalau kuantitas melebihi sisa slot', function () {
    $match = GameMatch::factory()->create(['capacity' => 2]);
    Booking::factory()->for($match, 'match')->create(['quantity' => 2, 'status' => 'paid']);
    $user = User::factory()->create();

    (new CreateBooking)->handle($match, [
        'user_id' => $user->id,
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ]);
})->throws(BookingException::class, 'Slot tidak cukup');

it('menolak booking sebelum match dibuka untuk umum', function () {
    $match = GameMatch::factory()->create(['public_opens_at' => now()->addHour()]);
    $user = User::factory()->create();

    (new CreateBooking)->handle($match, [
        'user_id' => $user->id,
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ]);
})->throws(BookingException::class, 'belum dibuka');

it('menolak booking pada match yang sudah selesai (computed finished)', function () {
    $match = GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->subWeek(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subHour(),
    ]);
    $user = User::factory()->create();

    (new CreateBooking)->handle($match, [
        'user_id' => $user->id,
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ]);
})->throws(BookingException::class, 'Match ini tidak sedang menerima booking.');

it('menolak booking pada match draft', function () {
    $match = GameMatch::factory()->create(['status' => 'draft', 'public_opens_at' => now()->subDay()]);
    $user = User::factory()->create();

    (new CreateBooking)->handle($match, [
        'user_id' => $user->id,
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ]);
})->throws(BookingException::class, 'Match ini tidak sedang menerima booking.');

it('dua booking berurutan yang bersamaan tidak pernah melebihi kapasitas', function () {
    $match = GameMatch::factory()->create(['capacity' => 1]);
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    (new CreateBooking)->handle($match, ['user_id' => $userA->id, 'quantity' => 1, 'terms_version' => User::TERMS_VERSION]);

    expect(fn () => (new CreateBooking)->handle($match, ['user_id' => $userB->id, 'quantity' => 1, 'terms_version' => User::TERMS_VERSION]))
        ->toThrow(BookingException::class, 'Slot tidak cukup');

    expect($match->fresh()->remainingSlots())->toBe(0);
});

it('menolak booking kedua dari user yang sama untuk match yang sama', function () {
    $match = GameMatch::factory()->create(['capacity' => 10]);
    $user = User::factory()->create();

    (new CreateBooking)->handle($match, ['user_id' => $user->id, 'quantity' => 1, 'terms_version' => User::TERMS_VERSION]);

    (new CreateBooking)->handle($match, ['user_id' => $user->id, 'quantity' => 1, 'terms_version' => User::TERMS_VERSION]);
})->throws(BookingException::class, 'Anda sudah punya booking untuk match ini.');

it('menolak booking kedua dari tamu dengan email yang sama untuk match yang sama', function () {
    $match = GameMatch::factory()->create(['capacity' => 10]);
    $data = [
        'guest_name' => 'Budi',
        'guest_email' => 'budi@example.test',
        'guest_phone' => '081234567890',
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ];

    (new CreateBooking)->handle($match, $data);

    (new CreateBooking)->handle($match, $data);
})->throws(BookingException::class, 'Anda sudah punya booking untuk match ini.');

it('user boleh booking di match lain walau sudah punya booking aktif', function () {
    $matchA = GameMatch::factory()->create(['capacity' => 10]);
    $matchB = GameMatch::factory()->create(['capacity' => 10]);
    $user = User::factory()->create();

    (new CreateBooking)->handle($matchA, ['user_id' => $user->id, 'quantity' => 1, 'terms_version' => User::TERMS_VERSION]);

    $booking = (new CreateBooking)->handle($matchB, ['user_id' => $user->id, 'quantity' => 1, 'terms_version' => User::TERMS_VERSION]);

    expect($booking->match_id)->toBe($matchB->id);
});

it('member aktif boleh booking sebelum public_opens_at kalau masih di jendela akses awal', function () {
    $match = GameMatch::factory()->create([
        'member_opens_at' => now()->subHour(),
        'public_opens_at' => now()->addHour(),
    ]);
    $user = User::factory()->create();
    Membership::factory()->for($user)->create(['expires_at' => now()->addMonth()]);

    $booking = (new CreateBooking)->handle($match, [
        'user_id' => $user->id,
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ]);

    expect($booking->status)->toBe('pending_payment');
});

it('non-member tetap ditolak sebelum public_opens_at walau jendela member sudah buka', function () {
    $match = GameMatch::factory()->create([
        'member_opens_at' => now()->subHour(),
        'public_opens_at' => now()->addHour(),
    ]);
    $user = User::factory()->create();

    (new CreateBooking)->handle($match, [
        'user_id' => $user->id,
        'quantity' => 1,
        'terms_version' => User::TERMS_VERSION,
    ]);
})->throws(BookingException::class, 'belum dibuka');

it('booking baru boleh dibuat kalau booking lama sudah cancelled/expired', function () {
    $match = GameMatch::factory()->create(['capacity' => 10]);
    $user = User::factory()->create();
    Booking::factory()->for($match, 'match')->for($user)->create(['status' => 'cancelled']);

    $booking = (new CreateBooking)->handle($match, ['user_id' => $user->id, 'quantity' => 1, 'terms_version' => User::TERMS_VERSION]);

    expect($booking->status)->toBe('pending_payment');
});
