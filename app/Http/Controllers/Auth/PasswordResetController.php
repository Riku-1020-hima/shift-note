<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * パスワードを忘れたときの再設定
 * 1) メールアドレスを入れる → 2) 届いたメールのリンクを開く → 3) 新しいパスワードを決める
 */
class PasswordResetController extends Controller
{
    public function showForgot(): View|RedirectResponse
    {
        if (config('demo.enabled')) {
            return redirect()->route('login');
        }

        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput()->withErrors(['email' => '少し時間をおいてから、もう一度お試しください。']);
        }

        // 登録されていないメールアドレスでも同じ表示にする(誰が登録しているか分からないように)
        return back()->with('status', '入力したメールアドレスが登録されていれば、パスワード再設定のメールを送りました。メールのリンクから新しいパスワードを決めてください。');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password, // モデルで自動的にハッシュ化される
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'リンクの有効期限が切れているか、メールアドレスが違います。もう一度「パスワードを忘れた方」からやり直してください。',
            ]);
        }

        return redirect()->route('login')->with('status', 'パスワードを変更しました。新しいパスワードでログインしてください。');
    }
}
