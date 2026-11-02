@extends('layouts.app', ['title' => $member->exists ? $member->name.'さんの編集' : 'スタッフを追加'])

@section('content')
<a href="{{ route('staff.index') }}" class="text-sm text-stone-500 hover:text-ink">← スタッフ一覧</a>
<h1 class="text-2xl font-bold mt-2 mb-5">{{ $member->exists ? $member->name.'さんの編集' : 'スタッフを追加' }}</h1>

<form method="POST" action="{{ $member->exists ? route('staff.update', $member) : route('staff.store') }}"
      data-draft="staff-{{ $member->id ?? 'new' }}"
      class="max-w-xl bg-white rounded-xl border border-stone-200 p-6 space-y-4">
    @csrf
    @if ($member->exists) @method('PUT') @endif

    <x-field label="名前" name="name" hint="スタッフ用のページとシフト表に出ます。名字だけ・ニックネームでもOKです">
        <input type="text" name="name" value="{{ old('name', $member->name) }}" required maxlength="50" placeholder="例:山田 はるか" class="w-full rounded-lg border border-stone-300 px-3 py-2">
    </x-field>
    <div class="grid sm:grid-cols-2 gap-4">
        <x-field label="担当(任意)" name="position" hint="両方できる人は「ホール・キッチン」のように書くと、どちらの条件にも数えます">
            <input type="text" name="position" value="{{ old('position', $member->position) }}" maxlength="30" list="positions" placeholder="例:ホール" class="w-full rounded-lg border border-stone-300 px-3 py-2">
            <datalist id="positions"><option value="ホール"><option value="キッチン"><option value="ホール・キッチン"></datalist>
        </x-field>
        <x-field label="週の上限(任意)" name="max_days_per_week" hint="学生さんの扶養内など。自動で作るときに守ります">
            <select name="max_days_per_week" class="w-full rounded-lg border border-stone-300 px-3 py-2 bg-white">
                <option value="">制限なし</option>
                @for ($i = 1; $i <= 6; $i++)
                    <option value="{{ $i }}" @selected((string) old('max_days_per_week', $member->max_days_per_week) === (string) $i)>週{{ $i }}日まで</option>
                @endfor
            </select>
        </x-field>
    </div>
    <x-field label="有給の残り日数" name="paid_leave_balance" hint="今持っている日数を入れてください。申請を承認すると1日ずつ減ります。新しく付与されたときや、期限で消えたときは、ここを書き換えてください">
        <div class="flex items-center gap-2">
            <input type="number" name="paid_leave_balance" value="{{ old('paid_leave_balance', $member->paid_leave_balance ?? 0) }}" min="0" max="60" class="w-24 rounded-lg border border-stone-300 px-3 py-2">
            <span class="text-sm">日</span>
        </div>
    </x-field>
    <x-field label="メモ(任意)" name="memo" hint="スタッフには見えません">
        <textarea name="memo" rows="2" maxlength="500" class="w-full rounded-lg border border-stone-300 px-3 py-2">{{ old('memo', $member->memo) }}</textarea>
    </x-field>
    <label class="flex items-center gap-2 text-sm">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $member->is_active))>
        在籍中(外すと、シフトの候補とスタッフ用ページから外れます)
    </label>

    <div class="flex flex-wrap gap-2 pt-2">
        <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-5 py-2.5">{{ $member->exists ? '保存する' : '登録する' }}</button>
        <a href="{{ route('staff.index') }}" data-discard class="rounded-lg border border-stone-300 bg-white px-5 py-2.5">キャンセル</a>
    </div>
</form>

@if ($member->exists)
    <section class="max-w-xl mt-6 bg-white rounded-xl border border-stone-200 p-6 space-y-3">
        <h2 class="font-bold">希望を出すページのURL</h2>
        <p class="text-sm text-stone-600">この人専用のURLです。ログインなしで、希望の提出と決まったシフトの確認ができます。</p>
        <input type="text" readonly value="{{ $member->portal_url }}" onclick="this.select()" class="w-full rounded-lg border border-stone-300 px-3 py-2 text-sm bg-stone-50 font-mono">
        <form method="POST" action="{{ route('staff.token', $member) }}" onsubmit="return confirm('URLを作り直すと、今のURLは使えなくなります。よろしいですか?')">
            @csrf
            <button class="text-sm text-stone-600 underline">URLを作り直す(他の人に知られたときなど)</button>
        </form>
    </section>

    <form method="POST" action="{{ route('staff.destroy', $member) }}" class="max-w-xl mt-6 text-right"
          onsubmit="return confirm('{{ $member->name }}さんを削除します。この人の希望とシフトも消えます。辞めた人は「在籍中」を外すだけにするのがおすすめです。削除しますか?')">
        @csrf @method('DELETE')
        <button class="text-sm text-alert-600 underline">このスタッフを削除する</button>
    </form>
@endif
@endsection
