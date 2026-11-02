<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $slots = $user->timeSlots()->get();
        $activeStaff = $user->staff()->active()->orderBy('id')->get();
        $today = now()->toImmutable()->startOfDay();

        // 今日のシフト(公開前でも店長には見せる)
        $todayShifts = Assignment::with('staff')->whereIn('time_slot_id', $slots->pluck('id'))
            ->where('date', $today->toDateString())->get()->groupBy('time_slot_id');

        // 来月の準備
        $nextYm = $today->addMonthNoOverflow()->format('Y-m');
        $next = $user->scheduleFor($nextYm);
        $submittedIds = Submission::whereIn('staff_id', $activeStaff->pluck('id'))->where('year_month', $nextYm)->pluck('staff_id');
        $nextDays = Shift::days($nextYm, $user);
        $nextAssigned = $nextDays ? Assignment::whereIn('time_slot_id', $slots->pluck('id'))
            ->whereBetween('date', [$nextDays[0]->toDateString(), end($nextDays)->toDateString()])
            ->get()->countBy(fn ($a) => $a->date.'|'.$a->time_slot_id) : collect();
        $nextNeed = 0;
        $nextShort = 0;
        foreach ($nextDays as $d) {
            foreach ($slots as $s) {
                $need = $s->requiredOn($d);
                $nextNeed += $need;
                $nextShort += max(0, $need - ($nextAssigned[$d->toDateString().'|'.$s->id] ?? 0));
            }
        }

        // 最初にやること
        $setup = [
            ['done' => $slots->isNotEmpty(), 'label' => '時間帯(ランチ・ディナーなど)と必要な人数を登録する', 'url' => route('slots.create')],
            ['done' => $activeStaff->isNotEmpty(), 'label' => 'スタッフを登録する', 'url' => route('staff.create')],
            ['done' => $submittedIds->isNotEmpty(), 'label' => 'スタッフに希望を出すURLを送る', 'url' => route('staff.index')],
            ['done' => $nextAssigned->isNotEmpty(), 'label' => '「自動で作る」でシフトの下書きを作る', 'url' => route('shifts.index', ['month' => $nextYm])],
        ];

        return view('dashboard', [
            'today' => $today,
            'todayClosed' => $user->isClosedOn($today),
            'slots' => $slots,
            'todayShifts' => $todayShifts,
            'nextYm' => $nextYm,
            'next' => $next,
            'activeCount' => $activeStaff->count(),
            'submittedCount' => $submittedIds->count(),
            'notSubmitted' => $activeStaff->whereNotIn('id', $submittedIds)->values(),
            'nextNeed' => $nextNeed,
            'nextShort' => $nextShort,
            'nextFilled' => $nextNeed - $nextShort,
            'setup' => $setup,
            'setupDone' => collect($setup)->every('done'),
            'pendingLeaves' => LeaveRequest::with('staff')->whereIn('staff_id', $user->staff()->select('id'))->status('pending')->orderBy('date')->get(),
        ]);
    }
}
