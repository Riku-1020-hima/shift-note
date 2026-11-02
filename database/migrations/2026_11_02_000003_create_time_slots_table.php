<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // ランチ・ディナーなど
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedTinyInteger('weekday_required')->default(2); // 平日に必要な人数
            $table->unsignedTinyInteger('weekend_required')->default(2); // 土日に必要な人数
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
