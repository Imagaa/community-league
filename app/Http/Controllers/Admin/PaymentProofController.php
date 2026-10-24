<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Bukti bayar disimpan privat; satu-satunya jalan melihatnya adalah rute admin ini. */
class PaymentProofController extends Controller
{
    public function __invoke(Booking $booking): StreamedResponse
    {
        abort_unless($booking->payment_proof_path && Storage::disk('proofs')->exists($booking->payment_proof_path), 404);

        return Storage::disk('proofs')->response($booking->payment_proof_path, headers: [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
