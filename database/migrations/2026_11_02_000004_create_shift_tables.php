<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 月ごとの状態(希望の締め切り・公開したか)
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('year_month', 7);              // 2026-12
            $table->date('deadline')->nullable();          // 希望の締め切り
            $table->timestamp('published_at')->nullable(); // スタッフに公開した日時
            $table->timestamps();
            $table->unique(['user_id', 'year_month']);
        });

        // スタッフが「その月の希望を出した」記録(全部×でも提出済みにする)
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->string('year_month', 7);
            $table->text('note')->nullable();             // 店長へのひとこと
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['staff_id', 'year_month']);
        });

        // 入れる日と時間帯(1行 = 「この日のこの時間帯なら入れる」)
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('time_slot_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();
            $table->unique(['staff_id', 'time_slot_id', 'date']);
            $table->index(['date', 'time_slot_id']);
        });

        // 決まったシフト(1行 = 「この日のこの時間帯にこの人が入る」)
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('time_slot_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();
            $table->unique(['staff_id', 'time_slot_id', 'date']);
            $table->index(['date', 'time_slot_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('availabilities');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('schedules');
    }
};
