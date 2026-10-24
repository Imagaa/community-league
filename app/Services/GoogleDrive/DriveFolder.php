<?php

namespace App\Services\GoogleDrive;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Akses baca folder Google Drive publik ("siapa pun yang memiliki link") lewat Drive API v3
 * dengan API key -- tanpa OAuth, karena folder dokumentasi memang dibagikan terbuka.
 */
class DriveFolder
{
    public function __construct(private readonly string $apiKey) {}

    /** ID folder dari link https://drive.google.com/drive/folders/{id}?usp=sharing (atau ?id=...). */
    public static function folderId(string $url): ?string
    {
        if (preg_match('#drive\.google\.com/(?:drive/(?:u/\d+/)?folders/|open\?id=)([A-Za-z0-9_-]{10,})#', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return list<array{id: string, name: string}> gambar di folder, urut nama, paling banyak $limit
     */
    public function images(string $folderId, int $limit): array
    {
        $response = Http::timeout(20)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => "'{$folderId}' in parents and mimeType contains 'image/' and trashed = false",
            'fields' => 'files(id,name)',
            'orderBy' => 'name',
            'pageSize' => $limit,
            'key' => $this->apiKey,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Folder Drive tidak bisa dibaca (pastikan aksesnya "Siapa saja yang memiliki link" dan API key benar). Kode '.$response->status().'.');
        }

        return array_values(array_map(
            fn (array $file) => ['id' => (string) $file['id'], 'name' => (string) $file['name']],
            (array) $response->json('files', [])
        ));
    }

    /** Isi file gambar (ukuran asli; dikompres pemanggil). */
    public function download(string $fileId): string
    {
        $response = Http::timeout(30)->get("https://www.googleapis.com/drive/v3/files/{$fileId}", [
            'alt' => 'media',
            'key' => $this->apiKey,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Gagal mengunduh foto {$fileId} dari Drive (kode {$response->status()}).");
        }

        return $response->body();
    }
}
