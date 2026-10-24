<?php

use App\Models\GameMatch;
use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\User;

// Akses awal member hanya berlaku saat saklar Membership aktif.
beforeEach(fn () => MembershipSettings::current()->update(['enabled' => true]));

it('member aktif melihat pesan sudah dibuka berkat membership di jendela akses awal', function () {
    $match = GameMatch::factory()->create([
        'status' => 'published',
        'member_opens_at' => now()->subHour(),
        'public_opens_at' => now()->addHour(),
        'starts_at' => now()->addDay(),
    ]);
    $user = User::factory()->create();
    Membership::factory()->for($user)->create(['expires_at' => now()->addMonth()]);

    $this->actingAs($user)
        ->get(route('match.show', $match))
        ->assertOk()
        ->assertSee('Sudah dibuka untuk Anda berkat Membership');
});

it('non-member melihat pesan sedang dibuka khusus member, bukan pesan sudah dibuka', function () {
    $match = GameMatch::factory()->create([
        'status' => 'published',
        'member_opens_at' => now()->subHour(),
        'public_opens_at' => now()->addHour(),
        'starts_at' => now()->addDay(),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('match.show', $match))
        ->assertOk()
        ->assertSee('Sedang dibuka khusus member')
        ->assertDontSee('Sudah dibuka untuk Anda berkat Membership');
});

it('tidak menampilkan banner jendela member sama sekali kalau member_opens_at sama dengan public_opens_at', function () {
    $waktu = now()->addHour();
    $match = GameMatch::factory()->create([
        'status' => 'published',
        'member_opens_at' => $waktu,
        'public_opens_at' => $waktu,
        'starts_at' => now()->addDay(),
    ]);
    $user = User::factory()->create();
    Membership::factory()->for($user)->create(['expires_at' => now()->addMonth()]);

    $this->actingAs($user)
        ->get(route('match.show', $match))
        ->assertOk()
        ->assertDontSee('Sedang dibuka khusus member')
        ->assertDontSee('Sudah dibuka untuk Anda berkat Membership');
});

it('member aktif bisa lihat booking widget di jendela akses awal, non-member masih lihat pesan belum dibuka', function () {
    $match = GameMatch::factory()->create([
        'status' => 'published',
        'member_opens_at' => now()->subHour(),
        'public_opens_at' => now()->addHour(),
        'starts_at' => now()->addDay(),
    ]);
    $member = User::factory()->create();
    Membership::factory()->for($member)->create(['expires_at' => now()->addMonth()]);
    $nonMember = User::factory()->create();

    $this->actingAs($member)
        ->get(route('match.show', $match))
        ->assertOk()
        ->assertSee('wire:click="buyAuthenticated"', false)
        ->assertDontSee('Dibuka untuk umum pada');

    $this->actingAs($nonMember)
        ->get(route('match.show', $match))
        ->assertOk()
        ->assertDontSee('wire:click="buyAuthenticated"', false)
        ->assertSee('Dibuka untuk umum pada');
});
