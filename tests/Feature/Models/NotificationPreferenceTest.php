<?php

use App\Models\NotificationPreference;
use App\Models\User;

it('forUser membuat baris default kalau belum ada', function () {
    $user = User::factory()->create();

    $pref = NotificationPreference::forUser($user);

    expect($pref->user_id)->toBe($user->id)
        ->and($pref->match_reminders)->toBeTrue()
        ->and($pref->promotions)->toBeFalse();
    expect(NotificationPreference::where('user_id', $user->id)->count())->toBe(1);
});

it('forUser tidak duplikat kalau dipanggil dua kali', function () {
    $user = User::factory()->create();

    $first = NotificationPreference::forUser($user);
    $second = NotificationPreference::forUser($user);

    expect($first->id)->toBe($second->id);
    expect(NotificationPreference::where('user_id', $user->id)->count())->toBe(1);
});
