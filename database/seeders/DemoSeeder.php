<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\Membership;
use App\Models\PointsTransaction;
use App\Models\RewardItem;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data demo untuk staging -- akun, match, merchandise, booking dengan berbagai kondisi tepi,
 * supaya aplikasi terasa "sudah jalan" saat dicoba. Dipanggil manual (bukan lewat
 * DatabaseSeeder biasa) -- `php artisan db:seed --class=DemoSeeder`. TIDAK idempotent (akun
 * pakai create() polos, bukan firstOrCreate -- run kedua akan gagal karena email unik) --
 * jalankan sekali saja per environment.
 *
 * Sengaja TIDAK pakai ::factory() sama sekali -- factory di proyek ini (UserFactory,
 * GameMatchFactory, RewardItemFactory) memanggil fake() di definition()-nya, yang tetap
 * dievaluasi Laravel walau field itu di-override di sini. paket fakerphp/faker ada di
 * require-dev, tidak ikut ter-install di image produksi (composer install --no-dev) --
 * ::factory() apa pun akan gagal "Call to undefined function fake()" saat dijalankan di
 * container staging/produksi, walau lolos mulus di test lokal (dev dependencies lengkap).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleSeeder::class, CitySeeder::class, VenueSeeder::class]);

        $venues = Venue::all();

        $users = $this->seedUsers();
        $this->seedMatchesAndBookings($venues, $users);
        $this->seedMarketplace($users);

        $this->command->info('Demo seed selesai. Password semua akun demo: Demo12345!');
    }

    /** @return array<string, User> keyed by a short handle for reuse below */
    private function seedUsers(): array
    {
        $make = function (array $attributes): User {
            return User::create(array_merge([
                'password' => 'Demo12345!',
                'email_verified_at' => now(),
                'phone' => '08'.str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT),
            ], $attributes));
        };

        $users = [];

        $users['reguler_aktif'] = $make([
            'name' => 'Ahmad Pratama',
            'email' => 'ahmad.pratama@demo.communityleague.id',
        ]);

        $users['reguler_baru'] = $make([
            'name' => 'Siti Nurhaliza',
            'email' => 'siti.nurhaliza@demo.communityleague.id',
        ]);

        $users['belum_verifikasi'] = $make([
            'name' => 'Rudi Hartono',
            'email' => 'rudi.hartono@demo.communityleague.id',
            'email_verified_at' => null,
        ]);

        $users['member_aktif'] = $make([
            'name' => 'Dewi Anggraini',
            'email' => 'dewi.anggraini@demo.communityleague.id',
        ]);
        Membership::create(['user_id' => $users['member_aktif']->id, 'expires_at' => now()->addDays(300)]);
        PointsTransaction::create(['user_id' => $users['member_aktif']->id, 'amount' => 750, 'type' => 'admin_grant', 'description' => 'Seed demo']);

        $users['member_hampir_habis'] = $make([
            'name' => 'Bagus Wicaksono',
            'email' => 'bagus.wicaksono@demo.communityleague.id',
        ]);
        Membership::create(['user_id' => $users['member_hampir_habis']->id, 'expires_at' => now()->addDays(5)]);
        PointsTransaction::create(['user_id' => $users['member_hampir_habis']->id, 'amount' => 1200, 'type' => 'admin_grant', 'description' => 'Seed demo']);

        $users['member_lapsed_tenggang'] = $make([
            'name' => 'Rina Marlina',
            'email' => 'rina.marlina@demo.communityleague.id',
        ]);
        Membership::create(['user_id' => $users['member_lapsed_tenggang']->id, 'expires_at' => now()->subDays(3)]);
        PointsTransaction::create(['user_id' => $users['member_lapsed_tenggang']->id, 'amount' => 400, 'type' => 'admin_grant', 'description' => 'Seed demo']);

        $users['member_lapsed_hangus'] = $make([
            'name' => 'Fajar Nugroho',
            'email' => 'fajar.nugroho@demo.communityleague.id',
        ]);
        Membership::create([
            'user_id' => $users['member_lapsed_hangus']->id,
            'expires_at' => now()->subDays(20),
            'points_forfeited_at' => now()->subDays(13),
        ]);
        PointsTransaction::create(['user_id' => $users['member_lapsed_hangus']->id, 'amount' => 900, 'type' => 'admin_grant', 'description' => 'Seed demo (sudah hangus)']);
        PointsTransaction::create(['user_id' => $users['member_lapsed_hangus']->id, 'amount' => -900, 'type' => 'forfeiture', 'description' => 'Poin hangus -- membership tidak diperpanjang dalam masa tenggang.']);

        $users['member_redeem'] = $make([
            'name' => 'Maya Puspita',
            'email' => 'maya.puspita@demo.communityleague.id',
        ]);
        Membership::create(['user_id' => $users['member_redeem']->id, 'expires_at' => now()->addDays(150)]);
        PointsTransaction::create(['user_id' => $users['member_redeem']->id, 'amount' => 1500, 'type' => 'admin_grant', 'description' => 'Seed demo']);

        $users['poin_tanpa_member'] = $make([
            'name' => 'Eko Setiawan',
            'email' => 'eko.setiawan@demo.communityleague.id',
        ]);
        PointsTransaction::create(['user_id' => $users['poin_tanpa_member']->id, 'amount' => 300, 'type' => 'admin_grant', 'description' => 'Seed demo -- poin tanpa membership']);

        $users['member_nol_poin'] = $make([
            'name' => 'Lestari Wijaya',
            'email' => 'lestari.wijaya@demo.communityleague.id',
        ]);
        Membership::create(['user_id' => $users['member_nol_poin']->id, 'expires_at' => now()->addDays(60)]);

        $users['panitia'] = $make([
            'name' => 'Panitia Demo',
            'email' => 'panitia.demo@communityleague.id',
        ]);
        $users['panitia']->assignRole('panitia');

        $users['super_admin'] = $make([
            'name' => 'Super Admin Demo',
            'email' => 'admin.demo@communityleague.id',
        ]);
        $users['super_admin']->assignRole('super_admin');

        return $users;
    }

    /**
     * @param  Collection<int, Venue>  $venues
     * @param  array<string, User>  $users
     */
    private function seedMatchesAndBookings($venues, array $users): void
    {
        $venueByName = fn (string $name) => $venues->firstWhere('name', $name);

        $match = function (array $attributes) use ($venueByName): GameMatch {
            $title = $attributes['title'];
            $starts = $attributes['starts_at'];

            return GameMatch::create(array_merge([
                'venue_id' => $venueByName($attributes['venue'])->id,
                'field_name' => 'Lapangan A',
                'type' => 'minisoccer',
                'title' => $title,
                'slug' => Str::slug($title).'-'.Str::random(6),
                'description' => 'Match komunitas -- data demo.',
                'poster_path' => null,
                'ends_at' => $starts->copy()->addHours(2),
                'price_member' => $attributes['price_regular'] ?? 35000,
                'points_reward' => 10,
                'member_opens_at' => now()->subDay(),
                'public_opens_at' => now()->subHour(),
                'status' => 'published',
                'hide_when_finished' => false,
                'last_completed_wizard_step' => 4,
                'score_a' => null,
                'score_b' => null,
                'team_a_name' => null,
                'team_b_name' => null,
                'team_a_logo_path' => null,
                'team_b_logo_path' => null,
            ], collect($attributes)->except(['venue'])->all()));
        };

        $matches = [];

        $matches['open_lapang'] = $match([
            'venue' => 'Nusukan Sport Center',
            'title' => 'Liga Malam Nusukan',
            'type' => 'minisoccer',
            'starts_at' => now()->addDays(3),
            'capacity' => 10,
            'price_regular' => 40000,
            'points_reward' => 15,
        ]);

        $matches['hampir_penuh'] = $match([
            'venue' => 'Manahan Mini Soccer',
            'title' => 'Turnamen Sore Manahan',
            'type' => 'minisoccer',
            'starts_at' => now()->addDays(5),
            'capacity' => 8,
            'price_regular' => 35000,
            'points_reward' => 10,
        ]);

        $matches['penuh'] = $match([
            'venue' => 'Gelora Mojosongo Arena',
            'title' => 'Derby Gelora Mojosongo',
            'type' => 'football',
            'starts_at' => now()->addDays(2),
            'capacity' => 5,
            'price_regular' => 60000,
            'points_reward' => 20,
        ]);

        $match([
            'venue' => 'Solo Baru Soccer Field',
            'title' => 'Pra-Musim Solo Baru',
            'type' => 'minisoccer',
            'starts_at' => now()->addDays(9),
            'public_opens_at' => now()->addDays(2),
            'capacity' => 10,
            'price_regular' => 35000,
            'points_reward' => 10,
        ]);

        $match([
            'venue' => 'Palur Sport Arena',
            'title' => 'Championship Palur',
            'type' => 'football',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'capacity' => 16,
            'price_regular' => 65000,
            'points_reward' => 30,
        ]);

        $matches['selesai_tampil'] = $match([
            'venue' => 'Nusukan Sport Center',
            'title' => 'Selesai Kemarin Nusukan',
            'type' => 'minisoccer',
            'starts_at' => now()->subDay()->subHours(2),
            'ends_at' => now()->subDay(),
            'capacity' => 10,
            'price_regular' => 35000,
            'points_reward' => 10,
            'hide_when_finished' => false,
        ]);

        $match([
            'venue' => 'Manahan Mini Soccer',
            'title' => 'Selesai Disembunyikan',
            'type' => 'minisoccer',
            'starts_at' => now()->subDays(2)->subHours(2),
            'ends_at' => now()->subDays(2),
            'capacity' => 10,
            'price_regular' => 35000,
            'points_reward' => 10,
            'hide_when_finished' => true,
        ]);

        $match([
            'venue' => 'Gelora Mojosongo Arena',
            'title' => 'Draft Belum Terbit',
            'type' => 'minisoccer',
            'status' => 'draft',
            'starts_at' => now()->addDays(14),
            'capacity' => 10,
            'price_regular' => 35000,
        ]);

        $match([
            'venue' => 'Solo Baru Soccer Field',
            'title' => 'Dibatalkan Karena Hujan',
            'type' => 'minisoccer',
            'status' => 'cancelled',
            'starts_at' => now()->addDays(4),
            'capacity' => 10,
            'price_regular' => 35000,
        ]);

        $match([
            'venue' => 'Palur Sport Arena',
            'title' => 'Liga Sepak Bola Akhir Pekan',
            'type' => 'football',
            'starts_at' => now()->addDays(10),
            'capacity' => 22,
            'price_regular' => 75000,
            'points_reward' => 0,
        ]);

        $match([
            'venue' => 'Nusukan Sport Center',
            'title' => 'Eksebisi All-Star',
            'type' => 'football',
            'starts_at' => now()->addDays(15),
            'capacity' => 14,
            'price_regular' => 100000,
            'points_reward' => 300,
            'team_a_name' => 'FC Solo Utara',
            'team_b_name' => 'FC Solo Selatan',
        ]);

        $match([
            'venue' => 'Manahan Mini Soccer',
            'title' => 'Community Cup Minisoccer',
            'type' => 'minisoccer',
            'starts_at' => now()->addDays(20),
            'capacity' => 12,
            'price_regular' => 45000,
            'points_reward' => 25,
        ]);

        $match([
            'venue' => 'Gelora Mojosongo Arena',
            'title' => 'Latihan Bersama',
            'type' => 'minisoccer',
            'starts_at' => now()->addDay(),
            'capacity' => 6,
            'price_regular' => 25000,
            'points_reward' => 5,
        ]);

        $match([
            'venue' => 'Solo Baru Soccer Field',
            'title' => 'Liga Malam Sukoharjo',
            'type' => 'football',
            'starts_at' => now()->addDays(25),
            'capacity' => 18,
            'price_regular' => 55000,
            'points_reward' => 50,
        ]);

        $match([
            'venue' => 'Palur Sport Arena',
            'title' => 'Turnamen Tahun Baru',
            'type' => 'minisoccer',
            'starts_at' => now()->addDays(60),
            'capacity' => 10,
            'price_regular' => 40000,
            'points_reward' => 10,
        ]);

        $booking = fn (array $attributes) => Booking::create(array_merge([
            'quantity' => 1,
            'terms_version' => User::TERMS_VERSION,
        ], $attributes));

        // Booking: match "hampir penuh" -- 7 dari 8 slot terisi (tamu, status campuran).
        for ($i = 1; $i <= 7; $i++) {
            $booking([
                'match_id' => $matches['hampir_penuh']->id,
                'user_id' => null,
                'guest_name' => "Tamu Manahan {$i}",
                'guest_email' => "tamu.manahan{$i}@example.test",
                'guest_phone' => '0812'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'unit_price' => 35000,
                'total' => 35000,
                'status' => 'paid',
                'paid_at' => now()->subHours($i),
            ]);
        }

        // Booking: match "penuh" -- 5 dari 5 slot terisi (2 akun demo + 3 tamu).
        $booking([
            'match_id' => $matches['penuh']->id,
            'user_id' => $users['reguler_aktif']->id,
            'unit_price' => 60000,
            'total' => 60000,
            'status' => 'paid',
            'paid_at' => now()->subDay(),
        ]);
        $booking([
            'match_id' => $matches['penuh']->id,
            'user_id' => $users['member_redeem']->id,
            'unit_price' => 60000,
            'total' => 60000,
            'status' => 'paid',
            'paid_at' => now()->subDay(),
        ]);
        for ($i = 1; $i <= 3; $i++) {
            $booking([
                'match_id' => $matches['penuh']->id,
                'user_id' => null,
                'guest_name' => "Tamu Derby {$i}",
                'guest_email' => "tamu.derby{$i}@example.test",
                'guest_phone' => '0813'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'unit_price' => 60000,
                'total' => 60000,
                'status' => 'paid',
                'paid_at' => now()->subHours($i),
            ]);
        }

        // Booking non-aktif di match yang masih longgar -- tidak memengaruhi sisa slot, tapi
        // memberi variasi status untuk dilihat di panel admin.
        $booking([
            'match_id' => $matches['open_lapang']->id,
            'user_id' => $users['reguler_baru']->id,
            'unit_price' => 40000,
            'total' => 40000,
            'status' => 'pending_payment',
            'hold_expires_at' => now()->addMinutes(20),
        ]);
        $booking([
            'match_id' => $matches['open_lapang']->id,
            'user_id' => null,
            'guest_name' => 'Tamu Batal',
            'guest_email' => 'tamu.batal@example.test',
            'guest_phone' => '081200000000',
            'unit_price' => 40000,
            'total' => 40000,
            'status' => 'expired',
            'hold_expires_at' => now()->subHour(),
        ]);

        // Riwayat "Tiket Saya" untuk akun demo lain, di match yang sudah selesai.
        $booking([
            'match_id' => $matches['selesai_tampil']->id,
            'user_id' => $users['member_aktif']->id,
            'unit_price' => 35000,
            'total' => 35000,
            'status' => 'paid',
            'paid_at' => now()->subDay()->subHour(),
        ]);
    }

    /**
     * @param  array<string, User>  $users
     *
     * Merchandise dengan foto asli (Kaos, Topi, Voucher, Jersey Edisi Terbatas, Tas Olahraga,
     * Lanyard, 3 varian Jersey) dibuat lewat MerchandiseSeeder (idempotent, dipakai bareng
     * DemoSeeder di sini supaya foto produk sungguhan ikut ada tiap kali environment demo
     * disiapkan dari nol) -- lihat Database\Seeders\MerchandiseSeeder. Cuma 2 item tanpa foto
     * asli (Botol Minum, Merchandise Lama) yang masih pakai thumbnail placeholder di sini.
     */
    private function seedMarketplace(array $users): void
    {
        $this->call(MerchandiseSeeder::class);

        RewardItem::create([
            'name' => 'Botol Minum Olahraga',
            'description' => 'Botol minum 750ml, stainless steel.',
            'point_cost' => 200,
            'stock' => 0,
            'active' => true,
            'sort_order' => 3,
            'image_path' => $this->rewardItemThumbnail('botol-minum', 'beaker', '#C51116'),
        ]);

        RewardItem::create([
            'name' => 'Merchandise Lama (Nonaktif)',
            'description' => 'Sudah tidak diproduksi lagi.',
            'point_cost' => 150,
            'stock' => 10,
            'active' => false,
            'sort_order' => 6,
            'image_path' => $this->rewardItemThumbnail('merchandise-lama', 'archive-box', '#a3a3a3'),
        ]);

        // Contoh penukaran: satu masih pending, satu sudah fulfilled -- untuk demo tab
        // "Penukaran Poin" di panel admin.
        $member = $users['member_redeem'];
        $kaos = RewardItem::where('name', 'Kaos Community League')->firstOrFail();
        $voucher = RewardItem::where('name', 'Voucher Diskon Turnamen')->firstOrFail();

        PointsTransaction::create([
            'user_id' => $member->id,
            'amount' => -$kaos->point_cost,
            'type' => 'redemption',
            'description' => "Tukar poin: {$kaos->name}",
        ]);
        RewardRedemption::create([
            'user_id' => $member->id,
            'reward_item_id' => $kaos->id,
            'points_spent' => $kaos->point_cost,
            'status' => 'pending',
            'redeemed_at' => now()->subDays(2),
        ]);

        PointsTransaction::create([
            'user_id' => $member->id,
            'amount' => -$voucher->point_cost,
            'type' => 'redemption',
            'description' => "Tukar poin: {$voucher->name}",
        ]);
        RewardRedemption::create([
            'user_id' => $member->id,
            'reward_item_id' => $voucher->id,
            'points_spent' => $voucher->point_cost,
            'status' => 'fulfilled',
            'redeemed_at' => now()->subWeek(),
            'fulfilled_at' => now()->subDays(5),
            'fulfilled_by' => $users['panitia']->id,
        ]);
    }

    /**
     * Thumbnail placeholder demo: heroicon putih di atas lingkaran warna brand, ditulis ke
     * storage disk 'public' supaya bisa dirender lewat pola image_path yang sama seperti upload
     * asli (lihat GameMatchForm::poster_path). Bukan foto produk sungguhan -- dipakai cuma untuk
     * 2 item yang belum ada foto aslinya (Botol Minum, Merchandise Lama).
     */
    private function rewardItemThumbnail(string $slug, string $iconFile, string $bgHex): string
    {
        // Heroicons dibawa Flux sebagai stub Blade; varian outline = <svg> pertama di file.
        $icon = file_get_contents(base_path("vendor/livewire/flux/stubs/resources/views/flux/icon/{$iconFile}.blade.php"));
        throw_if($icon === false || ! preg_match('/<svg[^>]*>(.*?)<\/svg>/s', $icon, $match), new RuntimeException("Ikon heroicon tidak ditemukan: {$iconFile}"));
        $inner = $match[1];

        $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
            <rect width="200" height="200" rx="28" fill="{$bgHex}" />
            <svg x="50" y="50" width="100" height="100" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="1.5">
                {$inner}
            </svg>
        </svg>
        SVG;

        $path = "reward-items/{$slug}.svg";
        Storage::disk('public')->put($path, $svg);

        return $path;
    }
}
