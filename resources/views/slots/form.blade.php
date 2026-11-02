@extends('layouts.app', ['title' => $slot->exists ? '時間帯の編集' : '時間帯を追加'])

@section('content')
<a href="{{ route('slots.index') }}" class="text-sm text-stone-500 hover:text-ink">← 時間帯</a>
<h1 class="text-2xl font-bold mt-2 mb-5">{{ $slot->exists ? '時間帯の編集' : '時間帯を追加' }}</h1>

<form method="POST" action="{{ $slot->exists ? route('slots.update', $slot) : route('slots.store') }}" class="max-w-xl bg-white rounded-xl border border-stone-200 p-6 space-y-4">
    @csrf
    @if ($slot->exists) @method('PUT') @endif

    <x-field label="名前" name="name" hint="短い名前がおすすめです(シフト表の小さなマスにも出ます)">
        <input type="text" name="name" value="{{ old('name', $slot->name) }}" required maxlength="20" list="slot-names" placeholder="例:ランチ" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        <datalist id="slot-names"><option value="モーニング"><option value="ランチ"><option value="ディナー"><option value="通し"><option value="仕込み"></datalist>
    </x-field>
    <x-field label="印刷用の記号(1〜2文字)" name="symbol" hint="印刷・掲示用の表のマスに出ます。形の似ていない字にすると見やすくなります(例:昼・夜・朝・L・D・○)。空欄なら名前の1文字目になります">
        <input type="text" name="symbol" value="{{ old('symbol', $slot->symbol) }}" maxlength="2" placeholder="例:昼" class="w-28 rounded-lg border border-stone-300 px-3 py-2">
    </x-field>
    <div class="grid grid-cols-2 gap-4">
        <x-field label="始まり" name="start_time">
            <input type="time" name="start_time" value="{{ old('start_time', $slot->start_time ? $slot->start : '11:00') }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <x-field label="終わり" name="end_time">
            <input type="time" name="end_time" value="{{ old('end_time', $slot->end_time ? $slot->end : '15:00') }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <x-field label="平日に必要な人数" name="weekday_required">
            <div class="flex items-center gap-2"><input type="number" name="weekday_required" value="{{ old('weekday_required', $slot->weekday_required) }}" min="0" max="30" required class="w-24 rounded-lg border border-stone-300 px-3 py-2"><span class="text-sm">人</span></div>
        </x-field>
        <x-field label="土日に必要な人数" name="weekend_required">
            <div class="flex items-center gap-2"><input type="number" name="weekend_required" value="{{ old('weekend_required', $slot->weekend_required) }}" min="0" max="30" required class="w-24 rounded-lg border border-stone-300 px-3 py-2"><span class="text-sm">人</span></div>
        </x-field>
    </div>
    <p class="text-xs text-stone-500">ランチとディナーのように時間が重ならない枠なら、同じ人が「通し」で両方に入ることもできます。</p>

    <div class="pt-2 border-t border-stone-100">
        <span class="block text-sm font-medium">担当の条件(任意)</span>
        <span class="block text-xs text-stone-500 mt-0.5 mb-2">「キッチンが最低1人はいないと回らない」などを決めると、自動で作るときに守ります。スタッフの「担当」にその言葉が入っている人が当てはまります(「ホール・キッチン」の人はどちらにも数えます)。</span>
        @php $rules = old('rules', $slot->position_rules ?? []); @endphp
        <datalist id="rule-positions">@foreach ($positions as $p)<option value="{{ $p }}">@endforeach</datalist>
        <div class="space-y-2">
            @for ($i = 0; $i < 3; $i++)
                <div class="flex items-center gap-2 text-sm">
                    <input type="text" name="rules[{{ $i }}][position]" value="{{ $rules[$i]['position'] ?? '' }}" list="rule-positions" maxlength="20" placeholder="{{ $i === 0 ? '例:キッチン' : '担当' }}" class="w-36 rounded-lg border border-stone-300 px-3 py-2" aria-label="担当{{ $i + 1 }}">
                    <span>が最低</span>
                    <input type="number" name="rules[{{ $i }}][min]" value="{{ $rules[$i]['min'] ?? '' }}" min="0" max="30" placeholder="1" class="w-20 rounded-lg border border-stone-300 px-3 py-2" aria-label="担当{{ $i + 1 }}の人数">
                    <span>人</span>
                </div>
            @endfor
        </div>
        @error('rules.*')<span class="block text-xs text-alert-600 mt-1">{{ $message }}</span>@enderror
    </div>

    <div class="flex flex-wrap gap-2 pt-2">
        <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5">{{ $slot->exists ? '保存する' : '登録する' }}</button>
        <a href="{{ route('slots.index') }}" class="rounded-lg border border-stone-300 bg-white px-5 py-2.5">キャンセル</a>
    </div>
</form>

@if ($slot->exists)
    <form method="POST" action="{{ route('slots.destroy', $slot) }}" class="max-w-xl mt-6 text-right"
          onsubmit="return confirm('この時間帯を削除すると、この時間帯の希望とシフトもすべて消えます。削除しますか?')">
        @csrf @method('DELETE')
        <button class="text-sm text-alert-600 underline">この時間帯を削除する</button>
    </form>
@endif
@endsection
