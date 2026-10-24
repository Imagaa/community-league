<?php

use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\User;
use App\Support\MemberCode;

beforeEach(fn () => MembershipSettings::current()->update(['enabled' => true]));

it('kode QR digital hanya untuk pemilik membership dan berisi kode CLD1 yang valid', function () {
    $membership = Membership::factory()->create();

    $res = $this->actingAs($membership->user)->getJson(route('membership.card.code'))->assertOk();
    expect($res->json('qr'))->toStartWith('data:image/png;base64,')
        ->and($res->json('refresh_in'))->toBeGreaterThan(0)->toBeLessThanOrEqual(30)
        ->and(MemberCode::resolve($membership->digitalCode())['type'])->toBe('digital');

    $this->actingAs(User::factory()->create())->getJson(route('membership.card.code'))->assertNotFound();
    $this->get(route('membership'))->assertOk();
});

it('halaman membership menampilkan QR digital; banner aktivasi muncul sampai kartu fisik aktif', function () {
    $membership = Membership::factory()->create(['card_printed_at' => now()]);

    $this->actingAs($membership->user)->get(route('membership'))->assertOk()
        ->assertSee(str_replace('/', '\/', route('membership.card.code')), false)
        ->assertSee('Aktifkan kartu fisikmu');

    $this->actingAs($membership->user)->postJson(route('membership.card.activate.store'), ['code' => $membership->cardCode()])
        ->assertOk()->assertJson(['ok' => true, 'result' => 'ok']);

    $this->actingAs($membership->user)->get(route('membership'))->assertDontSee('Aktifkan kartu fisikmu');
});

it('aktivasi mandiri menolak kartu milik orang lain dan memasangkan NFC kosong', function () {
    $membership = Membership::factory()->create(['card_printed_at' => now()]);
    $lain = Membership::factory()->create();

    $this->actingAs($membership->user)->postJson(route('membership.card.activate.store'), ['code' => $lain->cardCode()])
        ->assertOk()->assertJson(['ok' => false, 'result' => 'invalid']);
    $this->actingAs($membership->user)->postJson(route('membership.card.activate.store'), ['nfc' => '04:FF'])
        ->assertOk()->assertJson(['ok' => true]);

    expect($membership->fresh()->nfc_uid)->toBe('04FF')->and($membership->fresh()->cardActive())->toBeTrue();
    $this->actingAs($membership->user)->get(route('membership.card.activate'))->assertOk()->assertSee('id="nfc"', false);
});

it('member yang membership-nya habis tetap punya QR digital', function () {
    $membership = Membership::factory()->create(['expires_at' => now()->subDay()]);

    $this->actingAs($membership->user)->get(route('membership'))->assertOk()->assertSee(str_replace('/', '\/', route('membership.card.code')), false);
});
