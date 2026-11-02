<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            // 有給の残り日数(店長が入力。承認すると1日ずつ減る)
            $table->unsignedSmallInteger('paid_leave_balance')->default(0)->after('max_days_per_week');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('date');
            $table->text('note')->nullable();               // スタッフからのひとこと
            $table->string('status', 20)->default('pending'); // pending / approved / rejected / cancelled
            $table->text('reply')->nullable();              // 店長からの返事(却下の理由など)
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['staff_id', 'date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn('paid_leave_balance');
        });
    }
};
