<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Availability;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\Submission;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

/**
 * スタッフ用のページ(ログイン不要)
 * 一人ひとりに配る長いURL(トークン)で本人を見分ける
 */
class PortalController extends Controller
{
    /** 希望を出せる月:今月〜2か月先 */
    private function months(): array
    {
        $first = now()->startOfMonth();

        return [$first->format('Y-m'), $first->copy()->addMonthNoOverflow()->format('Y-m'), $first->copy()->addMonthsNoOverflow(2)->format('Y-m')];
    }

    private function staffFor(string $token): Staff
    {
        $staff = Staff::with('user')->where('token', $token)->first();
        abort_unless($staff && $staff->is_active, 404);

        return $staff;
    }

    public function show(Request $request, string $token): View
    {
        $staff = $this->staffFor($token);
        $user = $staff->user;
        $months = $this->months();
        $ym = in_array($request->query('month'), $months, true) ? $request->query('month') : $months[1];

        $schedule = $user->scheduleFor($ym);
        $slots = $user->timeSlots()->get();
        $days = Shift::days($ym, $user);
        [$from, $to] = [Shift::firstDay($ym)->toDateString(), Shift::firstDay($ym)->endOfMonth()->toDateString()];

        $mine = Availability::where('staff_id', $staff->id)->whereBetween('date', [$from, $to])->get()
            ->map(fn ($a) => $a->date.'|'.$a->time_slot_id)->all();
        $submission = Submission::where('staff_id', $staff->id)->where('year_month', $ym)->first();

        // 公開されている月の、自分のシフト
        $published = $user->schedules()->whereIn('year_month', $months)->whereNotNull('published_at')->pluck('year_month')->all();
        $myShifts = collect();
        if ($published) {
            $myShifts = Assignment::with('timeSlot')->where('staff_id', $staff->id)
                ->where('date', '>=', now()->toDateString())
                ->where(function ($q) use ($published) {
                    foreach ($published as $p) {
                        $q->orWhereBetween('date', [Shift::firstDay($p)->toDateString(), Shift::firstDay($p)->endOfMonth()->toDateString()]);
                    }
                })
                ->orderBy('date')->get()
                ->sortBy(fn ($a) => $a->date.' '.$a->timeSlot->start)->values();
        }

        // 有給:最近と今後の申請、承認された日
        $leaves = $staff->leaveRequests()->where('date', '>=', now()->subMonth()->toDateString())
            ->orderByDesc('date')->limit(20)->get();
        $leaveDates = $leaves->where('status', 'approved')->pluck('date')->all();
        $pendingLeaves = $leaves->where('status', 'pending')->count();

        return view('portal.show', compact('staff', 'user', 'months', 'ym', 'schedule', 'slots', 'days', 'mine', 'submission', 'myShifts', 'published', 'leaves', 'leaveDates', 'pendingLeaves'));
    }

    /** 有給を申請する */
    public function requestLeave(Request $request, string $token, LeaveService $service): RedirectResponse
    {
        $staff = $this->staffFor($token);
        $data = $request->validate([
            'leave_date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addMonthsNoOverflow(6)->toDateString()],
            'leave_note' => ['nullable', 'string', 'max:200'],
        ], [
            'leave_date.after_or_equal' => '今日より後の日を選んでください。',
            'leave_date.before_or_equal' => '申請できるのは6か月先までです。',
        ], ['leave_date' => '日付']);

        try {
            $leave = $service->request($staff, \Carbon\CarbonImmutable::parse($data['leave_date'])->toDateString(), $data['leave_note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('leave_error', $e->getMessage());
        }

        return redirect(route('portal.show', $staff->token).'#leave')
            ->with('status', Shift::label($leave->day).'の有給を申請しました。お店の返事を待ってください。');
    }

    /** 承認待ちの有給を取り下げる */
    public function withdrawLeave(Request $request, string $token, LeaveRequest $leave, LeaveService $service): RedirectResponse
    {
        $staff = $this->staffFor($token);
        abort_unless((int) $leave->staff_id === (int) $staff->id, 404);

        try {
            $service->withdraw($leave);
        } catch (RuntimeException $e) {
            return back()->with('leave_error', $e->getMessage());
        }

        return redirect(route('portal.show', $staff->token).'#leave')->with('status', '申請を取り下げました。');
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $staff = $this->staffFor($token);
        $user = $staff->user;
        $ym = $request->input('month');
        abort_unless(in_array($ym, $this->months(), true), 404);

        if ($user->scheduleFor($ym)->isPublished()) {
            return back()->with('status', 'この月のシフトはもう決まっているため、希望は変えられません。お店に直接ご相談ください。');
        }

        $data = $request->validate([
            'avail' => ['nullable', 'array'],
            'avail.*' => ['string'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        $slotIds = $user->timeSlots()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $validDays = collect(Shift::days($ym, $user))->map->toDateString()->all();
        [$from, $to] = [Shift::firstDay($ym)->toDateString(), Shift::firstDay($ym)->endOfMonth()->toDateString()];

        DB::transaction(function () use ($staff, $data, $slotIds, $validDays, $from, $to, $ym) {
            Availability::where('staff_id', $staff->id)->whereBetween('date', [$from, $to])->delete();
            $rows = [];
            foreach (array_unique($data['avail'] ?? []) as $value) {
                [$date, $slotId] = array_pad(explode('|', $value, 2), 2, null);
                if (in_array($date, $validDays, true) && in_array((int) $slotId, $slotIds, true)) {
                    $rows[] = ['staff_id' => $staff->id, 'time_slot_id' => (int) $slotId, 'date' => $date,
                        'created_at' => now(), 'updated_at' => now()];
                }
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                Availability::insert($chunk);
            }
            Submission::updateOrCreate(
                ['staff_id' => $staff->id, 'year_month' => $ym],
                ['note' => $data['note'] ?? null, 'submitted_at' => now()],
            );
        });

        return redirect()->route('portal.show', ['token' => $staff->token, 'month' => $ym])
            ->with('status', Shift::monthLabel($ym).'の希望を送りました。ありがとうございます！あとから直すこともできます。');
    }
}
