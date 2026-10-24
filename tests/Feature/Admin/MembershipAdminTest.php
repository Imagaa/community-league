<?php

use App\Models\GameMatch;
use App\Models\MembershipSettings;
use App\Models\PointsTransaction;
use App\Models\RewardItem;
use App\Models\RewardRedemption;
use App\Notifications\RewardRedemptionRejectedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(fn () => MembershipSettings::current()->update(['enabled' => true]));

it('Store & Penukaran selalu bisa dikelola, tab Match hanya saat membership aktif', function () {
    MembershipSettings::current()->update(['enabled' => false]);
    $this->actingAs(buatAdmin())->get(adminUrl('/membership'))->assertOk()
        ->assertSee("setTab('store')", false)->assertSee("setTab('penukaran')", false)
        ->assertDontSee("setTab('match')", false);

    MembershipSettings::current()->update(['enabled' => true]);
    $this->actingAs(buatAdmin())->get(adminUrl('/membership'))->assertSee("setTab('match')", false);
});

it('memproses, menandai diterima, dan menolak penukaran dengan pengembalian poin & stok', function () {
    Notification::fake();
    $item = RewardItem::factory()->create(['name' => 'Topi CL', 'stock' => 2, 'point_cost' => 300]);
    $a = RewardRedemption::factory()->create(['reward_item_id' => $item->id, 'points_spent' => 300, 'status' => 'pending']);
    $b = RewardRedemption::factory()->create(['reward_item_id' => $item->id, 'points_spent' => 300, 'status' => 'pending']);

    $page = Livewire::actingAs(buatAdmin())->test('pages::admin.membership')
        ->call('setTab', 'penukaran')->assertSee('Topi CL')
        ->call('processRedemption', $a->id)
        ->call('fulfillRedemption', $a->id);
    expect($a->fresh())->status->toBe('fulfilled')->fulfilled_at->not->toBeNull();

    $page->call('rejectRedemption', $b->id, 'Stok habis di gudang')->assertHasNoErrors();
    expect($b->fresh())->status->toBe('rejected')->admin_note->toBe('Stok habis di gudang')
        ->and($item->fresh()->stock)->toBe(3)
        ->and(PointsTransaction::where('user_id', $b->user_id)->where('type', 'redemption_refund')->sum('amount'))->toEqual(300);

    // Sudah ditolak -- tidak bisa diputuskan lagi (aman dari dobel klik).
    $page->call('fulfillRedemption', $b->id)->assertHasErrors('redemption');
    Notification::assertSentTo($b->user, RewardRedemptionRejectedNotification::class);
});

it('mengatur akses awal member (hari/jam/menit) dan poin per match', function () {
    $match = GameMatch::factory()->create([
        'title' => 'Liga Sabtu',
        'public_opens_at' => now()->addDays(2),
        'member_opens_at' => now()->addDays(2)->subMinutes(30),
        'points_reward' => 0,
    ]);

    Livewire::actingAs(buatAdmin())->test('pages::admin.membership')
        ->call('setTab', 'match')
        ->assertSee('Liga Sabtu')->assertSee('30 menit lebih awal')
        ->call('editMatch', $match->id)
        ->assertSet('leadMinutes', 30)
        ->set('leadDays', 1)->set('leadHours', 2)->set('leadMinutes', 0)->set('points', 15)
        ->call('saveMatch')->assertHasNoErrors()
        ->assertSee('1 hari 2 jam lebih awal');

    $match->refresh();
    expect($match->member_lead_minutes)->toBe(26 * 60)
        ->and($match->points_reward)->toBe(15)
        ->and($match->member_opens_at->diffInMinutes($match->public_opens_at))->toEqual(26 * 60);
});

it('menambah, mengubah, menyembunyikan, dan menghapus produk store', function () {
    Storage::fake('public');

    Livewire::actingAs(buatAdmin())->test('pages::admin.store-form')
        ->set('name', 'Jersey CL')->set('point_cost', 500)->set('stock', null)
        ->set('image', UploadedFile::fake()->image('jersey.png', 600, 600))
        ->call('save')->assertHasNoErrors()
        ->assertRedirect(route('admin.membership', ['tab' => 'store']));

    $item = RewardItem::sole();
    expect($item)->name->toBe('Jersey CL')->point_cost->toBe(500)->stock->toBeNull()->active->toBeTrue();
    Storage::disk('public')->assertExists($item->image_path);

    $page = Livewire::actingAs(buatAdmin())->test('pages::admin.membership')->call('setTab', 'store')->assertSee('Jersey CL');
    $page->call('toggleItem', $item->id);
    expect($item->fresh()->active)->toBeFalse();

    $page->call('deleteItem', $item->id);
    expect(RewardItem::find($item->id))->toBeNull();
});

it('produk yang sudah pernah ditukar tidak bisa dihapus', function () {
    $item = RewardItem::factory()->create();
    RewardRedemption::factory()->create(['reward_item_id' => $item->id]);

    Livewire::actingAs(buatAdmin())->test('pages::admin.membership')->call('deleteItem', $item->id);

    expect(RewardItem::find($item->id))->not->toBeNull();
});
