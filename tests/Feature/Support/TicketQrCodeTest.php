<?php

use App\Support\TicketQrCode;

it('dataUri menghasilkan data URI PNG yang bisa langsung dipakai di <img src>', function () {
    $uri = TicketQrCode::dataUri('token-contoh');

    expect($uri)->toStartWith('data:image/png;base64,');
});

it('png menghasilkan bytes PNG mentah (bukan data URI)', function () {
    $bytes = TicketQrCode::png('token-contoh');

    expect($bytes)->not->toStartWith('data:')
        ->and(substr($bytes, 1, 3))->toBe('PNG');
});
