<?php

use App\Actions\Membership\StartVenueRenewal;
use App\Models\Membership;
use App\Models\MembershipPurchase;
use App\Models\MembershipSettings;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.sumopod.webhook_token' => 'whtok_rahasia']);
    MembershipSettings::current()->update(['enabled' => true, 'price' => 150000]);
    Http::fake(['api-pay*.sumopod.com/*' => Http::response(['payment_id' => 'p1', 'payment_link_url' => 'https://pay.sumopod.com/p1'])]);
});

function bayarLunas(MembershipPurchase $purchase): void
{
    test()->post(route('webhooks.sumopod'), [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => $purchase->purchase_code, 'payment_id' => 'pay-'.$purchase->id],
    ], ['X-Webhook-Token' => 'whtok_rahasia'])->assertNoContent();
}

it('perpanjang di tempat membuat tagihan QRIS; lunas -> diperpanjang & kartu langsung aktif', function () {
    $membership = Membership::factory()->create(['expires_at' => now()->subDays(5)]);

    $purchase = app(StartVenueRenewal::class)->handle($membership);

    expect($purchase->amount)->toBe(150000)->and($purchase->activate_card)->toBeTrue()
        ->and($purchase->sumopod_payment_link_url)->toBe('https://pay.sumopod.com/p1')
        ->and($purchase->statusPayload())->toMatchArray(['paid' => false]);

    // Tombol ditekan dua kali -> tagihan yang sama dipakai lagi.
    expect(app(StartVenueRenewal::class)->handle($membership)->id)->toBe($purchase->id);

    bayarLunas($purchase);

    $membership->refresh();
    expect($membership->isActive())->toBeTrue()->and($membership->cardActive())->toBeTrue()
        ->and($purchase->fresh()->statusPayload())->toMatchArray(['paid' => true]);
});

it('pembelian membership biasa di web tidak mengaktifkan kartu fisik', function () {
    $membership = Membership::factory()->create(['expires_at' => now()->subDays(5)]);
    $purchase = MembershipPurchase::factory()->create(['user_id' => $membership->user_id, 'status' => 'pending_payment']);

    bayarLunas($purchase);

    expect($membership->fresh()->isActive())->toBeTrue()->and($membership->fresh()->cardActive())->toBeFalse();
});
