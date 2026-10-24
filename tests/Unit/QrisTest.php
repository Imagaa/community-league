<?php

use App\Support\Qris;

function contohQrisStatis(): string
{
    $body = '000201010211'
        .'26310017ID.CO.EXAMPLE.WWW0106123456'
        .'52045814'.'5303360'.'5802ID'
        .'5916COMMUNITY LEAGUE'.'6009SURAKARTA'.'6304';

    return $body.Qris::crc16($body);
}

it('menghitung CRC16-CCITT sesuai vektor standar', function () {
    expect(Qris::crc16('123456789'))->toBe('29B1');
});

it('mengubah QRIS statis jadi dinamis dengan nominal dan CRC baru', function () {
    $dinamis = Qris::withAmount(contohQrisStatis(), 75083);

    expect($dinamis)->toContain('010212')
        ->and($dinamis)->toContain('5303360'.'540575083'.'5802ID')
        ->and(Qris::isValid($dinamis))->toBeTrue()
        ->and(Qris::merchantName($dinamis))->toBe('COMMUNITY LEAGUE');
});

it('menolak payload rusak atau CRC salah', function () {
    expect(Qris::isValid('bukan qris'))->toBeFalse()
        ->and(Qris::isValid(substr(contohQrisStatis(), 0, -4).'0000'))->toBeFalse();
});
