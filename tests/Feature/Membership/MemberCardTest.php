<?php

use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\User;
use App\Support\MemberCardImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('halaman verifikasi publik sudah dihapus -- QR kartu hanya terbaca scanner CL', function () {
    $this->get('/member/token-apa-saja')->assertNotFound();
});

it('ganti kartu memblokir kode lama dan menandai kartu perlu dicetak lagi', function () {
    $membership = Membership::factory()->create(['card_printed_at' => now()]);
    $oldCode = $membership->cardCode();

    $membership->replaceCard();

    expect($membership->fresh()->card_printed_at)->toBeNull()
        ->and($membership->fresh()->cardCode())->not->toBe($oldCode);
});

it('unduhan kartu member tidak pernah memuat QR', function () {
    MembershipSettings::current()->update(['card_layout' => ['qr' => ['side' => 'front']]]);
    $membership = Membership::factory()->create();

    MemberCardImage::front($membership, withQr: false);
    expect($membership->fresh()->card_token)->toBeNull();

    $this->actingAs($membership->user)->get(route('membership.card', ['download' => 1]))->assertOk();
    expect($membership->fresh()->card_token)->toBeNull();
});

it('member mengunduh kartu digital PNG; non-member 404', function () {
    $membership = Membership::factory()->create();

    $this->actingAs($membership->user)->get(route('membership.card', ['download' => 1]))
        ->assertOk()->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'attachment; filename="kartu-member-'.$membership->memberNumber().'.png"');
    $this->actingAs($membership->user)->get(route('membership'))->assertSee($membership->memberNumber());

    $this->actingAs(User::factory()->create())->get(route('membership.card'))->assertNotFound();
});

it('admin mengunduh PNG cetak; sisi depan menandai sudah dicetak', function () {
    $membership = Membership::factory()->create();
    $admin = buatAdmin();

    $this->actingAs($admin)->get(adminUrl("/membership/kartu/{$membership->id}/belakang"))->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($membership->fresh()->card_printed_at)->toBeNull();

    $png = $this->actingAs($admin)->get(adminUrl("/membership/kartu/{$membership->id}/depan"))->assertOk()->getContent();
    expect(array_slice(getimagesizefromstring((string) $png) ?: [], 0, 2))->toBe([1011, 638])
        ->and($membership->fresh()->card_printed_at)->not->toBeNull();

    $this->actingAs(User::factory()->create())->get(adminUrl("/membership/kartu/{$membership->id}/depan"))->assertForbidden();
});

it('tab Kartu: filter perlu dicetak & ganti kartu', function () {
    $belum = Membership::factory()->create();
    $sudah = Membership::factory()->create(['card_printed_at' => now()]);
    $lewat = Membership::factory()->create(['expires_at' => now()->subDay()]);

    $page = Livewire::actingAs(buatAdmin())->test('pages::admin.membership')->call('setTab', 'kartu')
        ->assertSee($belum->user->name)->assertDontSee($sudah->user->name)->assertDontSee($lewat->user->name)
        ->set('cardFilter', 'semua')->assertSee($sudah->user->name)->assertSee($lewat->user->name);

    $token = $sudah->cardCode();
    $page->call('replaceCard', $sudah->id)->assertSee('Kartu lama tidak berlaku');
    expect($sudah->fresh())->card_printed_at->toBeNull()->and($sudah->fresh()->cardCode())->not->toBe($token);
});

it('admin mengunggah desain kartu & mengatur kotak data; kartu memakai desain itu', function () {
    Storage::fake('public');
    $layout = MemberCardImage::layout();
    $layout['name']['x'] = 10.5;
    $layout['qr']['side'] = 'front';

    Livewire::actingAs(buatAdmin())->test('pages::admin.membership')
        ->call('editCard')
        ->set('cardFront', UploadedFile::fake()->image('depan.png', 2022, 1276))
        ->call('saveCard', $layout)
        ->assertHasNoErrors()->assertSet('editingCard', false);

    $settings = MembershipSettings::current();
    Storage::disk('public')->assertExists((string) $settings->card_front_path);
    expect($settings->card_back_path)->toBeNull()
        ->and($settings->card_layout['name']['x'])->toEqual(10.5)
        ->and($settings->card_layout['qr']['side'])->toBe('front');

    // Resolusi hasil mengikuti desain unggahan.
    $png = MemberCardImage::front(Membership::factory()->create());
    expect(array_slice(getimagesizefromstring($png) ?: [], 0, 2))->toBe([2022, 1276]);

    $this->actingAs(buatAdmin())->get(adminUrl('/membership/kartu-contoh/front'))->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('desain kartu ditolak bila bukan rasio kartu atau kotak di luar kartu; bisa dikembalikan ke bawaan', function () {
    Storage::fake('public');
    $layout = MemberCardImage::layout();
    $layout['number']['x'] = 140;

    $page = Livewire::actingAs(buatAdmin())->test('pages::admin.membership')
        ->call('editCard')
        ->set('cardBack', UploadedFile::fake()->image('persegi.png', 1200, 1200))
        ->call('saveCard', $layout)
        ->assertHasErrors(['cardBack', 'layout.number.x']);

    MembershipSettings::current()->update(['card_layout' => ['name' => ['x' => 5]]]);
    $page->call('resetCardDesign');
    expect(MembershipSettings::current()->card_layout)->toBeNull()
        ->and(MemberCardImage::layout()['name']['x'])->toEqual(MemberCardImage::FIELDS['name']['x']);
});
