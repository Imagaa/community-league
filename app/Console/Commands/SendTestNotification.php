<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TestNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:test {email : Email user yang sudah terdaftar}')]
#[Description('Kirim satu notifikasi uji ke user tertentu, buat verifikasi lonceng notifikasi tanpa memicu event bisnis nyata')]
class SendTestNotification extends Command
{
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User dengan email [{$email}] tidak ditemukan.");

            return self::FAILURE;
        }

        $user->notify(new TestNotification);

        $this->info("Notifikasi uji terkirim ke {$user->name} ({$user->email}).");

        return self::SUCCESS;
    }
}
