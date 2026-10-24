<?php

namespace App\Actions\Booking;

use App\Exceptions\BookingException;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingRejectedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan admin atas bukti bayar QRIS manual. Baris booking dikunci dan statusnya dicek ulang
 * di dalam transaksi -- dobel-klik atau dua admin bersamaan tidak bisa menyetujui/menolak dua kali.
 */
class VerifyPayment
{
    public function approve(Booking $booking, User $admin): Booking
    {
        $booking = $this->decide($booking, $admin, [
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $booking->sendPaidNotification();

        return $booking;
    }

    public function reject(Booking $booking, User $admin, string $reason): Booking
    {
        $reason = trim($reason);
        throw_if($reason === '', new BookingException('Alasan penolakan wajib diisi.'));

        // 'rejected' bukan status aktif (GameMatch::ACTIVE_BOOKING_STATUSES) -- slot otomatis lepas.
        $booking = $this->decide($booking, $admin, [
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        $booking->notifyBuyer(new BookingRejectedNotification($booking));

        return $booking;
    }

    /** @param  array<string, mixed>  $changes */
    private function decide(Booking $booking, User $admin, array $changes): Booking
    {
        return DB::transaction(function () use ($booking, $admin, $changes) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($locked->status !== 'awaiting_verification') {
                throw new BookingException('Booking ini sudah diproses sebelumnya.');
            }

            $locked->update($changes + ['verified_by' => $admin->id, 'verified_at' => now()]);

            return $locked;
        });
    }
}
