@extends('layouts.app', ['title' => 'パスワードの再設定'])

@section('content')
<div class="max-w-sm mx-auto mt-12">
    <h1 class="text-2xl font-bold text-center mb-2">パスワードの再設定</h1>
    <p class="text-sm text-stone-500 text-center mb-6">登録したメールアドレスを入力してください。<br>新しいパスワードを決めるためのリンクをお送りします。</p>

    <form method="POST" action="{{ route('password.email') }}" class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
        @csrf
        <x-field label="メールアドレス" name="email">
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <button class="w-full rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold py-2.5">再設定のメールを送る</button>
    </form>

    @if (session('status') && app()->environment('local') && config('mail.default') === 'log')
        <p class="mt-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-xs leading-relaxed">
            開発中のため、メールは実際には送られず <code>storage/logs/laravel.log</code> に書き出されています。ログの中の「reset-password」で始まるリンクを開いてください。
        </p>
    @endif

    <p class="text-center text-sm mt-4"><a href="{{ route('login') }}" class="text-brand-600 underline">ログイン画面に戻る</a></p>
</div>
@endsection
