<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Support\CompressedImageUpload;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Isi/perbarui Post tipe 'news' dengan foto dokumentasi asli dari asset_foto/dokumentasi/
 * (dibawa masuk ke image Docker lewat `COPY . .`, tidak di .dockerignore -- jadi path ini ada
 * juga di container staging/produksi, bukan cuma lokal). Idempotent (updateOrCreate berdasar
 * slug) -- aman dijalankan ulang, sama seperti MerchandiseSeeder. Jalankan manual:
 * `php -d memory_limit=512M artisan db:seed --class=PostSeeder` -- foto dokumentasi asli dari
 * kamera (sampai 6000x4000px), decode GD-nya butuh lebih dari default memory_limit CLI 128M.
 *
 * Konten teks sengaja generik (bukan klaim skor/hasil spesifik) -- foto dokumentasi
 * menampilkan klub & orang sungguhan, jadi caption tidak menyematkan hasil pertandingan yang
 * tidak benar-benar terjadi seperti itu ke mereka.
 *
 * Setiap run generate file WebP baru (nama random) tanpa menghapus file lama -- oke untuk
 * skrip konten yang jarang dijalankan ulang, bukan concern kalau dijalankan sesekali.
 */
class PostSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $item) {
            $filename = $item['source'];
            unset($item['source']);

            $item['image_path'] = $this->storePhoto($filename);

            Post::updateOrCreate(['slug' => $item['slug']], $item);
        }
    }

    /** @return list<array{type: string, title: string, slug: string, excerpt: string, body: string, published_at: CarbonImmutable, sort_order: int, is_active: bool, source: string}> */
    private function items(): array
    {
        return [
            [
                'type' => 'news',
                'title' => 'Persiapan Matang Jelang Laga Community League',
                'slug' => 'persiapan-matang-jelang-laga-community-league',
                'excerpt' => 'Arahan singkat dari pelatih sebelum turun ke lapangan jadi rutinitas wajib di setiap laga Community League.',
                'body' => '<p>Setiap laga Community League selalu diawali dengan briefing singkat antara pelatih dan pemain. Instruksi taktik, pembagian posisi, hingga pesan semangat disampaikan beberapa menit sebelum peluit dibunyikan.</p><p>Rutinitas kecil seperti ini yang membuat setiap tim yang turun di Community League tampil lebih siap, baik secara teknik maupun mental.</p>',
                'published_at' => now()->subDays(12),
                'sort_order' => 1,
                'is_active' => true,
                'source' => '_D4S3370.jpg.jpeg',
            ],
            [
                'type' => 'news',
                'title' => 'Momen Juara di Turnamen Community League',
                'slug' => 'momen-juara-di-turnamen-community-league',
                'excerpt' => 'Trofi juara dan sertifikat apresiasi diserahkan langsung di tengah lapangan, menutup rangkaian turnamen dengan meriah.',
                'body' => '<p>Serah terima trofi juara jadi penutup manis dari rangkaian turnamen yang digelar Community League. Kapten tim tampil membawa pulang trofi bersama sertifikat apresiasi dari panitia.</p><p>Momen seperti ini jadi pengingat bahwa setiap turnamen bukan cuma soal menang-kalah, tapi juga soal kebersamaan yang terbangun sepanjang kompetisi.</p>',
                'published_at' => now()->subDays(9),
                'sort_order' => 2,
                'is_active' => true,
                'source' => 'mengky.-224.jpg.jpeg',
            ],
            [
                'type' => 'news',
                'title' => 'Serunya Laga Community League di Stadion Besar',
                'slug' => 'serunya-laga-community-league-di-stadion-besar',
                'excerpt' => 'Kesempatan bertanding di stadion berstandar besar jadi pengalaman tersendiri buat para pemain komunitas.',
                'body' => '<p>Tidak setiap hari pemain komunitas bisa merasakan atmosfer bertanding di stadion besar. Barisan pemain dari dua tim tampak berjajar rapi sebelum laga dimulai, menambah kesan spesial pertandingan kali ini.</p><p>Community League terus berupaya menghadirkan pengalaman bertanding yang berkesan, tidak cuma soal kompetisi tapi juga momen yang bisa dikenang para pemainnya.</p>',
                'published_at' => now()->subDays(20),
                'sort_order' => 3,
                'is_active' => true,
                'source' => 'MGKY3459.jpg.jpeg',
            ],
            [
                'type' => 'news',
                'title' => 'Kekompakan Tim Jadi Kunci di Setiap Laga',
                'slug' => 'kekompakan-tim-jadi-kunci-di-setiap-laga',
                'excerpt' => 'Semangat kebersamaan sebelum turun ke lapangan jadi salah satu ciri khas laga-laga Community League.',
                'body' => '<p>Sebelum turun ke lapangan, para pemain selalu menyempatkan diri untuk saling menyemangati. Kebersamaan seperti ini yang bikin suasana bertanding di Community League terasa lebih hangat.</p><p>Bukan cuma soal skill individu, kekompakan tim juga jadi salah satu kunci performa yang konsisten di setiap laga.</p>',
                'published_at' => now()->subDays(6),
                'sort_order' => 4,
                'is_active' => true,
                'source' => 'mengky.-73.jpg.jpeg',
            ],
            [
                'type' => 'news',
                'title' => 'Sorotan Kiper di Balik Gawang Community League',
                'slug' => 'sorotan-kiper-di-balik-gawang-community-league',
                'excerpt' => 'Peran penjaga gawang sering luput dari sorotan, padahal jadi benteng terakhir yang menentukan jalannya laga.',
                'body' => '<p>Di balik setiap kemenangan tim, ada peran penjaga gawang yang seringkali tidak banyak disorot. Konsentrasi tinggi sepanjang laga jadi tuntutan wajib buat posisi ini.</p><p>Community League mengapresiasi kerja keras seluruh pemain di semua posisi, termasuk mereka yang berjaga di bawah mistar.</p>',
                'published_at' => now()->subDays(3),
                'sort_order' => 5,
                'is_active' => true,
                'source' => 'mengkyproject-311.jpg.jpeg',
            ],
            [
                'type' => 'news',
                'title' => 'Gol Indah Warnai Laga Community League',
                'slug' => 'gol-indah-warnai-laga-community-league',
                'excerpt' => 'Selebrasi penuh semangat mewarnai setiap gol yang tercipta di laga-laga Community League.',
                'body' => '<p>Setiap gol yang tercipta selalu disambut dengan selebrasi penuh semangat dari para pemain. Momen-momen seperti ini yang bikin suasana pertandingan Community League selalu hidup.</p><p>Antusiasme pemain di lapangan jadi cerminan semangat komunitas yang terus tumbuh di setiap penyelenggaraan Community League.</p>',
                'published_at' => now()->subDays(15),
                'sort_order' => 6,
                'is_active' => true,
                'source' => 'MGKY5407.jpg.jpeg',
            ],
            [
                'type' => 'news',
                'title' => 'Aksi Menawan di Lapangan Community League',
                'slug' => 'aksi-menawan-di-lapangan-community-league',
                'excerpt' => 'Tendangan keras dan aksi individu jadi tontonan menarik di setiap laga Community League.',
                'body' => '<p>Aksi-aksi individu seperti tendangan keras dan dribel memukau selalu jadi tontonan menarik buat penonton yang hadir. Kualitas permainan di Community League terus meningkat seiring makin banyaknya pemain yang bergabung.</p><p>Semangat kompetitif yang sehat inilah yang ingin terus dijaga Community League di setiap penyelenggaraan laganya.</p>',
                'published_at' => now()->subDays(18),
                'sort_order' => 7,
                'is_active' => true,
                'source' => 'MGKY5448.jpg.jpeg',
            ],
            [
                'type' => 'news',
                'title' => 'Di Balik Layar: Tim Official yang Selalu Siaga',
                'slug' => 'di-balik-layar-tim-official-yang-selalu-siaga',
                'excerpt' => 'Kelancaran setiap laga tak lepas dari kerja tim official yang siap sedia dari awal hingga akhir pertandingan.',
                'body' => '<p>Selain pemain di lapangan, kelancaran setiap laga Community League juga ditopang oleh tim official yang selalu siaga menyiapkan kebutuhan minum hingga perlengkapan pertandingan.</p><p>Kerja keras di balik layar seperti ini yang membuat setiap penyelenggaraan Community League bisa berjalan lancar dari awal sampai akhir.</p>',
                'published_at' => now()->subDays(25),
                'sort_order' => 8,
                'is_active' => true,
                'source' => 'pgc-440.jpg.jpeg',
            ],
        ];
    }

    private function storePhoto(string $filename): string
    {
        $path = base_path("asset_foto/dokumentasi/{$filename}");
        throw_unless(is_file($path), new RuntimeException("Foto dokumentasi tidak ditemukan: {$path}"));

        $contents = file_get_contents($path);
        throw_if($contents === false, new RuntimeException("Gagal membaca foto dokumentasi: {$path}"));

        $stored = CompressedImageUpload::compressAndStoreContents($contents, 'posts', maxDimension: 1600);
        throw_if($stored === null, new RuntimeException("Gagal memproses foto dokumentasi (bukan gambar valid?): {$path}"));

        return $stored;
    }
}
