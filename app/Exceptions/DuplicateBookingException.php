<?php

namespace App\Exceptions;

/**
 * Khusus untuk kasus "sudah punya booking aktif untuk match ini" -- subclass terpisah (bukan
 * cek string pesan) supaya pemanggil (mis. booking-widget) bisa menampilkan tautan "Tiket Saya"
 * hanya untuk kasus ini, tanpa mencocokkan teks pesan yang gampang berubah/typo.
 */
class DuplicateBookingException extends BookingException
{
    //
}
