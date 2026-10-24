<?php

use App\Models\Booking;
use App\Models\City;
use App\Models\GameMatch;
use App\Models\Venue;

it('tamu tanpa login bisa membuka katalog match', function () {
    $this->get(route('match'))->assertOk();
});

it('hanya menampilkan match yang sudah published', function () {
    $terbuka = GameMatch::factory()->create(['status' => 'published', 'title' => 'Match Terbuka']);
    $draft = GameMatch::factory()->create(['status' => 'draft', 'title' => 'Match Draft']);

    $response = $this->get(route('match'));

    $response->assertSee('Match Terbuka')->assertDontSee('Match Draft');
});

it('memfilter berdasarkan kota lewat query string', function () {
    $solo = City::factory()->create(['name' => 'Surakarta']);
    $jogja = City::factory()->create(['name' => 'Yogyakarta']);
    $venueSolo = Venue::factory()->for($solo)->create();
    $venueJogja = Venue::factory()->for($jogja)->create();

    GameMatch::factory()->for($venueSolo, 'venue')->create(['status' => 'published', 'title' => 'Match Solo']);
    GameMatch::factory()->for($venueJogja, 'venue')->create(['status' => 'published', 'title' => 'Match Jogja']);

    $response = $this->get(route('match', ['city' => $solo->id]));

    $response->assertSee('Match Solo')->assertDontSee('Match Jogja');
});

it('memfilter berdasarkan jenis match', function () {
    GameMatch::factory()->create(['status' => 'published', 'type' => 'minisoccer', 'title' => 'Mini Soccer X']);
    GameMatch::factory()->create(['status' => 'published', 'type' => 'football', 'title' => 'Sepak Bola X']);

    $response = $this->get(route('match', ['type' => 'football']));

    $response->assertSee('Sepak Bola X')->assertDontSee('Mini Soccer X');
});

it('mencari lewat judul atau nama venue', function () {
    $venue = Venue::factory()->create(['name' => 'GOR Manahan']);
    GameMatch::factory()->for($venue, 'venue')->create(['status' => 'published', 'title' => 'Turnamen Akhir Pekan']);
    GameMatch::factory()->create(['status' => 'published', 'title' => 'Lain Sekali']);

    $response = $this->get(route('match', ['q' => 'Manahan']));

    $response->assertSee('Turnamen Akhir Pekan')->assertDontSee('Lain Sekali');
});

it('memfilter berdasarkan venue', function () {
    $venueA = Venue::factory()->create(['name' => 'Venue A']);
    $venueB = Venue::factory()->create(['name' => 'Venue B']);
    GameMatch::factory()->for($venueA, 'venue')->create(['status' => 'published', 'title' => 'Match Venue A']);
    GameMatch::factory()->for($venueB, 'venue')->create(['status' => 'published', 'title' => 'Match Venue B']);

    $response = $this->get(route('match', ['venue' => $venueA->id]));

    $response->assertSee('Match Venue A')->assertDontSee('Match Venue B');
});

it('memfilter berdasarkan rentang tanggal', function () {
    GameMatch::factory()->create(['status' => 'published', 'starts_at' => now()->addDays(2), 'title' => 'Match Dekat']);
    GameMatch::factory()->create(['status' => 'published', 'starts_at' => now()->addDays(20), 'title' => 'Match Jauh']);

    $response = $this->get(route('match', [
        'date_from' => now()->addDay()->toDateString(),
        'date_to' => now()->addDays(5)->toDateString(),
    ]));

    $response->assertSee('Match Dekat')->assertDontSee('Match Jauh');
});

it('memfilter hanya match yang masih ada slot', function () {
    $penuh = GameMatch::factory()->create(['status' => 'published', 'capacity' => 1, 'title' => 'Match Penuh']);
    Booking::factory()->for($penuh, 'match')->create(['quantity' => 1, 'status' => 'paid']);
    GameMatch::factory()->create(['status' => 'published', 'capacity' => 5, 'title' => 'Match Longgar']);

    $response = $this->get(route('match', ['available_only' => '1']));

    $response->assertSee('Match Longgar')->assertDontSee('Match Penuh');
});

it('katalog tidak error 500 kalau date_from tidak valid', function () {
    $this->get(route('match', ['date_from' => 'bukan-tanggal']))->assertOk();
});

it('katalog tidak error 500 kalau type dikirim sebagai array', function () {
    $this->get('/match?type[]=abc')->assertOk();
});

it('filter kota/jenis/venue di katalog bukan lagi native select', function () {
    // Sama seperti sidebar filter Home -- native <select> beda tampilan Android vs iPhone.
    $response = $this->get(route('match'));

    $response->assertDontSee('<select name="city"', false)
        ->assertDontSee('<select name="type"', false)
        ->assertDontSee('<select name="venue"', false);
});

it('katalog tidak error 500 kalau q dikirim sebagai array', function () {
    $this->get('/match?q[]=abc')->assertOk();
});

it('tidak menampilkan match finished kalau hide_when_finished aktif', function () {
    GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->subWeek(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subHour(),
        'hide_when_finished' => true,
        'title' => 'Match Sudah Selesai',
    ]);
    GameMatch::factory()->create(['status' => 'published', 'title' => 'Match Masih Berjalan']);

    $response = $this->get(route('match'));

    $response->assertSee('Match Masih Berjalan')->assertDontSee('Match Sudah Selesai');
});

it('match yang sudah selesai tetap tampil kalau hide_when_finished masih false (default)', function () {
    GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->subWeek(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subHour(),
        'hide_when_finished' => false,
        'title' => 'Match Selesai Tetap Tampil',
    ]);

    $this->get(route('match'))->assertSee('Match Selesai Tetap Tampil');
});

it('menampilkan match yang sudah diaktifkan tapi belum dibuka pendaftarannya', function () {
    GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->addHour(),
        'title' => 'Match Belum Buka Pendaftaran',
    ]);

    $this->get(route('match'))->assertSee('Match Belum Buka Pendaftaran');
});
