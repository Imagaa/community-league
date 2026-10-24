<?php

namespace Database\Seeders;

use App\Models\RewardItem;
use App\Support\CompressedImageUpload;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Isi/perbarui foto RewardItem dengan foto merchandise asli dari asset_foto/merchandise/ (dibawa
 * masuk ke image Docker lewat `COPY . .` di Dockerfile, tidak di .dockerignore -- jadi path ini
 * ada juga di container staging/produksi, bukan cuma lokal). Idempotent (updateOrCreate berdasar
 * nama) -- aman dijalankan ulang, beda dari DemoSeeder yang cuma sekali per environment. Jalankan
 * manual: `php artisan db:seed --class=MerchandiseSeeder`.
 *
 * Setiap run generate file WebP baru (nama random) tanpa menghapus file lama -- oke untuk skrip
 * konten yang jarang dijalankan ulang, bukan concern kalau dijalankan sesekali.
 */
class MerchandiseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $item) {
            $filename = $item['source'];
            unset($item['source']);

            $item['image_path'] = $this->storePhoto($filename);

            RewardItem::updateOrCreate(['name' => $item['name']], $item);
        }
    }

    /** @return list<array{name: string, description: string, point_cost: int, stock: int|null, active: bool, sort_order: int, source: string}> */
    private function items(): array
    {
        return [
            [
                'name' => 'Kaos Community League',
                'description' => 'Kaos jersey resmi Community League, bahan dry-fit.',
                'point_cost' => 500,
                'stock' => 20,
                'active' => true,
                'sort_order' => 1,
                'source' => 'Kaos CL.png',
            ],
            [
                'name' => 'Topi Community League',
                'description' => 'Topi snapback dengan logo Community League.',
                'point_cost' => 300,
                'stock' => 5,
                'active' => true,
                'sort_order' => 2,
                'source' => 'Topi CL.png',
            ],
            [
                'name' => 'Voucher Diskon Turnamen',
                'description' => 'Potongan harga 50% untuk pendaftaran turnamen berikutnya.',
                'point_cost' => 1000,
                'stock' => null,
                'active' => true,
                'sort_order' => 4,
                'source' => 'Kupon Diskon CL.png',
            ],
            [
                'name' => 'Jersey Edisi Terbatas',
                'description' => 'Jersey edisi terbatas, hanya 2 tersisa.',
                'point_cost' => 2000,
                'stock' => 2,
                'active' => true,
                'sort_order' => 5,
                'source' => 'Jersey CL 1.png',
            ],
            [
                'name' => 'Tas Olahraga',
                'description' => 'Tas serut untuk perlengkapan latihan.',
                'point_cost' => 800,
                'stock' => 15,
                'active' => true,
                'sort_order' => 7,
                'source' => 'Tas Serut CL.png',
            ],
            [
                'name' => 'Lanyard Community League',
                'description' => 'Lanyard/tali gantungan kartu dengan logo Community League.',
                'point_cost' => 150,
                'stock' => 25,
                'active' => true,
                'sort_order' => 8,
                'source' => 'Landyard CL.png',
            ],
            [
                'name' => 'Jersey Community League — Biru',
                'description' => 'Jersey Community League, varian warna biru.',
                'point_cost' => 1200,
                'stock' => 10,
                'active' => true,
                'sort_order' => 9,
                'source' => 'Jersey CL 2.png',
            ],
            [
                'name' => 'Jersey Community League — Merah Hitam',
                'description' => 'Jersey Community League, varian warna merah-hitam.',
                'point_cost' => 1200,
                'stock' => 10,
                'active' => true,
                'sort_order' => 10,
                'source' => 'Jersey CL 3.png',
            ],
            [
                'name' => 'Jersey Community League — Teal',
                'description' => 'Jersey Community League, varian warna teal.',
                'point_cost' => 1200,
                'stock' => 10,
                'active' => true,
                'sort_order' => 11,
                'source' => 'Jersey CL 4.png',
            ],
        ];
    }

    private function storePhoto(string $filename): string
    {
        $path = base_path("asset_foto/merchandise/{$filename}");
        throw_unless(is_file($path), new RuntimeException("Foto merchandise tidak ditemukan: {$path}"));

        $contents = file_get_contents($path);
        throw_if($contents === false, new RuntimeException("Gagal membaca foto merchandise: {$path}"));

        $stored = CompressedImageUpload::compressAndStoreContents($contents, 'reward-items', maxDimension: 1000);
        throw_if($stored === null, new RuntimeException("Gagal memproses foto merchandise (bukan gambar valid?): {$path}"));

        return $stored;
    }
}
