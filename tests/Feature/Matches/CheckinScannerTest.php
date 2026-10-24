<?php

use App\Models\Booking;
use App\Models\GameMatch;
use App\Support\CheckinToken;

function scannerMatch(): GameMatch
{
    $match = GameMatch::factory()->create(['title' => 'Liga Jumat']);
    $match->scannerUrl();

    return $match->fresh();
}

it('halaman scanner hanya bisa dibuka dengan token yang benar', function () {
    $match = scannerMatch();

    $this->get('/checkin/'.$match->scanner_token)->assertOk()->assertSee('Liga Jumat')->assertSee('Ganti kamera', false);
    $this->get('/checkin/'.str_repeat('x', 40))->assertNotFound();
    $this->get('/checkin/pendek')->assertNotFound();
});

it('memindai tiket lunas: berhasil sekali, lalu dibilang sudah check-in', function () {
    $match = scannerMatch();
    $booking = Booking::factory()->create(['match_id' => $match->id, 'status' => 'paid', 'user_id' => null, 'guest_name' => 'Budi', 'guest_email' => 'b@example.test']);
    $code = CheckinToken::encode($booking);

    $this->postJson('/checkin/'.$match->scanner_token, ['code' => $code])
        ->assertOk()->assertJson(['result' => 'ok', 'name' => 'Budi', 'stats' => ['hadir' => 1, 'total' => 1]]);
    expect($booking->fresh()->checked_in_at)->not->toBeNull();

    $this->postJson('/checkin/'.$match->scanner_token, ['code' => $code])->assertJson(['result' => 'already']);
});

it('menolak QR asing, tiket match lain, dan tiket belum lunas', function () {
    $match = scannerMatch();
    $lain = Booking::factory()->create(['status' => 'paid']);
    $belum = Booking::factory()->create(['match_id' => $match->id, 'status' => 'pending_payment']);
    $url = '/checkin/'.$match->scanner_token;

    $this->postJson($url, ['code' => 'https://contoh.com'])->assertJson(['result' => 'invalid']);
    $this->postJson($url, ['code' => CheckinToken::encode($lain)])->assertJson(['result' => 'other_match']);
    $this->postJson($url, ['code' => CheckinToken::encode($belum)])->assertJson(['result' => 'not_paid']);
    expect($lain->fresh()->checked_in_at)->toBeNull();
});

it('link baru membuat link lama tidak berlaku, dan admin melihat status hadir', function () {
    $match = scannerMatch();
    $lama = $match->scanner_token;
    Booking::factory()->create(['match_id' => $match->id, 'status' => 'paid', 'checked_in_at' => now()]);

    Livewire\Livewire::actingAs(buatAdmin())->test('pages::admin.match-pemesan', ['match' => $match])
        ->assertSee('Scanner check-in')->assertSee('1 hadir')
        ->call('regenerateScanner');

    expect($match->fresh()->scanner_token)->not->toBe($lama);
    $this->get('/checkin/'.$lama)->assertNotFound();
});
