<?php

namespace App\Http\Controllers;

use App\Actions\Booking\CreateBooking;
use App\Exceptions\BookingException;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\PaymentSettings;
use App\Models\User;
use App\Notifications\PaymentProofReceivedNotification;
use App\Services\Sumopod\SumopodClient;
use App\Support\CheckinToken;
use App\Support\CompressedImageUpload;
use App\Support\Qris;
use App\Support\TicketQrCode;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request, GameMatch $match, CreateBooking $createBooking): RedirectResponse
    {
        $data = [
            'quantity' => (int) $request->validated('quantity'),
            'terms_version' => User::TERMS_VERSION,
        ];

        if ($request->user()) {
            $data['user_id'] = $request->user()->id;
        } else {
            $data['guest_name'] = (string) $request->validated('guest_name');
            $data['guest_email'] = (string) $request->validated('guest_email');
            $data['guest_phone'] = (string) $request->validated('guest_phone');
        }

        try {
            $booking = $createBooking->handle($match, $data);
        } catch (BookingException $e) {
            return back()->withErrors(['booking' => $e->getMessage()])->withInput();
        }

        return redirect()->to($booking->urlFor('booking.payment'));
    }

    public function show(Request $request, Booking $booking): View|RedirectResponse
    {
        $this->authorizeAccess($request, $booking);

        if (in_array($booking->status, ['pending_payment', 'awaiting_verification', 'rejected'], true)) {
            return redirect()->to($booking->urlFor('booking.payment'));
        }

        $booking->load('match.venue');

        $qrDataUri = $booking->status === 'paid'
            ? TicketQrCode::dataUri(CheckinToken::encode($booking))
            : null;

        return view('pages.bookings.show', ['booking' => $booking, 'qrDataUri' => $qrDataUri]);
    }

    /**
     * Satu halaman untuk semua tahap pembayaran: bayar QRIS + unggah bukti (pending_payment),
     * menunggu verifikasi (awaiting_verification), atau ditolak (rejected).
     */
    public function payment(Request $request, Booking $booking): View|RedirectResponse
    {
        $this->authorizeAccess($request, $booking);

        if ($booking->status === 'paid') {
            return redirect()->to($booking->urlFor('booking.show'));
        }

        $booking->load('match.venue', 'user');

        if (in_array($booking->status, ['expired', 'cancelled'], true)) {
            return view('pages.bookings.expired', ['booking' => $booking]);
        }

        $settings = PaymentSettings::current();
        $qrisDataUri = null;

        if ($booking->status === 'pending_payment' && $settings->hasQris()) {
            $booking->ensureUniqueCode();
            $qrisDataUri = TicketQrCode::dataUri(Qris::withAmount((string) $settings->qris_payload, $booking->amountToPay()));
        }

        return view('pages.bookings.payment', [
            'booking' => $booking,
            'settings' => $settings,
            'qrisDataUri' => $qrisDataUri,
            'needsPhone' => $booking->user !== null && blank($booking->user->phone),
        ]);
    }

    public function uploadProof(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeAccess($request, $booking);

        $needsPhone = $booking->user !== null && blank($booking->user->phone);
        $request->merge(['phone' => WhatsApp::local((string) $request->input('phone'))]);

        $request->validate([
            'bukti' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'phone' => [$needsPhone ? 'required' : 'nullable', 'regex:'.WhatsApp::LOCAL_PATTERN],
        ], [
            'bukti.required' => 'Pilih foto/screenshot bukti pembayaran.',
            'bukti.mimes' => 'Bukti harus berupa gambar JPG, PNG, atau WEBP.',
            'bukti.max' => 'Ukuran gambar maksimal 8 MB.',
            'phone.required' => 'Nomor WhatsApp wajib diisi.',
            'phone.regex' => 'Nomor WhatsApp harus diawali 08 dan terdiri dari 10-14 angka.',
        ]);

        $path = CompressedImageUpload::compressAndStoreContents(
            (string) $request->file('bukti')?->get(), 'bukti-bayar', 1600, 'proofs'
        );

        if ($path === null) {
            return back()->withErrors(['bukti' => 'Gambar tidak bisa dibaca. Coba foto/screenshot ulang.']);
        }

        $updated = DB::transaction(function () use ($booking, $path) {
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($locked->status !== 'pending_payment') {
                return false;
            }

            // Hold tanpa batas sampai admin memutuskan (ExpireBookingHolds hanya menyentuh pending_payment).
            return $locked->update([
                'status' => 'awaiting_verification',
                'hold_expires_at' => null,
                'payment_proof_path' => $path,
                'proof_uploaded_at' => now(),
                // Akhirnya bayar lewat QRIS manual (uang sungguhan) walau sempat membuka link Sumopod sandbox.
                'is_sandbox' => false,
            ]);
        });

        if (! $updated) {
            return redirect()->to($booking->urlFor('booking.payment'))
                ->withErrors(['bukti' => 'Booking ini sudah tidak menunggu pembayaran.']);
        }

        if ($needsPhone) {
            $booking->user->update(['phone' => $request->input('phone')]);
        }

        $booking->refresh()->notifyBuyer(new PaymentProofReceivedNotification($booking));

        return redirect()->to($booking->urlFor('booking.payment'));
    }

    public function payWithSumopod(Request $request, Booking $booking, SumopodClient $sumopod): RedirectResponse
    {
        $this->authorizeAccess($request, $booking);

        abort_unless($booking->status === 'pending_payment' && PaymentSettings::current()->sumopodAvailable(), 404);

        if (! $booking->sumopod_payment_link_url) {
            $payment = $sumopod->createPayment($booking);
            // Hold slot ikut sampai link Sumopod kedaluwarsa -- kalau tidak, pembeli yang bayar
            // setelah hold 30 menit habis akan membayar booking yang sudah dilepas.
            $linkExpiresAt = isset($payment['expires_at']) ? Carbon::parse($payment['expires_at']) : now()->addHour();

            $booking->update([
                'sumopod_payment_id' => $payment['payment_id'],
                'sumopod_payment_link_url' => $payment['payment_link_url'],
                'hold_expires_at' => $booking->hold_expires_at?->max($linkExpiresAt) ?? $linkExpiresAt,
                'is_sandbox' => PaymentSettings::current()->sumopodIsSandbox(),
            ]);
        }

        return redirect()->away((string) $booking->sumopod_payment_link_url);
    }

    public function bypass(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeAccess($request, $booking);

        if ($booking->status === 'pending_payment') {
            $booking->update(['status' => 'paid', 'paid_at' => now(), 'is_sandbox' => true]);
            $booking->sendPaidNotification();
        }

        return redirect()->to($booking->urlFor('booking.payment.success'));
    }

    private function authorizeAccess(Request $request, Booking $booking): void
    {
        $pemilikSah = $request->user() && $request->user()->id === $booking->user_id;
        $tandaTanganSah = $booking->isGuest() && $request->hasValidSignature();

        abort_unless($pemilikSah || $tandaTanganSah, 403);
    }
}
