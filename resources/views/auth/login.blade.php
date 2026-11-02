@extends('layouts.app', ['title' => 'ログイン'])

@section('content')
<div class="max-w-sm mx-auto mt-12">
    <div class="text-center mb-8">
        <x-app-logo size="w-12 h-12" />
        <h1 class="text-2xl font-bold mt-3">シフトノート</h1>
        <p class="text-sm text-stone-500 mt-1">希望集めからシフト作りまで、スマホでかんたんに。</p>
    </div>

    @if (config('demo.enabled'))
        <div class="mb-4 rounded-xl border border-brand-100 bg-brand-50 p-5 text-sm">
            <p class="font-bold text-brand-700">デモ版です。自由にさわってみてください</p>
            <p class="mt-1 text-stone-600">架空の飲食店のデータ(スタッフ10人)が入っています。データは毎日{{ (int) config('demo.reset_at') }}時(日本時間)に元に戻ります。</p>
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-stone-700">
                <dt class="text-stone-500">メール</dt><dd class="font-mono">{{ config('demo.email') }}</dd>
                <dt class="text-stone-500">パスワード</dt><dd class="font-mono">{{ config('demo.password') }}</dd>
            </dl>
            <form method="POST" action="{{ route('login') }}" class="mt-4">
                @csrf
                <input type="hidden" name="email" value="{{ config('demo.email') }}">
                <input type="hidden" name="password" value="{{ config('demo.password') }}">
                <button class="w-full rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold py-2.5">デモ用アカウントでログイン</button>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
        @csrf
        <x-field label="メールアドレス" name="email">
            <input type="email" name="email" value="{{ old('email') }}" required autofocus class="w-full rounded-lg border-stone-300 border px-3 py-2">
        </x-field>
        <x-field label="パスワード" name="password">
            <input type="password" name="password" required class="w-full rounded-lg border-stone-300 border px-3 py-2">
        </x-field>
        <div class="flex items-center justify-between gap-2 text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember"> ログインしたままにする</label>
            @unless (config('demo.enabled'))
                <a href="{{ route('password.request') }}" class="text-brand-600 underline">パスワードを忘れた方</a>
            @endunless
        </div>
        <button class="w-full rounded-lg {{ config('demo.enabled') ? 'border border-stone-300 hover:bg-stone-50' : 'bg-brand-500 hover:bg-brand-600 text-white' }} font-bold py-2.5">ログイン</button>
    </form>
    @unless (config('demo.enabled'))
        <p class="text-center text-sm mt-4">はじめての方は <a href="{{ route('register') }}" class="text-brand-600 underline">無料で登録</a></p>
    @endunless
</div>
@endsection
