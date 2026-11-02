<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\Staff;
use App\Models\Submission;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\ShiftPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShiftNoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 10:00:00'); // 2026年12月1日は火曜日
    }

    private function store(array $attrs = []): User
    {
        return User::create($attrs + ['name' => 'テスト食堂', 'email' => 's'.uniqid().'@example.com', 'password' => 'password']);
    }

    private function slot(User $u, array $attrs = []): TimeSlot
    {
        return $u->timeSlots()->create($attrs + ['name' => 'ランチ', 'start_time' => '11:00', 'end_time' => '15:00', 'weekday_required' => 1, 'weekend_required' => 1]);
    }

    private function member(User $u, array $attrs = []): Staff
    {
        return $u->staff()->create($attrs + ['name' => 'スタッフ'.uniqid(), 'is_active' => true]);
    }

    private function avail(Staff $s, TimeSlot $slot, array $dates): void
    {
        foreach ($dates as $d) {
            Availability::create(['staff_id' => $s->id, 'time_slot_id' => $slot->id, 'date' => $d]);
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('シフトノート');
    }

    public function test_register_and_see_dashboard_setup_steps(): void
    {
        $this->post('/register', [
            'name' => 'キッチンテスト', 'email' => 'new@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect('/');

        $this->get('/')->assertOk()->assertSee('はじめにやること')->assertSee('時間帯');
    }

    public function test_time_slot_validation_and_crud(): void
    {
        $u = $this->store();
        $this->actingAs($u)->post('/slots', ['name' => '夜', 'start_time' => '22:00', 'end_time' => '17:00', 'weekday_required' => 2, 'weekend_required' => 3])
            ->assertSessionHasErrors('end_time');

        $this->post('/slots', ['name' => 'ディナー', 'symbol' => '夜', 'start_time' => '17:00', 'end_time' => '22:00', 'weekday_required' => 2, 'weekend_required' => 3])
            ->assertRedirect('/slots');
        $slot = $u->timeSlots()->first();
        $this->assertSame('17:00〜22:00', $slot->time_label);
        $this->assertSame('夜', $slot->mark);
        $this->assertSame('ラ', (new TimeSlot(['name' => 'ランチ']))->mark); // 記号が空なら名前の1文字目
        $this->get('/slots')->assertOk()->assertSee('ディナー');
        $this->get("/slots/{$slot->id}/edit")->assertOk();
    }

    public function test_staff_get_a_private_url(): void
    {
        $u = $this->store();
        $this->actingAs($u)->post('/staff', ['name' => '山田', 'max_days_per_week' => 3, 'is_active' => 1])->assertRedirect('/staff');
        $m = $u->staff()->first();

        $this->assertSame(32, strlen($m->token));
        $this->get('/staff')->assertOk()->assertSee('山田')->assertSee($m->portal_url);

        // URLを作り直すと前のURLは使えなくなる
        $old = $m->token;
        $this->post("/staff/{$m->id}/token");
        $this->get('/s/'.$old)->assertNotFound();
        $this->get('/s/'.$m->fresh()->token)->assertOk()->assertSee('山田さんのシフト');
    }

    public function test_other_stores_data_is_hidden(): void
    {
        $a = $this->store();
        $b = $this->store();
        $m = $this->member($b);
        $slot = $this->slot($b);

        $this->actingAs($a)->get("/staff/{$m->id}/edit")->assertNotFound();
        $this->get("/slots/{$slot->id}/edit")->assertNotFound();

        // 他のお店のスタッフ・時間帯は、1日の編集から入れられない
        $mySlot = $this->slot($a);
        $this->put('/shifts/day/2026-12-01', ['shifts' => [$slot->id => [$m->id], $mySlot->id => [$m->id]]]);
        $this->assertSame(0, Assignment::count());
    }

    public function test_staff_submit_availability(): void
    {
        $u = $this->store(['closed_weekdays' => [3]]);
        $slot = $this->slot($u);
        $m = $this->member($u, ['name' => '佐藤']);

        $this->get("/s/{$m->token}")->assertOk()->assertSee('2026年12月の希望');

        $this->post("/s/{$m->token}", [
            'month' => '2026-12',
            'avail' => ['2026-12-01|'.$slot->id, '2026-12-02|'.$slot->id, '2026-11-30|'.$slot->id, '2026-12-04|999'],
            'note' => '週末多めで',
        ])->assertRedirect();

        // 12/2は水曜(定休日)、11/30は別の月、999はない時間帯なので入らない
        $this->assertSame(['2026-12-01'], Availability::pluck('date')->all());
        $this->assertDatabaseHas('submissions', ['staff_id' => $m->id, 'year_month' => '2026-12', 'note' => '週末多めで']);

        // 送り直すと置きかわる(全部はずしても「提出済み」)
        $this->post("/s/{$m->token}", ['month' => '2026-12']);
        $this->assertSame(0, Availability::count());
        $this->assertSame(1, Submission::count());
    }

    public function test_staff_page_never_shows_manager_menu(): void
    {
        $u = $this->store();
        $m = $this->member($u);

        // スタッフ本人のスマホ(ログインしていない):店長の画面には入れない
        $this->get("/s/{$m->token}")->assertOk()->assertDontSee('ログアウト');
        $this->get('/shifts')->assertRedirect('/login');

        // 同じブラウザで店長がログインしていても、スタッフ用ページには店長のメニューを出さない
        $this->actingAs($u)->get("/s/{$m->token}")->assertOk()
            ->assertDontSee('ログアウト')->assertDontSee(route('shifts.index'));
    }

    public function test_portal_rejects_invalid_access(): void
    {
        $u = $this->store();
        $m = $this->member($u, ['is_active' => false]);
        $this->get("/s/{$m->token}")->assertNotFound();
        $this->get('/s/nonexistent-token')->assertNotFound();

        $active = $this->member($u);
        $this->post("/s/{$active->token}", ['month' => '2027-08'])->assertNotFound(); // 遠すぎる月
    }

    public function test_cannot_change_wishes_after_publish(): void
    {
        $u = $this->store();
        $slot = $this->slot($u);
        $m = $this->member($u);
        $u->scheduleFor('2026-12')->update(['published_at' => now()]);

        $this->post("/s/{$m->token}", ['month' => '2026-12', 'avail' => ['2026-12-01|'.$slot->id]])
            ->assertSessionHas('status');
        $this->assertSame(0, Availability::count());
    }

    public function test_planner_fills_slots_from_wishes_fairly(): void
    {
        $u = $this->store();
        $slot = $this->slot($u, ['weekday_required' => 1, 'weekend_required' => 1]);
        $a = $this->member($u, ['name' => 'A']);
        $b = $this->member($u, ['name' => 'B']);
        $c = $this->member($u, ['name' => 'C']); // 希望なし
        $days = ['2026-12-01', '2026-12-02', '2026-12-03', '2026-12-04'];
        $this->avail($a, $slot, $days);
        $this->avail($b, $slot, $days);

        $result = app(ShiftPlanner::class)->plan($u, '2026-12');

        $this->assertSame(4, Assignment::whereIn('date', $days)->count());
        // 偏りなく2回ずつ
        $this->assertSame(2, Assignment::where('staff_id', $a->id)->count());
        $this->assertSame(2, Assignment::where('staff_id', $b->id)->count());
        $this->assertSame(0, Assignment::where('staff_id', $c->id)->count());
        // 希望のない日は足りない枠として返す
        $this->assertSame(31 - 4, count($result['shortages']));
    }

    public function test_planner_respects_weekly_limit_and_overlap(): void
    {
        $u = $this->store();
        $lunch = $this->slot($u, ['name' => 'ランチ', 'start_time' => '11:00', 'end_time' => '15:00']);
        $long = $this->slot($u, ['name' => '通し', 'start_time' => '13:00', 'end_time' => '20:00']);
        $m = $this->member($u, ['max_days_per_week' => 2]);

        // 12/7(月)〜12/13(日)の1週間、毎日両方の希望
        $week = ['2026-12-07', '2026-12-08', '2026-12-09', '2026-12-10', '2026-12-11', '2026-12-12', '2026-12-13'];
        $this->avail($m, $lunch, $week);
        $this->avail($m, $long, $week);

        app(ShiftPlanner::class)->plan($u, '2026-12');

        $dates = Assignment::where('staff_id', $m->id)->pluck('date')->unique();
        $this->assertCount(2, $dates); // 週2日まで
        foreach ($dates as $d) {
            // 時間が重なる2つの枠には同時に入らない
            $this->assertSame(1, Assignment::where('staff_id', $m->id)->where('date', $d)->count());
        }
    }

    public function test_planner_fill_mode_keeps_manual_edits_and_rebuild_replaces(): void
    {
        $u = $this->store();
        $slot = $this->slot($u, ['weekday_required' => 2, 'weekend_required' => 2]);
        $a = $this->member($u);
        $b = $this->member($u);
        $manual = $this->member($u);
        $this->avail($a, $slot, ['2026-12-01']);
        $this->avail($b, $slot, ['2026-12-01']);
        Assignment::create(['staff_id' => $manual->id, 'time_slot_id' => $slot->id, 'date' => '2026-12-01']);

        app(ShiftPlanner::class)->plan($u, '2026-12');
        $onFirst = Assignment::where('date', '2026-12-01')->pluck('staff_id');
        $this->assertCount(2, $onFirst);
        $this->assertTrue($onFirst->contains($manual->id)); // 手で入れた人は残る

        app(ShiftPlanner::class)->plan($u, '2026-12', rebuild: true);
        $onFirst = Assignment::where('date', '2026-12-01')->pluck('staff_id')->sort()->values();
        $this->assertEquals(collect([$a->id, $b->id])->sort()->values(), $onFirst); // 希望の2人で作り直し
    }

    public function test_planner_skips_closed_days_and_inactive_staff(): void
    {
        $u = $this->store(['closed_weekdays' => [2]]); // 火曜定休
        $slot = $this->slot($u);
        $off = $this->member($u, ['is_active' => false]);
        $on = $this->member($u);
        $this->avail($off, $slot, ['2026-12-03']);
        $this->avail($on, $slot, ['2026-12-01', '2026-12-03']);

        app(ShiftPlanner::class)->plan($u, '2026-12');

        $this->assertSame(['2026-12-03'], Assignment::pluck('date')->all()); // 12/1は火曜で休み
        $this->assertSame($on->id, (int) Assignment::first()->staff_id);
    }

    public function test_generate_edit_publish_flow(): void
    {
        $u = $this->store();
        $slot = $this->slot($u);
        $m = $this->member($u, ['name' => '鈴木']);
        $this->avail($m, $slot, ['2026-12-05']);

        $this->actingAs($u)->post('/shifts/generate', ['month' => '2026-12', 'mode' => 'fill'])
            ->assertRedirect('/shifts?month=2026-12')->assertSessionHas('status');
        $this->get('/shifts?month=2026-12')->assertOk()->assertSee('鈴木')->assertSee('12/5(土)');
        $this->get('/shifts/day/2026-12-05')->assertOk()->assertSee('希望○');
        $this->get('/shifts/table?month=2026-12')->assertOk()->assertSee('鈴木')->assertSee('ランチ(11:00〜15:00)');

        // 公開前はスタッフのページに出ない
        $this->get("/s/{$m->token}")->assertSee('シフトが決まると、ここに表示されます');

        $this->post('/shifts/publish', ['month' => '2026-12', 'publish' => 1]);
        $this->assertTrue($u->scheduleFor('2026-12')->isPublished());
        $this->get("/s/{$m->token}")->assertDontSee('シフトが決まると、ここに表示されます')
            ->assertSee('のシフトは決まりました')->assertSee('ランチ 11:00〜15:00');

        // 1日分を手で直す
        $this->put('/shifts/day/2026-12-05', ['shifts' => []])->assertRedirect('/shifts?month=2026-12');
        $this->assertSame(0, Assignment::count());

        $this->post('/shifts/publish', ['month' => '2026-12', 'publish' => 0]);
        $this->assertFalse($u->scheduleFor('2026-12')->isPublished());
    }

    public function test_deadline_and_invalid_dates(): void
    {
        $u = $this->store();
        $this->actingAs($u)->put('/shifts/deadline', ['month' => '2026-12', 'deadline' => '2026-11-20']);
        $this->assertSame('2026-11-20', $u->scheduleFor('2026-12')->deadline->toDateString());

        $this->get('/shifts/day/not-a-date')->assertNotFound();
        $this->get('/shifts?month=2026-13')->assertOk(); // おかしな月は来月として開く
    }

    public function test_settings_update_closed_days(): void
    {
        $u = $this->store();
        $this->actingAs($u)->put('/settings', ['name' => '新しい店名', 'email' => $u->email, 'closed_weekdays' => ['1', '3']])
            ->assertRedirect('/settings');
        $u->refresh();
        $this->assertSame('新しい店名', $u->name);
        $this->assertSame([1, 3], $u->closed_weekdays);
        $this->assertSame('月・水曜', $u->closed_label);
    }

    public function test_demo_seed_renders_every_page(): void
    {
        $this->seed();
        $u = User::where('email', 'demo@example.com')->firstOrFail();

        $this->assertTrue($u->scheduleFor('2026-11')->isPublished());
        $this->assertGreaterThan(0, Assignment::count());
        $this->assertSame(8, Submission::where('year_month', '2026-12')->count());

        $this->actingAs($u);
        foreach (['/', '/shifts', '/shifts?month=2026-11', '/shifts/table?month=2026-11', '/shifts/day/2026-11-10', '/staff', '/staff/create', '/slots', '/slots/create', '/settings', '/leaves', '/leaves?tab=all'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/s/'.$u->staff()->first()->token)->assertOk()->assertSee('これからのシフト');

        // デモの来月分を自動で作れる
        $this->post('/shifts/generate', ['month' => '2026-12'])->assertSessionHas('status');
        $this->assertGreaterThan(0, Assignment::where('date', '>=', '2026-12-01')->count());
    }
}
