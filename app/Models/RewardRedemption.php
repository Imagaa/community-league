<?php

namespace App\Models;

use App\Notifications\RewardRedemptionFulfilledNotification;
use App\Notifications\RewardRedemptionProcessingNotification;
use App\Notifications\RewardRedemptionRejectedNotification;
use Database\Factories\RewardRedemptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $reward_item_id
 * @property int $points_spent
 * @property string $status
 * @property string|null $admin_note
 * @property Carbon $redeemed_at
 * @property Carbon|null $fulfilled_at
 * @property int|null $fulfilled_by
 */
#[Fillable(['user_id', 'reward_item_id', 'points_spent', 'status', 'admin_note', 'redeemed_at', 'fulfilled_at', 'fulfilled_by'])]
class RewardRedemption extends Model
{
    /** @use HasFactory<RewardRedemptionFactory> */
    use HasFactory;

    /** Label status TAMPILAN, dipakai admin & tab Penukaran di halaman Membership. */
    public const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'processing' => 'Diproses',
        'fulfilled' => 'Diterima',
        'rejected' => 'Ditolak',
    ];

    /** Warna Filament per status, konsisten sama pola STATUS_COLORS di GameMatch. */
    public const STATUS_COLORS = [
        'pending' => 'zinc',
        'processing' => 'blue',
        'fulfilled' => 'emerald',
        'rejected' => 'red',
    ];

    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<RewardItem, $this> */
    public function rewardItem(): BelongsTo
    {
        return $this->belongsTo(RewardItem::class);
    }

    /**
     * Satu sumber kebenaran buat "kapan user dikabari soal penukarannya" -- dipicu tiap kali
     * status BENERAN berubah (lewat update() dari mana pun: aksi Filament sekarang, atau aksi
     * lain nanti), bukan ditulis manual di tiap tempat yang mengubah status. Cegah notifikasi
     * dobel kalau ada banyak jalur ubah status di masa depan.
     */
    protected static function booted(): void
    {
        static::updated(function (RewardRedemption $redemption): void {
            if (! $redemption->wasChanged('status')) {
                return;
            }

            match ($redemption->status) {
                'fulfilled' => $redemption->user->notify(new RewardRedemptionFulfilledNotification($redemption)),
                'processing' => $redemption->user->notify(new RewardRedemptionProcessingNotification($redemption)),
                'rejected' => $redemption->user->notify(new RewardRedemptionRejectedNotification($redemption)),
                default => null,
            };
        });
    }
}
