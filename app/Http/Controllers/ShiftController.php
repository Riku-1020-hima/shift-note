<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\Submission;
use App\Services\ShiftPlanner;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    /** 開いている月(指定がなければ前回の月、それもなければ来月) */
    private function month(Request $request): string
    {
        $ym = Shift::validMonth($request->query('month'))
            ?? Shift::validMonth($request->input('month'))
            ?? Shift::validMonth($request->session()->get('shift_month'))
            ?? now()->addMonthNoOverflow()->format('Y-m');
        $request->session()->put('shift_month', $ym);

        return $ym;
    }

    /** シフト表(日付 × 時間帯) */
    public function index(Request $request): View
    {
        $user = $request->user();
        $ym = $this->month($request);
        $slots = $user->timeSlots()->get();
        $days = Shift::days($ym, $user);
        $staffAll = $user->staff()->orderBy('id')->get()->keyBy('id');
        $active = $staffAll->where('is_active', true);
        [$from, $to] = [Shift::firstDay($ym)->toDateString(), Shift::firstDay($ym)->endOfMonth()->toDateString()];

        $assigned = Assignment::whereIn('time_slot_id', $slots->pluck('id'))->whereBetween('date', [$from, $to])
            ->get()->groupBy(fn ($a) => $a->date.'|'.$a->time_slot_id);
        $availCount = Availability::whereIn('staff_id', $active->keys())->whereIn('time_slot_id', $slots->pluck('id'))
            ->whereBetween('date', [$from, $to])->get()->countBy(fn ($a) => $a->date.'|'.$a->time_slot_id);
        $submitted = Submission::whereIn('staff_id', $active->keys())->where('year_month', $ym)->pluck('staff_id');
        $leaves = LeaveRequest::whereIn('staff_id', $staffAll->keys())->status('approved')->whereBetween('date', [$from, $to])
            ->get()->groupBy('date')->map(fn ($g) => $g->pluck('staff_id')->all());
        $pendingLeaves = LeaveRequest::whereIn('staff_id', $staffAll->keys())->status('pending')->count();

        $shortage = 0;
        foreach ($days as $d) {
            foreach ($slots as $s) {
                $shortage += max(0, $s->requiredOn($d) - count($assigned[$d->toDateString().'|'.$s->id] ?? []));
            }
        }

        return view('shifts.index', [
            'ym' => $ym,
            'prev' => Shift::firstDay($ym)->subMonth()->format('Y-m'),
            'next' => Shift::firstDay($ym)->addMonth()->format('Y-m'),
            'schedule' => $user->scheduleFor($ym),
            'slots' => $slots,
            'days' => $days,
            'staff' => $staffAll,
            'assigned' => $assigned,
            'availCount' => $availCount,
            'activeCount' => $active->count(),
            'notSubmitted' => $active->whereNotIn('id', $submitted)->values(),
            'shortage' => $shortage,
            'totalAssigned' => $assigned->sum(fn ($g) => $g->count()),
            'leaves' => $leaves,
            'pendingLeaves' => $pendingLeaves,
        ]);
    }

    /** 1日分を手で直す画面 */
    public function editDay(Request $request, string $date): View
    {
        $user = $request->user();
        $day = $this->parseDate($date);
        $slots = $user->timeSlots()->get();
        $staff = $user->staff()->active()->orderBy('id')->get();
        $ds = $day->toDateString();

        $assigned = Assignment::whereIn('time_slot_id', $slots->pluck('id'))->where('date', $ds)->get()
            ->groupBy('time_slot_id')->map(fn ($g) => $g->pluck('staff_id')->all());
        $avail = Availability::whereIn('staff_id', $staff->pluck('id'))->where('date', $ds)->get()
            ->groupBy('time_slot_id')->map(fn ($g) => $g->pluck('staff_id')->all());
        $submitted = Submission::whereIn('staff_id', $staff->pluck('id'))->where('year_month', $day->format('Y-m'))->pluck('staff_id')->all();
        $onLeave = LeaveRequest::whereIn('staff_id', $staff->pluck('id'))->status('approved')->where('date', $ds)->pluck('staff_id')->all();

        // 退職などで一覧にいない人が入っていたら、その人も表示する
        $extraIds = $assigned->flatten()->diff($staff->pluck('id'))->all();
        $extra = $user->staff()->whereIn('id', $extraIds)->get();

        return view('shifts.edit-day', [
            'day' => $day,
            'closed' => $user->isClosedOn($day),
            'slots' => $slots,
            'staff' => $staff->concat($extra),
            'assigned' => $assigned,
            'avail' => $avail,
            'submitted' => $submitted,
            'onLeave' => $onLeave,
            'prevDay' => $day->subDay()->toDateString(),
            'nextDay' => $day->addDay()->toDateString(),
        ]);
    }

    public function updateDay(Request $request, string $date): RedirectResponse
    {
        $user = $request->user();
        $day = $this->parseDate($date);
        $slotIds = $user->timeSlots()->pluck('id');
        $staffIds = $user->staff()->pluck('id');

        $data = $request->validate(['shifts' => ['nullable', 'array'], 'shifts.*' => ['array'], 'shifts.*.*' => ['integer']]);

        Assignment::whereIn('time_slot_id', $slotIds)->where('date', $day->toDateString())->delete();
        foreach ($data['shifts'] ?? [] as $slotId => $ids) {
            if (! $slotIds->contains((int) $slotId)) {
                continue;
            }
            foreach (array_unique($ids) as $id) {
                if ($staffIds->contains((int) $id)) {
                    Assignment::create(['staff_id' => $id, 'time_slot_id' => $slotId, 'date' => $day->toDateString()]);
                }
            }
        }

        return redirect()->route('shifts.index', ['month' => $day->format('Y-m')])
            ->with('status', Shift::label($day).'のシフトを保存しました。');
    }

    /** 自動で下書きを作る */
    public function generate(Request $request, ShiftPlanner $planner): RedirectResponse
    {
        $ym = $this->month($request);
        $user = $request->user();

        if ($user->timeSlots()->doesntExist()) {
            return back()->with('status', '先に「時間帯」を登録してください。');
        }

        $result = $planner->plan($user, $ym, $request->input('mode') === 'rebuild');

        $message = "自動で{$result['added']}枠を埋めました。";
        if ($result['shortages']) {
            $message .= ' 希望が足りず、'.count($result['shortages']).'か所で人が足りていません(赤い枠)。';
        } else {
            $message .= ' すべての枠が埋まりました。';
        }

        return redirect()->route('shifts.index', ['month' => $ym])->with('status', $message)
            ->with('shortages', array_slice($result['shortages'], 0, 8));
    }

    /** 確定してスタッフに公開する / 公開をやめる */
    public function publish(Request $request): RedirectResponse
    {
        $ym = $this->month($request);
        $schedule = $request->user()->scheduleFor($ym);
        $publish = $request->boolean('publish');
        $schedule->update(['published_at' => $publish ? now() : null]);

        return redirect()->route('shifts.index', ['month' => $ym])->with('status', $publish
            ? Shift::monthLabel($ym).'のシフトを公開しました。スタッフは自分のURLから確認できます。'
            : '公開をやめました。スタッフからは見えなくなります。');
    }

    /** 希望の締め切り日 */
    public function deadline(Request $request): RedirectResponse
    {
        $ym = $this->month($request);
        $data = $request->validate(['deadline' => ['nullable', 'date']]);
        $request->user()->scheduleFor($ym)->update(['deadline' => $data['deadline'] ?? null]);

        return redirect()->route('shifts.index', ['month' => $ym])->with('status', '締め切り日を保存しました。');
    }

    /** 印刷・掲示用(スタッフ × 日付) */
    public function table(Request $request): View
    {
        $user = $request->user();
        $ym = $this->month($request);
        $slots = $user->timeSlots()->get();
        $days = Shift::days($ym);
        [$from, $to] = [$days[0]->toDateString(), end($days)->toDateString()];

        $assigned = Assignment::whereIn('time_slot_id', $slots->pluck('id'))->whereBetween('date', [$from, $to])->get();
        $byStaff = $assigned->groupBy(fn ($a) => $a->staff_id.'|'.$a->date)->map(fn ($g) => $g->pluck('time_slot_id')->all());
        $staff = $user->staff()->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $assigned->pluck('staff_id')))
            ->orderBy('id')->get();

        $leaves = LeaveRequest::whereIn('staff_id', $staff->pluck('id'))->status('approved')->whereBetween('date', [$from, $to])
            ->get()->mapWithKeys(fn ($l) => [$l->staff_id.'|'.$l->date => true])->all();

        return view('shifts.table', compact('ym', 'slots', 'days', 'staff', 'byStaff', 'leaves') + [
            'counts' => $assigned->countBy('staff_id'),
            'closedDays' => collect($days)->filter(fn ($d) => $user->isClosedOn($d))->map->toDateString()->values()->all(),
        ]);
    }

    private function parseDate(string $date): CarbonImmutable
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date), 404);

        return CarbonImmutable::parse($date)->startOfDay();
    }
}
