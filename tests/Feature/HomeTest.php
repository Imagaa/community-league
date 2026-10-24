<?php

use App\Models\City;
use App\Models\GameMatch;
use App\Models\MembershipSettings;
use App\Models\Post;
use App\Models\RewardItem;
use App\Models\Venue;

it('tamu tanpa login bisa membuka homepage', function () {
    $this->get(route('home'))->assertOk();
});

it('homepage menampilkan match yang sudah published, bukan draft atau cancelled', function () {
    $terbuka = GameMatch::factory()->create(['status' => 'published', 'title' => 'Match Terbuka Home']);
    $draft = GameMatch::factory()->create(['status' => 'draft', 'title' => 'Match Draft Home']);
    $batal = GameMatch::factory()->create(['status' => 'cancelled', 'title' => 'Match Batal Home']);

    $response = $this->get(route('home'));

    $response->assertSee('Match Terbuka Home')
        ->assertDontSee('Match Draft Home')
        ->assertDontSee('Match Batal Home');
});

it('homepage cuma menampilkan maksimal 6 match, urut yang terdekat dulu', function () {
    // Limit 6 (bukan 5) supaya grid desktop 3 kolom x 2 baris pas penuh -- mobile (list satu
    // kolom) menyembunyikan item ke-6 lewat CSS, lihat komentar di home.blade.php.
    $terdekat = GameMatch::factory()->create(['starts_at' => now()->addDay(), 'title' => 'Match Terdekat Home']);
    GameMatch::factory()->count(7)->create(['starts_at' => now()->addDays(10)]);

    $response = $this->get(route('home'));

    $response->assertSee('Match Terdekat Home');
    expect($response->viewData('matches'))->toHaveCount(6);
});

it('homepage tidak menampilkan match selesai duluan walau lebih lama ada di database', function () {
    $lama = GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->subMonths(2),
        'starts_at' => now()->subMonth(),
        'ends_at' => now()->subMonth()->addHours(2),
        'title' => 'Match Lama Selesai',
    ]);
    $upcoming = GameMatch::factory()->create([
        'status' => 'published',
        'starts_at' => now()->addWeek(),
        'title' => 'Match Akan Datang',
    ]);

    $response = $this->get(route('home'));

    $matches = $response->viewData('matches');

    expect($matches->pluck('title')->toArray())->toBe(['Match Akan Datang', 'Match Lama Selesai']);
});

it('homepage memfilter berdasarkan kota lewat query string', function () {
    $kotaA = City::factory()->create(['name' => 'Surakarta']);
    $kotaB = City::factory()->create(['name' => 'Sukoharjo']);
    $venueA = Venue::factory()->for($kotaA)->create();
    $venueB = Venue::factory()->for($kotaB)->create();
    GameMatch::factory()->for($venueA, 'venue')->create(['title' => 'Match Kota A Home']);
    GameMatch::factory()->for($venueB, 'venue')->create(['title' => 'Match Kota B Home']);

    $response = $this->get(route('home', ['city' => $kotaA->id]));

    $response->assertSee('Match Kota A Home')->assertDontSee('Match Kota B Home');
});

it('homepage menyediakan opsi Semua Kota di pemilih lokasi', function () {
    City::factory()->create(['name' => 'Surakarta']);

    $this->get(route('home'))->assertSee('Semua Kota');
});

it('homepage menampilkan blank-state untuk berita dan reward', function () {
    MembershipSettings::current()->update(['enabled' => true]);
    $this->get(route('home'))
        ->assertSee('Belum ada berita saat ini')
        ->assertSee('Belum ada reward yang tersedia saat ini.');
});

it('homepage menampilkan berita yang sudah terbit dan aktif', function () {
    Post::factory()->create(['title' => 'Matchday Solo', 'excerpt' => 'Ringkasan pertandingan']);

    $this->get(route('home'))
        ->assertSee('Matchday Solo')
        ->assertSee('Ringkasan pertandingan')
        ->assertDontSee('Belum ada berita saat ini');
});

it('homepage tidak menampilkan berita draft atau yang belum waktunya terbit', function () {
    Post::factory()->draft()->create(['title' => 'Berita Draft']);
    Post::factory()->create(['title' => 'Berita Masa Depan', 'published_at' => now()->addDay()]);

    $response = $this->get(route('home'));

    $response->assertDontSee('Berita Draft')
        ->assertDontSee('Berita Masa Depan')
        ->assertSee('Belum ada berita saat ini');
});

it('homepage tidak menampilkan post tipe galeri di carousel berita', function () {
    Post::factory()->create(['type' => 'carousel', 'title' => 'Galeri Dokumentasi']);

    $this->get(route('home'))
        ->assertDontSee('Galeri Dokumentasi')
        ->assertSee('Belum ada berita saat ini');
});

it('card promo di homepage mengarah ke link_url, bukan halaman detail', function () {
    Post::factory()->promo()->create(['title' => 'Promo Merchandise', 'link_url' => 'https://wa.me/6281234567890']);

    $this->get(route('home'))
        ->assertSee('https://wa.me/6281234567890', false)
        ->assertDontSee(route('berita.show', Post::first()), false);
});

it('panah navigasi carousel berita muncul kalau lebih dari satu post', function () {
    Post::factory()->create(['title' => 'Berita Satu']);
    Post::factory()->create(['title' => 'Berita Dua']);

    $this->get(route('home'))
        ->assertSee('Berita sebelumnya')
        ->assertSee('Berita berikutnya');
});

it('panah navigasi carousel berita tidak muncul kalau cuma satu post', function () {
    Post::factory()->create(['title' => 'Berita Tunggal']);

    $this->get(route('home'))
        ->assertDontSee('Berita sebelumnya')
        ->assertDontSee('Berita berikutnya');
});

it('homepage menampilkan preview reward marketplace kalau ada', function () {
    MembershipSettings::current()->update(['enabled' => true]);
    RewardItem::factory()->create(['name' => 'Kaos Community League', 'point_cost' => 500, 'active' => true, 'stock' => 5]);
    RewardItem::factory()->create(['name' => 'Nonaktif', 'active' => false]);

    $this->get(route('home'))
        ->assertSee('Kaos Community League')
        ->assertDontSee('Nonaktif')
        ->assertDontSee('Belum ada reward yang tersedia saat ini.');
});

it('homepage desktop memfilter berdasarkan jenis, venue, dan tanggal (bukan cuma kota)', function () {
    $venue = Venue::factory()->create();
    GameMatch::factory()->for($venue, 'venue')->create(['type' => 'football', 'title' => 'Match Sepak Bola Home']);
    GameMatch::factory()->for($venue, 'venue')->create(['type' => 'minisoccer', 'title' => 'Match Mini Home']);

    $response = $this->get(route('home', ['type' => 'football']));

    $response->assertSee('Match Sepak Bola Home')->assertDontSee('Match Mini Home');
});

it('homepage tidak lagi punya sidebar filter desktop -- filter lengkap cuma di /match', function () {
    // Sidebar filter (kota/jenis/venue/tanggal) sengaja DIHAPUS dari home 31 Ags 2026 -- home
    // cuma teaser 5 match terdekat, bukan katalog. Filter lengkap ada di /match saja. Filter
    // kota compact yang mobile-only (auto-submit, di sebelah "Jadwal Permainan Mendatang")
    // TETAP ada, jadi name="city" masih boleh muncul -- yang dihapus type/venue/date_from.
    $response = $this->get(route('home'));

    $response->assertDontSee('name="type"', false)
        ->assertDontSee('name="venue"', false)
        ->assertDontSee('name="date_from"', false)
        ->assertDontSee('<select name="city"', false);
});

it('homepage tidak error 500 kalau date_from tidak valid', function () {
    $this->get(route('home', ['date_from' => 'bukan-tanggal']))->assertOk();
});

it('homepage tidak error 500 kalau date_to tidak valid', function () {
    $this->get(route('home', ['date_to' => 'bukan-tanggal']))->assertOk();
});

it('homepage tidak error 500 kalau date_from dikirim sebagai array', function () {
    $this->get('/?date_from[]=abc')->assertOk();
});

it('homepage tidak error 500 kalau type dikirim sebagai array', function () {
    $this->get('/?type[]=abc')->assertOk();
});
