<?php

namespace App\Notifications;

use App\Models\RewardRedemption;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class RewardRedemptionProcessingNotification extends Notification
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
        $item = $this->redemption->rewardItem->name;
        $catatan = $this->redemption->admin_note;

        return [
            'title' => 'Penukaran hadiah sedang diproses',
            'body' => $catatan
                ? "Penukaran {$item} sedang diproses. {$catatan}"
                : "Penukaran {$item} sedang diproses.",
            'url' => route('membership.redemptions'),
        ];
    }
}
