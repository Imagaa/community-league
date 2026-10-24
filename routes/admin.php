<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CardScannerController;
use App\Http\Controllers\Admin\FinanceCsvController;
use App\Http\Controllers\Admin\PaymentProofController;
use App\Http\Controllers\MemberCardController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

/*
 * Dashboard admin custom di ADMIN_DOMAIN. Di-require PALING ATAS web.php: rute tanpa domain()
 * (mis. "/" dan "/match" publik) cocok untuk host mana pun, jadi rute admin harus terdaftar
 * lebih dulu supaya menang di domain admin. Sesi cookie per-host (SESSION_DOMAIN null), jadi
 * login admin terpisah dari login web publik.
 */
Route::domain(config('app.admin_domain'))->name('admin.')->group(function () {
    Route::get('/masuk', [AuthController::class, 'show'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');

    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');

        Route::redirect('/', '/verifikasi');
        Route::livewire('/verifikasi', 'pages::admin.verifikasi')->name('verifikasi');
        Route::get('/bukti/{booking}', PaymentProofController::class)->name('bukti');

        Route::livewire('/match', 'pages::admin.match')->name('match');
        Route::livewire('/match/baru', 'pages::admin.match-form')->name('match.create');
        Route::livewire('/match/{match}', 'pages::admin.match-form')->name('match.edit');
        Route::livewire('/match/{match}/pemesan', 'pages::admin.match-pemesan')->name('match.pemesan');

        Route::livewire('/keuangan', 'pages::admin.keuangan')->name('keuangan');
        Route::get('/keuangan/csv', FinanceCsvController::class)->name('keuangan.csv');

        Route::livewire('/berita', 'pages::admin.berita')->name('berita');
        Route::livewire('/berita/baru', 'pages::admin.berita-form')->name('berita.create');
        Route::livewire('/berita/{post}', 'pages::admin.berita-form')->name('berita.edit');

        Route::livewire('/akun', 'pages::admin.akun')->name('akun');
        Route::livewire('/akun/{user}', 'pages::admin.akun-detail')->name('akun.show');

        Route::livewire('/membership', 'pages::admin.membership')->name('membership');
        Route::get('/membership/kartu/{membership}/{side}', [MemberCardController::class, 'print'])
            ->whereIn('side', ['depan', 'belakang'])
            ->name('membership.card');
        Route::get('/membership/kartu-desain/{side}', [MemberCardController::class, 'template'])
            ->whereIn('side', ['front', 'back'])
            ->name('membership.card-template');
        Route::get('/membership/kartu-contoh/{side}', [MemberCardController::class, 'sample'])
            ->whereIn('side', ['front', 'back'])
            ->name('membership.card-sample');
        Route::get('/kartu-scanner', [CardScannerController::class, 'show'])->name('card-scanner');
        Route::post('/kartu-scanner/scan', [CardScannerController::class, 'scan'])->name('card-scanner.scan');
        Route::post('/kartu-scanner/hubungkan', [CardScannerController::class, 'link'])->name('card-scanner.link');
        Route::post('/kartu-scanner/ganti', [CardScannerController::class, 'replace'])->name('card-scanner.replace');
        Route::post('/kartu-scanner/perpanjang', [CardScannerController::class, 'renew'])->name('card-scanner.renew');
        Route::get('/kartu-scanner/perpanjang/{purchase}', [CardScannerController::class, 'renewStatus'])->name('card-scanner.renew.status');
        Route::livewire('/membership/store/baru', 'pages::admin.store-form')->name('membership.store.create');
        Route::livewire('/membership/store/{item}', 'pages::admin.store-form')->name('membership.store.edit');
        Route::livewire('/setelan', 'pages::admin.setelan')->name('setelan');
    });
});
