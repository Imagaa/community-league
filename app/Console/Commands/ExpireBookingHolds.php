<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:expire-holds')]
#[Description('Ubah status booking pending_payment yang hold-nya sudah lewat jadi expired')]
class ExpireBookingHolds extends Command
{
    public function handle(): int
    {
        $jumlah = Booking::query()
            ->where('status', 'pending_payment')
            ->where('hold_expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("{$jumlah} booking dikedaluwarsakan.");

        return self::SUCCESS;
    }
}
