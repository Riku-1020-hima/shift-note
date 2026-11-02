<?php

namespace App\Http\Controllers;

use App\Models\TimeSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimeSlotController extends Controller
{
    public function index(Request $request): View
    {
        return view('slots.index', ['slots' => $request->user()->timeSlots()->get()]);
    }

    public function create(Request $request): View
    {
        return view('slots.form', [
            'slot' => new TimeSlot(['weekday_required' => 2, 'weekend_required' => 3]),
            'positions' => $this->positions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->timeSlots()->create($this->validated($request));

        return redirect()->route('slots.index')->with('status', '時間帯を登録しました。');
    }

    public function edit(Request $request, TimeSlot $slot): View
    {
        abort_unless((int) $slot->user_id === (int) $request->user()->id, 404);

        return view('slots.form', ['slot' => $slot, 'positions' => $this->positions($request)]);
    }

    public function update(Request $request, TimeSlot $slot): RedirectResponse
    {
        abort_unless((int) $slot->user_id === (int) $request->user()->id, 404);
        $slot->update($this->validated($request));

        return redirect()->route('slots.index')->with('status', '時間帯を更新しました。');
    }

    public function destroy(Request $request, TimeSlot $slot): RedirectResponse
    {
        abort_unless((int) $slot->user_id === (int) $request->user()->id, 404);
        $slot->delete();

        return redirect()->route('slots.index')->with('status', '時間帯を削除しました。');
    }

    /** 登録されている担当の名前(入力の候補に出す) */
    private function positions(Request $request): array
    {
        return $request->user()->staff()->whereNotNull('position')->pluck('position')
            ->flatMap(fn ($p) => preg_split('/[・,、\/\s]+/u', $p))
            ->map(fn ($p) => trim($p))->filter()->unique()->values()
            ->merge(['ホール', 'キッチン'])->unique()->values()->all();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'rules' => ['nullable', 'array', 'max:3'],
            'rules.*.position' => ['nullable', 'string', 'max:20'],
            'rules.*.min' => ['nullable', 'integer', 'min:0', 'max:30'],
        ], [], ['rules.*.position' => '担当', 'rules.*.min' => '人数']);

        $rules = collect($data['rules'] ?? [])
            ->map(fn ($r) => ['position' => trim((string) ($r['position'] ?? '')), 'min' => (int) ($r['min'] ?? 0)])
            ->filter(fn ($r) => $r['position'] !== '' && $r['min'] > 0)
            ->unique('position')->values()->all();

        return $this->validatedBasics($request) + ['position_rules' => $rules ?: null];
    }

    private function validatedBasics(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:20'],
            'symbol' => ['nullable', 'string', 'max:2'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'weekday_required' => ['required', 'integer', 'min:0', 'max:30'],
            'weekend_required' => ['required', 'integer', 'min:0', 'max:30'],
        ], [
            'end_time.after' => '終わりの時刻は、始まりより後にしてください(日をまたぐ営業は 23:59 までで登録してください)。',
        ], [
            'name' => '名前', 'symbol' => '記号', 'start_time' => '始まり', 'end_time' => '終わり',
            'weekday_required' => '平日の人数', 'weekend_required' => '土日の人数',
        ]);
    }
}
