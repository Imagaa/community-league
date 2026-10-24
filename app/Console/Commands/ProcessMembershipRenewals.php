<?php

namespace App\Console\Commands;

use App\Models\Membership;
use App\Models\MembershipSettings;
use App\Models\PointsTransaction;
use App\Notifications\MembershipRenewalReminder;
use App\Notifications\PointsForfeitedNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('membership:process-renewals')]
#[Description('Kirim reminder H-3..H-0 dan hanguskan poin membership yang lewat masa tenggang')]
class ProcessMembershipRenewals extends Command
{
    public function handle(): int
    {
        $this->sendReminders();
        $this->forfeitExpiredPoints();

        return self::SUCCESS;
    }

    private function sendReminders(): void
    {
        $today = today();

        Membership::query()
            ->get()
            ->each(function (Membership $membership) use ($today) {
                $daysRemaining = (int) $today->diffInDays($membership->expires_at->copy()->startOfDay(), false);

                if (! in_array($daysRemaining, [3, 2, 1, 0], true)) {
                    return;
                }

                if ($membership->last_reminder_at?->isSameDay($today)) {
                    return;
                }

                $membership->user->notify(new MembershipRenewalReminder($daysRemaining));
                $membership->update(['last_reminder_at' => $today]);
            });
    }

    private function forfeitExpiredPoints(): void
    {
        $gracePeriodDays = MembershipSettings::current()->grace_period_days;

        Membership::query()
            ->whereNull('points_forfeited_at')
            ->get()
            ->filter(fn (Membership $m) => $m->expires_at->copy()->addDays($gracePeriodDays)->isPast())
            ->each(function (Membership $membership) {
                $balance = $membership->user->pointsBalance();

                if ($balance > 0) {
                    PointsTransaction::create([
                        'user_id' => $membership->user_id,
                        'amount' => -$balance,
                        'type' => 'forfeiture',
                        'description' => 'Poin hangus -- membership tidak diperpanjang dalam masa tenggang.',
                    ]);

                    $membership->user->notify(new PointsForfeitedNotification($balance));
                }

                $membership->update(['points_forfeited_at' => now()]);
            });
    }
}
