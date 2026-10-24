<?php

namespace App\Actions\Match;

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\PaymentSettings;
use App\Notifications\MatchDocumentationNotification;
use App\Services\GoogleDrive\DriveFolder;
use App\Support\CompressedImageUpload;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Simpan link folder Drive dokumentasi match yang sudah selesai: salin beberapa foto ke R2
 * untuk galeri di web, lalu kirim email terima kasih + link ke semua pembeli lunas (sekali saja).
 */
class PublishDocumentation
{
    public const PHOTO_LIMIT = 6;

    /** @return array{photos: int, emailed: int, warning: string|null} */
    public function handle(GameMatch $match, string $url): array
    {
        $url = trim($url);
        $folderId = DriveFolder::folderId($url);

        if ($folderId === null) {
            throw new InvalidArgumentException('Link harus berupa link folder Google Drive (drive.google.com/drive/folders/...).');
        }

        $match->update(['drive_folder_url' => $url]);

        $warning = null;
        $apiKey = PaymentSettings::current()->googleDriveApiKey();

        if (blank($apiKey)) {
            $warning = 'Galeri foto belum disalin: Google Drive API key belum diisi di Setelan.';
        } else {
            // ponytail: unduh & kompres sinkron di request (maks 6 foto); pindah ke queue kalau batasnya dinaikkan.
            set_time_limit(180);

            try {
                $this->copyPhotos($match, new DriveFolder($apiKey), $folderId);
            } catch (RuntimeException $e) {
                $warning = $e->getMessage();
            }
        }

        $emailed = 0;
        if ($match->documentation_sent_at === null) {
            $match->bookings()->where('status', 'paid')->with('user', 'match')->get()
                ->each(function (Booking $booking) use (&$emailed) {
                    $booking->notifyBuyer(new MatchDocumentationNotification($booking));
                    $emailed++;
                });

            $match->update(['documentation_sent_at' => now()]);
        }

        return ['photos' => count($match->fresh()->drive_photos ?? []), 'emailed' => $emailed, 'warning' => $warning];
    }

    private function copyPhotos(GameMatch $match, DriveFolder $drive, string $folderId): void
    {
        $paths = [];
        foreach ($drive->images($folderId, self::PHOTO_LIMIT) as $image) {
            $path = CompressedImageUpload::compressAndStoreContents($drive->download($image['id']), "match-docs/{$match->id}", 1600);
            if ($path !== null) {
                $paths[] = $path;
            }
        }

        if ($paths === []) {
            throw new RuntimeException('Tidak ada foto yang bisa disalin dari folder itu (folder kosong atau bukan gambar).');
        }

        Storage::disk('public')->delete($match->drive_photos ?? []);
        $match->update(['drive_photos' => $paths]);
    }
}
