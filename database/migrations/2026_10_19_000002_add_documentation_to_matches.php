<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Folder Google Drive dokumentasi match yang sudah selesai.
            $table->string('drive_folder_url')->nullable();
            // Path foto (disk public/R2) yang disalin dari folder Drive, untuk galeri di web.
            $table->json('drive_photos')->nullable();
            // Email terima kasih + link dokumentasi hanya dikirim sekali.
            $table->timestamp('documentation_sent_at')->nullable();
        });

        Schema::table('payment_settings', function (Blueprint $table) {
            $table->text('google_drive_api_key')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['drive_folder_url', 'drive_photos', 'documentation_sent_at']);
        });

        Schema::table('payment_settings', function (Blueprint $table) {
            $table->dropColumn('google_drive_api_key');
        });
    }
};
