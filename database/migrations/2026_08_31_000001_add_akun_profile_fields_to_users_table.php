<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname')->nullable()->after('name');
            $table->date('birthdate')->nullable();
            $table->string('shirt_size', 4)->nullable();
            $table->unsignedTinyInteger('shoe_size')->nullable();
            $table->text('address')->nullable();
            $table->timestamp('scheduled_deletion_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nickname', 'birthdate', 'shirt_size', 'shoe_size', 'address',
                'scheduled_deletion_at',
            ]);
        });
    }
};
