<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-super-admin {email : Email user yang sudah terdaftar}')]
#[Description('Jadikan user dengan email tertentu super_admin (bisa masuk panel /admin)')]
class MakeSuperAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User dengan email [{$email}] tidak ditemukan. Daftar dulu lewat aplikasi, baru jalankan perintah ini.");

            return self::FAILURE;
        }

        $user->assignRole('super_admin');

        $this->info("{$user->name} ({$user->email}) sekarang super_admin.");

        return self::SUCCESS;
    }
}
