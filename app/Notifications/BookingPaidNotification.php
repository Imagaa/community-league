<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\MembershipSettings;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use App\Support\CheckinToken;
use App\Support\TicketQrCode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class BookingPaidNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(private readonly Booking $booking) {}

    /**
     * 'database'/webpush cuma buat notifiable User sungguhan -- booking tamu lewat
     * Notification::route('mail', $email) (lihat Booking::sendPaidNotification()) mengirim ke
     * AnonymousNotifiable, yang TIDAK bisa terima channel database atau webpush (tidak ada baris
     * user buat disimpan notifikasi/subscription-nya).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database', WebPushChannel::class] : ['mail'];
    }

    /** @return array{title: string, body: string, url: string} */
    public function toDatabase(object $notifiable): array
    {
        $booking = $this->booking->loadMissing('match');
        $poinTotal = $this->poinTotal($booking);

        return [
            'title' => 'Pembayaran berhasil',
            'body' => $poinTotal > 0
                ? "Booking {$booking->match->title} sudah lunas. Anda berhak atas {$poinTotal} poin kalau hadir."
                : "Booking {$booking->match->title} sudah lunas.",
            'url' => $booking->urlFor('booking.show'),
        ];
    }

    public const QR_CID = 'qr-tiket@communityleague.id';

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing('match.venue', 'user');
        $qrPng = TicketQrCode::png(CheckinToken::encode($booking));

        return (new MailMessage)
            ->subject('E-Ticket Lunas — '.$booking->match->title)
            ->markdown('mail.e-ticket', [
                'booking' => $booking,
                'qrCid' => self::QR_CID,
                'poinTotal' => $this->poinTotal($booking),
                'ticketUrl' => $booking->urlFor('booking.show'),
            ])
            // QR inline (Content-ID), bukan lampiran -- tampil langsung di badan email.
            ->withSymfonyMessage(fn (Email $email) => $email->addPart(
                (new DataPart($qrPng, 'qr-'.$booking->booking_code.'.png', 'image/png'))
                    ->asInline()
                    ->setContentId(self::QR_CID)
            ));
    }

    /** Poin hanya disebut saat fitur membership aktif (saat off semua teks poin disembunyikan). */
    private function poinTotal(Booking $booking): int
    {
        return MembershipSettings::enabled() ? $booking->match->points_reward * $booking->quantity : 0;
    }
}
