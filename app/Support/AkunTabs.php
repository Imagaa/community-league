<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * Daftar sub-tab generik halaman Akun (di luar Wallet dan Tiket Saya, yang dipromosikan ke
 * posisi sendiri di atas list ini karena lebih sering dipakai -- lihat akun-menu.blade.php).
 * Satu sumber dipakai bareng oleh komponen tab-bar (pill horizontal, di dalam tiap halaman
 * akun/*) dan halaman menu /akun (list vertikal, tampil sekali begitu masuk dari bottom-nav).
 */
class AkunTabs
{
    /** @return Collection<int, array{route: string, label: string, icon: string}> */
    public static function all(): Collection
    {
        return collect([
            ['route' => 'akun.profil', 'label' => 'Profil', 'icon' => 'user'],
            ['route' => 'akun.keamanan', 'label' => 'Keamanan', 'icon' => 'shield-check'],
            ['route' => 'akun.notifikasi', 'label' => 'Notifikasi', 'icon' => 'bell'],
            ['route' => 'akun.riwayat', 'label' => 'Riwayat', 'icon' => 'clock'],
            ['route' => 'akun.bantuan', 'label' => 'Bantuan', 'icon' => 'question-mark-circle'],
        ])->filter(fn (array $tab) => Route::has($tab['route']))->values();
    }
}
