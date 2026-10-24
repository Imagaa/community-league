<?php

use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\PointsTransaction;
use App\Models\User;
use App\Notifications\MembershipRenewalReminder;
use Illuminate\Support\Facades\Notification;

it('mengirim reminder H-3 dan menandai last_reminder_at', function () {
    Notification::fake();
    $user = User::factory()->create();
    $membership = Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addDays(3)]);

    $this->artisan('membership:process-renewals')->assertSuccessful();

    Notification::assertSentTo($user, MembershipRenewalReminder::class, fn ($n) => $n->daysRemaining === 3);
    expect($membership->fresh()->last_reminder_at->isToday())->toBeTrue();
});

it('reminder juga masuk sebagai notifikasi in-app', function () {
    $user = User::factory()->create();
    Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addDays(1)]);

    $this->artisan('membership:process-renewals');

    $notification = $user->notifications()->latest()->firstOrFail();
    expect($notification->data['title'])->toBe('Membership Anda berakhir dalam 1 hari')
        ->and($notification->data['url'])->toBe(route('membership'));
});

it('tidak mengirim reminder dua kali di hari yang sama', function () {
    Notification::fake();
    $user = User::factory()->create();
    Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addDays(1), 'last_reminder_at' => now()->toDateString()]);

    $this->artisan('membership:process-renewals');

    Notification::assertNotSentTo($user, MembershipRenewalReminder::class);
});

it('tidak mengirim reminder untuk membership yang bukan H-3/H-2/H-1/H-0', function () {
    Notification::fake();
    $user = User::factory()->create();
    Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->addDays(10)]);

    $this->artisan('membership:process-renewals');

    Notification::assertNotSentTo($user, MembershipRenewalReminder::class);
});

it('menghanguskan poin setelah lewat masa tenggang', function () {
    $user = User::factory()->create();
    $settings = MembershipSettings::current();
    $settings->update(['grace_period_days' => 7]);
    Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subDays(10)]);
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'type' => 'admin_grant']);

    $this->artisan('membership:process-renewals');

    expect($user->pointsBalance())->toBe(0)
        ->and(PointsTransaction::where('user_id', $user->id)->where('type', 'forfeiture')->exists())->toBeTrue();
});

it('mengirim notifikasi in-app saat poin hangus', function () {
    $user = User::factory()->create();
    MembershipSettings::current()->update(['grace_period_days' => 7]);
    Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subDays(10)]);
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'type' => 'admin_grant']);

    $this->artisan('membership:process-renewals');

    $notification = $user->notifications()->latest()->firstOrFail();
    expect($notification->data['title'])->toBe('Poin hangus')
        ->and($notification->data['body'])->toContain('500 poin');
});

it('tidak menghanguskan poin dua kali untuk lapse yang sama', function () {
    $user = User::factory()->create();
    MembershipSettings::current()->update(['grace_period_days' => 7]);
    Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subDays(10), 'points_forfeited_at' => now()->subDay()]);
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'type' => 'admin_grant']);

    $this->artisan('membership:process-renewals');

    expect($user->pointsBalance())->toBe(500);
});

it('belum menghanguskan poin selagi masih dalam masa tenggang', function () {
    $user = User::factory()->create();
    MembershipSettings::current()->update(['grace_period_days' => 7]);
    Membership::factory()->create(['user_id' => $user->id, 'expires_at' => now()->subDays(3)]);
    PointsTransaction::factory()->create(['user_id' => $user->id, 'amount' => 500, 'type' => 'admin_grant']);

    $this->artisan('membership:process-renewals');

    expect($user->pointsBalance())->toBe(500);
});
