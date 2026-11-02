<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** パスワード再設定メール(日本語) */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('【'.$notifiable->display_app_name.'】パスワード再設定のご案内')
            ->greeting($notifiable->name.' 様')
            ->line('パスワード再設定のお申し込みを受け付けました。下のボタンから新しいパスワードを設定してください。')
            ->action('パスワードを再設定する', $url)
            ->line("このリンクの有効期限は{$minutes}分です。")
            ->line('お心当たりがない場合は、このメールは破棄してください。パスワードは変更されません。')
            ->salutation($notifiable->display_app_name);
    }
}
