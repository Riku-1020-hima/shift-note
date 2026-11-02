<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // 公開デモでは、みんなが同じアカウントを使うのでログイン情報は変えられない
        $demo = config('demo.enabled');
        if ($demo) {
            $request->merge(['email' => $user->email]);
            foreach (['password', 'password_confirmation', 'current_password'] as $key) {
                $request->request->remove($key);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'closed_weekdays' => ['nullable', 'array'],
            'closed_weekdays.*' => ['integer', 'between:0,6'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => '現在のパスワードが違います。',
            'current_password.required_with' => 'パスワードを変えるときは、現在のパスワードも入力してください。',
        ], ['name' => '店名']);

        $user->fill([
            'name' => $data['name'],
            'closed_weekdays' => array_values(array_unique(array_map('intval', $data['closed_weekdays'] ?? []))),
            'email' => $data['email'],
        ]);
        if (! $demo && ! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return redirect()->route('settings.edit')->with('status', '設定を保存しました。');
    }
}
