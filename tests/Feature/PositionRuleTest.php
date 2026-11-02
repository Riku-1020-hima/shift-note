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

class PositionRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $store;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 10:00:00');
        $this->store = User::create(['name' => 'テスト食堂', 'email' => 'rule@example.com', 'password' => 'password']);
    }

    private function member(string $name, ?string $position): Staff
    {
        return $this->store->staff()->create(['name' => $name, 'position' => $position, 'is_active' => true]);
    }

    private function wants(Staff $m, TimeSlot $slot, string $date = '2026-12-01'): void
    {
        Availability::create(['staff_id' => $m->id, 'time_slot_id' => $slot->id, 'date' => $date]);
    }

    public function test_position_matching(): void
    {
        $this->assertTrue(TimeSlot::matches('ホール・キッチン', 'キッチン'));
        $this->assertTrue(TimeSlot::matches('キッチン', 'キッチン'));
        $this->assertFalse(TimeSlot::matches('ホール', 'キッチン'));
        $this->assertFalse(TimeSlot::matches(null, 'キッチン'));
    }

    public function test_planner_puts_required_position_first(): void
    {
        $slot = $this->store->timeSlots()->create(['name' => 'ランチ', 'start_time' => '11:00', 'end_time' => '15:00',
            'weekday_required' => 2, 'weekend_required' => 2, 'position_rules' => [['position' => 'キッチン', 'min' => 1]]]);
        // ふつうの順番ならホールの2人が先に選ばれる(id が小さい)
        $hallA = $this->member('ホールA', 'ホール');
        $hallB = $this->member('ホールB', 'ホール');
        $kitchen = $this->member('キッチンK', 'キッチン');
        foreach ([$hallA, $hallB, $kitchen] as $m) {
            $this->wants($m, $slot);
        }

        app(ShiftPlanner::class)->plan($this->store, '2026-12');

        $ids = Assignment::where('date', '2026-12-01')->pluck('staff_id')->map(fn ($id) => (int) $id);
        $this->assertCount(2, $ids);
        $this->assertTrue($ids->contains($kitchen->id));
    }

    public function test_planner_reports_missing_position(): void
    {
        $slot = $this->store->timeSlots()->create(['name' => 'ランチ', 'start_time' => '11:00', 'end_time' => '15:00',
            'weekday_required' => 1, 'weekend_required' => 1, 'position_rules' => [['position' => 'キッチン', 'min' => 1]]]);
        $this->wants($this->member('ホールA', 'ホール'), $slot);

        $result = app(ShiftPlanner::class)->plan($this->store, '2026-12');

        $this->assertSame(1, Assignment::count()); // 人数は埋める
        $this->assertContains(['date' => '2026-12-01', 'slot' => 'ランチのキッチン', 'missing' => 1], $result['shortages']);

        // シフト表にも「キッチンがいません」と出る
        $this->actingAs($this->store)->get('/shifts?month=2026-12')->assertSee('キッチンがいません');
    }

    public function test_slot_form_saves_rules(): void
    {
        $this->actingAs($this->store)->post('/slots', [
            'name' => 'ディナー', 'start_time' => '17:00', 'end_time' => '22:00', 'weekday_required' => 3, 'weekend_required' => 4,
            'rules' => [
                ['position' => 'キッチン', 'min' => 1],
                ['position' => '', 'min' => 2],         // 空の行は無視
                ['position' => 'ホール', 'min' => 2],
            ],
        ])->assertRedirect('/slots');

        $slot = $this->store->timeSlots()->first();
        $this->assertSame([['position' => 'キッチン', 'min' => 1], ['position' => 'ホール', 'min' => 2]], $slot->rules());
        $this->get('/slots')->assertSee('キッチン1人以上・ホール2人以上');
        $this->get("/slots/{$slot->id}/edit")->assertOk()->assertSee('value="ホール"', false);
    }

    public function test_reminder_text_lists_names_without_private_urls(): void
    {
        $this->store->timeSlots()->create(['name' => 'ランチ', 'start_time' => '11:00', 'end_time' => '15:00', 'weekday_required' => 1, 'weekend_required' => 1]);
        $done = $this->member('提出ずみ', 'ホール');
        $late = $this->member('まだの人', 'ホール');
        Submission::create(['staff_id' => $done->id, 'year_month' => '2026-12', 'submitted_at' => now()]);
        $this->store->scheduleFor('2026-12')->update(['deadline' => '2026-11-20']);

        $html = $this->actingAs($this->store)->get('/shifts?month=2026-12')->assertSee('催促文をコピー')->getContent();
        preg_match('/data-copy="([^"]*【2026年12月のシフト希望】[^"]*)"/u', $html, $m);
        $text = html_entity_decode($m[1] ?? '');

        $this->assertStringContainsString('まだの人さん', $text);
        $this->assertStringNotContainsString('提出ずみ', $text);
        $this->assertStringContainsString('11/20(金)までに', $text);
        $this->assertStringNotContainsString($late->token, $text); // 専用URLはグループに流さない

        $this->get('/staff')->assertSee('まだの1人への催促文をコピー');
    }
}
