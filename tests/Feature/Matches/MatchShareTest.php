<?php

use App\Models\City;
use App\Models\GameMatch;
use App\Models\Venue;

it('halaman match punya tombol bagikan dan meta kartu pratinjau', function () {
    $match = GameMatch::factory()->create(['title' => 'Liga Jumat Malam', 'capacity' => 10]);

    $this->get(route('match.show', $match))
        ->assertOk()
        ->assertSee('Bagikan')
        ->assertSee('<meta property="og:title" content="Liga Jumat Malam', false)
        ->assertSee('<meta property="og:image" content="'.route('match.og', $match), false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('wa.me/?text=', false);
});

it('menghasilkan gambar pratinjau PNG 1200x630', function () {
    $match = GameMatch::factory()->create();

    $response = $this->get(route('match.og', $match))->assertOk()->assertHeader('Content-Type', 'image/png');

    [$width, $height] = getimagesizefromstring($response->getContent());
    expect([$width, $height])->toBe([1200, 630]);
});

it('tidak membuat gambar untuk match draft', function () {
    $this->get(route('match.og', GameMatch::factory()->create(['status' => 'draft'])))->assertNotFound();
});

it('halaman lain tetap punya meta pratinjau bawaan', function () {
    $this->get(route('home'))->assertSee('<meta property="og:image" content="'.asset('images/icon-512.png').'">', false);
});

it('detail match punya tombol Google Maps: link admin kalau ada, kalau tidak pencarian alamat', function () {
    $city = City::factory()->create(['name' => 'Surakarta']);
    $denganLink = GameMatch::factory()->for(Venue::factory()->create(['city_id' => $city->id, 'maps_url' => 'https://maps.app.goo.gl/abc']))->create();
    $tanpaLink = GameMatch::factory()->for(Venue::factory()->create(['city_id' => $city->id, 'name' => 'GOR Manahan', 'address' => 'Jl. Adi Sucipto', 'maps_url' => null]))->create();

    $this->get(route('match.show', $denganLink))->assertSee('Buka di Google Maps')->assertSee('https://maps.app.goo.gl/abc', false);
    $this->get(route('match.show', $tanpaLink))
        ->assertSee('Jl. Adi Sucipto')
        ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=GOR%20Manahan%2C%20Jl.%20Adi%20Sucipto%2C%20Surakarta', false);
});
