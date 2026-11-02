<?php

namespace Database\Seeders;

use App\Models\Availability;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\Submission;
use App\Models\User;
use App\Services\ShiftPlanner;
use Illuminate\Database\Seeder;

/**
 * デモ用データ(架空の飲食店)。ログイン: demo@example.com / password
 *  ・今月 … 全員が希望を出し、自動で作ったシフトを公開済み
 *  ・来月 … 10人中8人が希望を提出。まだシフトは作っていない(「自動で作る」を試せる)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(20261102); // 毎回同じデータになるように

        $user = User::create([
            'name' => 'キッチンさくら 渋谷店',
            'closed_weekdays' => [3], // 水曜定休
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $lunch = $user->timeSlots()->create(['name' => 'ランチ', 'symbol' => '昼', 'start_time' => '11:00', 'end_time' => '15:00', 'weekday_required' => 2, 'weekend_required' => 3,
            'position_rules' => [['position' => 'キッチン', 'min' => 1]]]);
        $dinner = $user->timeSlots()->create(['name' => 'ディナー', 'symbol' => '夜', 'start_time' => '17:00', 'end_time' => '22:00', 'weekday_required' => 3, 'weekend_required' => 4,
            'position_rules' => [['position' => 'キッチン', 'min' => 1], ['position' => 'ホール', 'min' => 1]]]);

        // [名前, 担当, 週の上限, 入りやすい曜日(0=日), 入りやすい時間帯, 入れる確率, メモ, 有給の残り]
        $people = [
            ['佐藤 ゆうき', 'キッチン', null, [0, 1, 2, 4, 5, 6], ['ランチ', 'ディナー'], 90, '社員', 12],
            ['鈴木 あかり', 'ホール', 4, [1, 2, 4, 5, 6], ['ランチ', 'ディナー'], 75, null, 7],
            ['高橋 れん', 'キッチン', 5, [0, 1, 2, 4, 5, 6], ['ディナー'], 85, null, 9],
            ['田中 みお', 'ホール', 3, [5, 6, 0], ['ディナー'], 85, '大学生', 5],
            ['伊藤 そうた', 'ホール・キッチン', 4, [1, 2, 4, 6, 0], ['ランチ', 'ディナー'], 70, null, 7],
            ['渡辺 ひな', 'ホール', 2, [6, 0], ['ランチ'], 90, '高校生(土日のみ)', 0],
            ['山本 だいき', 'キッチン', 5, [1, 2, 4, 5, 6], ['ランチ'], 85, null, 10],
            ['中村 さくら', 'ホール', 3, [1, 2, 4, 5], ['ランチ'], 80, '平日昼のみ', 5],
            ['小林 けんと', 'ホール', 3, [4, 5, 6], ['ディナー'], 80, '大学生', 3],
            ['加藤 まい', 'ホール・キッチン', 4, [0, 1, 2, 5, 6], ['ランチ', 'ディナー'], 65, null, 7],
        ];
        $slotByName = ['ランチ' => $lunch, 'ディナー' => $dinner];

        $staff = [];
        foreach ($people as [$name, $position, $max, $days, $slots, $rate, $memo, $leave]) {
            $staff[] = [$user->staff()->create([
                'name' => $name, 'position' => $position, 'max_days_per_week' => $max, 'paid_leave_balance' => $leave,
                'is_active' => true, 'memo' => $memo,
            ]), $days, $slots, $rate];
        }

        $thisMonth = now()->format('Y-m');
        $nextMonth = now()->addMonthNoOverflow()->format('Y-m');

        foreach ([$thisMonth, $nextMonth] as $ym) {
            $rows = [];
            foreach ($staff as $i => [$member, $days, $slots, $rate]) {
                if ($ym === $nextMonth && $i >= 8) {
                    continue; // 来月はまだ2人が出していない
                }
                foreach (Shift::days($ym, $user) as $day) {
                    foreach ($slots as $slotName) {
                        $likely = in_array((int) $day->format('w'), $days, true);
                        if (mt_rand(1, 100) <= ($likely ? $rate : 6)) {
                            $rows[] = ['staff_id' => $member->id, 'time_slot_id' => $slotByName[$slotName]->id,
                                'date' => $day->toDateString(), 'created_at' => now(), 'updated_at' => now()];
                        }
                    }
                }
                Submission::create(['staff_id' => $member->id, 'year_month' => $ym, 'submitted_at' => now()->subDays(mt_rand(1, 9)),
                    'note' => $i === 3 && $ym === $nextMonth ? '15日〜20日はテスト期間なので少なめだと助かります' : null]);
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                Availability::insert($chunk);
            }
        }

        // 有給:承認済み1件(今月。自動作成で外れる)、承認待ち2件、却下1件
        $openDay = fn ($date) => $user->isClosedOn($date) ? $date->addDay() : $date;
        $approvedDay = $openDay(now()->toImmutable()->addDays(6)->startOfDay());
        if ($approvedDay->format('Y-m') !== $thisMonth) {
            $approvedDay = $openDay(now()->toImmutable()->startOfMonth()->addDays(1)); // 月末に近いときは月初の日にする
        }
        LeaveRequest::create(['staff_id' => $staff[1][0]->id, 'date' => $approvedDay->toDateString(), 'status' => 'approved',
            'note' => '友人の結婚式のため', 'reply' => '楽しんできてください！', 'decided_at' => now()->subDays(3)]);
        $staff[1][0]->decrement('paid_leave_balance');
        $nextFirst = now()->toImmutable()->addMonthNoOverflow()->startOfMonth();
        LeaveRequest::create(['staff_id' => $staff[2][0]->id, 'date' => $openDay($nextFirst->addDays(9))->toDateString(), 'status' => 'pending', 'note' => '通院のため']);
        LeaveRequest::create(['staff_id' => $staff[0][0]->id, 'date' => $openDay($nextFirst->addDays(15))->toDateString(), 'status' => 'pending', 'note' => null]);
        LeaveRequest::create(['staff_id' => $staff[4][0]->id, 'date' => $openDay($nextFirst->addDays(4))->toDateString(), 'status' => 'rejected',
            'note' => null, 'reply' => 'この日は貸切の予約があるので、別の日でお願いできますか?', 'decided_at' => now()->subDay()]);

        // 今月は自動で作って公開済み
        app(ShiftPlanner::class)->plan($user, $thisMonth);
        $user->scheduleFor($thisMonth)->update(['published_at' => now()->startOfMonth()->subDays(5)]);
        // 締め切りは今月20日(もう過ぎていれば3日後)
        $deadline = now()->day < 18 ? now()->startOfMonth()->addDays(19) : now()->addDays(3);
        $user->scheduleFor($nextMonth)->update(['deadline' => $deadline->toDateString()]);
    }
}
