<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_benefits', function (Blueprint $table) {
            $table->id();
            $table->string('icon');
            $table->string('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Migrasi 4 bullet statis yang sudah ada di membership.blade.php supaya halaman tidak
        // kosong begitu fitur ini deploy.
        $now = now();
        DB::table('membership_benefits')->insert([
            ['icon' => 'tag', 'text' => 'Harga member lebih murah di setiap match.', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['icon' => 'clock', 'text' => 'Buka booking lebih awal sebelum publik.', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['icon' => 'star', 'text' => 'Kumpulkan poin di setiap match, tukar dengan merchandise.', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['icon' => 'user-group', 'text' => 'Badge komunitas & prioritas slot di match ramai.', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_benefits');
    }
};
