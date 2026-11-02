@extends('layouts.app', ['title' => Shift::monthLabel($ym).'のシフト表'])

@use('App\Models\Shift')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <div class="flex items-center gap-2">
        <a href="{{ route('shifts.index', ['month' => $prev]) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 w-9 h-9 inline-flex items-center justify-center" aria-label="前の月">‹</a>
        <h1 class="text-2xl font-bold">{{ Shift::monthLabel($ym) }}</h1>
        <a href="{{ route('shifts.index', ['month' => $next]) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 w-9 h-9 inline-flex items-center justify-center" aria-label="次の月">›</a>
        @if ($schedule->isPublished())
            <span class="ml-1 rounded-full bg-brand-50 text-brand-700 text-xs font-bold px-2.5 py-1">公開済み</span>
        @else
            <span class="ml-1 rounded-full bg-stone-100 text-stone-600 text-xs font-bold px-2.5 py-1">作成中</span>
        @endif
    </div>
    <a href="{{ route('shifts.table', ['month' => $ym]) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-4 py-2 text-sm">印刷・掲示用の表</a>
</div>

@if ($slots->isEmpty())
    <div class="bg-white rounded-xl border border-stone-200">
        <x-empty-state message="シフトを作るには、先に時間帯(ランチ・ディナーなど)と必要な人数を登録してください。" action="時間帯を登録する" :url="route('slots.create')" />
    </div>
@else
<div class="grid md:grid-cols-3 gap-4 mb-6">
    {{-- ① 希望を集める --}}
    <section data-tour="collect" class="bg-white rounded-xl border border-stone-200 p-4">
        <h2 class="font-bold text-sm"><span class="text-brand-600">1</span> 希望を集める</h2>
        <p class="mt-2 text-2xl font-bold">{{ $activeCount - $notSubmitted->count() }}<span class="text-sm font-normal text-stone-500"> / {{ $activeCount }}人が提出</span></p>
        @if ($notSubmitted->isNotEmpty())
            <p class="mt-1 text-xs text-stone-500">まだ:{{ $notSubmitted->take(5)->pluck('name')->implode('、') }}{{ $notSubmitted->count() > 5 ? ' ほか' : '' }}</p>
            @php
    $remindText = '【'.Shift::monthLabel($ym).'のシフト希望】'."\n"
        .'まだの人:'.$notSubmitted->map(fn ($m) => $m->name.'さん')->implode('、')."\n"
        .($schedule->deadline ? Shift::label($schedule->deadline).'までに、' : 'なるべく早めに、')
        .'前に送った自分専用のURLから出してください。URLが分からない人は声をかけてください。';
@endphp
            <button type="button" data-tour="remind" data-copy="{{ $remindText }}" onclick="copyText(this)" class="mt-2 rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-3 py-1 text-sm">まだの人への催促文をコピー</button>
        @endif
        <form method="POST" action="{{ route('shifts.deadline') }}" class="mt-3 flex items-center gap-2">
            @csrf @method('PUT')
            <input type="hidden" name="month" value="{{ $ym }}">
            <label class="text-xs text-stone-500 whitespace-nowrap" for="deadline">締め切り</label>
            <input type="date" id="deadline" name="deadline" value="{{ $schedule->deadline?->toDateString() }}" class="min-w-0 flex-1 rounded-lg border border-stone-300 px-2 py-1 text-sm">
            <button class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-2.5 py-1 text-sm">保存</button>
        </form>
        <a href="{{ route('staff.index') }}" class="mt-2 inline-block text-sm text-brand-600 underline">スタッフにURLを送る</a>
    </section>

    {{-- ② 自動で作る --}}
    <section data-tour="generate" class="bg-white rounded-xl border-2 border-brand-100 p-4">
        <h2 class="font-bold text-sm"><span class="text-brand-600">2</span> 自動で下書きを作る</h2>
        <form method="POST" action="{{ route('shifts.generate') }}" class="mt-2 space-y-2"
              onsubmit="if (this.mode.value === 'rebuild' && {{ $totalAssigned }} > 0) return confirm('今のシフトを消して、最初から作り直します。手で直したところも消えます。よろしいですか?')">
            @csrf
            <input type="hidden" name="month" value="{{ $ym }}">
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="mode" value="fill" checked> 空いている枠だけ埋める</label>
            <label class="flex items-center gap-2 text-sm"><input type="radio" name="mode" value="rebuild"> 全部作り直す</label>
            <button class="w-full rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold py-2">自動で作る</button>
        </form>
        <p class="mt-2 text-xs text-stone-500">希望・必要な人数・週の上限を見て、偏りが出ないように組みます。あとから1日ずつ手で直せます。</p>
    </section>

    {{-- ③ 公開する --}}
    <section data-tour="publish" class="bg-white rounded-xl border border-stone-200 p-4">
        <h2 class="font-bold text-sm"><span class="text-brand-600">3</span> 確定して公開する</h2>
        <p class="mt-2 text-2xl font-bold {{ $shortage ? 'text-alert-600' : '' }}">
            @if ($shortage) あと{{ $shortage }}人分<span class="text-sm font-normal"> 足りません</span>
            @else <span class="text-brand-700">全部埋まっています</span> @endif
        </p>
        <form method="POST" action="{{ route('shifts.publish') }}" class="mt-3">
            @csrf
            <input type="hidden" name="month" value="{{ $ym }}">
            @if ($schedule->isPublished())
                <input type="hidden" name="publish" value="0">
                <p class="text-xs text-stone-500 mb-2">{{ $schedule->published_at->format('n/j H:i') }}に公開しました。直した内容は、すぐスタッフのページにも反映されます。</p>
                <button class="w-full rounded-lg border border-stone-300 bg-white hover:bg-stone-50 py-2 text-sm">公開をやめる</button>
            @else
                <input type="hidden" name="publish" value="1">
                <button class="w-full rounded-lg bg-ink hover:bg-stone-800 text-white font-bold py-2 text-sm" @disabled($totalAssigned === 0)>スタッフに公開する</button>
                <p class="mt-2 text-xs text-stone-500">公開すると、スタッフは自分のURLからシフトを見られます。その月の希望は変えられなくなります。</p>
            @endif
        </form>
    </section>
</div>

@if ($pendingLeaves)
    <a href="{{ route('leaves.index') }}" class="mb-5 flex items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-900">
        <span>有給の申請が<b>{{ $pendingLeaves }}件</b>、承認待ちです。</span><span class="underline whitespace-nowrap">確認する →</span>
    </a>
@endif

@if (session('shortages'))
    <div class="mb-5 rounded-lg border border-alert-500/30 bg-alert-50 px-4 py-3 text-sm">
        <p class="font-bold text-alert-600">人が足りない枠</p>
        <ul class="mt-1 text-stone-700 flex flex-wrap gap-x-4 gap-y-1">
            @foreach (session('shortages') as $s)
                <li>{{ Shift::label(\Carbon\CarbonImmutable::parse($s['date'])) }} {{ $s['slot'] }}:あと{{ $s['missing'] }}人</li>
            @endforeach
        </ul>
        <p class="mt-1 text-xs text-stone-500">希望を出していない人に聞いてみるか、日付の「直す」から希望外の人を入れることもできます。</p>
    </div>
@endif

<div data-tour="grid" class="bg-white rounded-xl border border-stone-200 overflow-hidden">
    <table class="rtable w-full text-sm">
        <thead class="bg-stone-50 text-stone-500 text-left">
            <tr>
                <th class="px-4 py-3 font-medium w-28">日付</th>
                @foreach ($slots as $slot)
                    <th class="px-4 py-3 font-medium">{{ $slot->name }} <span class="font-normal text-xs">{{ $slot->time_label }}</span></th>
                @endforeach
                <th class="px-4 py-3 w-16"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
            @foreach ($days as $day)
                @php $w = (int) $day->format('w'); $ds = $day->toDateString(); @endphp
                <tr class="{{ $day->isToday() ? 'bg-brand-50/60' : '' }}">
                    <td class="rt-main px-4 py-3 font-bold whitespace-nowrap {{ $w === 0 ? 'text-alert-600' : ($w === 6 ? 'text-blue-700' : '') }}">
                        {{ Shift::label($day) }}
                        @if ($day->isToday())<span class="ml-1 text-xs font-normal text-brand-700">今日</span>@endif
                        @if (! empty($leaves[$ds]))
                            <div class="mt-1 text-xs font-normal text-stone-500">有給:{{ collect($leaves[$ds])->map(fn ($id) => $staff->get($id)?->name)->filter()->implode('、') }}</div>
                        @endif
                    </td>
                    @foreach ($slots as $slot)
                        @php
                            $key = $ds.'|'.$slot->id;
                            $people = $assigned[$key] ?? collect();
                            $need = $slot->requiredOn($day);
                            $missing = max(0, $need - $people->count());
                            $ruleShort = $people->isNotEmpty() ? $slot->ruleShortfalls($people->map(fn ($a) => $staff->get($a->staff_id))) : [];
                        @endphp
                        <td data-label="{{ $slot->name }}" class="px-4 py-2.5 align-top {{ $missing || $ruleShort ? 'bg-alert-50' : '' }}">
                            <div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($people as $a)
                                        @php $m = $staff->get($a->staff_id); @endphp
                                        <span class="rounded-full bg-stone-100 px-2 py-0.5 {{ $m?->is_active === false ? 'line-through text-stone-400' : '' }}">{{ $m?->name ?? '?' }}</span>
                                    @endforeach
                                    @if ($missing)
                                        <span class="rounded-full border border-dashed border-alert-500/50 text-alert-600 px-2 py-0.5">あと{{ $missing }}人</span>
                                    @endif
                                </div>
                                @foreach ($ruleShort as $r)
                                    <div class="mt-1 text-xs font-bold text-alert-600">{{ $r['position'] }}がいません({{ $r['have'] }}/{{ $r['min'] }}人)</div>
                                @endforeach
                                <div class="mt-1 text-xs text-stone-400">{{ $people->count() }}/{{ $need }}人 ・ 希望{{ $availCount[$key] ?? 0 }}人</div>
                            </div>
                        </td>
                    @endforeach
                    <td class="rt-actions px-4 py-2.5 text-right align-top">
                        <a href="{{ route('shifts.day', $ds) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-3 py-1.5 inline-block whitespace-nowrap">直す</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
@endsection
