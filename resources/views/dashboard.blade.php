@extends('layouts.app', ['title' => 'ホーム'])

@use('App\Models\Shift')

@section('content')
@unless ($setupDone)
    <section data-tour="setup" class="mb-6 rounded-xl border border-brand-100 bg-brand-50 p-5">
        <h2 class="font-bold text-brand-700">はじめにやること</h2>
        <ol class="mt-3 space-y-2 text-sm">
            @foreach ($setup as $i => $step)
                <li class="flex items-center gap-3">
                    <span class="inline-flex w-6 h-6 shrink-0 rounded-full items-center justify-center text-xs font-bold {{ $step['done'] ? 'bg-brand-500 text-white' : 'bg-white border border-brand-100 text-brand-700' }}">{{ $step['done'] ? '✓' : $i + 1 }}</span>
                    @if ($step['done'])
                        <span class="text-stone-400 line-through">{{ $step['label'] }}</span>
                    @else
                        <a href="{{ $step['url'] }}" class="underline decoration-brand-100 underline-offset-4 hover:text-brand-700">{{ $step['label'] }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@endunless

@if ($pendingLeaves->isNotEmpty())
    <a href="{{ route('leaves.index') }}" data-tour="leaves" class="mb-5 flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900">
        <span>
            有給の申請が<b>{{ $pendingLeaves->count() }}件</b>、承認待ちです
            <span class="text-amber-800/80">({{ $pendingLeaves->take(3)->map(fn ($l) => $l->staff->name.' '.Shift::label($l->day))->implode('、') }}{{ $pendingLeaves->count() > 3 ? ' ほか' : '' }})</span>
        </span>
        <span class="underline whitespace-nowrap">確認する →</span>
    </a>
@endif

<div class="grid lg:grid-cols-5 gap-5">
    {{-- 今日のシフト --}}
    <section data-tour="today" class="lg:col-span-2 bg-white rounded-xl border border-stone-200 p-5">
        <div class="flex items-baseline justify-between gap-2">
            <h2 class="font-bold">今日のシフト</h2>
            <span class="text-sm text-stone-500">{{ Shift::label($today) }}</span>
        </div>
        @if ($todayClosed)
            <p class="mt-4 text-sm text-stone-500">今日は定休日です。</p>
        @elseif ($slots->isEmpty())
            <x-empty-state message="時間帯がまだ登録されていません。" action="時間帯を登録する" :url="route('slots.create')" />
        @else
            <ul class="mt-4 space-y-4">
                @foreach ($slots as $slot)
                    @php $people = $todayShifts[$slot->id] ?? collect(); $need = $slot->requiredOn($today); @endphp
                    <li>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-bold">{{ $slot->name }} <span class="font-normal text-stone-500">{{ $slot->time_label }}</span></span>
                            <span class="{{ $people->count() < $need ? 'text-alert-600 font-bold' : 'text-stone-500' }}">{{ $people->count() }}/{{ $need }}人</span>
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                            @forelse ($people as $a)
                                <span class="rounded-full bg-stone-100 px-2.5 py-1 text-sm">{{ $a->staff->name }}</span>
                            @empty
                                <span class="text-sm text-stone-400">まだ誰も入っていません</span>
                            @endforelse
                        </div>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('shifts.day', $today->toDateString()) }}" class="mt-4 inline-block text-sm text-brand-600 underline">今日のシフトを直す</a>
        @endif
    </section>

    {{-- 来月の準備 --}}
    <section data-tour="next" class="lg:col-span-3 bg-white rounded-xl border border-stone-200 p-5">
        <div class="flex items-baseline justify-between gap-2">
            <h2 class="font-bold">{{ Shift::monthLabel($nextYm) }}のシフトづくり</h2>
            @if ($next->isPublished())
                <span class="rounded-full bg-brand-50 text-brand-700 text-xs font-bold px-2.5 py-1">公開済み</span>
            @else
                <span class="rounded-full bg-stone-100 text-stone-600 text-xs font-bold px-2.5 py-1">作成中</span>
            @endif
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-lg border border-stone-200 p-4">
                <p class="text-sm text-stone-500">希望の提出</p>
                <p class="mt-1 text-2xl font-bold">{{ $submittedCount }}<span class="text-sm font-normal text-stone-500"> / {{ $activeCount }}人</span></p>
                @if ($next->deadline)
                    <p class="mt-1 text-xs text-stone-500">締め切り {{ Shift::label($next->deadline) }}</p>
                @endif
            </div>
            <div class="rounded-lg border border-stone-200 p-4">
                <p class="text-sm text-stone-500">埋まった枠</p>
                <p class="mt-1 text-2xl font-bold">{{ $nextFilled }}<span class="text-sm font-normal text-stone-500"> / {{ $nextNeed }}人分</span></p>
                @if ($nextNeed > 0)
                    <div class="mt-2 h-1.5 rounded-full bg-stone-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ round($nextFilled / $nextNeed * 100) }}%"></div></div>
                @endif
            </div>
        </div>

        @if ($notSubmitted->isNotEmpty() && $activeCount > 0)
            <p class="mt-4 text-sm">
                <span class="text-stone-500">まだ出していない人:</span>
                {{ $notSubmitted->take(6)->pluck('name')->implode('、') }}{{ $notSubmitted->count() > 6 ? ' ほか'.($notSubmitted->count() - 6).'人' : '' }}
            </p>
        @endif

        <div class="mt-5 flex flex-wrap gap-2">
            <a href="{{ route('shifts.index', ['month' => $nextYm]) }}" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-2 text-sm">シフト表を開く</a>
            <a href="{{ route('staff.index') }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-4 py-2 text-sm">希望のURLを送る</a>
        </div>
    </section>
</div>
@endsection
