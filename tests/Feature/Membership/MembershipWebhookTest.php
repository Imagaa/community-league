<?php

use App\Models\Booking;
use App\Models\Membership;
use App\Models\MembershipPurchase;
use App\Models\User;

beforeEach(function () {
    config(['services.sumopod.webhook_token' => 'whtok_rahasia']);
});

it('webhook membership.completed membuat membership baru dengan expires_at dari sekarang', function () {
    $user = User::factory()->create();
    $purchase = MembershipPurchase::factory()->create(['user_id' => $user->id, 'status' => 'pending_payment']);

    $this->post(route('webhooks.sumopod'), [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => $purchase->purchase_code, 'payment_id' => 'pay-1'],
    ], ['X-Webhook-Token' => 'whtok_rahasia'])->assertNoContent();

    $membership = Membership::where('user_id', $user->id)->firstOrFail();
    expect($purchase->fresh()->status)->toBe('paid')
        ->and($membership->expires_at->isSameDay(now()->addDays(365)))->toBeTrue();
});

it('renew sebelum expired memperpanjang dari expires_at lama, bukan dari sekarang', function () {
    $user = User::factory()->create();
    $membership = Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addDays(30), 'points_forfeited_at' => now()]);
    $purchase = MembershipPurchase::factory()->create(['user_id' => $user->id, 'status' => 'pending_payment']);

    $this->post(route('webhooks.sumopod'), [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => $purchase->purchase_code, 'payment_id' => 'pay-2'],
    ], ['X-Webhook-Token' => 'whtok_rahasia']);

    $membership->refresh();
    expect($membership->expires_at->isSameDay(now()->addDays(30)->addDays(365)))->toBeTrue()
        ->and($membership->points_forfeited_at)->toBeNull();
});

it('renew setelah lapsed memperpanjang dari sekarang, bukan dari expires_at lama', function () {
    $user = User::factory()->create();
    Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subDays(60)]);
    $purchase = MembershipPurchase::factory()->create(['user_id' => $user->id, 'status' => 'pending_payment']);

    $this->post(route('webhooks.sumopod'), [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => $purchase->purchase_code, 'payment_id' => 'pay-3'],
    ], ['X-Webhook-Token' => 'whtok_rahasia']);

    $membership = Membership::where('user_id', $user->id)->firstOrFail();
    expect($membership->expires_at->isSameDay(now()->addDays(365)))->toBeTrue();
});

it('booking webhook tetap jalan seperti biasa (order_id tanpa prefix MBR)', function () {
    $booking = Booking::factory()->create(['status' => 'pending_payment']);

    $this->post(route('webhooks.sumopod'), [
        'event_type' => 'payment.completed',
        'data' => ['order_id' => $booking->booking_code, 'payment_id' => 'pay-4'],
    ], ['X-Webhook-Token' => 'whtok_rahasia'])->assertNoContent();

    expect($booking->fresh()->status)->toBe('paid');
});
