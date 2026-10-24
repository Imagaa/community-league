<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            // Berapa menit member bisa membeli lebih dulu sebelum publik (member_opens_at =
            // public_opens_at - ini). Diatur per match di menu Membership.
            $table->unsignedInteger('member_lead_minutes')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('member_lead_minutes');
        });
    }
};
