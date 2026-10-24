<?php

namespace App\Support;

class WhatsApp
{
    /** Format nomor yang disimpan: 08xxxxxxxx (10-14 digit). */
    public const LOCAL_PATTERN = '/^08[0-9]{8,12}$/';

    /** "+62 857-1343-8267" / "62857..." -> "0857..." (format simpan). */
    public static function local(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($digits, '62') ? '0'.substr($digits, 2) : $digits;
    }

    /** "0857-1343 8267" / "+62857..." -> "62857..." (format wa.me). */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }

    public static function url(string $phone, string $text = ''): string
    {
        return 'https://wa.me/'.self::normalize($phone).($text !== '' ? '?text='.rawurlencode($text) : '');
    }
}
