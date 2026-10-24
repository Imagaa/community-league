<?php

use App\Models\Booking;
use App\Models\GameMatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('menampilkan detail match dan sisa slot', function () {
    $match = GameMatch::factory()->create(['capacity' => 10, 'title' => 'Match Detail Test']);
    Booking::factory()->for($match, 'match')->create(['quantity' => 3, 'status' => 'paid']);

    $this->get(route('match.show', $match))
        ->assertOk()
        ->assertSee('Match Detail Test')
        ->assertSee('Sisa slot: 7');
});

it('menampilkan pesan penuh saat slot habis', function () {
    $match = GameMatch::factory()->create(['capacity' => 1]);
    Booking::factory()->for($match, 'match')->create(['quantity' => 1, 'status' => 'paid']);

    $this->get(route('match.show', $match))->assertSee('Penuh');
});

it('badge kategori di halaman detail sama warnanya dengan kartu katalog', function () {
    // Regresi dilaporkan user: kategori di kartu katalog dibedakan warna (teal/zinc-900), tapi
    // di halaman detail badge-nya selalu merah di atas header hitam -- tidak konsisten.
    $football = GameMatch::factory()->create(['type' => 'football']);
    $minisoccer = GameMatch::factory()->create(['type' => 'minisoccer']);

    $this->get(route('match.show', $football))
        ->assertOk()
        ->assertSee('bg-zinc-900', false)
        ->assertDontSee('bg-red-600 px-2', false);

    $this->get(route('match.show', $minisoccer))
        ->assertOk()
        ->assertSee('bg-teal-600', false);
});

it('badge Penuh di halaman detail pakai warna solid sama seperti kartu katalog', function () {
    $match = GameMatch::factory()->create(['capacity' => 1]);
    Booking::factory()->for($match, 'match')->create(['quantity' => 1, 'status' => 'paid']);

    $this->get(route('match.show', $match))
        ->assertOk()
        ->assertSee('bg-red-600 text-white', false)
        ->assertDontSee('bg-red-100', false);
});

it('match yang tidak ada mengembalikan 404', function () {
    $this->get('/match/tidak-ada-slug')->assertNotFound();
});

it('match draft tidak bisa dilihat publik walau tahu slug-nya', function () {
    $match = GameMatch::factory()->create(['status' => 'draft']);

    $this->get(route('match.show', $match))->assertNotFound();
});

it('match dibatalkan memberi pesan dibatalkan, bukan "belum dibuka"', function () {
    $match = GameMatch::factory()->create(['status' => 'cancelled']);

    $this->get(route('match.show', $match))
        ->assertOk()
        ->assertSee('Match ini telah dibatalkan.')
        ->assertDontSee('Match ini belum dibuka untuk pemesanan.');
});

it('menampilkan poster di atas detail match kalau ada', function () {
    Storage::fake('public');
    $path = UploadedFile::fake()->image('poster.jpg')->store('match-posters', 'public');
    $match = GameMatch::factory()->create(['poster_path' => $path]);

    $this->get(route('match.show', $match))
        ->assertOk()
        ->assertSee($path, false);
});

it('halaman detail tidak berubah kalau match tidak punya poster', function () {
    $match = GameMatch::factory()->create(['poster_path' => null, 'title' => 'Match Tanpa Poster']);

    $this->get(route('match.show', $match))
        ->assertOk()
        ->assertSee('Match Tanpa Poster')
        ->assertDontSee('Poster Match Tanpa Poster');
});
