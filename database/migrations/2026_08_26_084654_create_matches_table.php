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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('field_name');
            $table->enum('type', ['minisoccer', 'football']);
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('poster_path')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedInteger('price_regular');
            $table->unsignedInteger('price_member');
            $table->unsignedInteger('points_reward')->default(0);
            $table->timestamp('member_opens_at');
            $table->timestamp('public_opens_at');
            $table->enum('status', ['draft', 'open', 'ongoing', 'finished', 'cancelled'])
                ->default('draft');
            $table->unsignedTinyInteger('score_a')->nullable();
            $table->unsignedTinyInteger('score_b')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
