<?php

namespace App\Support;

use App\Models\GameMatch;
use GdImage;
use RuntimeException;

/**
 * Gambar kartu pratinjau link (Open Graph, 1200x630) per match -- yang muncul saat link match
 * dibagikan ke WhatsApp/Telegram/X/Facebook. Digambar dengan GD + font brand Komikazoom,
 * tanpa layanan luar.
 */
class MatchOgImage
{
    private const W = 1200;

    private const H = 630;

    public static function png(GameMatch $match): string
    {
        $match->loadMissing('venue.city');
        $font = resource_path('fonts/komikazoom.ttf');
        $img = imagecreatetruecolor(self::W, self::H);
        throw_if($img === false, new RuntimeException('Gagal membuat kanvas OG.'));

        $navy = self::color($img, 0x10132D);
        $navyLight = self::color($img, 0x191D45);
        $orange = self::color($img, 0xF14A1C);
        $white = self::color($img, 0xFFFFFF);
        $muted = self::color($img, 0xA3A3C2);

        imagefill($img, 0, 0, $navy);
        // Aksen diagonal di kanan + garis oranye bawah -- identitas brand tema gelap.
        imagefilledpolygon($img, [820, 0, self::W, 0, self::W, self::H, 620, self::H], $navyLight);
        imagefilledrectangle($img, 0, self::H - 14, self::W, self::H, $orange);

        // icon-512.png, bukan app-icon.webp (5760px -- butuh >128MB RAM untuk di-decode).
        $icon = @imagecreatefrompng(public_path('images/icon-512.png'));
        if ($icon !== false) {
            imagecopyresampled($img, $icon, 880, 150, 0, 0, 260, 260, imagesx($icon), imagesy($icon));
        }

        $logo = @imagecreatefromwebp(public_path('images/secondary-logo-landscape.webp'));
        if ($logo !== false) {
            $lh = 80;
            $lw = (int) round(imagesx($logo) * $lh / imagesy($logo));
            imagecopyresampled($img, $logo, 72, 60, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
        }

        $tz = (string) config('app.display_timezone');
        $y = 250;
        foreach (self::wrap(mb_strtoupper($match->title), $font, 60, 1000, 2) as $line) {
            imagettftext($img, 60, 0, 72, $y, $white, $font, $line);
            $y += 78;
        }

        $y += 6;
        imagettftext($img, 30, 0, 72, $y, $orange, $font, mb_strtoupper($match->starts_at->timezone($tz)->translatedFormat('l, d F Y · H.i').' WIB'));
        $y += 52;
        $venue = $match->venue->name.($match->venue->city ? ', '.$match->venue->city->name : '');
        imagettftext($img, 26, 0, 72, $y, $muted, $font, mb_strtoupper(self::wrap($venue, $font, 26, 1000, 1)[0]));

        // Pil harga + status slot di kiri bawah.
        $sisa = $match->remainingSlots();
        $pills = [
            [Money::rupiah($match->price_regular), $orange, $white],
            [$sisa > 0 ? "SISA {$sisa} SLOT" : 'SLOT PENUH', $navyLight, $white],
        ];
        $x = 72;
        foreach ($pills as [$text, $bg, $fg]) {
            $tw = self::width($text, $font, 28);
            imagefilledrectangle($img, $x, 480, $x + $tw + 48, 548, $bg);
            imagettftext($img, 28, 0, $x + 24, 528, $fg, $font, $text);
            $x += $tw + 72;
        }

        ob_start();
        imagepng($img, null, 6);

        return (string) ob_get_clean();
    }

    /** @return list<string> paling banyak $maxLines baris; sisa dipotong dengan elipsis. */
    public static function wrap(string $text, string $font, int $size, int $maxWidth, int $maxLines): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $try = $line === '' ? $word : "{$line} {$word}";
            if ($line !== '' && self::width($try, $font, $size) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        $lines[] = $line;

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = $lines[$maxLines - 1];
            while ($last !== '' && self::width($last.'…', $font, $size) > $maxWidth) {
                $last = mb_substr($last, 0, -1);
            }
            $lines[$maxLines - 1] = rtrim($last).'…';
        }

        return array_values($lines);
    }

    public static function width(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0 : $box[2] - $box[0];
    }

    private static function color(GdImage $img, int $rgb): int
    {
        return (int) imagecolorallocate($img, ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
    }
}
