<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Membership\ScanMemberCard;
use App\Actions\Membership\StartVenueRenewal;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\MembershipPurchase;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Scanner Kartu admin (login admin): profil member dari kartu digital/fisik/NFC, hubungkan &
 * aktifkan kartu fisik, ganti kartu hilang, dan perpanjang di tempat. Tanpa check-in match.
 */
class CardScannerController extends Controller
{
    public function show(Request $request): View
    {
        $member = $request->filled('member') ? Membership::with('user')->find($request->integer('member')) : null;

        return view('pages.admin.card-scanner', [
            'preselect' => $member ? ['id' => $member->id, 'name' => $member->user->name] : null,
        ]);
    }

    public function scan(Request $request, ScanMemberCard $cards): JsonResponse
    {
        $found = $cards->identify(trim((string) $request->input('code')), (string) $request->input('nfc'));
        if (isset($found['error'])) {
            return response()->json($found['error']);
        }

        $result = $cards->scan($found['type'], $found['membership'], null);
        $result['actions'][] = 'replace';

        return response()->json($result);
    }

    public function link(Request $request, ScanMemberCard $cards): JsonResponse
    {
        $membership = $this->membership($request);
        $result = $cards->link($membership, $request->string('code')->toString() ?: null, $request->string('nfc')->toString() ?: null);

        return response()->json($result + ['member' => ScanMemberCard::member($membership->refresh())]);
    }

    public function replace(Request $request): JsonResponse
    {
        $membership = $this->membership($request);
        $membership->replaceCard();

        return response()->json([
            'ok' => true,
            'message' => 'Kartu diganti. QR & NFC lama diblokir -- cetak kartu baru dari tab Kartu.',
            'member' => ScanMemberCard::member($membership->refresh()),
        ]);
    }

    public function renew(Request $request, StartVenueRenewal $renewal): JsonResponse
    {
        $membership = $this->membership($request);
        $purchase = $renewal->handle($membership);

        return response()->json([
            'purchase_id' => $purchase->id,
            'link' => $purchase->sumopod_payment_link_url,
            'total' => Money::rupiah($purchase->amount),
            'name' => $membership->user->name,
        ]);
    }

    public function renewStatus(MembershipPurchase $purchase): JsonResponse
    {
        return response()->json($purchase->statusPayload());
    }

    private function membership(Request $request): Membership
    {
        return Membership::with('user')->findOrFail($request->integer('membership_id'));
    }
}
