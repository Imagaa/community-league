<?php

use App\Models\Booking;
use App\Providers\AppServiceProvider;
use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Regresi: URL::forceRootUrl() di AppServiceProvider dulu jalan tanpa syarat, jadi
 * menimpa endpoint internal Livewire (yang tidak domain-scoped seperti route resource
 * Filament) balik ke domain publik walau request-nya sendiri sedang di domain admin.
 * Akibatnya semua form di panel admin (termasuk login) diam saja waktu disubmit --
 * request AJAX-nya cross-origin ke domain yang salah.
 *
 * Dites lewat AppServiceProvider::shouldForceRootUrl() langsung (bukan full HTTP request
 * ke halaman admin) -- Laravel test harness cuma boot Application (dan jalankan boot()
 * provider) SEKALI per test method SEBELUM $this->get(...) dipanggil, jadi tidak bisa
 * dites lewat full page render seperti request sungguhan (production boot per-request
 * asli, tervalidasi manual pakai curl -H "Host: ..." sebelum fix ini di-commit).
 */
it('tidak paksa root url ke domain publik kalau request sedang di domain admin', function () {
    $this->app->instance('request', Request::create('http://'.config('app.admin_domain').'/login'));

    expect(AppServiceProvider::shouldForceRootUrl())->toBeFalse();
});

it('tetap paksa root url ke domain publik kalau request di domain publik', function () {
    $this->app->instance('request', Request::create(config('app.url').'/'));

    expect(AppServiceProvider::shouldForceRootUrl())->toBeTrue();
});

it('link e-ticket di email yang dikirim dari domain admin tetap memakai domain publik', function () {
    // Tiru request produksi di domain admin: root URL TIDAK dipaksa (lihat AppServiceProvider).
    $this->app->instance('request', Request::create('http://'.config('app.admin_domain').'/verifikasi'));
    URL::forceRootUrl(null);
    URL::useOrigin('http://'.config('app.admin_domain'));

    $booking = Booking::factory()->create(['user_id' => null, 'guest_email' => 'tamu@example.test', 'guest_name' => 'Tamu']);
    expect(route('booking.show', $booking))->toContain(config('app.admin_domain'));

    $booking->sendPaidNotification();

    $html = (string) app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage()->getHtmlBody();
    expect($html)->toContain(config('app.url').'/booking/'.$booking->id)
        ->not->toContain(config('app.admin_domain').'/booking')
        ->and(PublicUrl::route('booking.show', $booking))->toStartWith(config('app.url'))
        // Selesai lingkup, URL kembali ke host admin (endpoint Livewire tetap benar).
        ->and(url('/x'))->toContain(config('app.admin_domain'));
});
