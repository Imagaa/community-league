<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\PaymentSettings;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class BookingRejectedNotification extends Notification
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
            'title' => 'Pembayaran ditolak',
            'body' => "Pembayaran {$this->booking->match->title} ditolak: {$this->booking->rejection_reason}",
            'url' => route('match.show', $this->booking->match),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing('match');

        return (new MailMessage)
            ->subject('Pembayaran Ditolak — '.$booking->match->title)
            ->greeting('Maaf, pembayaran Anda belum bisa kami terima.')
            ->line('Kode booking: **'.$booking->booking_code.'**')
            ->line('Alasan: '.$booking->rejection_reason)
            ->line('Slot Anda sudah dilepas. Kalau Anda merasa sudah membayar dengan benar, hubungi admin.')
            ->action('Hubungi Admin via WhatsApp', PaymentSettings::current()->whatsappUrl('Halo admin, pembayaran kode booking '.$booking->booking_code.' ditolak.'));
    }
}
