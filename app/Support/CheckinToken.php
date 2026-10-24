<?php

namespace App\Support;

use App\Models\Booking;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Token check-in di QR tiket -- cuma `booking_id` yang di-enkripsi lewat Laravel Crypt
 * (AES-256-CBC + MAC bawaan APP_KEY server). QR generic scanner cuma baca teks ciphertext
 * acak tidak bermakna; decode() di sini satu-satunya cara membaca isinya, dan decrypt()
 * otomatis menolak ciphertext yang di-tamper (MAC tidak cocok) atau dibuat tanpa APP_KEY
 * yang sama. Tidak ada expiry yang di-bake ke token -- validitas waktu (match belum lewat,
 * dsb) dicek belakangan lewat data booking/match yang sesungguhnya, bukan lewat token ini.
 */
class CheckinToken
{
    public static function encode(Booking $booking): string
    {
        return Crypt::encryptString((string) $booking->id);
    }

    public static function decode(string $token): ?Booking
    {
        try {
            $id = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        if (! ctype_digit($id)) {
            return null;
        }

        return Booking::find((int) $id);
    }
}
