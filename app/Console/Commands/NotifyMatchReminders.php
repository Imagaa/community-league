<?php

namespace App\Console\Commands;

use App\Models\MatchReminder;
use App\Notifications\MatchOpenedReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('matches:notify-reminders')]
#[Description('Kirim notifikasi ke user yang minta diingatkan begitu match-nya bisa dia pesan (member akses awal atau publik)')]
class NotifyMatchReminders extends Command
{
    public function handle(): int
    {
        $terkirim = 0;

        MatchReminder::query()
            ->with(['user.membership', 'match'])
            ->get()
            ->each(function (MatchReminder $reminder) use (&$terkirim) {
                $match = $reminder->match;

                // Match sudah dihapus/dibatalkan/draft lagi -- reminder ini tidak relevan lagi.
                if (! $match || $match->status !== 'published') {
                    $reminder->delete();

                    return;
                }

                // Jendela booking sudah lewat (match sudah mulai/berlangsung/selesai) sebelum
                // reminder sempat terkirim -- tidak relevan lagi diingatkan "sudah bisa dipesan".
                if (in_array($match->computedStatus(), ['ongoing', 'finished'], true)) {
                    $reminder->delete();

                    return;
                }

                // isBookableBy() (bukan cuma bandingkan public_opens_at) supaya member yang
                // jendela aksesnya lebih awal beneran diingatkan lebih awal juga.
                if (! $match->isBookableBy($reminder->user)) {
                    return;
                }

                $reminder->user->notify(new MatchOpenedReminderNotification($match));
                $reminder->delete();
                $terkirim++;
            });

        $this->info("{$terkirim} pengingat match terkirim.");

        return self::SUCCESS;
    }
}
