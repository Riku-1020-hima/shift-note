@extends('layouts.app', ['title' => '設定'])

@use('App\Models\Shift')

@section('content')
<h1 class="text-2xl font-bold mb-5">設定</h1>

<form method="POST" action="{{ route('settings.update') }}" class="max-w-2xl space-y-5">
    @csrf @method('PUT')

    <section data-tour="set-store" class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
        <h2 class="font-bold">お店</h2>
        <x-field label="店名" name="name" hint="画面の上と、スタッフ用のページに出ます">
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="100" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <div>
            <span class="block text-sm font-medium mb-2">定休日</span>
            @php $closed = array_map('intval', old('closed_weekdays', $user->closed_weekdays ?? [])); @endphp
            <div class="flex flex-wrap gap-2">
                @foreach ([1, 2, 3, 4, 5, 6, 0] as $d)
                    <label>
                        <input type="checkbox" name="closed_weekdays[]" value="{{ $d }}" class="peer sr-only" @checked(in_array($d, $closed, true))>
                        <span class="inline-flex w-11 h-11 items-center justify-center rounded-lg border border-stone-300 bg-white text-sm cursor-pointer select-none peer-checked:bg-ink peer-checked:text-white peer-checked:border-ink peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40">{{ Shift::WEEKDAYS[$d] }}</span>
                    </label>
                @endforeach
            </div>
            <span class="block text-xs text-stone-500 mt-2">選んだ曜日はシフト表に出ず、スタッフも希望を出しません</span>
            @error('closed_weekdays')<span class="block text-xs text-alert-600 mt-1">{{ $message }}</span>@enderror
        </div>
    </section>

    @if (config('demo.enabled'))
        <section data-tour="set-login" class="bg-white rounded-xl border border-stone-200 p-6 space-y-2">
            <h2 class="font-bold">ログイン情報</h2>
            <p class="text-sm text-stone-500">デモ版では、みんなが同じアカウントを使うため、メールアドレスとパスワードは変えられません。</p>
        </section>
    @else
        <section data-tour="set-login" class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
            <h2 class="font-bold">ログイン情報</h2>
            <x-field label="メールアドレス" name="email">
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full rounded-lg border border-stone-300 px-3 py-2">
            </x-field>
            <div class="pt-2 border-t border-stone-100">
                <p class="text-sm text-stone-500 mb-3">パスワードを変えるときだけ入力してください。</p>
                <div class="grid sm:grid-cols-3 gap-3">
                    <x-field label="現在のパスワード" name="current_password">
                        <input type="password" name="current_password" autocomplete="current-password" class="w-full rounded-lg border border-stone-300 px-3 py-2">
                    </x-field>
                    <x-field label="新しいパスワード" name="password">
                        <input type="password" name="password" autocomplete="new-password" class="w-full rounded-lg border border-stone-300 px-3 py-2">
                    </x-field>
                    <x-field label="新しいパスワード(確認)" name="password_confirmation">
                        <input type="password" name="password_confirmation" autocomplete="new-password" class="w-full rounded-lg border border-stone-300 px-3 py-2">
                    </x-field>
                </div>
            </div>
        </section>
    @endif

    <button class="rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold px-6 py-2.5">保存する</button>
</form>
@endsection
