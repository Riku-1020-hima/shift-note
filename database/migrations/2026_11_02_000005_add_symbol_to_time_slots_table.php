<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_slots', function (Blueprint $table) {
            // 印刷用の表に出す記号(昼・夜など)。空なら名前の1文字目
            $table->string('symbol', 2)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('time_slots', function (Blueprint $table) {
            $table->dropColumn('symbol');
        });
    }
};
