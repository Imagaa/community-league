<?php

namespace App\Models;

use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['city_id', 'name', 'address', 'maps_url', 'is_active'])]
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Link Maps dari admin; kalau kosong, pencarian Google Maps dari nama + alamat + kota. */
    public function mapsUrl(): string
    {
        return $this->maps_url ?: 'https://www.google.com/maps/search/?api=1&query='
            .rawurlencode(implode(', ', array_filter([$this->name, $this->address, $this->city?->name])));
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<GameMatch, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'venue_id');
    }
}
