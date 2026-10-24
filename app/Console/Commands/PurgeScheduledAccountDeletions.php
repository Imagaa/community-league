<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:purge-scheduled-deletions')]
#[Description('Hapus permanen akun yang masa tenggang 30 harinya sudah lewat')]
class PurgeScheduledAccountDeletions extends Command
{
    public function handle(): int
    {
        User::query()
            ->whereNotNull('scheduled_deletion_at')
            ->where('scheduled_deletion_at', '<=', now())
            ->each(fn (User $user) => $user->delete());

        return self::SUCCESS;
    }
}
