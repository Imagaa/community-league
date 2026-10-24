<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\PaymentSettings;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class PaymentProofReceivedNotification extends Notification
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
            'title' => 'Bukti pembayaran diterima',
            'body' => "Pembayaran {$this->booking->match->title} sedang diverifikasi admin.",
            'url' => $this->booking->urlFor('booking.payment'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing('match');

        return (new MailMessage)
            ->subject('Bukti Pembayaran Diterima — '.$booking->match->title)
            ->greeting('Bukti pembayaran Anda sudah kami terima.')
            ->line('Admin sedang memverifikasi pembayaran Anda. E-ticket dikirim ke email ini begitu disetujui.')
            ->line('Kode booking: **'.$booking->booking_code.'**')
            ->line('Nominal: **'.Money::rupiah($booking->amountToPay()).'**')
            ->action('Cek Status Pembayaran', $booking->urlFor('booking.payment'))
            ->line('Ada kendala? [Hubungi admin lewat WhatsApp]('.PaymentSettings::current()->whatsappUrl('Halo admin, kode booking saya '.$booking->booking_code).').');
    }
}
