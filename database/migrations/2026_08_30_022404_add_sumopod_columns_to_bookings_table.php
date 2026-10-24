<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('sumopod_payment_id')->nullable()->after('status');
            $table->string('sumopod_payment_link_url')->nullable()->after('sumopod_payment_id');
            $table->timestamp('paid_at')->nullable()->after('sumopod_payment_link_url');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['sumopod_payment_id', 'sumopod_payment_link_url', 'paid_at']);
        });
    }
};
