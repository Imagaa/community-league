<?php

namespace App\Actions\Membership;

use App\Models\Membership;
use App\Models\MembershipPurchase;
use App\Models\MembershipSettings;
use App\Services\Sumopod\SumopodClient;

/**
 * "Perpanjang sekarang" dari scanner: tagihan QRIS Sumopod untuk member yang sedang di depan
 * panitia. activate_card -> saat webhook lunas, kartu yang sedang dipegang langsung aktif.
 */
class StartVenueRenewal
{
    public function __construct(private SumopodClient $sumopod) {}

    public function handle(Membership $membership): MembershipPurchase
    {
        // Tombol ditekan dua kali -> pakai tagihan yang masih berlaku (link Sumopod berlaku 1 jam).
        $pending = MembershipPurchase::query()
            ->where('user_id', $membership->user_id)->where('activate_card', true)->where('status', 'pending_payment')
            ->whereNotNull('sumopod_payment_link_url')->where('created_at', '>', now()->subMinutes(50))
            ->latest('id')->first();
        if ($pending !== null) {
            return $pending;
        }

        $purchase = MembershipPurchase::create([
            'user_id' => $membership->user_id,
            'amount' => MembershipSettings::current()->price,
            'activate_card' => true,
        ]);
        $payment = $this->sumopod->createPayment($purchase);
        $purchase->update(['sumopod_payment_link_url' => $payment['payment_link_url']]);

        return $purchase;
    }
}
