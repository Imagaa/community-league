<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MembershipController;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\MembershipPurchase;
use App\Models\MembershipSettings;
use App\Models\PaymentSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SumopodWebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $token = (string) $request->header('X-Webhook-Token');

        $expected = (string) PaymentSettings::current()->sumopodWebhookToken();

        if ($token === '' || $expected === '' || ! hash_equals($expected, $token)) {
            abort(401);
        }

        $eventType = (string) $request->input('event_type');
        $orderId = (string) $request->input('data.order_id');

        if ($orderId !== '' && Str::startsWith($orderId, 'MBR')) {
            $this->handleMembershipPurchase($orderId, $eventType, (string) $request->input('data.payment_id'), $request->input('data.amount'), $request->input('data.fee'));

            return response()->noContent();
        }

        $booking = $orderId !== '' ? Booking::where('booking_code', $orderId)->first() : null;

        if ($booking) {
            match ($eventType) {
                'payment.completed' => $this->markPaid($booking, (string) $request->input('data.payment_id'), $request->input('data.amount'), $request->input('data.fee')),
                'payment.failed', 'payment.expired' => $this->markExpired($booking),
                default => null,
            };
        }

        return response()->noContent();
    }

    private function handleMembershipPurchase(string $orderId, string $eventType, string $paymentId, mixed $amount, mixed $fee): void
    {
        $purchase = MembershipPurchase::where('purchase_code', $orderId)->first();

        if (! $purchase) {
            return;
        }

        match ($eventType) {
            'payment.completed' => $this->markMembershipPaid($purchase, $paymentId, $amount, $fee),
            'payment.failed', 'payment.expired' => $this->markMembershipExpired($purchase),
            default => null,
        };
    }

    private function markMembershipPaid(MembershipPurchase $purchase, string $paymentId, mixed $amount, mixed $fee): void
    {
        if (! $this->amountMatches($purchase->payableAmount(), $amount, $fee)) {
            Log::error('Webhook Sumopod: nominal membership tidak cocok', ['purchase_code' => $purchase->purchase_code, 'dibayar' => $amount]);

            return;
        }

        // 'expired' ikut diterima: uang sudah masuk walau link sempat dianggap kedaluwarsa.
        if (! in_array($purchase->status, ['pending_payment', 'expired'], true)) {
            return;
        }

        $purchase->update(['status' => 'paid', 'paid_at' => now(), 'sumopod_payment_id' => $paymentId]);

        $membership = MembershipController::renewOrCreateMembership($purchase->user, MembershipSettings::current()->duration_days);

        // Perpanjang di tempat (scanner): kartu fisiknya sedang dipegang, langsung aktif.
        if ($purchase->activate_card) {
            $membership->activateCard();
        }
    }

    private function markMembershipExpired(MembershipPurchase $purchase): void
    {
        if ($purchase->status === 'pending_payment') {
            $purchase->update(['status' => 'expired']);
        }
    }

    private function markPaid(Booking $booking, string $paymentId, mixed $amount, mixed $fee): void
    {
        // Nominal yang dibayar harus sama dengan tagihan booking -- order_id saja tidak cukup.
        if (! $this->amountMatches($booking->payableAmount(), $amount, $fee)) {
            Log::error('Webhook Sumopod: nominal tidak cocok, booking tidak ditandai lunas', [
                'booking_code' => $booking->booking_code, 'tagihan' => $booking->payableAmount(), 'dibayar' => $amount,
            ]);

            return;
        }

        $paid = DB::transaction(function () use ($booking, $paymentId) {
            // Kunci match dulu (urutan sama dengan CreateBooking) supaya cek slot di bawah aman.
            GameMatch::query()->lockForUpdate()->find($booking->match_id);
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            // Uang sudah masuk walau hold sempat habis -- pulihkan selama slotnya masih ada.
            $bisaDipulihkan = $locked->status === 'expired' && $locked->match->remainingSlots() >= $locked->quantity;

            if ($locked->status !== 'pending_payment' && ! $bisaDipulihkan) {
                if (in_array($locked->status, ['expired', 'cancelled', 'rejected'], true)) {
                    Log::critical('Webhook Sumopod: pembayaran masuk untuk booking yang sudah tidak aktif -- perlu refund/penanganan manual', [
                        'booking_code' => $locked->booking_code, 'status' => $locked->status, 'payment_id' => $paymentId,
                    ]);
                }

                return false;
            }

            return $locked->update(['status' => 'paid', 'paid_at' => now(), 'sumopod_payment_id' => $paymentId]);
        });

        if ($paid) {
            $booking->refresh()->sendPaidNotification();
        }
    }

    private function markExpired(Booking $booking): void
    {
        if ($booking->status === 'pending_payment') {
            $booking->update(['status' => 'expired']);
        }
    }

    /**
     * Nominal webhook harus sama dengan tagihan. Kalau "Charge fee to customer" dinyalakan di
     * dashboard Sumopod, amount bisa sudah termasuk biaya -- jadi amount - fee juga diterima.
     */
    private function amountMatches(int $payable, mixed $amount, mixed $fee): bool
    {
        if ($amount === null) {
            return true;
        }

        return (int) $amount === $payable || ($fee !== null && (int) $amount - (int) $fee === $payable);
    }
}
