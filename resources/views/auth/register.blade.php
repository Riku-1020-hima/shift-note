@extends('layouts.app', ['title' => '新規登録'])

@section('content')
<div class="max-w-sm mx-auto mt-12">
    <h1 class="text-2xl font-bold text-center mb-6">お店を登録する</h1>

    <form method="POST" action="{{ route('register') }}" class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
        @csrf
        <x-field label="店名" name="name" hint="スタッフ用のページにも表示されます">
            <input type="text" name="name" value="{{ old('name') }}" required placeholder="例:キッチンさくら 渋谷店" class="w-full rounded-lg border-stone-300 border px-3 py-2">
        </x-field>
        <x-field label="メールアドレス" name="email">
            <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-lg border-stone-300 border px-3 py-2">
        </x-field>
        <x-field label="パスワード(8文字以上)" name="password">
            <input type="password" name="password" required class="w-full rounded-lg border-stone-300 border px-3 py-2">
        </x-field>
        <x-field label="パスワード(確認)" name="password_confirmation">
            <input type="password" name="password_confirmation" required class="w-full rounded-lg border-stone-300 border px-3 py-2">
        </x-field>
        <button class="w-full rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold py-2.5">登録してはじめる</button>
    </form>
    <p class="text-center text-sm mt-4"><a href="{{ route('login') }}" class="text-brand-600 underline">ログインはこちら</a></p>
</div>
@endsection
