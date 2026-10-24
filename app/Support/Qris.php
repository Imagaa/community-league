<?php

namespace App\Support;

/**
 * QRIS dinamis dari payload QRIS statis merchant (format TLV EMVCo): tag 01 jadi "12"
 * (dinamis), tag 54 (nominal) disisipkan setelah tag 53, CRC16 tag 63 dihitung ulang.
 */
class Qris
{
    public static function isValid(string $payload): bool
    {
        if (! str_starts_with($payload, '000201') || strlen($payload) < 12) {
            return false;
        }

        $tags = self::parse($payload);

        return $tags !== null
            && isset($tags['63'])
            && strtoupper($tags['63']) === self::crc16(substr($payload, 0, -4));
    }

    public static function merchantName(string $payload): ?string
    {
        return self::parse($payload)['59'] ?? null;
    }

    public static function withAmount(string $payload, int $amount): string
    {
        $tags = self::parse($payload) ?? [];
        unset($tags['63'], $tags['54']);
        $tags['01'] = '12';

        $out = '';
        foreach ($tags as $tag => $value) {
            $out .= self::tlv((string) $tag, $value);
            if ((string) $tag === '53') {
                $out .= self::tlv('54', (string) $amount);
            }
        }

        $out .= '6304';

        return $out.self::crc16($out);
    }

    /** @return array<int|string, string>|null tag => nilai, urutan asli dipertahankan */
    private static function parse(string $payload): ?array
    {
        $tags = [];
        $i = 0;
        $len = strlen($payload);

        while ($i < $len) {
            $tag = substr($payload, $i, 2);
            $size = substr($payload, $i + 2, 2);
            if (! ctype_digit($tag) || ! ctype_digit($size) || $i + 4 + (int) $size > $len) {
                return null;
            }
            // Kunci "52" dst jadi integer di PHP, tapi urutan sisip array tetap terjaga.
            $tags[$tag] = substr($payload, $i + 4, (int) $size);
            $i += 4 + (int) $size;
        }

        return $tags;
    }

    private static function tlv(string $tag, string $value): string
    {
        return str_pad($tag, 2, '0', STR_PAD_LEFT).str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    /** CRC16-CCITT (FALSE): poly 0x1021, init 0xFFFF -- yang dipakai EMVCo/QRIS. */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;
        foreach (str_split($data) as $char) {
            $crc ^= ord($char) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return sprintf('%04X', $crc);
    }
}
