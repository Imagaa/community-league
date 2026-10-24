<?php

use App\Models\GameMatch;
use App\Models\MembershipSettings;
use App\Models\PaymentSettings;
use App\Models\User;
use App\Support\Qris;
use Livewire\Livewire;

function qrisValid(): string
{
    $body = '00020101021126310017ID.CO.EXAMPLE.WWW01061234565204581453033605802ID5916COMMUNITY LEAGUE6009SURAKARTA6304';

    return $body.Qris::crc16($body);
}

it('menyimpan QRIS statis yang valid dan menolak yang tidak valid', function () {
    $page = Livewire::actingAs(buatAdmin())->test('pages::admin.setelan');

    $page->call('saveQris', 'https://bukan-qris.example')->assertHasErrors('qris');
    expect(PaymentSettings::current()->hasQris())->toBeFalse();

    $page->call('saveQris', qrisValid())->assertHasNoErrors()->assertSee('COMMUNITY LEAGUE');
    expect(PaymentSettings::current())
        ->qris_payload->toBe(qrisValid())
        ->qris_merchant_name->toBe('COMMUNITY LEAGUE');
});

it('setelan tampil sebagai ringkasan dulu; isian baru muncul setelah Ubah dan tersimpan hanya saat Simpan', function () {
    $page = Livewire::actingAs(buatAdmin())->test('pages::admin.setelan')
        ->assertSee(route('webhooks.sumopod'))
        ->assertSee('Belum diatur')
        ->assertDontSee('API key baru');

    // Batal tidak menyimpan apa pun.
    $page->call('edit', 'sumopod')->assertSee('API key baru')
        ->set('sumopod_api_key', 'sk-batal')->call('cancelEdit')
        ->assertSet('editing', null)->assertDontSee('API key baru');
    expect(PaymentSettings::current()->sumopod_api_key)->toBeNull();

    $page->call('edit', 'sumopod')->set('sumopod_enabled', true)->call('saveSumopod')->assertHasErrors('sumopod_api_key');

    $page->set('sumopod_api_key', 'sk-rahasia-e4f9')->set('sumopod_webhook_token', 'wh-rahasia')
        ->set('sumopod_mode', 'live')
        ->call('saveSumopod')->assertHasNoErrors()
        ->assertSet('editing', null)
        ->assertSee('Sudah diatur ••••e4f9')
        ->assertDontSee('sk-rahasia-e4f9');

    expect(DB::table('payment_settings')->value('sumopod_api_key'))->not->toContain('sk-rahasia');

    // Ubah lagi tanpa isi key: key lama dipertahankan.
    $page->call('edit', 'sumopod')->call('saveSumopod');
    expect(PaymentSettings::current())
        ->sumopod_api_key->toBe('sk-rahasia-e4f9')
        ->sumopod_enabled->toBeTrue()
        ->and(PaymentSettings::current()->sumopodAvailable())->toBeTrue();

    $page->call('edit', 'whatsapp')->set('admin_whatsapp', '0811 2222 3333')->call('saveWhatsapp')->assertHasNoErrors();
    expect(PaymentSettings::current()->admin_whatsapp)->toBe('6281122223333');

    $page->call('edit', 'drive')->call('saveDrive')->assertHasErrors('google_drive_api_key');
    $page->set('google_drive_api_key', 'AIza-test-1234')->call('saveDrive')->assertSee('Sudah diatur ••••1234');
});

it('webhook memakai token dari setelan admin', function () {
    PaymentSettings::current()->update(['sumopod_webhook_token' => 'token-admin']);

    $this->postJson('/webhooks/sumopod', ['event_type' => 'x'], ['X-Webhook-Token' => 'salah'])->assertUnauthorized();
    $this->postJson('/webhooks/sumopod', ['event_type' => 'x'], ['X-Webhook-Token' => 'token-admin'])->assertNoContent();
});

it('saklar membership menutup halaman membership dan teks poin di web', function () {
    $match = GameMatch::factory()->create(['points_reward' => 10]);

    expect(MembershipSettings::enabled())->toBeFalse();
    $this->get(route('membership'))->assertSee('Fitur membership sedang dikembangkan');
    $this->get(route('match.show', $match))->assertDontSee('+10 Pts');
    $this->actingAs(User::factory()->create())->get(route('marketplace'))->assertRedirect(route('membership'));

    Livewire::actingAs(buatAdmin())->test('pages::admin.membership')
        ->assertDontSee('Durasi (hari)')
        ->call('editSettings')
        ->set('enabled', true)->set('price', 150000)->call('saveSettings')->assertHasNoErrors();

    expect(MembershipSettings::current())->enabled->toBeTrue()->price->toBe(150000);
    $this->get(route('match.show', $match))->assertSee('+10 Pts');
});
