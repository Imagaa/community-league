<?php

use App\Actions\Membership\ScanMemberCard;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\Membership;
use App\Models\MembershipSettings;

beforeEach(fn () => MembershipSettings::current()->update(['enabled' => true]));

function tiketMember(Membership $membership, GameMatch $match, ?bool $memberAtPurchase = true): Booking
{
    return Booking::factory()->for($match, 'match')->for($membership->user)->create([
        'status' => 'paid', 'quantity' => 1, 'checked_in_at' => null, 'member_at_purchase' => $memberAtPurchase,
    ]);
}

it('scan kartu fisik: aktifkan otomatis bila sudah diperpanjang, lalu check-in + poin', function () {
    $membership = Membership::factory()->create(['expires_at' => now()->addMonth()]);
    $match = GameMatch::factory()->create(['points_reward' => 15]);
    tiketMember($membership, $match);

    $hasil = app(ScanMemberCard::class)->scan('physical', $membership, $match);

    expect($hasil['result'])->toBe('ok')
        ->and($hasil['detail'])->toContain('+15 poin')
        ->and($hasil['notes'][0])->toContain('Kartu diaktifkan')
        ->and($hasil['actions'])->toBe([])
        ->and($membership->fresh()->cardActive())->toBeTrue()
        ->and($membership->user->pointsBalance())->toBe(15);

    // Scan kedua: tidak dobel.
    $lagi = app(ScanMemberCard::class)->scan('nfc', $membership->fresh(), $match);
    expect($lagi['result'])->toBe('already')->and($lagi['notes'])->toBe([])
        ->and($membership->user->pointsBalance())->toBe(15);
});

it('scan kartu digital tidak mengaktifkan kartu fisik dan menawarkan hubungkan kartu', function () {
    $membership = Membership::factory()->create();

    $hasil = app(ScanMemberCard::class)->scan('digital', $membership, null);

    expect($hasil['result'])->toBe('info')->and($hasil['actions'])->toBe(['link'])
        ->and($membership->fresh()->cardActive())->toBeFalse();
});

it('membership habis: tiket dibeli saat aktif tetap check-in + poin, dengan tawaran perpanjang', function () {
    $membership = Membership::factory()->create(['expires_at' => now()->subDays(3)]);
    $match = GameMatch::factory()->create(['points_reward' => 10]);
    tiketMember($membership, $match, true);

    $hasil = app(ScanMemberCard::class)->scan('physical', $membership, $match);

    expect($hasil['result'])->toBe('ok')->and($hasil['actions'])->toBe(['renew'])
        ->and(implode(' ', $hasil['notes']))->toContain('Membership habis')
        ->and($membership->fresh()->cardActive())->toBeFalse()
        ->and($membership->user->pointsBalance())->toBe(10);
});

it('member tanpa tiket match ini: tidak check-in', function () {
    $membership = Membership::factory()->create();

    $hasil = app(ScanMemberCard::class)->scan('physical', $membership, GameMatch::factory()->create());

    expect($hasil['result'])->toBe('no_ticket')->and($membership->fresh()->cardActive())->toBeTrue();
});

it('hubungkan kartu: QR harus milik member yang sama; chip NFC baru terpasang, chip orang lain ditolak', function () {
    $a = Membership::factory()->create();
    $b = Membership::factory()->create();
    $aksi = app(ScanMemberCard::class);

    expect($aksi->link($a, $b->cardCode(), null)['ok'])->toBeFalse()
        ->and($aksi->link($a, 'teks-acak', null)['ok'])->toBeFalse()
        ->and($aksi->link($a, $a->cardCode(), null)['ok'])->toBeTrue()
        ->and($a->fresh()->cardActive())->toBeTrue();

    expect($aksi->link($a, null, '04:AA:BB')['ok'])->toBeTrue()
        ->and($a->fresh()->nfc_uid)->toBe('04AABB');

    $tolak = $aksi->link($b, null, '04aabb');
    expect($tolak['ok'])->toBeFalse()->and($tolak['message'])->toContain('member lain')
        ->and($a->fresh()->nfc_uid)->toBe('04AABB')->and($b->fresh()->nfc_uid)->toBeNull();
});

it('pemegang e-tiket yang membership-nya habis ditawari perpanjang', function () {
    $membership = Membership::factory()->create(['expires_at' => now()->subDay()]);
    $booking = tiketMember($membership, GameMatch::factory()->create());

    $info = app(ScanMemberCard::class)->forTicketHolder($booking);

    expect($info['actions'])->toBe(['renew'])->and($info['member']['id'])->toBe($membership->id)
        ->and(app(ScanMemberCard::class)->forTicketHolder(Booking::factory()->create())['actions'])->toBe([]);
});
