<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** スタッフ(アルバイト・社員) */
class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = ['name', 'position', 'max_days_per_week', 'paid_leave_balance', 'is_active', 'memo'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'max_days_per_week' => 'integer', 'paid_leave_balance' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Staff $staff) {
            $staff->token ??= self::newToken();
        });
    }

    public static function newToken(): string
    {
        return Str::random(32);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** 希望を出すページのURL(ログイン不要。この人専用) */
    public function getPortalUrlAttribute(): string
    {
        return route('portal.show', $this->token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** 承認待ちの日数(残り日数から申請できる分を計算するのに使う) */
    public function pendingLeaveCount(): int
    {
        return $this->leaveRequests()->status('pending')->count();
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
