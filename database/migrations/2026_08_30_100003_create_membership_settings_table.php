<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('price');
            $table->integer('duration_days');
            $table->integer('grace_period_days');
            $table->timestamps();
        });

        // Baris tunggal (id=1) selalu ada -- MembershipSettings::current() mengandalkan ini,
        // bukan cek-lalu-buat di request pertama.
        DB::table('membership_settings')->insert([
            'price' => 100000,
            'duration_days' => 365,
            'grace_period_days' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_settings');
    }
};
