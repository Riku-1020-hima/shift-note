<?php

namespace App\Models;

use Carbon\CarbonImmutable;

/** 日付まわりの小さな道具 */
final class Shift
{
    public const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    /** "2026-12" が正しい形ならそのまま、違えば null */
    public static function validMonth(?string $ym): ?string
    {
        return $ym && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym) ? $ym : null;
    }

    public static function firstDay(string $ym): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $ym);
    }

    /** その月の日付(定休日を除くこともできる) */
    public static function days(string $ym, ?User $exceptClosedOf = null): array
    {
        $first = self::firstDay($ym);
        $days = [];
        for ($d = $first; $d->month === $first->month; $d = $d->addDay()) {
            if ($exceptClosedOf && $exceptClosedOf->isClosedOn($d)) {
                continue;
            }
            $days[] = $d;
        }

        return $days;
    }

    public static function isWeekend(\DateTimeInterface $d): bool
    {
        return in_array((int) $d->format('w'), [0, 6], true);
    }

    /** 12/5(土) */
    public static function label(\DateTimeInterface $d): string
    {
        return $d->format('n/j').'('.self::WEEKDAYS[(int) $d->format('w')].')';
    }

    /** 2026年12月 */
    public static function monthLabel(string $ym): string
    {
        return self::firstDay($ym)->format('Y年n月');
    }
}
