<?php

use App\Models\RewardRedemption;
use App\Notifications\RewardRedemptionFulfilledNotification;
use App\Notifications\RewardRedemptionProcessingNotification;
use App\Notifications\RewardRedemptionRejectedNotification;
use Illuminate\Support\Facades\Notification;

it('mengirim notifikasi fulfilled begitu status berubah jadi fulfilled', function () {
    Notification::fake();
    $redemption = RewardRedemption::factory()->create(['status' => 'pending']);

    $redemption->update(['status' => 'fulfilled']);

    Notification::assertSentTo($redemption->user, RewardRedemptionFulfilledNotification::class);
});

it('mengirim notifikasi processing begitu status berubah jadi processing', function () {
    Notification::fake();
    $redemption = RewardRedemption::factory()->create(['status' => 'pending']);

    $redemption->update(['status' => 'processing', 'admin_note' => 'Sedang dikirim dari gudang.']);

    Notification::assertSentTo($redemption->user, RewardRedemptionProcessingNotification::class);
});

it('mengirim notifikasi rejected begitu status berubah jadi rejected', function () {
    Notification::fake();
    $redemption = RewardRedemption::factory()->create(['status' => 'pending']);

    $redemption->update(['status' => 'rejected', 'admin_note' => 'Stok fisik ternyata habis.']);

    Notification::assertSentTo($redemption->user, RewardRedemptionRejectedNotification::class);
});

it('tidak mengirim notifikasi kalau status tidak berubah', function () {
    Notification::fake();
    $redemption = RewardRedemption::factory()->create(['status' => 'pending']);

    $redemption->update(['admin_note' => 'Catatan tanpa ganti status.']);

    Notification::assertNothingSent();
});

it('catatan admin ikut muncul di body notifikasi rejected', function () {
    $redemption = RewardRedemption::factory()->create(['status' => 'pending']);

    $redemption->update(['status' => 'rejected', 'admin_note' => 'Stok fisik ternyata habis.']);

    $notification = $redemption->user->notifications()->latest()->firstOrFail();
    expect($notification->data['body'])->toContain('Stok fisik ternyata habis.');
});
