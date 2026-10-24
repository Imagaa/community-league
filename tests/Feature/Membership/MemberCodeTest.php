<?php

use App\Models\Membership;
use App\Support\MemberCode;

it('mengenali kode kartu fisik dan menolaknya setelah kartu diganti', function () {
    $membership = Membership::factory()->create();
    $code = $membership->cardCode();

    expect($code)->toStartWith('CLC1.')->not->toContain((string) $membership->id)
        ->and(MemberCode::resolve($code))->toMatchArray(['type' => 'physical'])
        ->and(MemberCode::resolve($code)['membership']->is($membership))->toBeTrue();

    $membership->replaceCard();
    expect(MemberCode::resolve($code))->toBeNull()
        ->and(MemberCode::resolve('https://contoh.com'))->toBeNull()
        ->and(MemberCode::resolve(''))->toBeNull();
});

it('kode digital berlaku di jendela 30 detik ±1 dan menolak kode lama atau diubah', function () {
    $membership = Membership::factory()->create();
    $now = intdiv(time(), 30);

    foreach ([$now, $now - 1, $now + 1] as $window) {
        $hasil = MemberCode::resolve($membership->digitalCode($window));
        expect($hasil['type'])->toBe('digital')->and($hasil['membership']->is($membership))->toBeTrue();
    }

    expect(MemberCode::resolve($membership->digitalCode($now - 2)))->toBe(['type' => 'expired', 'membership' => null]);

    $code = $membership->digitalCode();
    $tampered = substr($code, 0, -1).(str_ends_with($code, '0') ? '1' : '0');
    expect(MemberCode::resolve($tampered))->toBe(['type' => 'expired', 'membership' => null]);
});

it('mencari kartu lewat UID NFC dalam format apa pun', function () {
    $membership = Membership::factory()->create();
    $membership->pairNfc('04:a1:b2');

    expect($membership->fresh()->nfc_uid)->toBe('04A1B2')
        ->and(MemberCode::byNfc('04 A1 B2')?->is($membership))->toBeTrue()
        ->and(MemberCode::byNfc('FF'))->toBeNull();

    $membership->replaceCard();
    expect(MemberCode::byNfc('04A1B2'))->toBeNull();
});

it('kartu fisik aktif hanya untuk periode membership saat diaktifkan', function () {
    $membership = Membership::factory()->create(['expires_at' => now()->addDays(30)]);
    expect($membership->cardActive())->toBeFalse();

    $membership->activateCard();
    expect($membership->fresh()->cardActive())->toBeTrue();

    // Perpanjangan memajukan expires_at -> kartu perlu diaktifkan lagi.
    $membership->update(['expires_at' => now()->addDays(395)]);
    expect($membership->fresh()->cardActive())->toBeFalse();

    $lewat = now()->subDay()->startOfSecond();
    $membership->update(['expires_at' => $lewat, 'card_activated_until' => $lewat]);
    expect($membership->fresh()->cardActive())->toBeFalse();
});
