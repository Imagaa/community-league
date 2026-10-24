<?php

use App\Models\User;

it('mengirim notifikasi uji ke user berdasar email', function () {
    $user = User::factory()->create(['email' => 'preview@example.test']);

    $this->artisan('notifications:test', ['email' => 'preview@example.test'])
        ->assertSuccessful();

    $notification = $user->notifications()->latest()->firstOrFail();
    expect($notification->data['title'])->toBe('Notifikasi uji coba');
});

it('gagal untuk email yang belum terdaftar', function () {
    $this->artisan('notifications:test', ['email' => 'tidak-ada@example.test'])
        ->assertFailed();
});
