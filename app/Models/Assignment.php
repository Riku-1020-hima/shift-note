<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** date は "2026-12-05" の文字列のまま扱う(データベースによる違いをなくすため) */
class Assignment extends Model
{
    protected $fillable = ['staff_id', 'time_slot_id', 'date'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }
}
