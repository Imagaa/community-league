<?php

use App\Models\User;
use App\Notifications\TestNotification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

it('dikirim lewat channel database dan webpush', function () {
    $notification = new TestNotification;

    expect($notification->via(new User))->toBe(['database', WebPushChannel::class]);
});

it('toWebPush mengembalikan pesan dengan title dan body', function () {
    $message = (new TestNotification)->toWebPush(new User);

    expect($message)->toBeInstanceOf(WebPushMessage::class);

    $array = $message->toArray();
    expect($array['title'])->toBe('Notifikasi uji coba')
        ->and($array['body'])->toContain('push browser bekerja dengan benar')
        ->and($array['data'])->toBe(['url' => '/']);
});
