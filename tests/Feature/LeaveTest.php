<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\LeaveRequest;
use App\Models\Staff;
use App\Models\User;
use App\Services\ShiftPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $store;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->store = User::create(['name' => 'テスト食堂', 'email' => 'leave@example.com', 'password' => 'password']);
    }

    private function member(int $balance = 3, array $attrs = []): Staff
    {
        return $this->store->staff()->create($attrs + ['name' => '佐藤', 'is_active' => true, 'paid_leave_balance' => $balance]);
    }

    public function test_staff_can_request_and_withdraw_leave(): void
    {
        $m = $this->member(2);

        $this->get("/s/{$m->token}")->assertOk()->assertSee('有給を申請する')->assertSee('残り');
        $this->post("/s/{$m->token}/leave", ['leave_date' => '2026-12-04', 'leave_note' => '通院'])->assertRedirect();

        $leave = LeaveRequest::firstOrFail();
        $this->assertSame('pending', $leave->status);
        $this->assertSame('2026-12-04', $leave->date);
        $this->assertSame(2, $m->fresh()->paid_leave_balance); // 承認されるまでは減らない

        // 同じ日は二重に申請できない
        $this->post("/s/{$m->token}/leave", ['leave_date' => '2026-12-04'])->assertSessionHas('leave_error');
        $this->assertSame(1, LeaveRequest::count());

        $this->post("/s/{$m->token}/leave/{$leave->id}/withdraw")->assertRedirect();
        $this->assertSame('cancelled', $leave->fresh()->status);
    }

    public function test_cannot_request_more_than_balance_or_past_dates(): void
    {
        $m = $this->member(1);
        $this->post("/s/{$m->token}/leave", ['leave_date' => '2026-11-01'])->assertSessionHasErrors('leave_date'); // 過去
        $this->post("/s/{$m->token}/leave", ['leave_date' => '2026-12-01']);
        $this->post("/s/{$m->token}/leave", ['leave_date' => '2026-12-02'])->assertSessionHas('leave_error'); // 承認待ち分で残りがない
        $this->assertSame(1, LeaveRequest::count());

        $zero = $this->member(0, ['name' => '新人']);
        $this->get("/s/{$zero->token}")->assertSee('申請できる有給の残りがありません');
    }

    public function test_approve_reduces_balance_and_removes_shift(): void
    {
        $m = $this->member(3);
        $slot = $this->store->timeSlots()->create(['name' => 'ランチ', 'start_time' => '11:00', 'end_time' => '15:00', 'weekday_required' => 1, 'weekend_required' => 1]);
        Assignment::create(['staff_id' => $m->id, 'time_slot_id' => $slot->id, 'date' => '2026-12-04']);
        $leave = LeaveRequest::create(['staff_id' => $m->id, 'date' => '2026-12-04', 'status' => 'pending']);

        $this->actingAs($this->store)->get('/leaves')->assertOk()->assertSee('佐藤')->assertSee('承認する');
        $this->get('/')->assertSee('承認待ちです');

        $this->post("/leaves/{$leave->id}/approve", ['reply' => 'OKです'])->assertSessionHas('status');
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame(2, $m->fresh()->paid_leave_balance);
        $this->assertSame(0, Assignment::count()); // その日のシフトから外れる

        // 二重に承認しても減らない
        $this->post("/leaves/{$leave->id}/approve");
        $this->assertSame(2, $m->fresh()->paid_leave_balance);

        // シフト表・印刷・本人のページに出る
        $this->get('/shifts?month=2026-12')->assertSee('有給:佐藤');
        $this->get('/shifts/table?month=2026-12')->assertSee('= 有給', false);
        $this->get("/s/{$m->token}")->assertSee('OKです');

        // 取り消すと残りが戻る
        $this->post("/leaves/{$leave->id}/revoke");
        $this->assertSame('cancelled', $leave->fresh()->status);
        $this->assertSame(3, $m->fresh()->paid_leave_balance);
    }

    public function test_reject_keeps_balance_and_approve_needs_balance(): void
    {
        $m = $this->member(1);
        $a = LeaveRequest::create(['staff_id' => $m->id, 'date' => '2026-12-04', 'status' => 'pending']);
        $this->actingAs($this->store)->post("/leaves/{$a->id}/reject", ['reply' => '貸切のため']);
        $this->assertSame('rejected', $a->fresh()->status);
        $this->assertSame(1, $m->fresh()->paid_leave_balance);

        $m->update(['paid_leave_balance' => 0]);
        $b = LeaveRequest::create(['staff_id' => $m->id, 'date' => '2026-12-05', 'status' => 'pending']);
        $this->post("/leaves/{$b->id}/approve")->assertSessionHas('status');
        $this->assertSame('pending', $b->fresh()->status); // 残り0日では承認できない
    }

    public function test_planner_skips_staff_on_leave(): void
    {
        $slot = $this->store->timeSlots()->create(['name' => 'ランチ', 'start_time' => '11:00', 'end_time' => '15:00', 'weekday_required' => 1, 'weekend_required' => 1]);
        $m = $this->member(3);
        foreach (['2026-12-03', '2026-12-04'] as $d) {
            Availability::create(['staff_id' => $m->id, 'time_slot_id' => $slot->id, 'date' => $d]);
        }
        LeaveRequest::create(['staff_id' => $m->id, 'date' => '2026-12-04', 'status' => 'approved']);
        LeaveRequest::create(['staff_id' => $m->id, 'date' => '2026-12-03', 'status' => 'rejected']);

        app(ShiftPlanner::class)->plan($this->store, '2026-12');

        $this->assertSame(['2026-12-03'], Assignment::pluck('date')->all());
    }

    public function test_other_stores_cannot_touch_leaves(): void
    {
        $other = User::create(['name' => '別の店', 'email' => 'other@example.com', 'password' => 'password']);
        $m = $this->member(3);
        $leave = LeaveRequest::create(['staff_id' => $m->id, 'date' => '2026-12-04', 'status' => 'pending']);

        $this->actingAs($other)->post("/leaves/{$leave->id}/approve")->assertNotFound();
        $this->assertSame('pending', $leave->fresh()->status);

        // 他のスタッフの申請は取り下げられない
        $another = $this->member(3, ['name' => '鈴木']);
        $this->post("/s/{$another->token}/leave/{$leave->id}/withdraw")->assertNotFound();
    }

    public function test_staff_form_saves_balance(): void
    {
        $this->actingAs($this->store)->post('/staff', ['name' => '田中', 'paid_leave_balance' => 10, 'is_active' => 1]);
        $this->assertSame(10, $this->store->staff()->first()->paid_leave_balance);
        $this->get('/staff')->assertSee('10日');
    }
}
