<?php

namespace App\Models;

use Database\Factories\SandboxAccessCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pasangan email+PIN yang dibuat admin lewat panel (tab Sandbox) untuk memberi akses ke staging
 * yang digerbang SandboxGate -- pengganti Cloudflare Access. Sekali dipakai (di browser mana
 * pun), langsung terbakar (used_at terisi) dan tidak bisa dipakai lagi kecuali admin mereset.
 *
 * @property int $id
 * @property string $email
 * @property string $pin
 * @property Carbon|null $used_at
 */
#[Fillable(['email', 'pin', 'used_at'])]
class SandboxAccessCode extends Model
{
    /** @use HasFactory<SandboxAccessCodeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
}
