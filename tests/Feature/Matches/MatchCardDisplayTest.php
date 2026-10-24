<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\MembershipSettings;

// Akses awal member hanya berlaku saat saklar Membership aktif.
beforeEach(fn () => MembershipSettings::current()->update(['enabled' => true]));

it('badge kategori football dan minisoccer punya warna berbeda', function () {
    GameMatch::factory()->create(['status' => 'published', 'type' => 'football', 'title' => 'Match Bola']);
    GameMatch::factory()->create(['status' => 'published', 'type' => 'minisoccer', 'title' => 'Match Mini']);

    $response = $this->get(route('match'));

    $response->assertSee('bg-zinc-900', false)
        ->assertSee('bg-teal-600', false);
});

it('menampilkan sisa slot untuk match yang masih terbuka', function () {
    $match = GameMatch::factory()->create([
        'status' => 'published',
        'capacity' => 10,
        'public_opens_at' => now()->subDay(),
        'starts_at' => now()->addDay(),
    ]);
    Booking::factory()->for($match, 'match')->create(['quantity' => 3, 'status' => 'paid']);

    $this->get(route('match'))->assertSee('7 slot tersisa');
});

it('menampilkan badge Penuh kalau match sudah tidak ada slot', function () {
    $match = GameMatch::factory()->create([
        'status' => 'published',
        'capacity' => 1,
        'public_opens_at' => now()->subDay(),
        'starts_at' => now()->addDay(),
    ]);
    Booking::factory()->for($match, 'match')->create(['quantity' => 1, 'status' => 'paid']);

    $this->get(route('match'))->assertSee('Penuh');
});

it('menampilkan badge Segera Dibuka untuk match yang belum buka pendaftaran', function () {
    GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->addHour(),
        'title' => 'Match Belum Buka',
    ]);

    $this->get(route('match'))->assertSee('Segera Dibuka');
});

it('menampilkan badge Berlangsung untuk match yang sedang jalan', function () {
    GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->subWeek(),
        'starts_at' => now()->subMinutes(30),
        'ends_at' => now()->addHour(),
        'title' => 'Match Lagi Jalan',
    ]);

    $this->get(route('match'))->assertSee('Berlangsung');
});

it('menampilkan badge Member Akses Awal kalau member_opens_at beneran lebih awal', function () {
    GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->addHour(),
        'member_opens_at' => now()->subHour(),
        'title' => 'Match Ada Jendela Member',
    ]);

    $this->get(route('match'))->assertSee('Member Akses Awal');
});

it('tidak menampilkan badge Member Akses Awal kalau member_opens_at sama dengan public_opens_at', function () {
    $waktu = now()->addHour();
    GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => $waktu,
        'member_opens_at' => $waktu,
        'title' => 'Match Tanpa Jendela Member',
    ]);

    $this->get(route('match'))->assertDontSee('Member Akses Awal');
});

it('menampilkan badge Selesai untuk match yang sudah berakhir', function () {
    GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->subWeek(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subHour(),
        'hide_when_finished' => false,
        'title' => 'Match Udah Beres',
    ]);

    $this->get(route('match'))->assertSee('Selesai');
});
