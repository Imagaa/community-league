<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Notifications\BookingPaidNotification;
use App\Notifications\BookingPendingPaymentNotification;
use App\Notifications\CheckinPointsNotification;
use App\Notifications\MatchCancelledNotification;
use App\Notifications\MembershipRenewalReminder;
use App\Notifications\PointsForfeitedNotification;
use App\Notifications\RewardRedemptionFulfilledNotification;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

it('semua notifikasi bisnis nyata kirim lewat channel webpush juga', function (Notification $notification) {
    expect($notification->via(User::factory()->make()))->toContain(WebPushChannel::class);

    $message = $notification->toWebPush(User::factory()->make());
    expect($message)->toBeInstanceOf(WebPushMessage::class)
        ->and($message->toArray())->toHaveKeys(['title', 'body', 'data']);
})->with([
    fn () => new MatchCancelledNotification(GameMatch::factory()->create()),
    fn () => new BookingPendingPaymentNotification(Booking::factory()->for(GameMatch::factory(), 'match')->create()),
    fn () => new PointsForfeitedNotification(100),
    fn () => new RewardRedemptionFulfilledNotification(RewardRedemption::factory()->create()),
    fn () => new CheckinPointsNotification(Booking::factory()->for(GameMatch::factory(), 'match')->create(), 10),
    fn () => new MembershipRenewalReminder(3),
]);

it('BookingPaidNotification kirim webpush ke user login, tidak ke tamu', function () {
    $booking = Booking::factory()->for(GameMatch::factory(), 'match')->create();
    $notification = new BookingPaidNotification($booking);

    expect($notification->via(User::factory()->make()))->toContain(WebPushChannel::class)
        ->and($notification->via(new stdClass))->not->toContain(WebPushChannel::class);
});
