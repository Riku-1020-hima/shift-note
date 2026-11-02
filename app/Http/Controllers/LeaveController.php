<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** 店長用:有給の申請一覧と承認 */
class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $staffIds = $user->staff()->pluck('id');
        $tab = in_array($request->query('tab'), ['pending', 'all'], true) ? $request->query('tab') : 'pending';

        $pending = LeaveRequest::with('staff')->whereIn('staff_id', $staffIds)->status('pending')->orderBy('date')->get();
        $history = $tab === 'all'
            ? LeaveRequest::with('staff')->whereIn('staff_id', $staffIds)->where('status', '!=', 'pending')
                ->orderByDesc('date')->limit(100)->get()
            : collect();
        $balances = $user->staff()->active()->orderBy('id')->get();

        return view('leaves.index', compact('tab', 'pending', 'history', 'balances'));
    }

    public function approve(Request $request, LeaveRequest $leave, LeaveService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $leave);

        try {
            $removed = $service->approve($leave, $this->reply($request));
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        $message = $leave->staff->name.'さんの'.Shift::label($leave->day).'の有給を承認しました。';
        if ($removed) {
            $message .= ' この日のシフトから外したので、人が足りないときはシフト表で確認してください。';
        }

        return back()->with('status', $message);
    }

    public function reject(Request $request, LeaveRequest $leave, LeaveService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $leave);

        try {
            $service->reject($leave, $this->reply($request));
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', $leave->staff->name.'さんの申請を却下しました。');
    }

    public function revoke(Request $request, LeaveRequest $leave, LeaveService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $leave);

        try {
            $service->revoke($leave, $this->reply($request));
        } catch (RuntimeException $e) {
            return back()->with('status', $e->getMessage());
        }

        return back()->with('status', '承認を取り消し、有給の残りを1日戻しました。必要ならシフトに入れ直してください。');
    }

    private function reply(Request $request): ?string
    {
        return $request->validate(['reply' => ['nullable', 'string', 'max:200']])['reply'] ?? null;
    }

    private function authorizeOwner(Request $request, LeaveRequest $leave): void
    {
        abort_unless((int) $leave->staff?->user_id === (int) $request->user()->id, 404);
    }
}
