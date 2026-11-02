<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('position')->nullable();             // ホール・キッチンなど
            $table->unsignedTinyInteger('max_days_per_week')->nullable(); // 週に入れる日数の上限(空=制限なし)
            $table->boolean('is_active')->default(true);         // 在籍中か
            $table->string('token', 40)->unique();               // 希望を出すページ専用のURLに使う
            $table->text('memo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
