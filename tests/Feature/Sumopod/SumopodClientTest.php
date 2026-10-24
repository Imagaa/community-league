<?php

use App\Services\Sumopod\Payable;
use App\Services\Sumopod\SumopodClient;
use Illuminate\Support\Facades\Http;

it('createPayment mengirim field dari Payable, bukan tergantung tipe Booking', function () {
    Http::fake([
        'api-pay*.sumopod.com/*' => Http::response(['payment_id' => 'p', 'payment_link_url' => 'https://pay.sumopod.com/p']),
    ]);

    $payable = new class implements Payable
    {
        public function payableOrderId(): string
        {
            return 'FAKE123';
        }

        public function payableAmount(): int
        {
            return 100000;
        }

        public function payableSuccessReturnUrl(): string
        {
            return 'https://example.test/success';
        }

        public function payableCancelReturnUrl(): string
        {
            return 'https://example.test/cancel';
        }
    };

    (new SumopodClient)->createPayment($payable);

    Http::assertSent(fn ($request) => $request['order_id'] === 'FAKE123' && $request['amount'] === 100000);
});
