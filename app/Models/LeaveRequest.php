<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 有給の申請(date は "2026-12-05" の文字列のまま扱う) */
class LeaveRequest extends Model
{
    public const STATUSES = [
        'pending' => '承認待ち',
        'approved' => '承認',
        'rejected' => '却下',
        'cancelled' => '取り消し',
    ];

    protected $fillable = ['staff_id', 'date', 'note', 'status', 'reply', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    /** まだ生きている申請(承認待ち・承認) */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', ['pending', 'approved']);
    }

    public function getDayAttribute(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->date);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
