<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** お店(店長のアカウント) */
class User extends Authenticatable
{
    use Notifiable;

    public const DEFAULT_APP_NAME = 'シフトノート';

    protected $fillable = ['name', 'closed_weekdays', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'closed_weekdays' => 'array',
        ];
    }

    public function getDisplayAppNameAttribute(): string
    {
        return self::DEFAULT_APP_NAME;
    }

    /** その日が定休日か */
    public function isClosedOn(\DateTimeInterface $date): bool
    {
        return in_array((int) $date->format('w'), array_map('intval', $this->closed_weekdays ?? []), true);
    }

    /** 定休日の表示(例:水曜) */
    public function getClosedLabelAttribute(): string
    {
        $days = collect($this->closed_weekdays ?? [])->map(fn ($d) => (int) $d)->sortBy(fn ($d) => ($d + 6) % 7);

        return $days->isEmpty() ? 'なし' : $days->map(fn ($d) => Shift::WEEKDAYS[$d])->implode('・').'曜';
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class)->orderBy('start_time')->orderBy('id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /** その月の状態(なければ作る) */
    public function scheduleFor(string $yearMonth): Schedule
    {
        return $this->schedules()->firstOrCreate(['year_month' => $yearMonth]);
    }
}
