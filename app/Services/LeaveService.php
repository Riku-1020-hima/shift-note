<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\LeaveRequest;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 有給の申請・承認と、残り日数の増減をまとめて扱う
 *  ・承認すると残りが1日減り、その日のシフトから外す
 *  ・承認したものを取り消すと、残りが1日戻る
 */
class LeaveService
{
    /** スタッフからの申請 */
    public function request(Staff $staff, string $date, ?string $note): LeaveRequest
    {
        return DB::transaction(function () use ($staff, $date, $note) {
            $staff = Staff::whereKey($staff->id)->lockForUpdate()->first();

            if ($staff->leaveRequests()->active()->where('date', $date)->exists()) {
                throw new RuntimeException('その日はもう申請しています。');
            }
            if ($staff->paid_leave_balance - $staff->pendingLeaveCount() < 1) {
                throw new RuntimeException('申請できる有給の残りがありません。お店に確認してください。');
            }

            return $staff->leaveRequests()->create(['date' => $date, 'note' => $note, 'status' => 'pending']);
        });
    }

    /** スタッフが承認待ちの申請を取り下げる */
    public function withdraw(LeaveRequest $leave): void
    {
        if ($leave->status !== 'pending') {
            throw new RuntimeException('承認待ちの申請だけ取り下げられます。');
        }
        $leave->update(['status' => 'cancelled', 'decided_at' => now()]);
    }

    /** 承認:残りを1日減らし、その日のシフトから外す。外したシフトの数を返す */
    public function approve(LeaveRequest $leave, ?string $reply = null): int
    {
        return DB::transaction(function () use ($leave, $reply) {
            $staff = Staff::whereKey($leave->staff_id)->lockForUpdate()->first();
            if ($leave->fresh()->status !== 'pending') {
                throw new RuntimeException('この申請はもう処理されています。');
            }
            if ($staff->paid_leave_balance < 1) {
                throw new RuntimeException($staff->name.'さんの有給の残りが0日です。スタッフの編集で日数を直してから承認してください。');
            }

            $staff->decrement('paid_leave_balance');
            $leave->update(['status' => 'approved', 'reply' => $reply, 'decided_at' => now()]);

            return Assignment::where('staff_id', $staff->id)->where('date', $leave->date)->delete();
        });
    }

    /** 却下 */
    public function reject(LeaveRequest $leave, ?string $reply = null): void
    {
        if ($leave->status !== 'pending') {
            throw new RuntimeException('この申請はもう処理されています。');
        }
        $leave->update(['status' => 'rejected', 'reply' => $reply, 'decided_at' => now()]);
    }

    /** 承認した有給を取り消す(残りを1日戻す) */
    public function revoke(LeaveRequest $leave, ?string $reply = null): void
    {
        DB::transaction(function () use ($leave, $reply) {
            if ($leave->fresh()->status !== 'approved') {
                throw new RuntimeException('承認した申請だけ取り消せます。');
            }
            Staff::whereKey($leave->staff_id)->increment('paid_leave_balance');
            $leave->update(['status' => 'cancelled', 'reply' => $reply ?? $leave->reply, 'decided_at' => now()]);
        });
    }
}
