<?php

namespace App\Actions\Marketplace;

use App\Exceptions\RedemptionException;
use App\Models\PointsTransaction;
use App\Models\RewardItem;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Support\PublicUrl;
use Illuminate\Support\Facades\DB;

/**
 * Keputusan admin atas penukaran poin: diproses -> diterima, atau ditolak (poin & stok
 * dikembalikan). Baris dikunci + status dicek ulang supaya aman dari dobel klik / dua admin.
 * Notifikasi ke member dikirim otomatis oleh RewardRedemption::booted() saat status berubah.
 */
class HandleRedemption
{
    public function process(RewardRedemption $redemption, ?string $note = null): void
    {
        $this->transition($redemption, ['pending'], ['status' => 'processing', 'admin_note' => $note ?: null]);
    }

    public function fulfill(RewardRedemption $redemption, User $admin): void
    {
        $this->transition($redemption, ['pending', 'processing'], [
            'status' => 'fulfilled',
            'fulfilled_at' => now(),
            'fulfilled_by' => $admin->id,
        ]);
    }

    public function reject(RewardRedemption $redemption, string $reason): void
    {
        $reason = trim($reason);
        throw_if($reason === '', new RedemptionException('Alasan penolakan wajib diisi.'));

        $this->transition($redemption, ['pending', 'processing'], ['status' => 'rejected', 'admin_note' => $reason], refund: true);
    }

    /**
     * @param  list<string>  $from
     * @param  array<string, mixed>  $changes
     */
    private function transition(RewardRedemption $redemption, array $from, array $changes, bool $refund = false): void
    {
        // Dipanggil dari dashboard admin -- link di notifikasi harus domain publik.
        PublicUrl::scope(fn () => DB::transaction(function () use ($redemption, $from, $changes, $refund) {
            $locked = RewardRedemption::query()->lockForUpdate()->findOrFail($redemption->id);

            if (! in_array($locked->status, $from, true)) {
                throw new RedemptionException('Penukaran ini sudah diproses sebelumnya.');
            }

            if ($refund) {
                PointsTransaction::create([
                    'user_id' => $locked->user_id,
                    'amount' => $locked->points_spent,
                    'type' => 'redemption_refund',
                    'description' => 'Poin dikembalikan: penukaran '.$locked->rewardItem->name.' ditolak',
                ]);

                RewardItem::query()->whereKey($locked->reward_item_id)->whereNotNull('stock')->increment('stock');
            }

            $locked->update($changes);
        }));
    }
}
