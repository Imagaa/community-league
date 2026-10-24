<?php

namespace Tests\Feature\Webhooks;

use App\Models\Booking;
use App\Models\GameMatch;
use App\Notifications\BookingPaidNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SumopodWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.sumopod.webhook_token' => 'whtok_rahasia']);
    }

    public function test_menolak_token_salah(): void
    {
        $this->postJson('/webhooks/sumopod', ['event_type' => 'payment.completed'], [
            'X-Webhook-Token' => 'salah',
        ])->assertStatus(401);
    }

    public function test_payment_completed_menandai_booking_lunas_dan_kirim_notifikasi(): void
    {
        Notification::fake();

        $booking = Booking::factory()->create(['booking_code' => 'ABCD1234', 'status' => 'pending_payment']);

        $this->postJson('/webhooks/sumopod', [
            'event_type' => 'payment.completed',
            'data' => ['payment_id' => 'pay-1', 'order_id' => 'ABCD1234'],
        ], ['X-Webhook-Token' => 'whtok_rahasia'])->assertNoContent();

        $booking->refresh();
        expect($booking->status)->toBe('paid')
            ->and($booking->paid_at)->not->toBeNull()
            ->and($booking->sumopod_payment_id)->toBe('pay-1');

        Notification::assertSentTo($booking->user, BookingPaidNotification::class);
    }

    public function test_payment_completed_idempotent_tidak_kirim_notifikasi_dua_kali(): void
    {
        Notification::fake();

        $booking = Booking::factory()->create(['booking_code' => 'ABCD1234', 'status' => 'paid', 'paid_at' => now()]);

        $this->postJson('/webhooks/sumopod', [
            'event_type' => 'payment.completed',
            'data' => ['payment_id' => 'pay-1', 'order_id' => 'ABCD1234'],
        ], ['X-Webhook-Token' => 'whtok_rahasia'])->assertNoContent();

        Notification::assertNothingSent();
    }

    public function test_payment_expired_membuat_booking_kedaluwarsa(): void
    {
        $booking = Booking::factory()->create(['booking_code' => 'WXYZ9999', 'status' => 'pending_payment']);

        $this->postJson('/webhooks/sumopod', [
            'event_type' => 'payment.expired',
            'data' => ['order_id' => 'WXYZ9999'],
        ], ['X-Webhook-Token' => 'whtok_rahasia'])->assertNoContent();

        expect($booking->fresh()->status)->toBe('expired');
    }

    public function test_order_id_tidak_dikenal_tetap_balas_2xx(): void
    {
        $this->postJson('/webhooks/sumopod', [
            'event_type' => 'payment.completed',
            'data' => ['order_id' => 'TIDAK-ADA'],
        ], ['X-Webhook-Token' => 'whtok_rahasia'])->assertNoContent();
    }

    public function test_nominal_tidak_cocok_tidak_menandai_lunas(): void
    {
        Notification::fake();
        $booking = Booking::factory()->create(['booking_code' => 'AMNT0001', 'status' => 'pending_payment', 'total' => 50000]);

        $this->postJson('/webhooks/sumopod', [
            'event_type' => 'payment.completed',
            'data' => ['payment_id' => 'pay-x', 'order_id' => 'AMNT0001', 'amount' => 1000],
        ], ['X-Webhook-Token' => 'whtok_rahasia'])->assertNoContent();

        expect($booking->fresh()->status)->toBe('pending_payment');
        Notification::assertNothingSent();
    }

    public function test_bayar_setelah_hold_habis_dipulihkan_kalau_slot_masih_ada(): void
    {
        Notification::fake();
        $booking = Booking::factory()->create(['booking_code' => 'LATE0001', 'status' => 'expired', 'total' => 50000]);

        $this->postJson('/webhooks/sumopod', [
            'event_type' => 'payment.completed',
            'data' => ['payment_id' => 'pay-late', 'order_id' => 'LATE0001', 'amount' => 50000],
        ], ['X-Webhook-Token' => 'whtok_rahasia'])->assertNoContent();

        expect($booking->fresh()->status)->toBe('paid');
        Notification::assertSentTo($booking->user, BookingPaidNotification::class);
    }

    public function test_bayar_setelah_hold_habis_dan_slot_penuh_tidak_menggandakan_slot(): void
    {
        Notification::fake();
        $match = GameMatch::factory()->create(['capacity' => 1]);
        Booking::factory()->create(['match_id' => $match->id, 'status' => 'paid']);
        $booking = Booking::factory()->create(['match_id' => $match->id, 'booking_code' => 'LATE0002', 'status' => 'expired', 'total' => 50000]);

        $this->postJson('/webhooks/sumopod', [
            'event_type' => 'payment.completed',
            'data' => ['payment_id' => 'pay-late2', 'order_id' => 'LATE0002', 'amount' => 50000],
        ], ['X-Webhook-Token' => 'whtok_rahasia'])->assertNoContent();

        expect($booking->fresh()->status)->toBe('expired');
        Notification::assertNothingSent();
    }

    public function test_event_test_dari_dashboard_dibalas_2xx(): void
    {
        $this->postJson('/webhooks/sumopod', ['event_type' => 'payment.test', 'data' => []], ['X-Webhook-Token' => 'whtok_rahasia'])
            ->assertNoContent();
    }

    public function test_nominal_termasuk_biaya_saat_fee_dibebankan_ke_pembeli_tetap_diterima(): void
    {
        Notification::fake();
        $booking = Booking::factory()->create(['booking_code' => 'FEE00001', 'status' => 'pending_payment', 'total' => 50000]);

        $this->postJson('/webhooks/sumopod', [
            'event_type' => 'payment.completed',
            'data' => ['payment_id' => 'pay-fee', 'order_id' => 'FEE00001', 'amount' => 50650, 'fee' => 650],
        ], ['X-Webhook-Token' => 'whtok_rahasia'])->assertNoContent();

        expect($booking->fresh()->status)->toBe('paid');
    }
}
