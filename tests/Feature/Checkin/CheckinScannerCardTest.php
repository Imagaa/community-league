<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\Membership;
use App\Models\MembershipPurchase;
use App\Models\MembershipSettings;
use App\Support\CheckinToken;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    MembershipSettings::current()->update(['enabled' => true, 'price' => 100000]);
    config(['services.sumopod.webhook_token' => 'whtok_rahasia']);
    Http::fake(['api-pay*.sumopod.com/*' => Http::response(['payment_id' => 'p1', 'payment_link_url' => 'https://pay.sumopod.com/p1'])]);
});

function matchBerScanner(): GameMatch
{
    $match = GameMatch::factory()->create(['points_reward' => 10]);
    $match->scannerUrl();

    return $match->fresh();
}

function scanKartu(GameMatch $match, array $body)
{
    return test()->postJson(route('checkin.scan', $match->scanner_token), $body);
}

it('scan QR kartu fisik atau tap NFC: check-in + poin', function () {
    $match = matchBerScanner();
    $membership = Membership::factory()->create();
    Booking::factory()->for($match, 'match')->for($membership->user)->create(['status' => 'paid', 'quantity' => 1, 'checked_in_at' => null, 'member_at_purchase' => true]);

    scanKartu($match, ['code' => $membership->cardCode()])->assertOk()
        ->assertJson(['result' => 'ok', 'name' => $membership->user->name, 'stats' => ['hadir' => 1, 'total' => 1]]);
    expect($membership->user->pointsBalance())->toBe(10);

    $membership->pairNfc('04AA');
    scanKartu($match, ['nfc' => '04:aa'])->assertOk()->assertJson(['result' => 'already']);
    scanKartu($match, ['nfc' => '99:99'])->assertOk()->assertJson(['result' => 'invalid']);
});

it('kartu digital menawarkan hubungkan; hubungkan NFC hanya untuk member yang sudah discan', function () {
    $match = matchBerScanner();
    $membership = Membership::factory()->create();
    $lain = Membership::factory()->create();

    $this->postJson(route('checkin.link', $match->scanner_token), ['membership_id' => $membership->id, 'nfc' => '04BB'])->assertForbidden();

    scanKartu($match, ['code' => $membership->digitalCode()])->assertOk()->assertJson(['result' => 'no_ticket', 'actions' => ['link']]);
    scanKartu($match, ['code' => $membership->digitalCode(intdiv(time(), 30) - 5)])->assertOk()->assertJson(['result' => 'invalid']);

    $this->postJson(route('checkin.link', $match->scanner_token), ['membership_id' => $membership->id, 'nfc' => '04BB'])
        ->assertOk()->assertJson(['ok' => true]);
    expect($membership->fresh()->nfc_uid)->toBe('04BB');
    $this->postJson(route('checkin.link', $match->scanner_token), ['membership_id' => $lain->id, 'nfc' => '04CC'])->assertForbidden();
});

it('e-tiket milik member yang habis ditawari perpanjang; QRIS dibuat & status lunas terbaca', function () {
    $match = matchBerScanner();
    $membership = Membership::factory()->create(['expires_at' => now()->subDay()]);
    $booking = Booking::factory()->for($match, 'match')->for($membership->user)->create(['status' => 'paid', 'checked_in_at' => null]);

    $this->postJson(route('checkin.renew', $match->scanner_token), ['membership_id' => $membership->id])->assertForbidden();

    scanKartu($match, ['code' => CheckinToken::encode($booking)])->assertOk()
        ->assertJson(['result' => 'ok', 'actions' => ['renew'], 'member' => ['id' => $membership->id]]);

    $renew = $this->postJson(route('checkin.renew', $match->scanner_token), ['membership_id' => $membership->id])
        ->assertOk()->assertJson(['link' => 'https://pay.sumopod.com/p1']);
    $purchase = MembershipPurchase::findOrFail($renew->json('purchase_id'));

    $status = route('checkin.renew.status', [$match->scanner_token, $purchase]);
    $this->getJson($status)->assertOk()->assertJson(['paid' => false]);

    $this->post(route('webhooks.sumopod'), [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => $purchase->purchase_code, 'payment_id' => 'pay-x'],
    ], ['X-Webhook-Token' => 'whtok_rahasia']);

    $this->getJson($status)->assertOk()->assertJson(['paid' => true]);
    expect($membership->fresh()->cardActive())->toBeTrue();
});

it('halaman scanner memuat tombol NFC dan konfigurasi scanner', function () {
    $match = matchBerScanner();

    $this->get(route('checkin.scanner', $match->scanner_token))->assertOk()
        ->assertSee('id="nfc"', false)->assertSee('id="linkbar"', false)
        ->assertSee($match->scanner_token, false)->assertSee('hubungkan', false);
});
