<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\User;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Kartu member = latar desain (PNG/JPG unggahan admin, atau bawaan resources/images/card-*.png)
 * + data member yang ditulis ke kotak-kotak yang posisinya diatur admin (persen kartu, jadi
 * resolusi latar bebas asal rasio kartu KTP 85,6 x 54 mm). Kartu sengaja TANPA masa berlaku --
 * status dicek lewat QR, jadi kartu cetak tetap dipakai setelah perpanjangan.
 */
class MemberCardImage
{
    /** Kotak data bawaan -- cocok dengan desain bawaan resources/images/card-{front,back}.png. */
    public const FIELDS = [
        'name' => ['side' => 'front', 'x' => 41.9, 'y' => 63.3, 'w' => 53.4, 'h' => 6.9, 'color' => '#17181c', 'font' => 'sans', 'align' => 'left'],
        'number' => ['side' => 'front', 'x' => 41.9, 'y' => 78.4, 'w' => 40.0, 'h' => 6.3, 'color' => '#17181c', 'font' => 'sans', 'align' => 'left'],
        'qr' => ['side' => 'back', 'x' => 72.6, 'y' => 51.7, 'w' => 20.6, 'h' => 32.6],
    ];

    public const LABELS = ['name' => 'Nama member', 'number' => 'No. member', 'qr' => 'QR cek status'];

    public const FONTS = ['sans' => 'instrument-sans-600.ttf', 'display' => 'komikazoom.ttf'];

    /** $withQr false untuk unduhan member: QR statis di file sama dengan salinan kartu fisik. */
    public static function front(Membership $membership, bool $withQr = true): string
    {
        return self::render('front', $membership, $withQr);
    }

    public static function back(Membership $membership, bool $withQr = true): string
    {
        return self::render('back', $membership, $withQr);
    }

    /** Pratinjau admin dengan data contoh. */
    public static function sample(string $side): string
    {
        $membership = new Membership(['card_token' => 'ContohKartuMember123']);
        $membership->id = 123;
        $membership->setRelation('user', new User(['name' => 'Nama Member Contoh']));

        return self::render($side, $membership);
    }

    /** Latar desain tanpa data: unggahan admin, atau desain bawaan. */
    public static function template(string $side): string
    {
        $settings = MembershipSettings::current();
        $path = $side === 'front' ? $settings->card_front_path : $settings->card_back_path;
        $bytes = $path ? Storage::disk('public')->get($path) : null;

        return $bytes ?? (string) file_get_contents(resource_path("images/card-{$side}.png"));
    }

    /**
     * Posisi kotak tersimpan digabung ke bawaan (field baru otomatis dapat posisi bawaan).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function layout(): array
    {
        $saved = MembershipSettings::current()->card_layout ?? [];
        $layout = [];
        foreach (self::FIELDS as $key => $default) {
            $layout[$key] = array_merge($default, array_intersect_key((array) ($saved[$key] ?? []), $default));
        }

        return $layout;
    }

    private static function render(string $side, Membership $membership, bool $withQr = true): string
    {
        $img = imagecreatefromstring(self::template($side));
        throw_if($img === false, new RuntimeException('Desain kartu tidak bisa dibaca.'));
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        [$w, $h] = [imagesx($img), imagesy($img)];

        foreach (self::layout() as $key => $field) {
            if ($field['side'] !== $side || ($key === 'qr' && ! $withQr)) {
                continue;
            }
            $box = [
                (int) round((float) $field['x'] * $w / 100), (int) round((float) $field['y'] * $h / 100),
                (int) round((float) $field['w'] * $w / 100), (int) round((float) $field['h'] * $h / 100),
            ];
            match ($key) {
                'qr' => self::qr($img, $membership->cardCode(), $box),
                'name' => self::text($img, mb_strtoupper(trim($membership->loadMissing('user')->user->name)), $box, $field, abbreviate: true),
                default => self::text($img, $membership->memberNumber(), $box, $field, abbreviate: false),
            };
        }

        // dpi sebenarnya untuk lebar 85,6 mm -- percetakan membaca ukuran fisik dari sini.
        $dpi = (int) round($w / (85.6 / 25.4));
        imageresolution($img, $dpi, $dpi);
        ob_start();
        imagepng($img, null, 6);

        return (string) ob_get_clean();
    }

    /** @param  array{int, int, int, int}  $box */
    private static function qr(GdImage $img, string $url, array $box): void
    {
        [$x, $y, $w, $h] = $box;
        $qr = imagecreatefromstring(TicketQrCode::png($url));
        throw_if($qr === false, new RuntimeException('Gagal membuat QR kartu member.'));
        $size = min($w, $h);
        imagecopyresampled($img, $qr, $x + intdiv($w - $size, 2), $y + intdiv($h - $size, 2), 0, 0, $size, $size, imagesx($qr), imagesy($qr));
    }

    /**
     * Teks setinggi kotak, mengecil bila kepanjangan; nama yang tetap kepanjangan disingkat dari
     * nama tengah paling belakang ("RIZKY R. P. SAPUTRA"), sisanya dipotong elipsis.
     *
     * @param  array{int, int, int, int}  $box
     * @param  array<string, mixed>  $field
     */
    private static function text(GdImage $img, string $text, array $box, array $field, bool $abbreviate): void
    {
        [$x, $y, $w, $h] = $box;
        $font = resource_path('fonts/'.(self::FONTS[$field['font']] ?? self::FONTS['sans']));

        $size = max(6, $h);
        while ($size > 6 && self::capHeight($font, $size) > $h * 0.7) {
            $size--;
        }
        $min = max(6, (int) floor($size * 0.65));
        while ($size > $min && MatchOgImage::width($text, $font, $size) > $w) {
            $size--;
        }

        $words = preg_split('/\s+/', $text) ?: [$text];
        for ($i = count($words) - 2; $abbreviate && $i >= 1 && MatchOgImage::width(implode(' ', $words), $font, $size) > $w; $i--) {
            $words[$i] = mb_substr($words[$i], 0, 1).'.';
        }
        $text = MatchOgImage::wrap(implode(' ', $words), $font, $size, $w, 1)[0];

        $width = MatchOgImage::width($text, $font, $size);
        $left = match ($field['align']) {
            'center' => $x + intdiv($w - $width, 2),
            'right' => $x + $w - $width,
            default => $x,
        };
        $color = (int) hexdec(ltrim((string) $field['color'], '#'));
        imagettftext($img, $size, 0, $left, $y + intdiv($h + self::capHeight($font, $size), 2), $color, $font, $text);
    }

    private static function capHeight(string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, 'H');

        return $box === false ? $size : $box[1] - $box[7];
    }
}
