<?php

namespace App\Models;

use App\Support\MemberCode;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Satu baris per user, dibuat sekali saat pembelian pertama, TIDAK PERNAH dihapus. "Belum pernah
 * beli" = tidak ada baris ini sama sekali; "lapsed" = baris ada tapi expires_at sudah lewat. Jangan
 * tambah kolom status terpisah -- expires_at sendirian sudah cukup membedakan ketiga state
 * halaman membership.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $expires_at
 * @property Carbon|null $last_reminder_at
 * @property Carbon|null $points_forfeited_at
 * @property string|null $card_token
 * @property Carbon|null $card_printed_at
 * @property string|null $nfc_uid
 * @property Carbon|null $card_activated_until
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'expires_at', 'last_reminder_at', 'points_forfeited_at', 'card_token', 'card_printed_at', 'nfc_uid', 'card_activated_until'])]
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_reminder_at' => 'date',
            'points_forfeited_at' => 'datetime',
            'card_printed_at' => 'datetime',
            'card_activated_until' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }

    public function isLapsed(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Nomor member di kartu, mis. CL-00012 -- turunan id, baris membership tidak pernah dihapus. */
    public function memberNumber(): string
    {
        return 'CL-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /** Isi QR kartu fisik (lihat MemberCode) -- token dibuat saat pertama diminta. */
    public function cardCode(): string
    {
        if ($this->card_token === null) {
            $this->update(['card_token' => Str::random(20)]);
        }

        return "CLC1.{$this->card_token}";
    }

    /** Isi QR kartu digital, berganti tiap 30 detik. */
    public function digitalCode(?int $window = null): string
    {
        return MemberCode::digital($this->id, $window);
    }

    /** Kartu fisik aktif untuk periode membership yang sedang berjalan. */
    public function cardActive(): bool
    {
        return $this->isActive() && $this->card_activated_until?->timestamp === $this->expires_at->timestamp;
    }

    public function activateCard(): void
    {
        $this->update(['card_activated_until' => $this->expires_at]);
    }

    public function pairNfc(string $uid): void
    {
        $this->update(['nfc_uid' => MemberCode::normalizeUid($uid)]);
    }

    /** Kartu hilang/rusak: QR & NFC kartu lama langsung tidak berlaku, kartu baru perlu dicetak lagi. */
    public function replaceCard(): void
    {
        $this->update(['card_token' => Str::random(20), 'card_printed_at' => null, 'nfc_uid' => null, 'card_activated_until' => null]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
