<?php

namespace App\Notifications;

use App\Models\RewardRedemption;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class RewardRedemptionFulfilledNotification extends Notification
{
    use SendsWebPush;

    public function __construct(private readonly RewardRedemption $redemption) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Penukaran hadiah disetujui',
            'body' => "Penukaran {$this->redemption->rewardItem->name} sudah selesai diproses.",
            'url' => route('membership.redemptions'),
        ];
    }
}
