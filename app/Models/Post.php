<?php

namespace App\Models;

use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string|null $body
 * @property string|null $image_path
 * @property string|null $link_url
 * @property Carbon|null $published_at
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable([
    'type', 'title', 'slug', 'excerpt', 'body', 'image_path', 'link_url',
    'published_at', 'sort_order', 'is_active',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    /** Jumlah maksimal post di hero carousel homepage. */
    public const CAROUSEL_LIMIT = 5;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Post yang boleh tampil ke publik -- aktif DAN sudah lewat waktu terbitnya (atau tidak
     * dijadwalkan sama sekali). published_at null bukan draft di sini -- is_active yang jadi
     * saklar utama, published_at cuma buat jadwal-terbit opsional.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /**
     * Isi hero carousel homepage, dalam urutan tampilnya. Dipakai HomeController dan admin
     * Berita supaya urutan di admin selalu sama persis dengan yang dilihat pengunjung.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopeCarouselOrder(Builder $query): Builder
    {
        return $query->whereIn('type', ['news', 'promo'])
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function isNews(): bool
    {
        return $this->type === 'news';
    }

    public function isPromo(): bool
    {
        return $this->type === 'promo';
    }
}
