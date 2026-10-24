<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;
use Throwable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $nickname
 * @property string $email
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon|null $birthdate
 * @property string|null $shirt_size
 * @property int|null $shoe_size
 * @property string|null $address
 * @property Carbon|null $scheduled_deletion_at
 * @property string|null $avatar_path
 * @property string|null $google_id
 * @property string|null $referral_code
 * @property int|null $referred_by
 * @property int $no_show_count
 * @property string|null $terms_version
 * @property Carbon|null $terms_accepted_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'nickname', 'email', 'phone', 'password',
    'birthdate', 'shirt_size', 'shoe_size', 'address', 'scheduled_deletion_at',
    'avatar_path', 'google_id', 'referral_code', 'referred_by',
    'terms_version', 'terms_accepted_at',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    public const TERMS_VERSION = '2026-08-26';

    /** Boleh masuk dashboard admin (ADMIN_DOMAIN) -- dicek middleware EnsureAdmin. */
    public function isAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Kirim notifikasi verifikasi email. Kegagalan pengiriman (mailer down, domain belum
     * diverifikasi, dst) tidak boleh meruntuhkan registrasi/login -- akun sudah terlanjur
     * dibuat, jadi kegagalan di sini cuma dicatat, bukan dilempar sebagai 500.
     */
    public function sendEmailVerificationNotification(): void
    {
        try {
            $this->notify(new VerifyEmail);
        } catch (Throwable $e) {
            Log::error('Gagal mengirim email verifikasi', [
                'user_id' => $this->id,
                'email' => $this->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
            'birthdate' => 'date',
            'scheduled_deletion_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            $user->referral_code ??= self::generateReferralCode();
        });
    }

    // ponytail: loop tabrakan kode referral memakai query per percobaan (36^8 kemungkinan,
    // tabrakan praktis tidak pernah terjadi). Kalau user mencapai jutaan, ganti retry-on-conflict.
    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** Jumlah tiket "masih aktif" -- lunas dan matchnya belum selesai. Dipakai buat badge di menu Akun. */
    public function activeTicketsCount(): int
    {
        return $this->bookings()
            ->where('status', 'paid')
            ->whereHas('match', fn ($query) => $query->where('ends_at', '>', now()))
            ->count();
    }

    /** @return HasOne<Membership, $this> */
    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    /** @return HasMany<PointsTransaction, $this> */
    public function pointsTransactions(): HasMany
    {
        return $this->hasMany(PointsTransaction::class);
    }

    /** @return HasOne<NotificationPreference, $this> */
    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    /** @return HasMany<MembershipPurchase, $this> */
    public function membershipPurchases(): HasMany
    {
        return $this->hasMany(MembershipPurchase::class);
    }

    /** @return HasMany<RewardRedemption, $this> */
    public function rewardRedemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }

    public function pointsBalance(): int
    {
        return (int) $this->pointsTransactions()->sum('amount');
    }
}
