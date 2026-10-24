<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class CheckinPointsNotification extends Notification
{
    use SendsWebPush;

    public function __construct(
        private readonly Booking $booking,
        private readonly int $points,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Poin masuk',
            'body' => "Anda dapat +{$this->points} poin dari check-in {$this->booking->match->title}.",
            'url' => $this->booking->urlFor('booking.show'),
        ];
    }
}
