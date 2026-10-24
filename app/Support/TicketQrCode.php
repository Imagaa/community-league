<?php

namespace App\Support;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Render token check-in (App\Support\CheckinToken) jadi gambar QR PNG -- dipakai di halaman
 * tiket, lampiran email, dan tombol download. chillerlan/php-qrcode sudah terpasang (dependency
 * transitif Fortify buat QR setup 2FA), tidak perlu package baru.
 */
class TicketQrCode
{
    /** Data URI (data:image/png;base64,...) -- langsung dipakai di <img src="...">. */
    public static function dataUri(string $token): string
    {
        return self::qrCode(imageBase64: true)->render($token);
    }

    /** Bytes PNG mentah -- dipakai buat lampiran email (butuh binary, bukan data URI). */
    public static function png(string $token): string
    {
        return self::qrCode(imageBase64: false)->render($token);
    }

    private static function qrCode(bool $imageBase64): QRCode
    {
        return new QRCode(new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'imageBase64' => $imageBase64,
            'scale' => 8,
            'imageTransparent' => false,
        ]));
    }
}
