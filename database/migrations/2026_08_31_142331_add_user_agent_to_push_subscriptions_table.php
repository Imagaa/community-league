<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('webpush.table_name', 'push_subscriptions'), function (Blueprint $table) {
            $table->string('user_agent')->nullable()->after('content_encoding');
        });
    }

    public function down(): void
    {
        Schema::table(config('webpush.table_name', 'push_subscriptions'), function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });
    }
};
