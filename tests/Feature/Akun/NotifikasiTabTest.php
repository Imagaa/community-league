<?php

use App\Models\NotificationPreference;
use App\Models\User;
use Livewire\Livewire;

it('halaman notifikasi menampilkan preferensi default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('akun.notifikasi'))
        ->assertOk()
        ->assertSee('Pengingat match');
});

it('toggle kategori notifikasi tersimpan otomatis', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::akun.notifikasi')
        ->set('promotions', true);

    expect(NotificationPreference::forUser($user)->fresh()->promotions)->toBeTrue();
});

it('subscribeToPush menyimpan subscription, user agent, dan status izin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test('pages::akun.notifikasi')
        ->call('subscribeToPush', 'https://fcm.googleapis.com/fcm/send/abc', 'public-key', 'auth-token', 'Mozilla/5.0 (Linux; Android 14) Chrome/120.0 Mobile')
        ->assertSet('browser_push_enabled', true);

    $subscription = $user->pushSubscriptions()->where('endpoint', 'https://fcm.googleapis.com/fcm/send/abc')->first();
    expect(NotificationPreference::forUser($user)->fresh()->browser_push_enabled)->toBeTrue()
        ->and($subscription)->not->toBeNull()
        ->and($subscription->user_agent)->toContain('Android');
});

it('subscribeToPush dengan endpoint yang sama tidak membuat subscription duplikat', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test('pages::akun.notifikasi');
    $component->call('subscribeToPush', 'https://fcm.googleapis.com/fcm/send/abc', 'key-lama', 'token-lama');
    $component->call('subscribeToPush', 'https://fcm.googleapis.com/fcm/send/abc', 'key-baru', 'token-baru');

    expect($user->pushSubscriptions()->where('endpoint', 'https://fcm.googleapis.com/fcm/send/abc')->count())->toBe(1)
        ->and($user->pushSubscriptions()->first()->public_key)->toBe('key-baru');
});

it('unsubscribeFromPush menghapus subscription tapi mempertahankan yang lain', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test('pages::akun.notifikasi');
    $component->call('subscribeToPush', 'https://fcm.googleapis.com/fcm/send/hp', 'key-hp', 'token-hp');
    $component->call('subscribeToPush', 'https://fcm.googleapis.com/fcm/send/laptop', 'key-laptop', 'token-laptop');

    $hpId = $user->pushSubscriptions()->where('endpoint', 'https://fcm.googleapis.com/fcm/send/hp')->value('id');

    $component->call('unsubscribeFromPush', $hpId)
        ->assertSet('browser_push_enabled', true);

    expect($user->pushSubscriptions()->count())->toBe(1)
        ->and($user->pushSubscriptions()->where('endpoint', 'https://fcm.googleapis.com/fcm/send/laptop')->exists())->toBeTrue();
});

it('unsubscribeFromPush dari subscription terakhir mematikan status izin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test('pages::akun.notifikasi');
    $component->call('subscribeToPush', 'https://fcm.googleapis.com/fcm/send/abc', 'key', 'token');

    $id = $user->pushSubscriptions()->value('id');

    $component->call('unsubscribeFromPush', $id)
        ->assertSet('browser_push_enabled', false);

    expect(NotificationPreference::forUser($user)->fresh()->browser_push_enabled)->toBeFalse()
        ->and($user->pushSubscriptions()->count())->toBe(0);
});

it('unsubscribeFromPush tidak bisa hapus subscription milik user lain', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $this->actingAs($owner);

    Livewire::test('pages::akun.notifikasi')
        ->call('subscribeToPush', 'https://fcm.googleapis.com/fcm/send/abc', 'key', 'token');
    $subscriptionId = $owner->pushSubscriptions()->value('id');

    $this->actingAs($intruder);
    Livewire::test('pages::akun.notifikasi')
        ->call('unsubscribeFromPush', $subscriptionId);

    expect($owner->pushSubscriptions()->count())->toBe(1);
});

it('halaman notifikasi menampilkan opsi Terang, Gelap, dan Sistem', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('akun.notifikasi'))
        ->assertOk()
        ->assertSee('value="light"', false)
        ->assertSee('value="dark"', false)
        ->assertSee('value="system"', false);
});

it('tombol tambah pintasan ke layar utama tampil', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('akun.notifikasi'))
        ->assertSee('Tambahkan Pintasan ke Layar Utama');
});
