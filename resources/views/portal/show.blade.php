@extends('layouts.app', ['title' => $staff->name.'さんのシフト', 'staffPage' => true])

@use('App\Models\Shift')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-3 mb-5">
        <x-app-logo size="w-10 h-10" />
        <div>
            <p class="text-sm text-stone-500">{{ $user->name }}</p>
            <h1 class="text-xl font-bold">{{ $staff->name }}さんのシフト</h1>
        </div>
    </div>

    {{-- 決まったシフト --}}
    <section class="bg-white rounded-xl border border-stone-200 p-5 mb-5">
        <h2 class="font-bold">これからのシフト</h2>
        @if ($myShifts->isEmpty())
            <p class="mt-2 text-sm text-stone-500">{{ $published ? '今のところ予定はありません。' : 'シフトが決まると、ここに表示されます。' }}</p>
        @else
            <ul class="mt-3 divide-y divide-stone-100">
                @foreach ($myShifts->groupBy('date') as $date => $items)
                    @php $d = \Carbon\CarbonImmutable::parse($date); $w = (int) $d->format('w'); @endphp
                    <li class="py-2.5 flex items-start gap-4">
                        <span class="w-20 shrink-0 font-bold {{ $w === 0 ? 'text-alert-600' : ($w === 6 ? 'text-blue-700' : '') }}">{{ Shift::label($d) }}</span>
                        <span class="text-sm">
                            @foreach ($items as $a)
                                <span class="block">{{ $a->timeSlot->name }} {{ $a->timeSlot->time_label }}</span>
                            @endforeach
                        </span>
                        @if ($d->isToday())<span class="ml-auto rounded-full bg-brand-50 text-brand-700 text-xs font-bold px-2 py-0.5">今日</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- 希望を出す --}}
    <section class="bg-white rounded-xl border border-stone-200 overflow-hidden">
        <div class="flex border-b border-stone-200 text-sm">
            @foreach ($months as $m)
                <a href="{{ route('portal.show', ['token' => $staff->token, 'month' => $m]) }}"
                   class="flex-1 text-center px-2 py-3 border-b-2 {{ $m === $ym ? 'border-brand-500 text-brand-700 font-bold' : 'border-transparent text-stone-500' }}">
                    {{ Shift::firstDay($m)->format('n') }}月
                    @if (in_array($m, $published, true))<span class="block text-[11px] font-normal">決定</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="p-5">
            @if ($schedule->isPublished())
                <p class="text-sm rounded-lg bg-stone-50 px-4 py-3">
                    {{ Shift::monthLabel($ym) }}のシフトは決まりました。上の「これからのシフト」で確認してください。変えたいときは、お店に直接ご相談ください。
                </p>
            @elseif ($slots->isEmpty())
                <p class="text-sm text-stone-500">お店の準備中です。もうしばらくお待ちください。</p>
            @else
                <h2 class="font-bold">{{ Shift::monthLabel($ym) }}の希望</h2>
                <p class="mt-1 text-sm text-stone-600">
                    入れる日と時間帯をタップしてください。
                    @if ($schedule->deadline)<b>{{ Shift::label($schedule->deadline) }}まで</b>に送ってください。@endif
                </p>
                @if ($submission)
                    <p class="mt-2 text-xs text-brand-700 font-bold">{{ $submission->submitted_at->format('n/j H:i') }}に送信済み(直して送り直せます)</p>
                @endif

                {{-- まとめて選ぶ:選んだ曜日 × 時間帯だけにチェックが入った状態に置きかえる --}}
                <div class="mt-4 rounded-lg bg-stone-50 p-3 text-sm space-y-2">
                    <p class="font-bold text-xs text-stone-500">まとめて選ぶ</p>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="w-12 text-xs text-stone-500">曜日</span>
                        @foreach (['all' => '全部', 'weekday' => '平日', 'weekend' => '土日'] as $value => $label)
                            <label><input type="radio" name="bulk-day" value="{{ $value }}" class="peer sr-only" @checked($value === 'all')><span class="inline-block rounded-full border border-stone-300 bg-white px-3 py-1 cursor-pointer select-none peer-checked:bg-ink peer-checked:text-white peer-checked:border-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40">{{ $label }}</span></label>
                        @endforeach
                    </div>
                    <div id="bulk-slots" class="flex items-center gap-2 flex-wrap rounded-lg -mx-1 px-1 py-0.5 transition-colors">
                        <span class="w-12 text-xs text-stone-500">時間帯</span>
                        @foreach ($slots as $slot)
                            <label><input type="checkbox" name="bulk-slot" value="{{ $slot->id }}" class="peer sr-only" checked><span class="inline-block rounded-full border border-stone-300 bg-white px-3 py-1 cursor-pointer select-none peer-checked:bg-ink peer-checked:text-white peer-checked:border-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40">{{ $slot->name }}</span></label>
                        @endforeach
                        <span class="text-xs text-stone-400">(いくつでも)</span>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-1">
                        <button type="button" onclick="bulk()" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-3 py-1.5">この条件で選ぶ</button>
                        <button type="button" onclick="clearAll()" class="rounded-lg border border-stone-300 bg-white px-3 py-1.5">全部はずす</button>
                        <span id="bulk-msg" class="self-center text-xs" aria-live="polite"></span>
                    </div>
                    <p class="text-xs text-stone-400">今のチェックは消えて、選んだ曜日と時間帯だけが選ばれます。そのあと1マスずつ直せます。</p>
                </div>

                <form method="POST" action="{{ route('portal.update', $staff->token) }}" id="avail-form" class="mt-4">
                    @csrf
                    <input type="hidden" name="month" value="{{ $ym }}">
                    <ul class="divide-y divide-stone-100 border-y border-stone-100">
                        @foreach ($days as $day)
                            @php $w = (int) $day->format('w'); $ds = $day->toDateString(); @endphp
                            <li class="py-2.5 flex items-center gap-3">
                                <span class="w-16 shrink-0 font-bold text-sm {{ $w === 0 ? 'text-alert-600' : ($w === 6 ? 'text-blue-700' : '') }}">
                                    {{ Shift::label($day) }}
                                    @if (in_array($ds, $leaveDates, true))<span class="block text-[11px] font-normal text-amber-700">有給</span>@endif
                                </span>
                                <span class="flex-1 grid gap-2" style="grid-template-columns: repeat({{ $slots->count() }}, minmax(0, 1fr))">
                                    @foreach ($slots as $slot)
                                        <label class="block">
                                            <input type="checkbox" name="avail[]" value="{{ $ds }}|{{ $slot->id }}" class="peer sr-only" data-weekend="{{ Shift::isWeekend($day) ? 1 : 0 }}" data-slot="{{ $slot->id }}" @checked(in_array($ds.'|'.$slot->id, $mine, true))>
                                            <span class="block text-center rounded-lg border border-stone-200 py-2 text-sm text-stone-400 peer-checked:border-brand-500 peer-checked:bg-brand-500 peer-checked:text-white peer-checked:font-bold peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40 select-none cursor-pointer">
                                                {{ $slot->name }}
                                            </span>
                                        </label>
                                    @endforeach
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-xs text-stone-500">
                        @foreach ($slots as $slot){{ $slot->name }} {{ $slot->time_label }}@if (! $loop->last) ・ @endif @endforeach
                    </p>

                    <label class="block mt-4">
                        <span class="block text-sm font-medium mb-1">お店へのひとこと(任意)</span>
                        <textarea name="note" rows="2" maxlength="300" placeholder="例:テスト期間なので、15日〜20日は少なめだと助かります" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('note', $submission?->note) }}</textarea>
                    </label>

                    <div class="sticky bottom-0 -mx-5 mt-4 bg-white/95 backdrop-blur border-t border-stone-200 px-5 py-3 flex items-center gap-3">
                        <span class="text-sm text-stone-600"><b id="picked">0</b>枠を選択中</span>
                        <button class="ml-auto rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5">この内容で送る</button>
                    </div>
                </form>
            @endif
        </div>
    </section>
    {{-- 有給 --}}
    <section id="leave" class="bg-white rounded-xl border border-stone-200 p-5 mt-5">
        <div class="flex items-baseline justify-between gap-2">
            <h2 class="font-bold">有給</h2>
            <p class="text-sm">残り <b class="text-lg">{{ $staff->paid_leave_balance }}</b>日
                @if ($pendingLeaves)<span class="text-xs text-stone-500">(うち{{ $pendingLeaves }}日は承認待ち)</span>@endif
            </p>
        </div>

        @if (session('leave_error'))
            <p class="mt-3 rounded-lg bg-alert-50 border border-alert-500/40 text-alert-600 font-bold text-sm px-3 py-2">⚠ {{ session('leave_error') }}</p>
        @endif

        @if ($staff->paid_leave_balance - $pendingLeaves > 0)
            <form method="POST" action="{{ route('portal.leave', $staff->token) }}" class="mt-3 space-y-3">
                @csrf
                <div class="grid grid-cols-[auto_1fr] items-center gap-x-3 gap-y-2">
                    <label for="leave_date" class="text-sm">日付</label>
                    <input type="date" id="leave_date" name="leave_date" value="{{ old('leave_date') }}" min="{{ now()->toDateString() }}" max="{{ now()->addMonthsNoOverflow(6)->toDateString() }}" required class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <label for="leave_note" class="text-sm">ひとこと</label>
                    <input type="text" id="leave_note" name="leave_note" value="{{ old('leave_note') }}" maxlength="200" placeholder="任意(例:家族の用事のため)" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                @error('leave_date')<p class="text-xs text-alert-600">{{ $message }}</p>@enderror
                <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2 text-sm">有給を申請する</button>
            </form>
        @else
            <p class="mt-3 text-sm text-stone-500">今は申請できる有給の残りがありません。日数が違うと思ったら、お店に確認してください。</p>
        @endif

        @if ($leaves->isNotEmpty())
            <ul class="mt-4 divide-y divide-stone-100 border-t border-stone-100 text-sm">
                @foreach ($leaves as $leave)
                    <li class="py-2.5 flex items-start gap-3">
                        <span class="w-16 shrink-0 font-bold">{{ Shift::label($leave->day) }}</span>
                        <span class="flex-1 min-w-0">
                            <span class="{{ ['approved' => 'text-brand-700 font-bold', 'pending' => 'text-amber-700 font-bold', 'rejected' => 'text-alert-600', 'cancelled' => 'text-stone-400'][$leave->status] ?? '' }}">{{ $leave->status_label }}</span>
                            @if ($leave->reply)<span class="block text-xs text-stone-600">お店から:「{{ $leave->reply }}」</span>@endif
                        </span>
                        @if ($leave->status === 'pending')
                            <form method="POST" action="{{ route('portal.leave.withdraw', [$staff->token, $leave]) }}">
                                @csrf
                                <button class="text-xs text-stone-500 underline">取り下げる</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <p class="text-center text-xs text-stone-400 mt-6">このページはあなた専用です。URLを他の人に教えないでください。</p>
</div>

<script>
    const boxes = () => [...document.querySelectorAll('#avail-form input[name="avail[]"]')];
    const update = () => { const el = document.getElementById('picked'); if (el) el.textContent = boxes().filter((b) => b.checked).length; };
    // error = true のときは赤く目立たせ、時間帯の行も光らせる
    const say = (text, error = false) => {
        const msg = document.getElementById('bulk-msg');
        const row = document.getElementById('bulk-slots');
        msg.textContent = (error ? '⚠ ' : '') + text;
        msg.className = error
            ? 'self-center rounded-md bg-alert-50 border border-alert-500/40 text-alert-600 font-bold text-sm px-2 py-1'
            : 'self-center text-xs text-stone-500';
        row.classList.toggle('bg-alert-50', error);
        row.classList.toggle('ring-2', error);
        row.classList.toggle('ring-alert-500/50', error);
        clearTimeout(msg._t);
        msg._t = setTimeout(() => {
            msg.textContent = '';
            row.classList.remove('bg-alert-50', 'ring-2', 'ring-alert-500/50');
        }, error ? 4000 : 2500);
    };
    // 選んだ曜日 × 時間帯にあてはまる枠だけにチェックを入れ、それ以外ははずす
    function bulk() {
        const day = document.querySelector('input[name="bulk-day"]:checked').value;
        const slots = [...document.querySelectorAll('input[name="bulk-slot"]:checked')].map((el) => el.value);
        if (! slots.length) { say('時間帯を1つ以上選んでください', true); return; }
        let n = 0;
        boxes().forEach((b) => {
            const weekend = b.dataset.weekend === '1';
            const dayOk = day === 'all' || (day === 'weekend' ? weekend : ! weekend);
            b.checked = dayOk && slots.includes(b.dataset.slot);
            if (b.checked) n++;
        });
        update();
        say(`${n}枠を選びました`);
    }
    function clearAll() {
        boxes().forEach((b) => { b.checked = false; });
        update();
        say('全部はずしました');
    }
    document.addEventListener('change', (e) => { if (e.target.name === 'avail[]') update(); });
    update();
</script>
@endsection
