<?php

namespace App\Notifications\Concerns;

use NotificationChannels\WebPush\WebPushMessage;

/**
 * Dipakai notification class yang toDatabase()-nya sudah punya bentuk
 * array{title: string, body: string, url: string} -- reuse data yang sama buat payload push,
 * tanpa nulis toWebPush() manual di tiap class.
 */
trait SendsWebPush
{
    public function toWebPush(object $notifiable): WebPushMessage
    {
        /** @var array{title: string, body: string, url: string} $data */
        $data = $this->toDatabase($notifiable);

        return (new WebPushMessage)
            ->title($data['title'])
            ->body($data['body'])
            ->data(['url' => $data['url']]);
    }
}
