<?php

namespace Tests\Feature\Notifications;

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\MembershipSettings;
use App\Models\User;
use App\Notifications\BookingPaidNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class BookingPaidNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mengirim_notifikasi_ke_user_yang_login(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $match = GameMatch::factory()->create(['points_reward' => 10]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'match_id' => $match->id,
            'quantity' => 2,
        ]);

        $booking->sendPaidNotification();

        Notification::assertSentTo($user, BookingPaidNotification::class);
    }

    public function test_mengirim_notifikasi_ke_email_tamu(): void
    {
        Notification::fake();

        $match = GameMatch::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'tamu@example.test',
            'match_id' => $match->id,
        ]);

        $booking->sendPaidNotification();

        Notification::assertSentOnDemand(BookingPaidNotification::class);
    }

    public function test_email_e_ticket_menampilkan_qr_inline_bukan_lampiran(): void
    {
        $user = User::factory()->create();
        $match = GameMatch::factory()->create(['title' => 'Liga Jumat']);
        $booking = Booking::factory()->create(['user_id' => $user->id, 'match_id' => $match->id, 'status' => 'paid', 'unique_code' => 83]);

        $mail = (new BookingPaidNotification($booking))->toMail($user);
        $html = (string) $mail->render();

        expect($mail->rawAttachments)->toBeEmpty()
            ->and($html)->toContain('cid:'.BookingPaidNotification::QR_CID)
            ->and($html)->toContain('LUNAS')
            ->and($html)->toContain('Liga Jumat')
            ->and($html)->toContain($booking->booking_code)
            ->and($html)->toContain('Rp 35.083');

        $email = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($email);
        }
        $part = $email->getAttachments()[0];
        expect($part->getContentId())->toBe(BookingPaidNotification::QR_CID)
            ->and($part->getPreparedHeaders()->getHeaderBody('Content-Disposition'))->toBe('inline');
    }

    public function test_user_login_dapat_notifikasi_in_app_selain_email(): void
    {
        MembershipSettings::current()->update(['enabled' => true]);
        $user = User::factory()->create();
        $match = GameMatch::factory()->create(['points_reward' => 10, 'title' => 'Liga Malam']);
        $booking = Booking::factory()->create(['user_id' => $user->id, 'match_id' => $match->id, 'quantity' => 2]);

        $booking->sendPaidNotification();

        $notification = $user->notifications()->latest()->firstOrFail();
        expect($notification->data['title'])->toBe('Pembayaran berhasil')
            ->and($notification->data['body'])->toContain('Liga Malam')
            ->and($notification->data['body'])->toContain('20 poin')
            ->and($notification->data['url'])->toBe($booking->urlFor('booking.show'));
    }

    public function test_booking_tamu_tidak_membuat_notifikasi_database(): void
    {
        $match = GameMatch::factory()->create();
        $booking = Booking::factory()->create([
            'user_id' => null,
            'guest_email' => 'tamu@example.test',
            'match_id' => $match->id,
        ]);

        $booking->sendPaidNotification();

        expect(DatabaseNotification::count())->toBe(0);
    }
}
