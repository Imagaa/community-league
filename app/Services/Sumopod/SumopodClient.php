<?php

namespace App\Services\Sumopod;

use App\Models\PaymentSettings;
use Illuminate\Support\Facades\Http;

class SumopodClient
{
    /** @return array{payment_id: string, payment_link_url: string, status: string, expires_at?: string} */
    public function createPayment(Payable $payable): array
    {
        $settings = PaymentSettings::current();

        $response = Http::withHeaders(['X-Api-Key' => $settings->sumopodApiKey()])
            ->post($settings->sumopodBaseUrl().'/payments', [
                'order_id' => $payable->payableOrderId(),
                'amount' => $payable->payableAmount(),
                'currency' => 'IDR',
                // Default Sumopod 24 jam -- jauh lebih lama dari hold booking kita. Dibuat 1 jam
                // (nilai terkecil, satuannya jam) dan hold booking diperpanjang mengikuti expires_at.
                'expires_in_hours' => 1,
                'success_return_url' => $payable->payableSuccessReturnUrl(),
                'cancel_return_url' => $payable->payableCancelReturnUrl(),
                'payment_method_type_code' => 'QRIS',
            ])
            ->throw();

        return $response->json();
    }
}
