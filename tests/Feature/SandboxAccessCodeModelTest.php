<?php

use App\Models\SandboxAccessCode;

it('isUsed mencerminkan used_at', function () {
    $belum = SandboxAccessCode::factory()->create(['used_at' => null]);
    $sudah = SandboxAccessCode::factory()->create(['used_at' => now()]);

    expect($belum->isUsed())->toBeFalse()
        ->and($sudah->isUsed())->toBeTrue();
});
