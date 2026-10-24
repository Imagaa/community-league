<?php

namespace App\Models;

use App\Services\Sumopod\Payable;
use Database\Factories\MembershipPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $purchase_code
 * @property int $amount
 * @property string $status
 * @property string|null $sumopod_payment_id
 * @property string|null $sumopod_payment_link_url
 * @property Carbon|null $paid_at
 * @property bool $activate_card
 */
#[Fillable([
    'user_id', 'purchase_code', 'amount', 'status',
    'sumopod_payment_id', 'sumopod_payment_link_url', 'paid_at', 'activate_card',
])]
class MembershipPurchase extends Model implements Payable
{
    /** @use HasFactory<MembershipPurchaseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'activate_card' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $purchase) {
            $purchase->purchase_code ??= self::generatePurchaseCode();
        });
    }

    public static function generatePurchaseCode(): string
    {
        do {
            $code = 'MBR'.strtoupper(Str::random(8));
        } while (self::where('purchase_code', $code)->exists());

        return $code;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array{status: string, paid: bool, expires: string|null} dipakai scanner saat menunggu QRIS dibayar */
    public function statusPayload(): array
    {
        $expires = $this->status === 'paid' ? $this->user->membership?->expires_at : null;

        return [
            'status' => $this->status,
            'paid' => $this->status === 'paid',
            'expires' => $expires?->timezone((string) config('app.display_timezone'))->translatedFormat('d M Y'),
        ];
    }

    public function payableOrderId(): string
    {
        return $this->purchase_code;
    }

    public function payableAmount(): int
    {
        return $this->amount;
    }

    public function payableSuccessReturnUrl(): string
    {
        return route('membership');
    }

    public function payableCancelReturnUrl(): string
    {
        return route('membership.payment', $this);
    }
}
