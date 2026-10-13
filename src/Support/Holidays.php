<?php

namespace Arzcode\Sisifo\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads the host app's holidays table. The table is optional: when it — or
 * its date column — does not exist, no day is a holiday.
 *
 * @internal
 */
class Holidays
{
    public static function isToday(): bool
    {
        return (bool)self::fromToday()?->whereDate(self::column(), today())->exists();
    }

    /**
     * The closest holiday that has not passed yet, today included.
     */
    public static function next(): ?Carbon
    {
        $date = self::fromToday()?->min(self::column());

        return is_string($date) ? Carbon::parse($date)->startOfDay() : null;
    }

    /**
     * What the holidays mean for the scheduled tasks, for display in the panel.
     */
    public static function notice(): ?string
    {
        $next = self::next();

        if (! $next) {
            return null;
        }

        return __($next->isToday() ? 'sisifo::sisifo.holiday_today' : 'sisifo::sisifo.holiday_next', [
            'date' => $next->format('d/m/Y'),
        ]);
    }

    private static function fromToday(): ?Builder
    {
        $table = config('sisifo.holidays.table');
        $column = self::column();

        if (! is_string($table) || $table === '' || $column === '') {
            return null;
        }

        if (! Schema::hasColumn($table, $column)) {
            return null;
        }

        return DB::table($table)->whereDate($column, '>=', today());
    }

    private static function column(): string
    {
        $column = config('sisifo.holidays.date_column');

        return is_string($column) ? $column : '';
    }
}
