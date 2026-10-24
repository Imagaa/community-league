<?php

namespace App\Notifications;

use App\Models\GameMatch;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class MatchOpenedReminderNotification extends Notification
{
    use SendsWebPush;

    public function __construct(private readonly GameMatch $match) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Sudah bisa dipesan',
            'body' => "{$this->match->title} sekarang sudah bisa dipesan -- ambil slotnya sebelum kehabisan.",
            'url' => route('match.show', $this->match),
        ];
    }
}
