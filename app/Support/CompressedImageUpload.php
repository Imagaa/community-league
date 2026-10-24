<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Semua foto yang disimpan ke R2 (bucket gratis cuma 10GB) WAJIB dikompres (di-resize +
 * di-encode ulang ke WebP) sebelum disimpan, bukan disimpan mentah.
 */
class CompressedImageUpload
{
    /**
     * @param  string  $disk  'public' untuk poster/foto publik, 'local'/privat untuk bukti bayar
     * @return string|null path hasil simpan, atau null kalau $contents bukan gambar valid
     *                     (GD gagal decode) -- pemanggil yang memutuskan menolak atau tidak.
     */
    public static function compressAndStoreContents(string $contents, string $directory, int $maxDimension, string $disk = 'public'): ?string
    {
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        imagealphablending($source, false);
        imagesavealpha($source, true);

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxDimension / max($width, $height));

        if ($scale < 1) {
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        ob_start();
        imagewebp($source, quality: 80);
        $webp = ob_get_clean();
        imagedestroy($source);
        throw_if($webp === false, new RuntimeException('Gagal meng-encode gambar ke WebP.'));

        $path = $directory.'/'.Str::random(24).'.webp';
        Storage::disk($disk)->put($path, $webp);

        return $path;
    }
}
