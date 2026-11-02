@extends('layouts.app', ['title' => '時間帯'])

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-2">
    <h1 class="text-2xl font-bold">時間帯</h1>
    <a href="{{ route('slots.create') }}" class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-4 py-2 text-sm">+ 時間帯を追加</a>
</div>
<p class="text-sm text-stone-600 mb-5">シフトを組む単位です。「ランチ」「ディナー」のように分けて、それぞれ何人いれば回るかを決めておきます。定休日は<a href="{{ route('settings.edit') }}" class="underline">設定</a>で変えられます。</p>

<div class="bg-white rounded-xl border border-stone-200 overflow-hidden">
    @if ($slots->isEmpty())
        <x-empty-state message="まだ時間帯がありません。まずはランチやディナーなど、お店の営業時間を登録しましょう。" action="時間帯を追加する" :url="route('slots.create')" />
    @else
        <table class="rtable w-full text-sm">
            <thead class="bg-stone-50 text-stone-500 text-left">
                <tr>
                    <th class="px-4 py-3 font-medium">名前</th>
                    <th class="px-4 py-3 font-medium">記号</th>
                    <th class="px-4 py-3 font-medium">時間</th>
                    <th class="px-4 py-3 font-medium">平日の人数</th>
                    <th class="px-4 py-3 font-medium">土日の人数</th>
                    <th class="px-4 py-3 font-medium">担当の条件</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @foreach ($slots as $slot)
                    <tr>
                        <td class="rt-main px-4 py-3 font-bold">{{ $slot->name }}</td>
                        <td data-label="記号" class="px-4 py-3"><span class="inline-flex min-w-7 h-7 px-1 items-center justify-center rounded border border-stone-300 font-bold">{{ $slot->mark }}</span></td>
                        <td data-label="時間" class="px-4 py-3">{{ $slot->time_label }}</td>
                        <td data-label="平日" class="px-4 py-3">{{ $slot->weekday_required }}人</td>
                        <td data-label="土日" class="px-4 py-3">{{ $slot->weekend_required }}人</td>
                        <td data-label="条件" class="px-4 py-3">{{ $slot->rules_label ?: '—' }}</td>
                        <td class="rt-actions px-4 py-3 text-right">
                            <a href="{{ route('slots.edit', $slot) }}" class="rounded-lg border border-stone-300 bg-white hover:bg-stone-50 px-3 py-1.5 inline-block">編集</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
