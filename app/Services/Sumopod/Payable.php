<?php

namespace App\Services\Sumopod;

/**
 * Apa pun yang bisa dibayar lewat Sumopod (Booking, MembershipPurchase, dan seterusnya) --
 * SumopodClient hanya butuh empat nilai ini, tidak perlu tahu tipe konkretnya.
 */
interface Payable
{
    public function payableOrderId(): string;

    public function payableAmount(): int;

    public function payableSuccessReturnUrl(): string;

    public function payableCancelReturnUrl(): string;
}
