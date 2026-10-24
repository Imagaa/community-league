<?php

namespace App\Models;

use Database\Factories\PointsTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger poin -- satu-satunya sumber kebenaran saldo (User::pointsBalance() = SUM(amount)).
 * `type`: 'admin_grant' (positif, dibuat admin lewat panel), 'redemption' (negatif, penukaran
 * marketplace), 'forfeiture' (negatif, hangus karena membership lapsed lewat masa tenggang).
 *
 * @property int $id
 * @property int $user_id
 * @property int $amount
 * @property string $type
 * @property string|null $description
 * @property int|null $created_by
 */
#[Fillable(['user_id', 'amount', 'type', 'description', 'created_by'])]
class PointsTransaction extends Model
{
    /** @use HasFactory<PointsTransactionFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
