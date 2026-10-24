<?php

use App\Models\MembershipPurchase;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('user login terverifikasi bisa membeli membership', function () {
    Http::fake(['api-pay*.sumopod.com/*' => Http::response(['payment_id' => 'p', 'payment_link_url' => 'https://pay.sumopod.com/p'])]);
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->post(route('membership.purchase'));

    $purchase = MembershipPurchase::first();
    $response->assertRedirect(route('membership.payment', $purchase));
    expect($purchase->user_id)->toBe($user->id)
        ->and($purchase->sumopod_payment_link_url)->toBe('https://pay.sumopod.com/p');
});

it('guest tidak bisa membeli membership', function () {
    $this->post(route('membership.purchase'))->assertRedirect(route('login'));
});

it('user belum verifikasi email tidak bisa membeli membership', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('membership.purchase'))->assertForbidden();

    expect(MembershipPurchase::count())->toBe(0);
});
