@extends('layouts.app', ['title' => 'スタッフ'])

@use('App\Models\Shift')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-5">
    <h1 class="text-2xl font-bold">スタッフ</h1>
    <a href="{{ route('staff.create') }}" data-tour="staff-add" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-2 text-sm">+ スタッフを追加</a>
</div>

@if ($staff->isEmpty())
    <div class="bg-white rounded-xl border border-stone-200">
        <x-empty-state message="まだスタッフがいません。登録すると、一人ひとりに希望を出すためのURLができます。" action="スタッフを追加する" :url="route('staff.create')" />
    </div>
@else
    <div class="flex flex-wrap items-end justify-between gap-3 mb-3">
        <p class="text-sm text-stone-600 max-w-2xl">
            「URLをコピー」を押すと、{{ Shift::monthLabel($ym) }}の希望を出してもらう案内文がコピーされます。LINEなどに貼り付けて、<b>本人だけに</b>送ってください(URLはその人専用です)。
        </p>
        @if ($notSubmitted->isNotEmpty())
            @php
    $remindText = '【'.Shift::monthLabel($ym).'のシフト希望】'."\n"
        .'まだの人:'.$notSubmitted->map(fn ($m) => $m->name.'さん')->implode('、')."\n"
        .($schedule->deadline ? Shift::label($schedule->deadline).'までに、' : 'なるべく早めに、')
        .'前に送った自分専用のURLから出してください。URLが分からない人は声をかけてください。';
@endphp
            <button type="button" data-copy="{{ $remindText }}" onclick="copyText(this)" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-3 py-1.5 text-sm whitespace-nowrap">まだの{{ $notSubmitted->count() }}人への催促文をコピー</button>
        @endif
    </div>
    <div class="bg-white rounded-xl border border-stone-200 overflow-hidden">
        <table class="rtable w-full text-sm">
            <thead class="bg-stone-50 text-stone-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">名前</th>
                    <th class="px-4 py-3 font-medium">担当</th>
                    <th class="px-4 py-3 font-medium">週の上限</th>
                    <th class="px-4 py-3 font-medium">有給の残り</th>
                    <th class="px-4 py-3 font-medium">{{ Shift::firstDay($ym)->format('n') }}月の希望</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($staff as $member)
                    @php $sub = $submitted[$member->id] ?? null; @endphp
                    <tr class="{{ $member->is_active ? '' : 'bg-stone-50 text-stone-400' }}">
                        <td class="rt-main px-4 py-3 font-bold">
                            {{ $member->name }}
                            @unless ($member->is_active)<span class="ml-1 text-xs font-normal">(休み中)</span>@endunless
                        </td>
                        <td data-label="担当" class="px-4 py-3">{{ $member->position ?: '—' }}</td>
                        <td data-label="週の上限" class="px-4 py-3">{{ $member->max_days_per_week ? '週'.$member->max_days_per_week.'日まで' : '制限なし' }}</td>
                        <td data-label="有給の残り" class="px-4 py-3">{{ $member->paid_leave_balance }}日</td>
                        <td data-label="希望" class="px-4 py-3">
                            @if ($sub)
                                <span class="text-brand-700 font-bold">提出済み</span>
                                <span class="text-xs text-stone-500">{{ $sub->submitted_at->format('n/j H:i') }}</span>
                                @if ($sub->note)<div class="text-xs text-stone-600 mt-0.5">「{{ $sub->note }}」</div>@endif
                            @elseif ($member->is_active)
                                <span class="text-stone-500">まだ</span>
                            @endif
                        </td>
                        <td class="rt-actions px-4 py-3 text-right whitespace-nowrap">
                            @if ($member->is_active)
                                <button type="button" data-tour="staff-url"
                                        data-copy="{{ $member->name }}さん、{{ Shift::monthLabel($ym) }}のシフト希望を{{ $schedule->deadline ? Shift::label($schedule->deadline).'までに' : '' }}こちらから出してください。&#10;{{ $member->portal_url }}"
                                        onclick="copyText(this)"
                                        class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-3 py-1.5">URLをコピー</button>
                                <a href="{{ $member->portal_url }}" target="_blank" rel="noopener" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-3 py-1.5 inline-block">開く</a>
                            @endif
                            <a href="{{ route('staff.edit', $member) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-3 py-1.5 inline-block">編集</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@endsection
