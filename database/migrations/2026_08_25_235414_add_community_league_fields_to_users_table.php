<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar_path')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->string('referral_code', 8)->nullable()->unique();
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('no_show_count')->default(0);
            $table->string('terms_version')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by');
            $table->dropColumn([
                'phone', 'avatar_path', 'google_id', 'referral_code',
                'no_show_count', 'terms_version', 'terms_accepted_at',
            ]);
        });
    }
};
