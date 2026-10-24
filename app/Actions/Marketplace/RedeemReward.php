<?php

namespace App\Actions\Marketplace;

use App\Exceptions\RedemptionException;
use App\Models\PointsTransaction;
use App\Models\RewardItem;
use App\Models\RewardRedemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RedeemReward
{
    public function handle(User $user, RewardItem $item): RewardRedemption
    {
        return DB::transaction(function () use ($user, $item) {
            $locked = RewardItem::query()->lockForUpdate()->findOrFail($item->id);

            if (! $locked->active || ($locked->stock !== null && $locked->stock < 1)) {
                throw new RedemptionException('Stok habis.');
            }

            if ($user->pointsBalance() < $locked->point_cost) {
                throw new RedemptionException('Poin tidak cukup.');
            }

            if ($locked->stock !== null) {
                $locked->decrement('stock');
            }

            PointsTransaction::create([
                'user_id' => $user->id,
                'amount' => -$locked->point_cost,
                'type' => 'redemption',
                'description' => "Tukar poin: {$locked->name}",
            ]);

            return RewardRedemption::create([
                'user_id' => $user->id,
                'reward_item_id' => $locked->id,
                'points_spent' => $locked->point_cost,
                'status' => 'pending',
                'redeemed_at' => now(),
            ]);
        });
    }
}
