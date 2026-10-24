<?php

namespace App\Models;

use App\Notifications\BookingPaidNotification;
use App\Notifications\CheckinPointsNotification;
use App\Services\Sumopod\Payable;
use App\Support\PublicUrl;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $match_id
 * @property int|null $user_id
 * @property string|null $guest_name
 * @property string|null $guest_email
 * @property string|null $guest_phone
 * @property int $quantity
 * @property int $unit_price
 * @property int $total
 * @property string $booking_code
 * @property string $status
 * @property Carbon|null $hold_expires_at
 * @property Carbon|null $checked_in_at
 * @property string|null $team
 * @property string $terms_version
 * @property string|null $sumopod_payment_id
 * @property string|null $sumopod_payment_link_url
 * @property Carbon|null $paid_at
 * @property int|null $unique_code
 * @property string|null $payment_proof_path
 * @property Carbon|null $proof_uploaded_at
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 * @property int $admin_fee
 * @property bool $is_sandbox
 * @property bool|null $member_at_purchase
 */
#[Fillable([
    'match_id', 'user_id', 'guest_name', 'guest_email', 'guest_phone',
    'quantity', 'unit_price', 'total', 'booking_code', 'status',
    'hold_expires_at', 'checked_in_at', 'team', 'terms_version',
    'sumopod_payment_id', 'sumopod_payment_link_url', 'paid_at',
    'unique_code', 'payment_proof_path', 'proof_uploaded_at',
    'verified_by', 'verified_at', 'rejection_reason', 'admin_fee', 'is_sandbox', 'member_at_purchase',
])]
class Booking extends Model implements Payable
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'hold_expires_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'paid_at' => 'datetime',
            'proof_uploaded_at' => 'datetime',
            'verified_at' => 'datetime',
            'is_sandbox' => 'boolean',
            'member_at_purchase' => 'boolean',
        ];
    }

    /**
     * Kode unik 1-999 dipilih acak, menghindari kode milik booking lain dengan harga sama yang
     * masih menunggu bayar/verifikasi -- supaya nominal transfer bisa dicocokkan ke satu booking.
     */
    public function ensureUniqueCode(): int
    {
        if ($this->unique_code) {
            return $this->unique_code;
        }

        $taken = self::query()
            ->where('total', $this->total)
            ->whereIn('status', ['pending_payment', 'awaiting_verification'])
            ->whereNotNull('unique_code')
            ->pluck('unique_code')
            ->all();

        $free = array_values(array_diff(range(1, 999), $taken));
        // ponytail: kalau 999 kode sudah terpakai untuk harga yang sama, kode boleh kembar.
        $code = $free !== [] ? $free[random_int(0, count($free) - 1)] : random_int(1, 999);

        $this->update(['unique_code' => $code]);

        return $code;
    }

    /** Nominal yang ditransfer lewat QRIS manual: harga + biaya admin + kode unik pencocok mutasi. */
    public function amountToPay(): int
    {
        return $this->total + $this->admin_fee + (int) $this->unique_code;
    }

    /** Yang benar-benar dibayar: lewat Sumopod tanpa kode unik, lewat QRIS manual dengan kode unik. */
    public function paidAmount(): int
    {
        return $this->sumopod_payment_id ? $this->payableAmount() : $this->amountToPay();
    }

    public function buyerName(): string
    {
        return $this->user->name ?? (string) $this->guest_name;
    }

    public function buyerEmail(): string
    {
        return $this->user->email ?? (string) $this->guest_email;
    }

    public function buyerPhone(): ?string
    {
        return $this->user ? $this->user->phone : $this->guest_phone;
    }

    protected static function booted(): void
    {
        static::creating(function (self $booking) {
            $booking->booking_code ??= self::generateBookingCode();
        });
    }

    public static function generateBookingCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('booking_code', $code)->exists());

        return $code;
    }

    /** @return BelongsTo<GameMatch, $this> */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /**
     * Tautan booking untuk pemiliknya -- bertanda tangan untuk tamu (tidak ada sesi login untuk
     * membuktikan kepemilikan), polos untuk user login (middleware auth + pengecekan user_id
     * yang sudah cukup).
     */
    public function urlFor(string $routeName): string
    {
        return $this->isGuest()
            ? URL::signedRoute($routeName, $this)
            : route($routeName, $this);
    }

    public function payableOrderId(): string
    {
        return $this->booking_code;
    }

    /** Tagihan ke Sumopod: harga + biaya admin (tanpa kode unik -- Sumopod mencocokkan lewat order_id). */
    public function payableAmount(): int
    {
        return $this->total + $this->admin_fee;
    }

    public function payableSuccessReturnUrl(): string
    {
        return $this->urlFor('booking.payment.success');
    }

    public function payableCancelReturnUrl(): string
    {
        return $this->urlFor('booking.payment');
    }

    public function sendPaidNotification(): void
    {
        $this->notifyBuyer(new BookingPaidNotification($this));
    }

    /** Ke akun pembeli kalau ada, kalau tamu langsung ke email-nya. */
    public function notifyBuyer(BaseNotification $notification): void
    {
        // Sering dipanggil dari dashboard admin (setujui/tolak) -- link di notifikasi harus domain publik.
        PublicUrl::scope(function () use ($notification) {
            if ($this->user) {
                $this->user->notify($notification);

                return;
            }

            Notification::route('mail', $this->guest_email)->notify($notification);
        });
    }

    /**
     * Tandai booking check-in di venue -- poin (points_reward × quantity)
     * masuk otomatis begitu check-in, bukan saat bayar. Idempotent (scan dua kali TIDAK
     * menggandakan poin, dan TIDAK kirim notifikasi dobel) -- dipanggil scanner check-in per
     * match (CheckinScannerController). Booking tamu & non-member tetap ditandai hadir, tapi
     * poin hanya untuk member aktif saat fitur membership dinyalakan.
     *
     * @return bool true kalau baru saja check-in, false kalau sudah check-in sebelumnya (no-op)
     */
    /**
     * Poin check-in: tiket dibeli saat member aktif (dicatat saat booking dibuat), walau saat check-in
     * membership sudah habis. Booking lama tanpa catatan ikut status saat check-in.
     */
    public function earnsMemberPoints(): bool
    {
        if ($this->user === null || ! MembershipSettings::enabled()) {
            return false;
        }

        return $this->member_at_purchase ?? (bool) $this->user->membership?->isActive();
    }

    public function confirmCheckin(): bool
    {
        // Update bersyarat (bukan cek lalu tulis) -- dua scanner yang memindai tiket sama bersamaan
        // tetap hanya satu yang "berhasil", jadi poin tidak pernah dobel.
        $now = now();
        $affected = self::query()->whereKey($this->id)->whereNull('checked_in_at')->update(['checked_in_at' => $now]);

        if ($affected === 0) {
            $this->refresh();

            return false;
        }

        $this->refresh();

        if (! $this->earnsMemberPoints()) {
            return true;
        }

        $poinTotal = $this->match->points_reward * $this->quantity;

        if ($poinTotal > 0) {
            PointsTransaction::create([
                'user_id' => $this->user_id,
                'amount' => $poinTotal,
                'type' => 'match_checkin',
                'description' => "Check-in: {$this->match->title}",
            ]);

            $this->user->notify(new CheckinPointsNotification($this, $poinTotal));
        }

        return true;
    }
}
