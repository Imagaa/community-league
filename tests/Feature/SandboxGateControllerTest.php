<?php

use App\Http\Middleware\SandboxGate;
use App\Models\SandboxAccessCode;

beforeEach(function () {
    config(['services.sandbox_gate.enabled' => true]);
});

it('kode yang benar dan belum dipakai memberi cookie dan membakar kode', function () {
    $code = SandboxAccessCode::factory()->create(['email' => 'client@example.test', 'pin' => '123456']);

    $response = $this->post(route('sandbox-gate.verify'), [
        'email' => 'client@example.test',
        'pin' => '123456',
    ]);

    $response->assertRedirect(route('home'))
        ->assertCookie(SandboxGate::COOKIE);
    expect($code->fresh()->used_at)->not->toBeNull();
});

it('pin salah ditolak dan tidak memberi cookie', function () {
    SandboxAccessCode::factory()->create(['email' => 'client@example.test', 'pin' => '123456']);

    $response = $this->post(route('sandbox-gate.verify'), [
        'email' => 'client@example.test',
        'pin' => '000000',
    ]);

    $response->assertSessionHasErrors('pin')
        ->assertCookieMissing(SandboxGate::COOKIE);
});

it('kode yang sudah dipakai tidak bisa dipakai lagi', function () {
    SandboxAccessCode::factory()->create([
        'email' => 'client@example.test',
        'pin' => '123456',
        'used_at' => now()->subMinute(),
    ]);

    $response = $this->post(route('sandbox-gate.verify'), [
        'email' => 'client@example.test',
        'pin' => '123456',
    ]);

    $response->assertSessionHasErrors('pin')
        ->assertCookieMissing(SandboxGate::COOKIE);
});

it('setelah verify berhasil, request berikutnya dengan cookie yang sama lolos gate tanpa redirect lagi', function () {
    SandboxAccessCode::factory()->create(['email' => 'client@example.test', 'pin' => '123456']);

    $verify = $this->post(route('sandbox-gate.verify'), [
        'email' => 'client@example.test',
        'pin' => '123456',
    ]);

    $cookieValue = $verify->headers->getCookies()[0]->getValue();

    // Set-Cookie dari respons verify() tidak otomatis terbawa ke request berikutnya di test
    // client (bukan browser sungguhan) -- teruskan manual, persis seperti browser akan simpan &
    // kirim ulang cookie itu di request selanjutnya.
    $this->withCookie(SandboxGate::COOKIE, $cookieValue)->get('/')->assertOk();
});

it('redirect kembali ke halaman yang tadi dituju sebelum digerbang', function () {
    SandboxAccessCode::factory()->create(['email' => 'client@example.test', 'pin' => '123456']);

    $this->get('/match');

    $response = $this->post(route('sandbox-gate.verify'), [
        'email' => 'client@example.test',
        'pin' => '123456',
    ]);

    $response->assertRedirect('/match');
});
