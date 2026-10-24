<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:wipe-test-accounts {--force : Lewati konfirmasi}')]
#[Description('Hapus semua akun publik (bukan super_admin/panitia/editor_konten) -- buat reset cepat saat testing flow register/login')]
class WipeTestAccounts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::whereDoesntHave('roles', function ($query) {
            $query->whereIn('name', ['super_admin', 'panitia', 'editor_konten']);
        })->get();

        if ($users->isEmpty()) {
            $this->info('Tidak ada akun publik untuk dihapus.');

            return self::SUCCESS;
        }

        $this->info("Akan menghapus {$users->count()} akun publik (akun super_admin/panitia/editor_konten tidak disentuh):");
        $this->table(['Nama', 'Email'], $users->map(fn (User $user) => [$user->name, $user->email]));

        if (! $this->option('force') && ! $this->confirm('Lanjutkan?')) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $count = $users->count();
        User::destroy($users->pluck('id'));

        $this->info("{$count} akun publik terhapus.");

        return self::SUCCESS;
    }
}
