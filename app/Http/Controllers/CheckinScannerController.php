<?php

namespace App\Http\Controllers;

use App\Actions\Membership\ScanMemberCard;
use App\Actions\Membership\StartVenueRenewal;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\Membership;
use App\Models\MembershipPurchase;
use App\Models\MembershipSettings;
use App\Support\CheckinToken;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Scanner check-in publik per match: siapa pun yang memegang link (panitia di venue) bisa
 * memindai QR e-ticket, kartu member (fisik/digital), atau tap NFC kartu. Linknya rahasia (token
 * acak per match) dan bisa dibuat ulang dari admin. Perpanjang & hubungkan kartu hanya untuk
 * member yang baru saja discan di sesi browser ini (bukan id bebas).
 */
class CheckinScannerController extends Controller
{
    private const SEEN = 'scanner.members';

    public function show(string $token): View
    {
        $match = $this->match($token);

        return view('pages.checkin.scanner', [
            'match' => $match->load('venue'),
            'stats' => $this->stats($match),
        ]);
    }

    public function scan(Request $request, string $token, ScanMemberCard $cards): JsonResponse
    {
        $match = $this->match($token);
        $code = trim((string) $request->input('code'));
        $nfc = (string) $request->input('nfc');

        if ($nfc !== '' || str_starts_with($code, 'CLC1.') || str_starts_with($code, 'CLD1.')) {
            return response()->json($this->scanCard($request, $cards, $match, $code, $nfc) + ['stats' => $this->stats($match)]);
        }

        $booking = CheckinToken::decode($code);

        $result = match (true) {
            $booking === null => ['result' => 'invalid', 'title' => 'QR tidak dikenal', 'detail' => 'Bukan e-ticket Community League.'],
            $booking->match_id !== $match->id => [
                'result' => 'other_match',
                'title' => 'Tiket match lain',
                'detail' => $booking->match->title.' · '.$booking->match->starts_at->timezone((string) config('app.display_timezone'))->translatedFormat('d M H.i'),
            ],
            $booking->status !== 'paid' => ['result' => 'not_paid', 'title' => 'Belum lunas', 'detail' => 'Status booking: '.$booking->status.'.'],
            default => $this->checkin($booking),
        };

        $member = $booking && MembershipSettings::enabled() ? $cards->forTicketHolder($booking) : ['notes' => [], 'actions' => [], 'member' => null];
        if ($member['member'] !== null) {
            $this->remember($request, (int) $member['member']['id']);
        }

        return response()->json($result + $member + [
            'name' => $booking?->buyerName(),
            'code' => $booking?->booking_code,
            'stats' => $this->stats($match),
        ]);
    }

    public function renew(Request $request, string $token, StartVenueRenewal $renewal): JsonResponse
    {
        $this->match($token);
        $membership = $this->seenMembership($request);
        $purchase = $renewal->handle($membership);

        return response()->json([
            'purchase_id' => $purchase->id,
            'link' => $purchase->sumopod_payment_link_url,
            'total' => Money::rupiah($purchase->amount),
            'name' => $membership->user->name,
        ]);
    }

    public function renewStatus(Request $request, string $token, MembershipPurchase $purchase): JsonResponse
    {
        $this->match($token);
        $membershipId = $purchase->user->membership?->id;
        abort_unless($membershipId !== null && in_array($membershipId, (array) $request->session()->get(self::SEEN, []), true), 403);

        return response()->json($purchase->statusPayload());
    }

    public function link(Request $request, string $token, ScanMemberCard $cards): JsonResponse
    {
        $this->match($token);
        $membership = $this->seenMembership($request);
        $result = $cards->link($membership, $request->string('code')->toString() ?: null, $request->string('nfc')->toString() ?: null);

        return response()->json($result + ['member' => ScanMemberCard::member($membership->refresh())]);
    }

    /** @return array<string, mixed> */
    private function scanCard(Request $request, ScanMemberCard $cards, GameMatch $match, string $code, string $nfc): array
    {
        if (! MembershipSettings::enabled()) {
            return ['result' => 'invalid', 'title' => 'Membership belum aktif', 'detail' => 'Kartu member belum bisa dipakai.'];
        }

        $found = $cards->identify($code, $nfc);
        if (isset($found['error'])) {
            return $found['error'];
        }

        $this->remember($request, $found['membership']->id);

        return $cards->scan($found['type'], $found['membership'], $match);
    }

    private function remember(Request $request, int $membershipId): void
    {
        $seen = (array) $request->session()->get(self::SEEN, []);
        $request->session()->put(self::SEEN, array_values(array_unique([...array_slice($seen, -49), $membershipId])));
    }

    private function seenMembership(Request $request): Membership
    {
        $id = $request->integer('membership_id');
        abort_unless(in_array($id, (array) $request->session()->get(self::SEEN, []), true), 403, 'Scan kartu member dulu.');

        return Membership::with('user')->findOrFail($id);
    }

    /** @return array{result: string, title: string, detail: string} */
    private function checkin(Booking $booking): array
    {
        $tz = (string) config('app.display_timezone');

        if ($booking->confirmCheckin()) {
            return ['result' => 'ok', 'title' => 'Check-in berhasil', 'detail' => $booking->quantity > 1 ? "{$booking->quantity} slot" : '1 slot'];
        }

        return [
            'result' => 'already',
            'title' => 'Sudah check-in',
            'detail' => 'Pukul '.$booking->checked_in_at?->timezone($tz)->format('H.i').' WIB',
        ];
    }

    private function match(string $token): GameMatch
    {
        abort_unless(strlen($token) === 40, 404);

        return GameMatch::query()->where('scanner_token', $token)->firstOrFail();
    }

    /** @return array{hadir: int, total: int} jumlah slot lunas yang sudah / harus check-in */
    private function stats(GameMatch $match): array
    {
        $paid = $match->bookings()->where('status', 'paid');

        return [
            'hadir' => (int) (clone $paid)->whereNotNull('checked_in_at')->sum('quantity'),
            'total' => (int) $paid->sum('quantity'),
        ];
    }
}
