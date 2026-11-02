@extends('layouts.app', ['title' => Shift::monthLabel($ym).'のシフト表(印刷用)'])

@use('App\Models\Shift')

@section('content')
<style>
    @media print {
        header, nav, .no-print { display: none !important; }
        main { max-width: none !important; padding: 0 !important; }
        body { background: #fff !important; }
        .print-wrap { overflow: visible !important; border: none !important; }
        .print-table { font-size: 9px !important; }
        @page { size: A4 landscape; margin: 8mm; }
    }
</style>

<div class="no-print flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
        <a href="{{ route('shifts.index', ['month' => $ym]) }}" class="text-sm text-stone-500 hover:text-ink">← シフト表</a>
        <h1 class="text-2xl font-bold mt-1">{{ Shift::monthLabel($ym) }}のシフト表</h1>
    </div>
    <button type="button" onclick="window.print()" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-2 text-sm">印刷する</button>
</div>
<p class="text-sm text-stone-600 mb-1">
    @foreach ($slots as $slot)<span class="mr-4"><b>{{ $slot->mark }}</b> = {{ $slot->name }}({{ $slot->time_label }})</span>@endforeach<span class="mr-4"><b>有</b> = 有給</span>
</p>
<p class="no-print text-xs text-stone-500 mb-3">
    横向きで印刷すると1枚に収まります。
</p>
<h1 class="hidden print:block text-base font-bold mb-2">{{ Shift::monthLabel($ym) }} シフト表 — {{ auth()->user()->name }}</h1>

<div class="print-wrap bg-white rounded-xl border border-stone-200 overflow-x-auto">
    <table class="print-table text-xs border-collapse min-w-full">
        <thead>
            <tr class="bg-stone-50">
                <th class="sticky left-0 z-10 bg-stone-50 border-b border-r border-stone-200 px-3 py-2 text-left whitespace-nowrap">名前</th>
                @foreach ($days as $day)
                    @php $w = (int) $day->format('w'); $closed = in_array($day->toDateString(), $closedDays); @endphp
                    <th class="border-b border-stone-200 px-1 py-1 text-center font-medium min-w-7 {{ $closed ? 'bg-stone-100 text-stone-400' : ($w === 0 ? 'text-alert-600' : ($w === 6 ? 'text-blue-700' : '')) }}">
                        {{ $day->format('j') }}<br><span class="font-normal">{{ Shift::WEEKDAYS[$w] }}</span>
                    </th>
                @endforeach
                <th class="border-b border-l border-stone-200 px-2 py-1 whitespace-nowrap">回数</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($staff as $m)
                <tr class="odd:bg-white even:bg-stone-50/60">
                    <td class="sticky left-0 z-10 bg-inherit border-r border-stone-200 px-3 py-1.5 whitespace-nowrap font-bold">{{ $m->name }}</td>
                    @foreach ($days as $day)
                        @php $ids = $byStaff[$m->id.'|'.$day->toDateString()] ?? []; $closed = in_array($day->toDateString(), $closedDays); @endphp
                        <td class="text-center px-0.5 py-1.5 {{ $closed ? 'bg-stone-100' : '' }}">
                            @if (! empty($leaves[$m->id.'|'.$day->toDateString()]))
                                <span class="text-stone-500">有</span>
                            @else
                                {{ $slots->whereIn('id', $ids)->map->mark->implode('') }}
                            @endif
                        </td>
                    @endforeach
                    <td class="text-center border-l border-stone-200 px-2">{{ $counts[$m->id] ?? 0 }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ count($days) + 2 }}" class="px-4 py-6 text-center text-stone-500">スタッフがいません。</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
