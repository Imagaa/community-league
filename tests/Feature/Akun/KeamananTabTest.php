<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('akses tab keamanan butuh konfirmasi password (password.confirm)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('akun.keamanan'))
        ->assertRedirect(route('password.confirm'));
});

it('setelah konfirmasi password, bisa buka tab keamanan dan lihat email read-only', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    $this->actingAs($user);
    session(['auth.password_confirmed_at' => time()]);

    $this->get(route('akun.keamanan'))
        ->assertOk()
        ->assertSee('user@example.com')
        ->assertDontSee('type="email"', false);
});

it('email belum diverifikasi menampilkan banner dengan tautan verifikasi', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);
    session(['auth.password_confirmed_at' => time()]);

    $this->get(route('akun.keamanan'))
        ->assertSee('Email Anda belum diverifikasi.')
        ->assertSee(route('verification.notice'), false);
});

it('email sudah diverifikasi tidak menampilkan banner', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    session(['auth.password_confirmed_at' => time()]);

    $this->get(route('akun.keamanan'))
        ->assertDontSee('Email Anda belum diverifikasi.');
});

it('tombol hubungkan Google tampil kalau belum terhubung, status kalau sudah', function () {
    $belumTerhubung = User::factory()->create(['google_id' => null]);
    $sudahTerhubung = User::factory()->create(['google_id' => 'google-1']);

    $this->actingAs($belumTerhubung);
    session(['auth.password_confirmed_at' => time()]);
    $this->get(route('akun.keamanan'))->assertSee('Hubungkan Google');

    $this->actingAs($sudahTerhubung);
    session(['auth.password_confirmed_at' => time()]);
    $this->get(route('akun.keamanan'))->assertSee('Terhubung ke Google');
});

it('password bisa diperbarui dengan password saat ini yang benar', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->actingAs($user);

    Livewire::test('pages::akun.keamanan')
        ->set('current_password', 'password')
        ->set('password', 'Rahasia-Panjang-Baru456!')
        ->set('password_confirmation', 'Rahasia-Panjang-Baru456!')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('Rahasia-Panjang-Baru456!', $user->refresh()->password))->toBeTrue();
});

it('password saat ini yang salah ditolak', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->actingAs($user);

    Livewire::test('pages::akun.keamanan')
        ->set('current_password', 'wrong-password')
        ->set('password', 'Rahasia-Panjang-Baru456!')
        ->set('password_confirmation', 'Rahasia-Panjang-Baru456!')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);
});

it('konfirmasi hapus akun menjadwalkan penghapusan 30 hari dan logout user', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->actingAs($user);
    session(['auth.password_confirmed_at' => time()]);

    Livewire::test('pages::akun.keamanan')
        ->set('delete_password', 'password')
        ->call('confirmAccountDeletion');

    $user->refresh();
    expect($user->scheduled_deletion_at)->not->toBeNull();
    expect($user->scheduled_deletion_at->diffInDays(now()))->toBeLessThanOrEqual(30);
    $this->assertGuest();
});

it('password salah saat konfirmasi hapus akun ditolak, akun tidak dijadwalkan', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->actingAs($user);
    session(['auth.password_confirmed_at' => time()]);

    Livewire::test('pages::akun.keamanan')
        ->set('delete_password', 'wrong-password')
        ->call('confirmAccountDeletion')
        ->assertHasErrors(['delete_password']);

    expect($user->refresh()->scheduled_deletion_at)->toBeNull();
});

it('user pending-deletion bisa membatalkan lewat akun.batalkan-hapus', function () {
    $user = User::factory()->create(['scheduled_deletion_at' => now()->addDays(20)]);

    $this->actingAs($user);

    $this->post(route('akun.batalkan-hapus'))->assertRedirect();

    expect($user->refresh()->scheduled_deletion_at)->toBeNull();
});
