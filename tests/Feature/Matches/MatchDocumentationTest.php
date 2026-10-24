<?php

use App\Actions\Match\PublishDocumentation;
use App\Models\Booking;
use App\Models\GameMatch;
use App\Models\PaymentSettings;
use App\Models\User;
use App\Notifications\MatchDocumentationNotification;
use App\Services\GoogleDrive\DriveFolder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const FOLDER = 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOp?usp=sharing';

function matchSelesai(): GameMatch
{
    return GameMatch::factory()->create(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
}

function fakeDrive(int $jumlah = 2): void
{
    $png = UploadedFile::fake()->image('foto.png', 800, 600)->get();

    Http::fake([
        'www.googleapis.com/drive/v3/files?*' => Http::response(['files' => collect(range(1, $jumlah))
            ->map(fn ($i) => ['id' => "foto{$i}", 'name' => "IMG_{$i}.jpg"])->all()]),
        'www.googleapis.com/drive/v3/files/*' => Http::response($png),
    ]);
}

it('membaca ID folder dari link Drive', function () {
    expect(DriveFolder::folderId(FOLDER))->toBe('1AbCdEfGhIjKlMnOp')
        ->and(DriveFolder::folderId('https://drive.google.com/drive/u/0/folders/1AbCdEfGhIjKlMnOp'))->toBe('1AbCdEfGhIjKlMnOp')
        ->and(DriveFolder::folderId('https://example.com/foto'))->toBeNull();
});

it('menyalin foto ke galeri dan mengirim email terima kasih sekali ke pembeli lunas', function () {
    Storage::fake('public');
    Notification::fake();
    fakeDrive(2);
    PaymentSettings::current()->update(['google_drive_api_key' => 'AIza-test']);

    $match = matchSelesai();
    $lunas = Booking::factory()->create(['match_id' => $match->id, 'status' => 'paid']);
    Booking::factory()->create(['match_id' => $match->id, 'status' => 'expired']);

    $hasil = app(PublishDocumentation::class)->handle($match, FOLDER);

    expect($hasil)->toBe(['photos' => 2, 'emailed' => 1, 'warning' => null]);
    $match->refresh();
    expect($match->drive_photos)->toHaveCount(2)->and($match->documentation_sent_at)->not->toBeNull();
    Storage::disk('public')->assertExists($match->drive_photos[0]);
    Notification::assertSentTo($lunas->user, MatchDocumentationNotification::class);

    // Simpan ulang (mis. link diganti): foto diperbarui, email tidak dikirim lagi.
    $lagi = app(PublishDocumentation::class)->handle($match, FOLDER);
    expect($lagi['emailed'])->toBe(0);
    Notification::assertSentToTimes($lunas->user, MatchDocumentationNotification::class, 1);
});

it('tanpa API key link tetap tersimpan dan email tetap terkirim, galeri menyusul', function () {
    Notification::fake();
    Http::fake();
    $match = matchSelesai();
    Booking::factory()->create(['match_id' => $match->id, 'status' => 'paid']);

    $hasil = app(PublishDocumentation::class)->handle($match, FOLDER);

    expect($hasil['photos'])->toBe(0)->and($hasil['emailed'])->toBe(1)->and($hasil['warning'])->toContain('API key');
    expect($match->fresh()->drive_folder_url)->toBe(FOLDER);
    Http::assertNothingSent();
});

it('admin menyimpan link dari tab Selesai dan menolak link yang bukan folder Drive', function () {
    Notification::fake();
    $match = matchSelesai();

    $page = Livewire::actingAs(buatAdmin())->test('pages::admin.match')
        ->set('tab', 'selesai')
        ->assertSee('Tempel link folder Google Drive');

    $page->call('saveDrive', $match->id, 'https://example.com')->assertHasErrors('drive.'.$match->id);
    expect($match->fresh()->drive_folder_url)->toBeNull();

    $page->call('saveDrive', $match->id, FOLDER);
    expect($match->fresh()->drive_folder_url)->toBe(FOLDER);
});

it('halaman match menampilkan galeri dan tombol unduh hanya untuk pembeli lunas', function () {
    $match = matchSelesai();
    $match->update(['drive_folder_url' => FOLDER, 'drive_photos' => ['match-docs/1/a.webp']]);
    $pembeli = User::factory()->create();
    Booking::factory()->create(['match_id' => $match->id, 'user_id' => $pembeli->id, 'status' => 'paid']);

    $this->get(route('match.show', $match))->assertSee('Dokumentasi')->assertDontSee('Lihat &amp; unduh semua foto', false);
    $this->actingAs($pembeli)->get(route('match.show', $match))->assertSee('Lihat &amp; unduh semua foto', false);
});
