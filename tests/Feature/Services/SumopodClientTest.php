<?php

namespace Tests\Feature\Services;

use App\Models\Booking;
use App\Services\Sumopod\SumopodClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SumopodClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_payment_dengan_payload_benar(): void
    {
        config(['services.sumopod.api_key' => 'test-key']);

        Http::fake([
            'api-pay.sumopod.com/*' => Http::response([
                'payment_id' => 'uuid-123',
                'order_id' => 'BOOKCODE',
                'payment_link_url' => 'https://pay.sumopod.com/pay/uuid-123',
                'status' => 'pending',
            ], 200),
        ]);

        $booking = Booking::factory()->create(['booking_code' => 'BOOKCODE', 'total' => 150000]);

        $result = app(SumopodClient::class)->createPayment($booking);

        expect($result['payment_link_url'])->toBe('https://pay.sumopod.com/pay/uuid-123');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api-pay.sumopod.com/api/v1/payments'
                && $request->hasHeader('X-Api-Key', 'test-key')
                && $request['order_id'] === 'BOOKCODE'
                && $request['amount'] === 150000
                && $request['payment_method_type_code'] === 'QRIS';
        });
    }

    public function test_pakai_base_url_dari_config_bukan_hardcode(): void
    {
        // Sandbox Sumopod pakai domain BEDA (api-pay-sandbox.sumopod.com), bukan cuma API key
        // beda di domain yang sama -- ketemu langsung dari dashboard Sumopod, tidak ada di
        // dokumentasi yang diberikan. base_url wajib bisa dikonfigurasi per environment.
        config([
            'services.sumopod.api_key' => 'test-key',
            'services.sumopod.base_url' => 'https://api-pay-sandbox.sumopod.com/api/v1',
        ]);

        Http::fake([
            'api-pay-sandbox.sumopod.com/*' => Http::response([
                'payment_id' => 'uuid-sandbox',
                'payment_link_url' => 'https://pay.sumopod.com/pay/uuid-sandbox',
            ], 200),
        ]);

        $booking = Booking::factory()->create(['booking_code' => 'SANDBOX1', 'total' => 50000]);

        app(SumopodClient::class)->createPayment($booking);

        Http::assertSent(fn ($request) => $request->url() === 'https://api-pay-sandbox.sumopod.com/api/v1/payments');
    }
}
