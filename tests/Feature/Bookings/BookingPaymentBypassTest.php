<?php

namespace Tests\Feature\Bookings;

use App\Http\Controllers\BookingController;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingPaidNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BookingPaymentBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_tidak_terdaftar_kalau_sandbox_bypass_mati(): void
    {
        // Default env testing: services.sumopod.sandbox_bypass = false (lihat .env.example).
        expect(config('services.sumopod.sandbox_bypass'))->toBeFalsy()
            ->and(Route::has('booking.payment.bypass'))->toBeFalse();
    }

    public function test_pendaftaran_route_di_routes_web_php_dibungkus_kondisi_sandbox_bypass(): void
    {
        // Verifikasi langsung dari source, bukan lewat refreshApplication() (env reload di
        // tengah test terbukti tidak konsisten lintas platform -- lulus di Windows lokal,
        // gagal di CI Linux). Ini memastikan pendaftaran route booking.payment.bypass
        // benar-benar dibungkus pengecekan config, bukan didaftarkan tanpa syarat.
        $source = file_get_contents(base_path('routes/web.php'));

        expect($source)->toContain("if (config('services.sumopod.sandbox_bypass'))")
            ->and($source)->toContain("->name('booking.payment.bypass');");
    }

    public function test_menandai_booking_lunas_saat_dipanggil(): void
    {
        // Menguji logika controller-nya langsung (bukan lewat route, yang pendaftarannya
        // tergantung config saat boot) -- method yang sama persis dipanggil route saat aktif.
        Notification::fake();

        $user = User::factory()->create();
        $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'pending_payment']);

        $response = app(BookingController::class)->bypass(
            Request::create('/', 'POST')->setUserResolver(fn () => $user),
            $booking,
        );

        expect($response->getTargetUrl())->toBe($booking->urlFor('booking.payment.success'))
            ->and($booking->fresh()->status)->toBe('paid');
        Notification::assertSentTo($user, BookingPaidNotification::class);
    }
}
