<?php

namespace App\Support;

use App\Models\Membership;

/**
 * Kode kartu member yang hanya bermakna bagi server CL -- scanner biasa cuma melihat teks acak:
 * - fisik  `CLC1.<card_token>` (token acak, statis, tercetak di kartu)
 * - digital `CLD1.<membership_id>.<jendela>.<hmac16>` (berganti tiap 30 detik, tampil live di web)
 * - NFC: UID chip yang dipasangkan ke kartu fisik.
 */
class MemberCode
{
    public const WINDOW_SECONDS = 30;

    /** @return array{type: 'physical'|'digital', membership: Membership}|array{type: 'expired', membership: null}|null null = bukan kode kartu CL */
    public static function resolve(string $scanned): ?array
    {
        $scanned = trim($scanned);

        if (preg_match('/^CLC1\.([A-Za-z0-9]{20})$/', $scanned, $m)) {
            $membership = Membership::where('card_token', $m[1])->first();

            return $membership ? ['type' => 'physical', 'membership' => $membership] : null;
        }

        if (preg_match('/^CLD1\.(\d+)\.(\d+)\.([0-9a-f]{16})$/', $scanned, $m)) {
            [, $id, $window, $mac] = $m;
            $fresh = abs((int) $window - intdiv(time(), self::WINDOW_SECONDS)) <= 1;
            if (! $fresh || ! hash_equals(self::mac((int) $id, (int) $window), $mac)) {
                return ['type' => 'expired', 'membership' => null];
            }
            $membership = Membership::find((int) $id);

            return $membership ? ['type' => 'digital', 'membership' => $membership] : null;
        }

        return null;
    }

    public static function digital(int $membershipId, ?int $window = null): string
    {
        $window ??= intdiv(time(), self::WINDOW_SECONDS);

        return "CLD1.{$membershipId}.{$window}.".self::mac($membershipId, $window);
    }

    public static function normalizeUid(string $uid): string
    {
        return strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $uid));
    }

    public static function byNfc(string $uid): ?Membership
    {
        $uid = self::normalizeUid($uid);

        return $uid === '' ? null : Membership::where('nfc_uid', $uid)->first();
    }

    private static function mac(int $id, int $window): string
    {
        return substr(hash_hmac('sha256', "member-card|{$id}|{$window}", (string) config('app.key')), 0, 16);
    }
}
