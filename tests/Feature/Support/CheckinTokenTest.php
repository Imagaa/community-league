<?php

use App\Models\Booking;
use App\Support\CheckinToken;

it('encode lalu decode mengembalikan booking yang sama', function () {
    $booking = Booking::factory()->create();

    $token = CheckinToken::encode($booking);
    $decoded = CheckinToken::decode($token);

    expect($decoded)->not->toBeNull()
        ->and($decoded->id)->toBe($booking->id);
});

it('token dua booking berbeda tidak bisa dipertukarkan', function () {
    $a = Booking::factory()->create();
    $b = Booking::factory()->create();

    $tokenA = CheckinToken::encode($a);

    expect(CheckinToken::decode($tokenA)->id)->toBe($a->id)
        ->and(CheckinToken::decode($tokenA)->id)->not->toBe($b->id);
});

it('token yang di-tamper (diubah sedikit) ditolak, bukan malah decode ke booking lain', function () {
    $booking = Booking::factory()->create();
    $token = CheckinToken::encode($booking);

    $tampered = substr($token, 0, -1).(substr($token, -1) === 'a' ? 'b' : 'a');

    expect(CheckinToken::decode($tampered))->toBeNull();
});

it('teks acak yang bukan token sama sekali ditolak', function () {
    expect(CheckinToken::decode('bukan-token-sama-sekali'))->toBeNull();
});

it('token buat booking yang sudah dihapus mengembalikan null', function () {
    $booking = Booking::factory()->create();
    $token = CheckinToken::encode($booking);

    $booking->delete();

    expect(CheckinToken::decode($token))->toBeNull();
});
