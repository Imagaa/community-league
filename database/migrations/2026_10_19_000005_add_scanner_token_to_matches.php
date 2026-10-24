<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Token rahasia link scanner check-in publik per match (bisa dibuat ulang dari admin).
            $table->string('scanner_token', 40)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('scanner_token');
        });
    }
};
