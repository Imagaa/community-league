<?php

use App\Models\User;

it('menghapus akun yang masa tenggangnya sudah lewat', function () {
    $sudahLewat = User::factory()->create(['scheduled_deletion_at' => now()->subDay()]);
    $belumLewat = User::factory()->create(['scheduled_deletion_at' => now()->addDays(5)]);
    $tidakDijadwalkan = User::factory()->create(['scheduled_deletion_at' => null]);

    $this->artisan('users:purge-scheduled-deletions')->assertExitCode(0);

    expect(User::find($sudahLewat->id))->toBeNull();
    expect(User::find($belumLewat->id))->not->toBeNull();
    expect(User::find($tidakDijadwalkan->id))->not->toBeNull();
});
