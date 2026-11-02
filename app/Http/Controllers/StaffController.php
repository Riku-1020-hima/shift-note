<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\Staff;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $ym = Shift::validMonth($request->session()->get('shift_month')) ?? now()->addMonthNoOverflow()->format('Y-m');
        $staff = $user->staff()->orderByDesc('is_active')->orderBy('id')->get();
        $submitted = Submission::whereIn('staff_id', $staff->pluck('id'))->where('year_month', $ym)->get()->keyBy('staff_id');
        $schedule = $user->scheduleFor($ym);

        $notSubmitted = $staff->where('is_active', true)->whereNotIn('id', $submitted->keys())->values();

        return view('staff.index', compact('staff', 'submitted', 'ym', 'schedule', 'notSubmitted'));
    }

    public function create(): View
    {
        return view('staff.form', ['member' => new Staff(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->staff()->create($this->validated($request));

        return redirect()->route('staff.index')->with('status', 'スタッフを登録しました。「URLをコピー」から、希望を出すページを本人に送ってください。');
    }

    public function edit(Request $request, Staff $staff): View
    {
        $this->authorizeOwner($request, $staff);

        return view('staff.form', ['member' => $staff]);
    }

    public function update(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorizeOwner($request, $staff);
        $staff->update($this->validated($request));

        return redirect()->route('staff.index')->with('status', $staff->name.'さんの情報を更新しました。');
    }

    public function destroy(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorizeOwner($request, $staff);
        $name = $staff->name;
        $staff->delete();

        return redirect()->route('staff.index')->with('status', $name.'さんを削除しました。');
    }

    /** URLを作り直す(前のURLは使えなくなる) */
    public function regenerateToken(Request $request, Staff $staff): RedirectResponse
    {
        $this->authorizeOwner($request, $staff);
        $staff->forceFill(['token' => Staff::newToken()])->save();

        return redirect()->route('staff.edit', $staff)->with('status', 'URLを作り直しました。前のURLはもう使えません。新しいURLを本人に送ってください。');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:30'],
            'max_days_per_week' => ['nullable', 'integer', 'min:1', 'max:7'],
            'paid_leave_balance' => ['nullable', 'integer', 'min:0', 'max:60'],
            'is_active' => ['nullable', 'boolean'],
            'memo' => ['nullable', 'string', 'max:500'],
        ], [], ['max_days_per_week' => '週の上限', 'paid_leave_balance' => '有給の残り日数']);
        $data['is_active'] = $request->boolean('is_active');
        $data['paid_leave_balance'] = (int) ($data['paid_leave_balance'] ?? 0);

        return $data;
    }

    private function authorizeOwner(Request $request, Staff $staff): void
    {
        abort_unless((int) $staff->user_id === (int) $request->user()->id, 404);
    }
}
