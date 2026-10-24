<?php

namespace App\Actions\Booking;

use App\Exceptions\BookingException;
use App\Exceptions\DuplicateBookingException;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\PaymentSettings;
use App\Models\User;
use App\Notifications\BookingPendingPaymentNotification;
use Illuminate\Support\Facades\DB;

class CreateBooking
{
    /**
     * Batas slot per booking; dicerminkan oleh aturan `max` di StoreBookingRequest. Diset ke 1
     * (per akun/tamu, per match) supaya manajemen tiket tetap sederhana -- lihat juga cek
     * duplikat booking aktif di bawah.
     */
    public const MAX_QUANTITY = 1;

    /**
     * @param  array{user_id?: int|null, guest_name?: string, guest_email?: string, guest_phone?: string, quantity: int, terms_version: string}  $data
     */
    public function handle(GameMatch $match, array $data): Booking
    {
        $isGuest = empty($data['user_id']);

        if ($isGuest && (empty($data['guest_name']) || empty($data['guest_email']) || empty($data['guest_phone']))) {
            throw new BookingException('Data tamu wajib diisi (nama, email, nomor HP).');
        }

        return DB::transaction(function () use ($match, $data, $isGuest) {
            // Kunci baris match -- transaksi lain yang mencoba mengunci match yang sama akan
            // menunggu sampai transaksi ini commit. Ini yang mencegah dua booking bersamaan
            // sama-sama lolos cek kapasitas.
            $locked = GameMatch::query()->lockForUpdate()->findOrFail($match->id);

            // User (bukan cuma user_id) supaya isBookableBy() bisa cek Membership aktif -- member
            // dengan jendela akses awal (member_opens_at < public_opens_at) boleh lolos di sini
            // sebelum public_opens_at, tamu/non-member tidak.
            $bookingUser = $isGuest ? null : User::find($data['user_id']);

            if (! $locked->isBookableBy($bookingUser)) {
                throw new BookingException(
                    $locked->computedStatus() === 'published'
                        ? 'Match ini belum dibuka untuk umum.'
                        : 'Match ini tidak sedang menerima booking.'
                );
            }

            $quantity = (int) $data['quantity'];

            if ($quantity < 1) {
                throw new BookingException('Jumlah tiket tidak valid.');
            }

            // Batas atas ada di sini, bukan cuma di StoreBookingRequest: action ini satu-satunya
            // batas keamanan uang/kapasitas, dan dipanggil juga dari luar alur form.
            if ($quantity > self::MAX_QUANTITY) {
                throw new BookingException('Maksimal '.self::MAX_QUANTITY.' slot per booking.');
            }

            if ($locked->remainingSlots() < $quantity) {
                throw new BookingException('Slot tidak cukup. Sisa slot: '.$locked->remainingSlots().'.');
            }

            // Satu akun/email hanya boleh punya satu booking aktif per match. Dicek silang: email
            // tamu yang sama dengan email akun (dan sebaliknya) dihitung orang yang sama.
            $email = mb_strtolower(trim($isGuest ? (string) $data['guest_email'] : (string) $bookingUser?->email));
            $userId = $isGuest ? User::whereRaw('lower(email) = ?', [$email])->value('id') : $data['user_id'];

            $sudahPunyaBooking = Booking::query()
                ->where('match_id', $locked->id)
                ->whereIn('status', GameMatch::ACTIVE_BOOKING_STATUSES)
                ->where(fn ($query) => $query
                    ->whereRaw('lower(guest_email) = ?', [$email])
                    ->when($userId, fn ($q) => $q->orWhere('user_id', $userId)))
                ->exists();

            if ($sudahPunyaBooking) {
                throw new DuplicateBookingException('Anda sudah punya booking untuk match ini.');
            }

            // Harga selalu price_regular di rencana ini -- Membership (dan price_member) baru
            // ada di rencana 1E.
            $unitPrice = $locked->price_regular;

            $booking = Booking::create([
                'match_id' => $locked->id,
                'user_id' => $isGuest ? null : $data['user_id'],
                'guest_name' => $isGuest ? $data['guest_name'] : null,
                'guest_email' => $isGuest ? $data['guest_email'] : null,
                'guest_phone' => $isGuest ? $data['guest_phone'] : null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $unitPrice * $quantity,
                'admin_fee' => PaymentSettings::current()->adminFeeFor($unitPrice * $quantity),
                'status' => 'pending_payment',
                'hold_expires_at' => now()->addMinutes(30),
                'terms_version' => $data['terms_version'],
                // Hak poin check-in dikunci saat beli (tamu tidak punya membership).
                'member_at_purchase' => $isGuest ? null : (bool) User::find($data['user_id'])?->membership?->isActive(),
            ]);

            // Tamu tidak punya inbox notifikasi in-app -- cuma user login yang dikirimi.
            if (! $isGuest) {
                $booking->user->notify(new BookingPendingPaymentNotification($booking));
            }

            return $booking;
        });
    }
}
