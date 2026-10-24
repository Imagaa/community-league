<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Kode unik 1-999 yang ditambahkan ke nominal QRIS manual supaya admin bisa
            // mencocokkan mutasi rekening dengan booking.
            $table->unsignedSmallInteger('unique_code')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->timestamp('proof_uploaded_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['unique_code', 'payment_proof_path', 'proof_uploaded_at', 'verified_at', 'rejection_reason']);
        });
    }
};
