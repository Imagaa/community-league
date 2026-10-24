<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            // Isi teks QR statis merchant (EMVCo), hasil decode gambar yang diunggah admin.
            $table->text('qris_payload')->nullable();
            $table->string('qris_merchant_name')->nullable();
            $table->boolean('sumopod_enabled')->default(false);
            $table->string('sumopod_mode')->nullable(); // sandbox | live; null = ikut env
            $table->text('sumopod_api_key')->nullable();
            $table->text('sumopod_webhook_token')->nullable();
            $table->string('admin_whatsapp')->default('6285713438267');
            $table->timestamps();
        });

        // Baris tunggal (id=1), pola yang sama dengan membership_settings.
        DB::table('payment_settings')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        Schema::table('membership_settings', function (Blueprint $table) {
            $table->boolean('enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');

        Schema::table('membership_settings', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};
