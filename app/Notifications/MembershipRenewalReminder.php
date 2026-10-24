<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class MembershipRenewalReminder extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public readonly int $daysRemaining) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', WebPushChannel::class];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $judul = $this->title();

        return (new MailMessage)
            ->subject($judul)
            ->greeting("Halo {$notifiable->name},")
            ->line($judul.'.')
            ->line('Perpanjang sekarang supaya poin Anda tidak hangus.')
            ->action('Perpanjang Membership', route('membership'));
    }

    /** @return array{title: string, body: string, url: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => 'Perpanjang sekarang supaya poin Anda tidak hangus.',
            'url' => route('membership'),
        ];
    }

    private function title(): string
    {
        return $this->daysRemaining > 0
            ? "Membership Anda berakhir dalam {$this->daysRemaining} hari"
            : 'Membership Anda berakhir hari ini';
    }
}
