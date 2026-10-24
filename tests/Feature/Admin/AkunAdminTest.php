<?php

use App\Models\Booking;
use App\Models\User;
use Livewire\Livewire;

it('mencari akun berdasarkan nama, email, atau WA', function () {
    User::factory()->create(['name' => 'Rina Andriani', 'email' => 'rina@example.test']);
    User::factory()->create(['name' => 'Budi', 'email' => 'budi@example.test', 'phone' => '081299990000']);

    Livewire::actingAs(buatAdmin())->test('pages::admin.akun')
        ->set('search', 'rina')->assertSee('Rina Andriani')->assertDontSee('budi@example.test')
        ->set('search', '9999')->assertSee('budi@example.test')->assertDontSee('Rina Andriani');
});

it('menampilkan riwayat booking dan mengubah nama serta WA', function () {
    $user = User::factory()->create(['name' => 'Lama']);
    $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => 'paid']);

    Livewire::actingAs(buatAdmin())->test('pages::admin.akun-detail', ['user' => $user])
        ->assertSee($booking->booking_code)->assertSee('Lunas')
        ->set('name', 'Baru')->set('phone', '+62 812 3456 7890')
        ->call('save')->assertHasNoErrors();

    expect($user->fresh())->name->toBe('Baru')->phone->toBe('081234567890');
});

it('menghapus akun tapi mempertahankan booking-nya, dan tidak bisa hapus diri sendiri', function () {
    $admin = buatAdmin();
    $user = User::factory()->create();
    $booking = Booking::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($admin)->test('pages::admin.akun-detail', ['user' => $user])
        ->call('delete')->assertRedirect(route('admin.akun'));

    expect(User::find($user->id))->toBeNull()
        ->and($booking->fresh()->user_id)->toBeNull();

    Livewire::actingAs($admin)->test('pages::admin.akun-detail', ['user' => $admin])
        ->call('delete')->assertForbidden();
});
