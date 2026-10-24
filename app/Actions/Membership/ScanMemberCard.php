<?php

namespace App\Actions\Membership;

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\Membership;
use App\Support\MemberCode;
use Carbon\CarbonInterface;

/**
 * Satu aturan hasil scan kartu member untuk semua scanner (per match & admin): kartu fisik/NFC
 * diaktifkan otomatis bila membership sudah diperpanjang, check-in + poin bila ada match, tawaran
 * perpanjang bila membership habis, dan "hubungkan kartu fisik" setelah scan kartu digital.
 */
class ScanMemberCard
{
    /**
     * @param  'physical'|'digital'|'nfc'  $type
     * @return array{result: string, title: string, detail: string, name: string, member: array<string, mixed>, notes: list<string>, actions: list<string>}
     */
    public function scan(string $type, Membership $membership, ?GameMatch $match): array
    {
        $membership->loadMissing('user');
        $notes = [];
        $actions = [];

        if ($type !== 'digital' && $membership->isActive() && ! $membership->cardActive()) {
            $membership->activateCard();
            $notes[] = 'Kartu diaktifkan s/d '.self::date($membership->expires_at);
        }

        [$result, $title, $detail] = $match
            ? $this->checkin($membership, $match)
            : ['info', $membership->isActive() ? 'Member aktif' : 'Membership habis', $membership->memberNumber()];

        if ($membership->isLapsed()) {
            $notes[] = 'Membership habis sejak '.self::date($membership->expires_at).'. Tawarkan perpanjang.';
            $actions[] = 'renew';
        }
        if ($type === 'digital') {
            $actions[] = 'link';
        }

        return [
            'result' => $result,
            'title' => $title,
            'detail' => $detail,
            'name' => $membership->user->name,
            'member' => self::member($membership),
            'notes' => $notes,
            'actions' => $actions,
        ];
    }

    /**
     * Kenali kartu dari QR (fisik/digital) atau UID NFC. Gagal -> respons scan siap tampil.
     *
     * @return array{membership: Membership, type: 'physical'|'digital'|'nfc'}|array{error: array{result: string, title: string, detail: string}}
     */
    public function identify(string $code, string $nfc): array
    {
        if ($nfc !== '') {
            $membership = MemberCode::byNfc($nfc);

            return $membership
                ? ['membership' => $membership, 'type' => 'nfc']
                : ['error' => ['result' => 'invalid', 'title' => 'Kartu NFC belum terhubung', 'detail' => 'Scan kartu digital member dulu, lalu "Hubungkan kartu fisik".']];
        }

        $found = MemberCode::resolve($code);

        return match (true) {
            $found === null => ['error' => ['result' => 'invalid', 'title' => 'Kartu tidak berlaku', 'detail' => 'Kartu sudah diganti atau bukan kartu Community League.']],
            $found['membership'] === null => ['error' => ['result' => 'invalid', 'title' => 'QR kartu kedaluwarsa', 'detail' => 'Minta member membuka ulang kartu digitalnya.']],
            default => ['membership' => $found['membership'], 'type' => $found['type']],
        };
    }

    /**
     * Hubungkan kartu fisik ke member: QR harus kartu miliknya; chip NFC kosong dipasangkan ke dia.
     *
     * @return array{ok: bool, message: string}
     */
    public function link(Membership $membership, ?string $code, ?string $nfcUid): array
    {
        if ($code !== null && $code !== '') {
            $found = MemberCode::resolve($code);
            if ($found === null || $found['type'] !== 'physical') {
                return ['ok' => false, 'message' => 'Bukan QR kartu fisik Community League.'];
            }
            if ($found['membership']->id !== $membership->id) {
                return ['ok' => false, 'message' => 'Kartu ini milik member lain.'];
            }
        } elseif ($nfcUid !== null && MemberCode::normalizeUid($nfcUid) !== '') {
            $owner = MemberCode::byNfc($nfcUid);
            if ($owner !== null && $owner->id !== $membership->id) {
                return ['ok' => false, 'message' => 'Chip NFC ini sudah terpasang ke member lain.'];
            }
            $membership->pairNfc($nfcUid);
        } else {
            return ['ok' => false, 'message' => 'Scan QR kartu fisik atau tap NFC kartunya.'];
        }

        if (! $membership->isActive()) {
            return ['ok' => true, 'message' => 'Kartu terhubung. Kartu aktif setelah membership diperpanjang.'];
        }
        $membership->activateCard();

        return ['ok' => true, 'message' => 'Kartu fisik aktif s/d '.self::date($membership->expires_at).'.'];
    }

    /**
     * Pemilik e-tiket yang membership-nya habis tetap ditawari perpanjang.
     *
     * @return array{notes: list<string>, actions: list<string>, member: array<string, mixed>|null}
     */
    public function forTicketHolder(Booking $booking): array
    {
        $membership = $booking->user?->membership;
        if ($membership === null || ! $membership->isLapsed()) {
            return ['notes' => [], 'actions' => [], 'member' => null];
        }

        return [
            'notes' => ['Membership habis sejak '.self::date($membership->expires_at).'. Tawarkan perpanjang.'],
            'actions' => ['renew'],
            'member' => self::member($membership),
        ];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function checkin(Membership $membership, GameMatch $match): array
    {
        $booking = Booking::query()->where('match_id', $match->id)->where('user_id', $membership->user_id)->where('status', 'paid')->first();
        if ($booking === null) {
            return ['no_ticket', 'Tidak punya tiket', 'Member ini belum membeli tiket match ini.'];
        }
        if (! $booking->confirmCheckin()) {
            return ['already', 'Sudah check-in', 'Pukul '.$booking->checked_in_at?->timezone((string) config('app.display_timezone'))->format('H.i').' WIB'];
        }

        $points = $booking->earnsMemberPoints() ? $match->points_reward * $booking->quantity : 0;

        return ['ok', 'Check-in berhasil', ($booking->quantity > 1 ? "{$booking->quantity} slot" : '1 slot').($points > 0 ? " · +{$points} poin" : '')];
    }

    /** @return array<string, mixed> */
    public static function member(Membership $membership): array
    {
        return [
            'id' => $membership->id,
            'number' => $membership->memberNumber(),
            'name' => $membership->user->name,
            'active' => $membership->isActive(),
            'expires' => self::date($membership->expires_at),
            'card_active' => $membership->cardActive(),
            'card_printed' => $membership->card_printed_at !== null,
            'nfc' => $membership->nfc_uid !== null,
        ];
    }

    private static function date(CarbonInterface $date): string
    {
        return $date->timezone((string) config('app.display_timezone'))->translatedFormat('d M Y');
    }
}
