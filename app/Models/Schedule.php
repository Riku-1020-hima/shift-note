<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** 月ごとの状態 */
class Schedule extends Model
{
    protected $fillable = ['year_month', 'deadline', 'published_at'];

    protected function casts(): array
    {
        return ['deadline' => 'date', 'published_at' => 'datetime'];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
