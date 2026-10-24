<?php

use App\Models\Booking;
use App\Models\City;
use App\Models\GameMatch;
use App\Models\MembershipSettings;
use App\Models\Venue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('membuat match dengan venue dan kota baru, default internal terisi', function () {
    Livewire::actingAs(buatAdmin())->test('pages::admin.match-form')
        ->set('title', 'Mini Soccer Jumat')
        ->set('type', 'minisoccer')
        ->set('venue', 'new')
        ->set('venue_name', 'GOR Manahan')
        ->set('venue_address', 'Jl. Adi Sucipto')
        ->set('venue_maps_url', 'https://maps.app.goo.gl/abc')
        ->set('city', 'new')
        ->set('city_name', 'Surakarta')
        ->set('starts_at', now('Asia/Jakarta')->addDays(3)->setTime(19, 0)->format('Y-m-d\TH:i'))
        ->set('ends_at', now('Asia/Jakarta')->addDays(3)->setTime(21, 0)->format('Y-m-d\TH:i'))
        ->set('capacity', 14)
        ->set('price', 75000)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.match'));

    $match = GameMatch::with('venue.city')->sole();
    // Disimpan sebagai draft: belum tampil/bisa dibeli sampai diterbitkan dari daftar match.
    expect($match->status)->toBe('draft')
        ->and($match->isBookableBy(null))->toBeFalse();

    Livewire::actingAs(buatAdmin())->test('pages::admin.match')->call('publishNow', $match->id);
    $match->refresh();

    expect($match)
        ->status->toBe('published')
        ->price_member->toBe(75000)
        ->points_reward->toBe(0)
        ->last_completed_wizard_step->toBe(4)
        ->and($match->venue->city->name)->toBe('Surakarta')
        ->and($match->starts_at->timezone('Asia/Jakarta')->format('H:i'))->toBe('19:00')
        ->and($match->isBookableBy(null))->toBeTrue();
});

it('memvalidasi jadwal dan slot minimal yang sudah terisi', function () {
    $match = GameMatch::factory()->create(['capacity' => 10]);
    Booking::factory()->count(3)->create(['match_id' => $match->id, 'status' => 'paid']);

    Livewire::actingAs(buatAdmin())->test('pages::admin.match-form', ['match' => $match])
        ->set('capacity', 2)
        ->set('ends_at', '2000-01-01T00:00')
        ->call('save')
        ->assertHasErrors(['capacity', 'ends_at']);
});

it('memisahkan match ke tab aktif, penuh, selesai, dan batal', function () {
    GameMatch::factory()->create(['title' => 'Match Aktif', 'capacity' => 5]);
    $penuh = GameMatch::factory()->create(['title' => 'Match Penuh', 'capacity' => 1]);
    Booking::factory()->create(['match_id' => $penuh->id, 'status' => 'paid']);
    GameMatch::factory()->create(['title' => 'Match Batal', 'status' => 'cancelled']);
    GameMatch::factory()->create(['title' => 'Match Lalu', 'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);

    expect(GameMatch::adminTab('aktif')->pluck('title')->all())->toBe(['Match Aktif'])
        ->and(GameMatch::adminTab('penuh')->pluck('title')->all())->toBe(['Match Penuh'])
        ->and(GameMatch::adminTab('selesai')->pluck('title')->all())->toBe(['Match Lalu'])
        ->and(GameMatch::adminTab('batal')->pluck('title')->all())->toBe(['Match Batal']);

    $this->actingAs(buatAdmin())->get(adminUrl('/match?tab=selesai'))
        ->assertOk()->assertSee('Aktif (1)')->assertSee('Penuh (1)')->assertSee('Selesai (1)')->assertSee('Batal (1)')
        ->assertSee('Match Lalu')->assertDontSee('Match Aktif');
});

it('menghapus permanen match batal beserta booking dan bukti bayarnya', function () {
    Storage::fake('proofs');
    Storage::disk('proofs')->put('bukti-bayar/x.webp', 'x');
    $batal = GameMatch::factory()->create(['status' => 'cancelled']);
    $booking = Booking::factory()->create(['match_id' => $batal->id, 'payment_proof_path' => 'bukti-bayar/x.webp']);
    $aktif = GameMatch::factory()->create();

    $page = Livewire::actingAs(buatAdmin())->test('pages::admin.match');
    $page->call('delete', $aktif->id);
    expect(GameMatch::find($aktif->id))->not->toBeNull();

    $page->call('delete', $batal->id);
    expect(GameMatch::find($batal->id))->toBeNull()
        ->and(Booking::find($booking->id))->toBeNull();
    Storage::disk('proofs')->assertMissing('bukti-bayar/x.webp');
});

it('membatalkan match', function () {
    $match = GameMatch::factory()->create();

    Livewire::actingAs(buatAdmin())->test('pages::admin.match')->call('cancel', $match->id);

    expect($match->fresh()->status)->toBe('cancelled');
});

it('memakai venue yang sudah ada sebagai tile', function () {
    $venue = Venue::factory()->create(['name' => 'Futsal Center', 'city_id' => City::factory()]);

    $this->actingAs(buatAdmin())->get(adminUrl('/match/baru'))->assertOk()->assertSee('Futsal Center');
});

it('menampilkan pemesan per match dikelompokkan per status', function () {
    $match = GameMatch::factory()->create(['title' => 'Liga Kamis', 'capacity' => 10]);
    Booking::factory()->create(['match_id' => $match->id, 'user_id' => null, 'guest_name' => 'Budi Lunas', 'guest_email' => 'budi@example.test', 'guest_phone' => '081234567890', 'status' => 'paid', 'paid_at' => now(), 'total' => 50000, 'unique_code' => 12]);
    Booking::factory()->create(['match_id' => $match->id, 'user_id' => null, 'guest_name' => 'Rina Tunggu', 'guest_email' => 'rina@example.test', 'status' => 'awaiting_verification']);
    Booking::factory()->create(['match_id' => $match->id, 'user_id' => null, 'guest_name' => 'Doni Ditolak', 'guest_email' => 'doni@example.test', 'status' => 'rejected', 'rejection_reason' => 'Nominal kurang']);
    Booking::factory()->create(['user_id' => null, 'guest_name' => 'Match Lain', 'guest_email' => 'x@example.test']);

    $this->actingAs(buatAdmin())->get(adminUrl('/match/'.$match->id.'/pemesan'))
        ->assertOk()
        ->assertSeeInOrder(['Lunas (1)', 'Budi Lunas', 'Menunggu verifikasi (1)', 'Rina Tunggu', 'Tidak aktif (1)', 'Doni Ditolak', 'ditolak: Nominal kurang'])
        ->assertSee('Rp 50.012')
        ->assertSee('wa.me/6281234567890', false)
        ->assertDontSee('Match Lain');

    Livewire::actingAs(buatAdmin())->test('pages::admin.match-pemesan', ['match' => $match])
        ->assertSet('paidList', fn (string $list) => str_contains($list, '1. Budi Lunas'));
});

it('publish terjadwal: publik buka di waktu yang dipilih, member lebih awal saat membership aktif', function () {
    MembershipSettings::current()->update(['enabled' => true]);
    $match = GameMatch::factory()->create(['status' => 'draft', 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHours(2)]);
    $publik = now('Asia/Jakarta')->addDay()->setTime(19, 0);

    $page = Livewire::actingAs(buatAdmin())->test('pages::admin.match');
    $page->call('publishAt', $match->id, now('Asia/Jakarta')->subHour()->format('Y-m-d\TH:i'))->assertHasErrors('publish.'.$match->id);
    $page->call('publishAt', $match->id, $publik->format('Y-m-d\TH:i'))->assertHasNoErrors();

    $match->refresh();
    expect($match->status)->toBe('published')
        ->and($match->public_opens_at->timezone('Asia/Jakarta')->format('d H:i'))->toBe($publik->format('d H:i'))
        ->and($match->member_opens_at->diffInMinutes($match->public_opens_at))->toEqual(30);
});

it('publish sekarang: member langsung bisa beli, publik setelah 30 menit; tanpa membership langsung dibuka', function () {
    MembershipSettings::current()->update(['enabled' => true]);
    $match = GameMatch::factory()->create(['status' => 'draft']);
    $match->publishNow();
    expect($match->computedStatus())->toBe('published')
        ->and($match->public_opens_at->diffInMinutes(now(), true))->toBeGreaterThan(29);

    MembershipSettings::current()->update(['enabled' => false]);
    $lain = GameMatch::factory()->create(['status' => 'draft']);
    $lain->publishNow();
    expect($lain->isBookableBy(null))->toBeTrue();
});
