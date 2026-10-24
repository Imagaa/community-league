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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->unsignedTinyInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('total');
            $table->string('booking_code', 8)->unique();
            $table->enum('status', [
                'pending_payment', 'awaiting_verification', 'paid',
                'rejected', 'cancelled', 'expired',
            ])->default('pending_payment');
            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->enum('team', ['A', 'B'])->nullable();
            $table->string('terms_version');
            $table->timestamps();

            $table->index(['match_id', 'status']);
            $table->index(['status', 'hold_expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
