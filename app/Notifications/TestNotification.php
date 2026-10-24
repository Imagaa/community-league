<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notifikasi uji coba -- dikirim lewat tombol "Kirim Notifikasi Uji Coba" di Akun > Notifikasi,
 * hanya tampil di local/staging (app()->environment(['local', 'staging'])), lihat
 * pages::akun.notifikasi. Tidak ada logic bisnis di sini, cuma buat verifikasi lonceng/badge
 * benar-benar kepasang tanpa perlu memicu event nyata.
 */
class TestNotification extends Notification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /** @return array{title: string, body: string, url: null} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Notifikasi uji coba',
            'body' => 'Ini notifikasi tes -- kalau Anda melihat ini, lonceng notifikasi bekerja dengan benar.',
            'url' => null,
        ];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Notifikasi uji coba')
            ->body('Ini notifikasi tes -- kalau Anda melihat ini, push browser bekerja dengan benar.')
            ->data(['url' => '/']);
    }
}
