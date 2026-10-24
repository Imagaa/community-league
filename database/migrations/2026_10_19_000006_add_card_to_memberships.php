<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            // Token QR kartu member (link verifikasi status). Dibuat ulang saat kartu hilang -> kartu lama tidak berlaku.
            $table->string('card_token', 40)->nullable()->unique();
            // Terakhir kali admin mengunduh kartu untuk dicetak; dikosongkan saat kartu diganti.
            $table->timestamp('card_printed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropColumn(['card_token', 'card_printed_at']);
        });
    }
};
