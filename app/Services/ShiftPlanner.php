<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * シフトの下書きを自動で作る
 *
 * 考え方(店長が手で組むときと同じ順番):
 *  1. 人が集まりにくい枠(候補が少ない日・時間帯)から先に埋める
 *  2. 候補の中では、その月にまだ入っていない人を優先する(偏りを減らす)
 *  3. 「週◯日まで」の上限と、同じ日に時間が重なる枠には入れない
 *  5. 「キッチン最低1人」のような担当の条件があれば、当てはまる人から先に入れる
 *  4. 希望を出していない日と、有給が承認された日には入れない
 */
class ShiftPlanner
{
    /**
     * @param  bool  $rebuild  true なら今のシフトを消して作り直す。false なら空いている枠だけ埋める
     * @return array{added: int, shortages: list<array{date: string, slot: string, missing: int}>}
     */
    public function plan(User $user, string $ym, bool $rebuild = false): array
    {
        return DB::transaction(function () use ($user, $ym, $rebuild) {
            $slots = $user->timeSlots()->get()->keyBy('id');
            $staff = $user->staff()->active()->get()->keyBy('id');
            $days = Shift::days($ym, $user);

            if ($slots->isEmpty() || empty($days)) {
                return ['added' => 0, 'shortages' => []];
            }

            $from = $days[0]->toDateString();
            $to = end($days)->toDateString();

            if ($rebuild) {
                Assignment::whereIn('time_slot_id', $slots->keys())->whereBetween('date', [$from, $to])->delete();
            }

            // 週の上限を正しく数えるため、月をまたぐ週の分(前後の月)も読み込む
            $weekFrom = $days[0]->startOfWeek(CarbonInterface::MONDAY)->toDateString();
            $weekTo = end($days)->endOfWeek(CarbonInterface::SUNDAY)->toDateString();
            $assigned = Assignment::whereIn('time_slot_id', $slots->keys())
                ->whereBetween('date', [$weekFrom, $weekTo])
                ->get(['staff_id', 'time_slot_id', 'date']);

            // 誰がどの日のどの枠に入っているか
            $cell = [];      // "date|slot" => [staffId => true]
            $byDay = [];     // "staff|date" => [slotId, ...]
            $monthCount = $staff->map(fn () => 0)->all(); // その月に入っている回数
            foreach ($assigned as $a) {
                $cell[$a->date.'|'.$a->time_slot_id][$a->staff_id] = true;
                $byDay[$a->staff_id.'|'.$a->date][] = $a->time_slot_id;
                if ($a->date >= $from && $a->date <= $to && isset($monthCount[$a->staff_id])) {
                    $monthCount[$a->staff_id]++;
                }
            }

            // 希望(入れる日と時間帯)
            $avail = [];     // "date|slot" => [staffId, ...]
            $offered = $staff->map(fn () => 0)->all();
            Availability::whereIn('staff_id', $staff->keys())
                ->whereIn('time_slot_id', $slots->keys())
                ->whereBetween('date', [$from, $to])
                ->get(['staff_id', 'time_slot_id', 'date'])
                ->each(function ($a) use (&$avail, &$offered) {
                    $avail[$a->date.'|'.$a->time_slot_id][] = $a->staff_id;
                    $offered[$a->staff_id]++;
                });

            // 有給が承認された日("staff|date" => true)
            $onLeave = LeaveRequest::whereIn('staff_id', $staff->keys())->status('approved')
                ->whereBetween('date', [$from, $to])->get(['staff_id', 'date'])
                ->mapWithKeys(fn ($l) => [$l->staff_id.'|'.$l->date => true])->all();

            // 埋める枠の一覧
            $cells = [];
            foreach ($days as $day) {
                foreach ($slots as $slot) {
                    $key = $day->toDateString().'|'.$slot->id;
                    $need = $slot->requiredOn($day) - count($cell[$key] ?? []);
                    if ($need > 0) {
                        $cells[] = ['day' => $day, 'slot' => $slot, 'key' => $key, 'need' => $need,
                            'room' => count($avail[$key] ?? []) - $need];
                    }
                }
            }
            // 候補に余裕がない枠から先に(同じなら日付順)
            usort($cells, fn ($a, $b) => [$a['room'], $a['key']] <=> [$b['room'], $b['key']]);

            $added = 0;
            $shortages = [];
            $rows = [];
            foreach ($cells as $c) {
                $date = $c['day']->toDateString();
                $weekKeyFrom = $c['day']->startOfWeek(CarbonInterface::MONDAY)->toDateString();
                $weekKeyTo = $c['day']->endOfWeek(CarbonInterface::SUNDAY)->toDateString();

                $candidates = collect($avail[$c['key']] ?? [])
                    ->filter(fn ($id) => $staff->has($id) && empty($cell[$c['key']][$id]) && empty($onLeave[$id.'|'.$date]))
                    ->filter(function ($id) use ($byDay, $date, $slots, $c) {
                        // 同じ日に時間が重なる枠に入っていないか
                        foreach ($byDay[$id.'|'.$date] ?? [] as $otherSlotId) {
                            if ($slots[$otherSlotId]->overlaps($c['slot'])) {
                                return false;
                            }
                        }

                        return true;
                    })
                    ->filter(function ($id) use ($staff, $byDay, $date, $weekKeyFrom, $weekKeyTo) {
                        // 週の上限(その日すでに入っていれば日数は増えない)
                        $max = $staff[$id]->max_days_per_week;
                        if (! $max || ! empty($byDay[$id.'|'.$date])) {
                            return true;
                        }

                        return $this->daysInWeek($byDay, $id, $weekKeyFrom, $weekKeyTo) < $max;
                    })
                    ->sortBy(fn ($id) => [$monthCount[$id], $offered[$id], $id])
                    ->values();

                // 担当の条件を満たす人を先に選び、残りをふつうの順番で埋める
                $picked = [];
                $inCell = array_keys($cell[$c['key']] ?? []);
                foreach ($c['slot']->rules() as $rule) {
                    $have = collect(array_merge($inCell, $picked))
                        ->filter(fn ($id) => TimeSlot::matches($staff[$id]->position ?? null, $rule['position']))->count();
                    $want = $rule['min'] - $have;
                    if ($want <= 0) {
                        continue;
                    }
                    $more = $candidates->reject(fn ($id) => in_array($id, $picked, true))
                        ->filter(fn ($id) => TimeSlot::matches($staff[$id]->position, $rule['position']))
                        ->take(min($want, $c['need'] - count($picked)));
                    $picked = array_merge($picked, $more->all());
                }
                foreach ($candidates as $id) {
                    if (count($picked) >= $c['need']) {
                        break;
                    }
                    if (! in_array($id, $picked, true)) {
                        $picked[] = $id;
                    }
                }

                foreach ($picked as $id) {
                    $cell[$c['key']][$id] = true;
                    $byDay[$id.'|'.$date][] = $c['slot']->id;
                    $monthCount[$id]++;
                    $rows[] = ['staff_id' => $id, 'time_slot_id' => $c['slot']->id, 'date' => $date,
                        'created_at' => now(), 'updated_at' => now()];
                    $added++;
                }

                $missing = $c['need'] - count($picked);
                if ($missing > 0) {
                    $shortages[] = ['date' => $date, 'slot' => $c['slot']->name, 'missing' => $missing];
                }
                // 担当の条件(誰もいない枠は「あと◯人」だけで十分なので、1人以上いるときに知らせる)
                $people = collect(array_merge($inCell, $picked))->map(fn ($id) => $staff[$id] ?? null);
                foreach ($people->filter()->isEmpty() ? [] : $c['slot']->ruleShortfalls($people) as $r) {
                    $shortages[] = ['date' => $date, 'slot' => $c['slot']->name.'の'.$r['position'], 'missing' => $r['min'] - $r['have']];
                }
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                Assignment::insert($chunk);
            }

            usort($shortages, fn ($a, $b) => [$a['date'], $a['slot']] <=> [$b['date'], $b['slot']]);

            return ['added' => $added, 'shortages' => $shortages];
        });
    }

    /** その週に何日入っているか */
    private function daysInWeek(array $byDay, int $staffId, string $from, string $to): int
    {
        $n = 0;
        foreach ($byDay as $key => $slotIds) {
            [$id, $date] = explode('|', $key);
            if ((int) $id === $staffId && $date >= $from && $date <= $to && $slotIds) {
                $n++;
            }
        }

        return $n;
    }
}
