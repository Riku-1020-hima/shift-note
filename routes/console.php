<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

/*
 | デモのデータを初期状態に戻す
 |  php artisan demo:reset            … データを全部消して、デモデータを入れ直す(DEMO_MODE=true のときだけ)
 |  php artisan demo:reset --if-empty … まだ誰も登録されていないときだけ入れる(初回デプロイ用)
 */
Artisan::command('demo:reset {--if-empty : ユーザーが1人もいないときだけ実行する}', function () {
    if (! config('demo.enabled')) {
        $this->warn('DEMO_MODE が有効ではないので、何もしません(本番データを守るため)。');

        return 0;
    }

    if ($this->option('if-empty') && Schema::hasTable('users') && \App\Models\User::query()->exists()) {
        $this->info('デモデータはすでにあります。');

        return 0;
    }

    $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
    $this->info('デモデータを初期状態に戻しました。');

    return 0;
})->purpose('デモのデータを初期状態に戻す');

Schedule::command('demo:reset')
    ->dailyAt(config('demo.reset_at'))
    ->timezone(config('demo.timezone'))
    ->when(fn () => config('demo.enabled'));
