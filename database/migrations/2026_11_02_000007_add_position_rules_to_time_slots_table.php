<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_slots', function (Blueprint $table) {
            // 担当の条件 [{"position":"キッチン","min":1}, ...]
            $table->json('position_rules')->nullable()->after('weekend_required');
        });
    }

    public function down(): void
    {
        Schema::table('time_slots', function (Blueprint $table) {
            $table->dropColumn('position_rules');
        });
    }
};
