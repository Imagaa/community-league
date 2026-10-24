<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CheckinScannerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\MemberCardController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\SandboxGateController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\Webhooks\SumopodWebhookController;
use App\Http\Middleware\EnsureMembershipEnabled;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

require __DIR__.'/admin.php';

// Rute gate sendiri harus di luar jangkauan gate-nya (dikecualikan lewat nama rute di
// SandboxGate) supaya tidak infinite redirect.
Route::get('/akses-staging', [SandboxGateController::class, 'show'])->name('sandbox-gate.show');
Route::post('/akses-staging', [SandboxGateController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('sandbox-gate.verify');

Route::view('/syarat-ketentuan', 'pages.legal.terms')->name('legal.terms');
Route::view('/kebijakan-privasi', 'pages.legal.privacy')->name('legal.privacy');

// Tujuan VerifiedEmailResponse (App\Http\Responses\Auth) -- halaman statis "berhasil
// diverifikasi", bukan lagi banner di home.
Route::view('/email/verified', 'pages.auth.email-verified')->name('verification.success');

// Di produksi ini tidak pernah tersentuh -- Caddy menyajikan public/manifest.json langsung
// sebagai file statis. Rute ini cuma jaring pengaman untuk test (kernel testing Laravel tidak
// menyajikan file statis sama sekali) dan untuk server dev tanpa webserver static-file layer.
Route::get('/manifest.json', fn () => response()
    ->json(json_decode(File::get(public_path('manifest.json')), true))
    ->header('Content-Type', 'application/manifest+json'));

Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])
    ->name('auth.google.redirect');

Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->name('auth.google.callback');

// Publik -- guest boleh baca berita tanpa akun, sama seperti match.
Route::get('/berita/{post:slug}', [PostController::class, 'show'])->name('berita.show');

// Publik -- guest boleh lihat & cari match tanpa akun.
Route::get('/match', [MatchController::class, 'index'])->name('match');
Route::get('/match/{match:slug}', [MatchController::class, 'show'])->name('match.show');
Route::get('/match/{match:slug}/og.png', [MatchController::class, 'ogImage'])
    ->middleware('throttle:60,1')
    ->name('match.og');
// Throttle karena Turnstile bisa non-aktif (secret kosong => rule lolos otomatis); tanpa ini
// satu guest bisa spam booking sampai kapasitas match habis.
Route::post('/match/{match:slug}/booking', [BookingController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('booking.store');
Route::get('/booking/{booking}', [BookingController::class, 'show'])->name('booking.show');
Route::get('/booking/{booking}/payment', [BookingController::class, 'payment'])->name('booking.payment');
Route::post('/booking/{booking}/bukti', [BookingController::class, 'uploadProof'])
    ->middleware('throttle:6,1')
    ->name('booking.proof');
Route::post('/booking/{booking}/payment/sumopod', [BookingController::class, 'payWithSumopod'])
    ->middleware('throttle:10,1')
    ->name('booking.payment.sumopod');
if (config('services.sumopod.sandbox_bypass')) {
    Route::post('/booking/{booking}/payment/bypass', [BookingController::class, 'bypass'])
        ->name('booking.payment.bypass');
}
Route::livewire('/booking/{booking}/payment/success', 'pages::bookings.payment-success')
    ->name('booking.payment.success');

// Scanner check-in publik per match (link rahasia, dibagikan ke panitia) -- lihat CheckinScannerController.
Route::get('/checkin/{token}', [CheckinScannerController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('checkin.scanner');
Route::post('/checkin/{token}', [CheckinScannerController::class, 'scan'])
    ->middleware('throttle:120,1')
    ->name('checkin.scan');
Route::post('/checkin/{token}/perpanjang', [CheckinScannerController::class, 'renew'])
    ->middleware('throttle:20,1')
    ->name('checkin.renew');
Route::get('/checkin/{token}/perpanjang/{purchase}', [CheckinScannerController::class, 'renewStatus'])
    ->middleware('throttle:60,1')
    ->name('checkin.renew.status');
Route::post('/checkin/{token}/hubungkan', [CheckinScannerController::class, 'link'])
    ->middleware('throttle:30,1')
    ->name('checkin.link');

Route::post('/webhooks/sumopod', SumopodWebhookController::class)
    ->name('webhooks.sumopod');
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/membership', [MembershipController::class, 'index'])->name('membership');
Route::get('/membership/kartu', [MemberCardController::class, 'mine'])
    ->middleware('auth')
    ->name('membership.card');
Route::middleware(EnsureMembershipEnabled::class)->group(function () {
    Route::post('/membership/login-intent', [MembershipController::class, 'loginIntent'])
        ->name('membership.login-intent');
    Route::post('/membership/purchase', [MembershipController::class, 'purchase'])
        ->middleware('auth')
        ->name('membership.purchase');
    Route::get('/membership/purchase/{purchase}/payment', [MembershipController::class, 'payment'])
        ->middleware('auth')
        ->name('membership.payment');
    if (config('services.sumopod.sandbox_bypass')) {
        Route::post('/membership/purchase/{purchase}/payment/bypass', [MembershipController::class, 'bypass'])
            ->middleware('auth')
            ->name('membership.payment.bypass');
    }
    Route::get('/membership/kartu/kode', [MemberCardController::class, 'code'])
        ->middleware(['auth', 'throttle:30,1'])
        ->name('membership.card.code');
    Route::get('/membership/aktifkan-kartu', [MemberCardController::class, 'activate'])
        ->middleware('auth')
        ->name('membership.card.activate');
    Route::post('/membership/aktifkan-kartu', [MemberCardController::class, 'activateStore'])
        ->middleware(['auth', 'throttle:30,1'])
        ->name('membership.card.activate.store');
    Route::livewire('/membership/penukaran', 'pages::membership.redemptions')
        ->middleware('auth')
        ->name('membership.redemptions');

    Route::get('/marketplace', [MarketplaceController::class, 'index'])
        ->middleware('auth')
        ->name('marketplace');
    Route::get('/marketplace/{item}', [MarketplaceController::class, 'show'])
        ->middleware('auth')
        ->name('marketplace.show');
});

Route::view('/feed', 'pages.feed')->name('feed');
Route::get('/akun/tiket-saya', [TicketController::class, 'index'])
    ->middleware('auth')
    ->name('tiket-saya');

require __DIR__.'/akun.php';
