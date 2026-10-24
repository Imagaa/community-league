<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_settings', function (Blueprint $table) {
            // Latar desain kartu unggahan admin (null = desain bawaan resources/images/card-*.png).
            $table->string('card_front_path')->nullable();
            $table->string('card_back_path')->nullable();
            // Posisi kotak data (nama, nomor, QR) dalam persen kartu; null = posisi bawaan.
            $table->json('card_layout')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('membership_settings', function (Blueprint $table) {
            $table->dropColumn(['card_front_path', 'card_back_path', 'card_layout']);
        });
    }
};
