<?php

use App\Models\GameMatch;
use App\Models\MatchReminder;
use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\User;
use App\Notifications\MatchOpenedReminderNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

// Akses awal member hanya berlaku saat saklar Membership aktif.
beforeEach(fn () => MembershipSettings::current()->update(['enabled' => true]));

it('tombol pengingat dimatikan sementara di halaman match', function () {
    $match = GameMatch::factory()->create(['status' => 'published', 'public_opens_at' => now()->addDay()]);

    $this->actingAs(User::factory()->create())
        ->get(route('match.show', $match))
        ->assertOk()
        ->assertDontSee('Ingatkan Saya');
});

it('toggleReminder membuat lalu menghapus baris match_reminders', function () {
    $match = GameMatch::factory()->create(['status' => 'published', 'public_opens_at' => now()->addDay()]);
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test('pages::matches.reminder-button', ['match' => $match]);

    $component->call('toggleReminder')->assertSet('reminderSet', true);
    expect(MatchReminder::where('user_id', $user->id)->where('match_id', $match->id)->exists())->toBeTrue();

    $component->call('toggleReminder')->assertSet('reminderSet', false);
    expect(MatchReminder::where('user_id', $user->id)->where('match_id', $match->id)->exists())->toBeFalse();
});

it('toggleReminder menolak tamu tanpa login', function () {
    $match = GameMatch::factory()->create(['status' => 'published', 'public_opens_at' => now()->addDay()]);

    Livewire::test('pages::matches.reminder-button', ['match' => $match])
        ->call('toggleReminder')
        ->assertForbidden();
});

it('command mengirim reminder begitu waktu efektif tercapai lalu menghapus baris reminder', function () {
    Notification::fake();

    $match = GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->subMinute(),
        'starts_at' => now()->addDay(),
    ]);
    $user = User::factory()->create();
    MatchReminder::factory()->create(['user_id' => $user->id, 'match_id' => $match->id]);

    $this->artisan('matches:notify-reminders')->assertSuccessful();

    Notification::assertSentTo($user, MatchOpenedReminderNotification::class);
    expect(MatchReminder::count())->toBe(0);
});

it('command belum mengirim kalau belum masuk jendela buka user itu', function () {
    Notification::fake();

    $match = GameMatch::factory()->create([
        'status' => 'published',
        'public_opens_at' => now()->addHour(),
        'starts_at' => now()->addDay(),
    ]);
    $user = User::factory()->create();
    MatchReminder::factory()->create(['user_id' => $user->id, 'match_id' => $match->id]);

    $this->artisan('matches:notify-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    expect(MatchReminder::count())->toBe(1);
});

it('member dengan jendela akses awal diingatkan lebih cepat dari non-member', function () {
    Notification::fake();

    $match = GameMatch::factory()->create([
        'status' => 'published',
        'member_opens_at' => now()->subMinute(),
        'public_opens_at' => now()->addHour(),
        'starts_at' => now()->addDay(),
    ]);

    $member = User::factory()->create();
    Membership::factory()->for($member)->create(['expires_at' => now()->addMonth()]);
    MatchReminder::factory()->create(['user_id' => $member->id, 'match_id' => $match->id]);

    $nonMember = User::factory()->create();
    MatchReminder::factory()->create(['user_id' => $nonMember->id, 'match_id' => $match->id]);

    $this->artisan('matches:notify-reminders')->assertSuccessful();

    Notification::assertSentTo($member, MatchOpenedReminderNotification::class);
    Notification::assertNotSentTo($nonMember, MatchOpenedReminderNotification::class);
    expect(MatchReminder::where('user_id', $nonMember->id)->exists())->toBeTrue();
});

it('command membersihkan reminder untuk match yang sudah dibatalkan tanpa mengirim notifikasi', function () {
    Notification::fake();

    $match = GameMatch::factory()->create(['status' => 'cancelled']);
    $user = User::factory()->create();
    MatchReminder::factory()->create(['user_id' => $user->id, 'match_id' => $match->id]);

    $this->artisan('matches:notify-reminders')->assertSuccessful();

    Notification::assertNothingSent();
    expect(MatchReminder::count())->toBe(0);
});
