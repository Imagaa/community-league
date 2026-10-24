<?php

namespace App\Models;

use Database\Factories\MatchReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu user minta diingatkan begitu match tertentu bisa dia pesan. Dihapus begitu
 * notifikasi terkirim (lihat NotifyMatchReminders) -- tidak ada riwayat "sudah pernah
 * diingatkan", cuma status "masih menunggu" atau tidak ada sama sekali.
 *
 * @property int $id
 * @property int $match_id
 * @property int $user_id
 */
#[Fillable(['match_id', 'user_id'])]
class MatchReminder extends Model
{
    /** @use HasFactory<MatchReminderFactory> */
    use HasFactory;

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
}
