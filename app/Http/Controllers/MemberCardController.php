<?php

namespace App\Http\Controllers;

use App\Actions\Membership\ScanMemberCard;
use App\Models\Membership;
use App\Support\MemberCardImage;
use App\Support\MemberCode;
use App\Support\TicketQrCode;
use finfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Kartu member: halaman verifikasi publik (tujuan QR di kartu), kartu digital milik member, dan
 * unduhan PNG 300 dpi siap cetak untuk admin.
 */
class MemberCardController extends Controller
{
    /** Kartu digital (sisi depan) milik user yang login. */
    public function mine(Request $request): Response
    {
        $membership = $request->user()->membership;
        abort_if($membership === null, 404);

        return $this->png(MemberCardImage::front($membership, withQr: false), $request->boolean('download') ? "kartu-member-{$membership->memberNumber()}.png" : null);
    }

    /** Kode QR kartu digital (berganti tiap 30 detik) untuk ditunjukkan ke panitia. */
    public function code(Request $request): JsonResponse
    {
        $membership = $request->user()->membership;
        abort_if($membership === null, 404);

        return response()->json([
            'qr' => TicketQrCode::dataUri($membership->digitalCode()),
            'refresh_in' => MemberCode::WINDOW_SECONDS - time() % MemberCode::WINDOW_SECONDS,
        ]);
    }

    /** Aktivasi mandiri kartu fisik (kamera / NFC) oleh pemiliknya sendiri. */
    public function activate(Request $request): View
    {
        $membership = $request->user()->membership;
        abort_if($membership === null, 404);

        return view('pages.membership.activate-card', ['membership' => $membership]);
    }

    public function activateStore(Request $request, ScanMemberCard $cards): JsonResponse
    {
        $membership = $request->user()->membership;
        abort_if($membership === null, 404);

        $result = $cards->link($membership, $request->string('code')->toString() ?: null, $request->string('nfc')->toString() ?: null);

        // Bentuk respons scan juga, supaya scan ulang setelah berhasil tetap tampil rapi.
        return response()->json($result + [
            'result' => $result['ok'] ? 'ok' : 'invalid',
            'title' => $result['ok'] ? 'Kartu aktif' : 'Gagal mengaktifkan',
            'detail' => $result['message'],
            'member' => ScanMemberCard::member($membership->refresh()),
        ]);
    }

    /** Admin: PNG siap cetak per sisi. Unduhan sisi depan dicatat sebagai "sudah dicetak". */
    public function print(Membership $membership, string $side): Response
    {
        if ($side === 'depan') {
            $membership->update(['card_printed_at' => now()]);
        }

        return $this->png(
            $side === 'depan' ? MemberCardImage::front($membership) : MemberCardImage::back($membership),
            "kartu-{$membership->memberNumber()}-{$side}.png",
        );
    }

    /** Admin: latar desain tanpa data -- dasar editor kotak di Setelan. */
    public function template(string $side): Response
    {
        return $this->png(MemberCardImage::template($side), null);
    }

    /** Admin: pratinjau kartu dengan data contoh. */
    public function sample(string $side): Response
    {
        return $this->png(MemberCardImage::sample($side), null);
    }

    private function png(string $bytes, ?string $downloadName): Response
    {
        return response($bytes, 200, array_filter([
            'Content-Type' => (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/png',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => $downloadName ? "attachment; filename=\"{$downloadName}\"" : null,
        ]));
    }
}
