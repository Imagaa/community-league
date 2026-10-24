<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class MatchDocumentationNotification extends Notification
{
    use SendsWebPush;

    public function __construct(private readonly Booking $booking) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database', WebPushChannel::class] : ['mail'];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Dokumentasi match sudah ada',
            'body' => "Foto {$this->booking->match->title} sudah bisa dilihat & diunduh.",
            'url' => (string) $this->booking->match->drive_folder_url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $match = $this->booking->match;

        return (new MailMessage)
            ->subject('Terima kasih sudah main! Dokumentasi '.$match->title)
            ->greeting('Terima kasih sudah ikut '.$match->title.'!')
            ->line('Foto-foto dokumentasi match sudah kami unggah. Silakan lihat dan unduh lewat tombol di bawah.')
            ->action('Lihat & unduh semua foto', (string) $match->drive_folder_url)
            ->line('Sampai jumpa di match berikutnya!')
            ->line('[Lihat jadwal match lainnya]('.route('match').')');
    }
}
