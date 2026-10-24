<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris tunggal (id=1), di-seed lewat migrasi -- current() TIDAK membuat baris baru di jalur
 * normal, cuma jaring pengaman kalau baris seed pernah terhapus manual.
 *
 * @property int $id
 * @property int $price
 * @property int $duration_days
 * @property int $grace_period_days
 * @property bool $enabled
 * @property string|null $card_front_path
 * @property string|null $card_back_path
 * @property array<string, array<string, mixed>>|null $card_layout
 */
#[Fillable(['price', 'duration_days', 'grace_period_days', 'enabled', 'card_front_path', 'card_back_path', 'card_layout'])]
class MembershipSettings extends Model
{
    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'card_layout' => 'array'];
    }

    /** Saklar admin; saat off halaman membership & semua teks poin disembunyikan. */
    public static function enabled(): bool
    {
        return self::current()->enabled;
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? self::create([
            'id' => 1,
            'price' => 100000,
            'duration_days' => 365,
            'grace_period_days' => 7,
        ]);
    }
}
