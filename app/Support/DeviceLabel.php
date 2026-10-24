<?php

namespace App\Support;

/**
 * Label ringkas dari raw User-Agent buat ditampilkan di daftar perangkat push subscription
 * (Akun > Notifikasi) -- bukan parser lengkap, cuma cukup buat user bedain "HP lama" vs
 * "laptop kerja".
 */
class DeviceLabel
{
    public static function fromUserAgent(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Perangkat tidak dikenal';
        }

        $platform = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Perangkat lain',
        };

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };

        return $browser ? "{$browser} di {$platform}" : $platform;
    }
}
