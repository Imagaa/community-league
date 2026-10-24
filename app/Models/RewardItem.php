<?php

namespace App\Models;

use Database\Factories\RewardItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $image_path
 * @property int $point_cost
 * @property int|null $stock
 * @property bool $active
 * @property int $sort_order
 */
#[Fillable(['name', 'description', 'image_path', 'point_cost', 'stock', 'active', 'sort_order'])]
class RewardItem extends Model
{
    /** @use HasFactory<RewardItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<RewardRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }

    public function isAvailable(): bool
    {
        return $this->active && ($this->stock === null || $this->stock > 0);
    }
}
