<?php

use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\User;
use App\Support\MemberCode;
use Livewire\Livewire;

beforeEach(fn () => MembershipSettings::current()->update(['enabled' => true]));

it('Scanner Kartu hanya untuk admin', function () {
    $this->actingAs(User::factory()->create())->get(adminUrl('/kartu-scanner'))->assertForbidden();
    $this->actingAs(User::factory()->create())->postJson(adminUrl('/kartu-scanner/scan'), ['code' => 'x'])->assertForbidden();

    $membership = Membership::factory()->create();
    $this->actingAs(buatAdmin())->get(adminUrl('/kartu-scanner?member='.$membership->id))->assertOk()
        ->assertSee('id="nfc"', false)->assertSee($membership->user->name);
});

it('scan kartu menampilkan profil member + aksi ganti kartu; ganti kartu memblokir QR & NFC lama', function () {
    $membership = Membership::factory()->create();
    $membership->pairNfc('04DD');
    $code = $membership->cardCode();
    $admin = buatAdmin();

    $this->actingAs($admin)->postJson(adminUrl('/kartu-scanner/scan'), ['code' => $code])->assertOk()
        ->assertJson(['result' => 'info', 'title' => 'Member aktif', 'member' => ['id' => $membership->id], 'actions' => ['replace']]);

    $this->actingAs($admin)->postJson(adminUrl('/kartu-scanner/ganti'), ['membership_id' => $membership->id])->assertOk()->assertJson(['ok' => true]);

    expect(MemberCode::resolve($code))->toBeNull()->and(MemberCode::byNfc('04DD'))->toBeNull();
    $this->actingAs($admin)->postJson(adminUrl('/kartu-scanner/scan'), ['code' => $code])->assertJson(['result' => 'invalid']);
});

it('aktifkan kartu untuk member terpilih: QR kartu orang lain ditolak', function () {
    $membership = Membership::factory()->create(['card_printed_at' => now()]);
    $lain = Membership::factory()->create();
    $admin = buatAdmin();

    $this->actingAs($admin)->postJson(adminUrl('/kartu-scanner/hubungkan'), ['membership_id' => $membership->id, 'code' => $lain->cardCode()])
        ->assertOk()->assertJson(['ok' => false]);
    $this->actingAs($admin)->postJson(adminUrl('/kartu-scanner/hubungkan'), ['membership_id' => $membership->id, 'code' => $membership->cardCode()])
        ->assertOk()->assertJson(['ok' => true, 'member' => ['card_active' => true]]);
});

it('tab Kartu menampilkan status kartu fisik dan tombol aktifkan', function () {
    $belum = Membership::factory()->create(['card_printed_at' => now()]);
    $aktif = Membership::factory()->create(['card_printed_at' => now()]);
    $aktif->activateCard();
    $aktif->pairNfc('04EE');

    Livewire::actingAs(buatAdmin())->test('pages::admin.membership')->call('setTab', 'kartu')->set('cardFilter', 'semua')
        ->assertSee('Kartu belum aktif')->assertSee('Kartu aktif')->assertSee('NFC terhubung')
        ->assertSee(route('admin.card-scanner', ['member' => $belum->id]), false)
        ->assertDontSee(route('admin.card-scanner', ['member' => $aktif->id]), false);
});
