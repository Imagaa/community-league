<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class PointsForfeitedNotification extends Notification
{
    use SendsWebPush;

    public function __construct(private readonly int $amount) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Poin hangus',
            'body' => "{$this->amount} poin Anda hangus karena membership tidak diperpanjang dalam masa tenggang.",
            'url' => route('membership'),
        ];
    }
}
