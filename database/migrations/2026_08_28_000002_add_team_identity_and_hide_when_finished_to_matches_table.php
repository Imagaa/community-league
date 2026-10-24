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
        Schema::table('matches', function (Blueprint $table) {
            $table->boolean('hide_when_finished')->default(false)->after('status');
            $table->string('team_a_name')->nullable()->after('score_a');
            $table->string('team_b_name')->nullable()->after('score_b');
            $table->string('team_a_logo_path')->nullable()->after('team_a_name');
            $table->string('team_b_logo_path')->nullable()->after('team_b_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['hide_when_finished', 'team_a_name', 'team_b_name', 'team_a_logo_path', 'team_b_logo_path']);
        });
    }
};
