@extends('layouts.app', ['title' => Shift::label($day).'のシフト'])

@use('App\Models\Shift')

@section('content')
<a href="{{ route('shifts.index', ['month' => $day->format('Y-m')]) }}" class="text-sm text-stone-500 hover:text-ink">← {{ $day->format('n') }}月のシフト表</a>
<div class="flex items-center gap-2 mt-2 mb-1">
    <a href="{{ route('shifts.day', $prevDay) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 w-9 h-9 inline-flex items-center justify-center" aria-label="前の日">‹</a>
    <h1 class="text-2xl font-bold">{{ Shift::label($day) }}のシフト</h1>
    <a href="{{ route('shifts.day', $nextDay) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 w-9 h-9 inline-flex items-center justify-center" aria-label="次の日">›</a>
</div>
<p class="text-sm text-stone-600 mb-5">入れる人にチェックを入れて保存します。<span class="inline-block rounded bg-brand-50 text-brand-700 px-1.5 text-xs font-bold">希望○</span> はこの時間帯に入れると答えた人です。</p>

@if ($closed)
    <p class="mb-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 px-4 py-2 text-sm">この日は定休日です。臨時で営業する場合は、そのまま入れる人を選べます。</p>
@endif

<form method="POST" action="{{ route('shifts.day.update', $day->toDateString()) }}" class="space-y-4 max-w-3xl">
    @csrf @method('PUT')
    @foreach ($slots as $slot)
        @php
            $on = $assigned[$slot->id] ?? [];
            $ok = $avail[$slot->id] ?? [];
            $need = $slot->requiredOn($day);
            // 希望○の人を上に
            $sorted = $staff->sortBy(fn ($m) => [in_array($m->id, $onLeave) ? 2 : (in_array($m->id, $ok) ? 0 : 1), $m->id]);
        @endphp
        <section data-tour="day-slot" class="bg-white rounded-xl border border-stone-200 p-5" data-slot>
            <div class="flex items-baseline justify-between gap-2">
                <h2 class="font-bold">{{ $slot->name }} <span class="font-normal text-sm text-stone-500">{{ $slot->time_label }}</span></h2>
                <span class="text-sm"><span data-count class="font-bold">{{ count($on) }}</span> / {{ $need }}人</span>
            </div>
            @if ($slot->rules())
                <p class="mt-1 text-xs text-stone-500">条件:{{ $slot->rules_label }}</p>
            @endif
            <div class="mt-3 grid sm:grid-cols-2 gap-2">
                @foreach ($sorted as $m)
                    @php
                        $wants = in_array($m->id, $ok);
                        $answered = in_array($m->id, $submitted);
                        $otherSlots = $slots->filter(fn ($s) => $s->id !== $slot->id && in_array($m->id, $assigned[$s->id] ?? []))->pluck('name');
                    @endphp
                    <label class="flex items-center gap-3 rounded-lg border px-3 py-2 cursor-pointer has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 {{ $wants ? 'border-stone-300' : 'border-stone-200' }}">
                        <input type="checkbox" name="shifts[{{ $slot->id }}][]" value="{{ $m->id }}" @checked(in_array($m->id, $on)) onchange="recount(this)">
                        <span class="flex-1 min-w-0">
                            <span class="font-bold {{ $wants ? '' : 'text-stone-500' }}">{{ $m->name }}</span>
                            @if ($m->position)<span class="text-xs text-stone-500 ml-1">{{ $m->position }}</span>@endif
                            @if ($otherSlots->isNotEmpty())<span class="block text-xs text-stone-500">{{ $otherSlots->implode('・') }}にも入っています</span>@endif
                        </span>
                        @if (in_array($m->id, $onLeave))
                            <span class="shrink-0 rounded bg-amber-100 text-amber-900 px-1.5 text-xs font-bold">有給</span>
                        @elseif ($wants)
                            <span class="shrink-0 rounded bg-brand-50 text-brand-700 px-1.5 text-xs font-bold">希望○</span>
                        @elseif ($answered)
                            <span class="shrink-0 text-xs text-stone-400">希望×</span>
                        @else
                            <span class="shrink-0 text-xs text-stone-400">未提出</span>
                        @endif
                    </label>
                @endforeach
            </div>
            @if ($staff->isEmpty())
                <p class="text-sm text-stone-500 mt-2">在籍中のスタッフがいません。<a href="{{ route('staff.create') }}" class="underline">スタッフを追加</a></p>
            @endif
        </section>
    @endforeach

    <div class="flex flex-wrap gap-2">
        <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5">保存する</button>
        <a href="{{ route('shifts.index', ['month' => $day->format('Y-m')]) }}" class="rounded-lg border border-stone-300 bg-white px-5 py-2.5">キャンセル</a>
    </div>
</form>

<script>
    function recount(input) {
        const box = input.closest('[data-slot]');
        box.querySelector('[data-count]').textContent = box.querySelectorAll('input[type=checkbox]:checked').length;
    }
</script>
@endsection
