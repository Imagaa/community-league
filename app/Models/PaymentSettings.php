<?php

namespace App\Models;

use App\Support\WhatsApp;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris tunggal (id=1), di-seed lewat migrasi -- pola yang sama dengan MembershipSettings.
 * Kunci Sumopod yang kosong di sini jatuh ke env (config/services.php) supaya deployment lama
 * tetap jalan sampai admin mengisinya.
 *
 * @property int $id
 * @property string|null $qris_payload
 * @property string|null $qris_merchant_name
 * @property bool $sumopod_enabled
 * @property string|null $sumopod_mode
 * @property string|null $sumopod_api_key
 * @property string|null $sumopod_webhook_token
 * @property string $admin_whatsapp
 * @property string $admin_fee_percent
 * @property int $admin_fee_flat
 * @property string|null $google_drive_api_key
 */
#[Fillable([
    'qris_payload', 'qris_merchant_name', 'sumopod_enabled', 'sumopod_mode',
    'sumopod_api_key', 'sumopod_webhook_token', 'admin_whatsapp',
    'admin_fee_percent', 'admin_fee_flat', 'google_drive_api_key',
])]
class PaymentSettings extends Model
{
    public const SUMOPOD_SANDBOX_URL = 'https://api-pay-sandbox.sumopod.com/api/v1';

    public const SUMOPOD_LIVE_URL = 'https://api-pay.sumopod.com/api/v1';

    protected function casts(): array
    {
        return [
            'sumopod_enabled' => 'boolean',
            'sumopod_api_key' => 'encrypted',
            'sumopod_webhook_token' => 'encrypted',
            'google_drive_api_key' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return self::query()->find(1) ?? self::create(['id' => 1]);
    }

    /**
     * Biaya admin untuk satu tagihan: persen x harga + flat, dibulatkan ke atas ke Rp100.
     * Harga 0 (match gratis) tidak dikenai biaya.
     */
    public function adminFeeFor(int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $fee = $amount * (float) $this->admin_fee_percent / 100 + $this->admin_fee_flat;

        return (int) (ceil(round($fee, 2) / 100) * 100);
    }

    public function hasQris(): bool
    {
        return filled($this->qris_payload);
    }

    public function sumopodAvailable(): bool
    {
        return $this->sumopod_enabled && filled($this->sumopodApiKey());
    }

    public function sumopodApiKey(): ?string
    {
        return $this->sumopod_api_key ?: config('services.sumopod.api_key');
    }

    public function sumopodWebhookToken(): ?string
    {
        return $this->sumopod_webhook_token ?: config('services.sumopod.webhook_token');
    }

    /** API key Google Drive (akun Community League) untuk menyalin foto dokumentasi. */
    public function googleDriveApiKey(): ?string
    {
        return $this->google_drive_api_key ?: config('services.google.drive_api_key');
    }

    /** Pembayaran Sumopod saat ini memakai sandbox (uang tidak sungguhan)? */
    public function sumopodIsSandbox(): bool
    {
        return str_contains($this->sumopodBaseUrl(), 'sandbox');
    }

    public function sumopodBaseUrl(): string
    {
        return match ($this->sumopod_mode) {
            'sandbox' => self::SUMOPOD_SANDBOX_URL,
            'live' => self::SUMOPOD_LIVE_URL,
            default => (string) config('services.sumopod.base_url'),
        };
    }

    /** Link wa.me ke admin, opsional dengan pesan awal. */
    public function whatsappUrl(string $text = ''): string
    {
        return WhatsApp::url($this->admin_whatsapp, $text);
    }
}
