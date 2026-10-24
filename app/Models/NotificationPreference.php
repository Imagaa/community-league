<?php

namespace App\Models;

use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $match_reminders
 * @property bool $payment_confirmations
 * @property bool $promotions
 * @property bool $browser_push_enabled
 */
#[Fillable(['user_id', 'match_reminders', 'payment_confirmations', 'promotions', 'browser_push_enabled'])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'match_reminders' => 'boolean',
            'payment_confirmations' => 'boolean',
            'promotions' => 'boolean',
            'browser_push_enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(User $user): self
    {
        return self::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'match_reminders' => true,
                'payment_confirmations' => true,
                'promotions' => false,
                'browser_push_enabled' => false,
            ],
        );
    }
}
