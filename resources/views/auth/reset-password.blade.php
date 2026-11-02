@extends('layouts.app', ['title' => '新しいパスワード'])

@section('content')
<div class="max-w-sm mx-auto mt-12">
    <h1 class="text-2xl font-bold text-center mb-6">新しいパスワードを決める</h1>

    <form method="POST" action="{{ route('password.update') }}" class="bg-white rounded-xl border border-stone-200 p-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field label="メールアドレス" name="email">
            <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <x-field label="新しいパスワード(8文字以上)" name="password">
            <input type="password" name="password" required autofocus autocomplete="new-password" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <x-field label="新しいパスワード(確認)" name="password_confirmation">
            <input type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-lg border border-stone-300 px-3 py-2">
        </x-field>
        <button class="w-full rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-bold py-2.5">パスワードを変更する</button>
    </form>
</div>
@endsection
