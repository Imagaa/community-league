<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class BookingPendingPaymentNotification extends Notification
{
    use SendsWebPush;

    public function __construct(private readonly Booking $booking) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toDatabase(object $notifiable): array
    {
        $booking = $this->booking->loadMissing('match');
        $batas = $booking->hold_expires_at?->timezone(config('app.display_timezone'))->translatedFormat('H:i');

        return [
            'title' => 'Selesaikan pembayaran',
            'body' => "Booking {$booking->match->title} menunggu pembayaran, selesaikan sebelum {$batas} WIB atau slot dilepas otomatis.",
            'url' => $booking->urlFor('booking.payment'),
        ];
    }
}
