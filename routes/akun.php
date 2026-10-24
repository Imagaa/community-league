<?php

use Illuminate\Support\Facades\Route;

Route::get('/akun', function () {
    return auth()->check()
        ? view('pages.akun-menu')
        : view('pages.akun-guest');
})->name('akun');

// Bantuan tidak butuh login -- cuma info kontak statis, tamu juga boleh lihat.
Route::view('akun/bantuan', 'pages.akun.bantuan')->name('akun.bantuan');

Route::middleware(['auth'])->group(function () {
    Route::livewire('akun/profil', 'pages::akun.profil')->name('akun.profil');
    Route::livewire('akun/notifikasi', 'pages::akun.notifikasi')->name('akun.notifikasi');
    Route::livewire('akun/riwayat', 'pages::akun.riwayat')->name('akun.riwayat');
});

Route::middleware(['auth'])->group(function () {
    // BUKAN middleware 'verified' -- tab ini justru menampilkan banner "verifikasi email"
    // buat user yang BELUM verified (dipindah dari akun.blade.php lama). Middleware 'verified'
    // akan mengarahkan user belum-verified ke halaman verify-email duluan sebelum sempat
    // melihat tab ini sama sekali, jadi bannernya tidak akan pernah kelihatan.
    Route::livewire('akun/keamanan', 'pages::akun.keamanan')
        ->middleware(['password.confirm'])
        ->name('akun.keamanan');

    Route::post('/akun/batalkan-hapus', function () {
        auth()->user()->update(['scheduled_deletion_at' => null]);

        return back()->with('status', 'deletion-cancelled');
    })->name('akun.batalkan-hapus');
});
