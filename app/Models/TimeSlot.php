<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 時間帯(ランチ 11:00〜15:00 など) */
class TimeSlot extends Model
{
    protected $fillable = ['name', 'symbol', 'start_time', 'end_time', 'weekday_required', 'weekend_required', 'position_rules'];

    protected function casts(): array
    {
        return ['weekday_required' => 'integer', 'weekend_required' => 'integer', 'position_rules' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStartAttribute(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }

    public function getEndAttribute(): string
    {
        return substr((string) $this->end_time, 0, 5);
    }

    /** 印刷用の表に出す記号(未設定なら名前の1文字目) */
    public function getMarkAttribute(): string
    {
        return $this->symbol ?: mb_substr($this->name, 0, 1);
    }

    /** 11:00〜15:00 */
    public function getTimeLabelAttribute(): string
    {
        return $this->start.'〜'.$this->end;
    }

    /** その日に必要な人数 */
    public function requiredOn(\DateTimeInterface $d): int
    {
        return Shift::isWeekend($d) ? $this->weekend_required : $this->weekday_required;
    }

    /**
     * 担当の条件(例:キッチン最低1人)
     * 担当の欄にその言葉を含む人が当てはまる(「ホール・キッチン」はホールにもキッチンにも数える)
     *
     * @return list<array{position: string, min: int}>
     */
    public function rules(): array
    {
        return collect($this->position_rules ?? [])
            ->filter(fn ($r) => trim((string) ($r['position'] ?? '')) !== '' && (int) ($r['min'] ?? 0) > 0)
            ->map(fn ($r) => ['position' => trim($r['position']), 'min' => (int) $r['min']])
            ->values()->all();
    }

    public static function matches(?string $staffPosition, string $rulePosition): bool
    {
        return $staffPosition !== null && $rulePosition !== '' && mb_strpos($staffPosition, $rulePosition) !== false;
    }

    /**
     * 条件が足りていない分(例:[['position' => 'キッチン', 'have' => 0, 'min' => 1]])
     *
     * @param  iterable<Staff|null>  $people  その枠に入っている人
     */
    public function ruleShortfalls(iterable $people): array
    {
        $people = collect($people)->filter();
        $out = [];
        foreach ($this->rules() as $r) {
            $have = $people->filter(fn ($m) => self::matches($m->position, $r['position']))->count();
            if ($have < $r['min']) {
                $out[] = $r + ['have' => $have];
            }
        }

        return $out;
    }

    /** 「キッチン1人以上・ホール2人以上」 */
    public function getRulesLabelAttribute(): string
    {
        return collect($this->rules())->map(fn ($r) => $r['position'].$r['min'].'人以上')->implode('・');
    }

    /** 時間が重なるか(同じ人を同じ日に入れられるかの判定に使う) */
    public function overlaps(TimeSlot $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }
}
