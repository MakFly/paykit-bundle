<?php

declare(strict_types=1);

namespace PayKit\Bundle\Catalog;

enum ResetInterval: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /**
     * Adds one interval, clamping month/year overflow to the last day of the target month
     * (Jan 31 + 1 month = Feb 28/29, not Mar 3).
     */
    public function addTo(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return match ($this) {
            self::Day => $date->modify('+1 day'),
            self::Week => $date->modify('+7 days'),
            self::Month => self::addMonths($date, 1),
            self::Year => self::addMonths($date, 12),
        };
    }

    /** Rolls $resetAt forward until it is strictly in the future relative to $now. */
    public function nextAfter(\DateTimeImmutable $resetAt, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $next = $resetAt;
        while ($next <= $now) {
            $next = $this->addTo($next);
        }

        return $next;
    }

    private static function addMonths(\DateTimeImmutable $date, int $months): \DateTimeImmutable
    {
        $day = (int) $date->format('j');
        $firstOfTarget = $date->setDate((int) $date->format('Y'), (int) $date->format('n'), 1)->modify(\sprintf('+%d months', $months));
        $daysInTarget = (int) $firstOfTarget->format('t');

        return $firstOfTarget->setDate((int) $firstOfTarget->format('Y'), (int) $firstOfTarget->format('n'), min($day, $daysInTarget));
    }
}
