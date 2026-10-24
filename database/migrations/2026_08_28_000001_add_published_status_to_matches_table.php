<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE matches DROP CONSTRAINT matches_status_check');
        DB::statement(
            'ALTER TABLE matches ADD CONSTRAINT matches_status_check '.
            "CHECK (status IN ('draft', 'published', 'open', 'ongoing', 'finished', 'cancelled'))"
        );

        // open/ongoing/finished are no longer written by the app -- they're computed live
        // from published + schedule now (GameMatch::computedStatus()). Existing rows that
        // were manually transitioned into those statuses become 'published' so the computed
        // logic takes over correctly from here.
        DB::table('matches')
            ->whereIn('status', ['open', 'ongoing', 'finished'])
            ->update(['status' => 'published']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE matches DROP CONSTRAINT matches_status_check');
        DB::statement(
            'ALTER TABLE matches ADD CONSTRAINT matches_status_check '.
            "CHECK (status IN ('draft', 'open', 'ongoing', 'finished', 'cancelled'))"
        );
    }
};
