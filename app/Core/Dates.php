<?php

declare(strict_types=1);

namespace Sibol\Core;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Dates are stored in UTC and shown in the viewer's timezone
 * (user preference, else the school's, else Asia/Manila).
 */
final class Dates
{
    private const MONTHS = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    private const MONTHS_FIL = [1 => 'Enero', 'Pebrero', 'Marso', 'Abril', 'Mayo', 'Hunyo', 'Hulyo', 'Agosto', 'Setyembre', 'Oktubre', 'Nobyembre', 'Disyembre'];
    private const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    private const DAYS_FIL = ['Linggo', 'Lunes', 'Martes', 'Miyerkules', 'Huwebes', 'Biyernes', 'Sabado'];

    private static ?string $override = null;

    public static function setTimezone(?string $tz): void
    {
        self::$override = $tz;
    }

    public static function tz(): string
    {
        if (self::$override) {
            return self::$override;
        }
        $user = Auth::user();
        $tz = ($user['timezone'] ?? null) ?: (($user['org_timezone'] ?? null) ?: (string) Config::get('app.timezone', 'Asia/Manila'));
        return in_array($tz, DateTimeZone::listIdentifiers(), true) ? $tz : 'Asia/Manila';
    }

    public static function zone(): DateTimeZone
    {
        return new DateTimeZone(self::tz());
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::zone());
    }

    public static function local(?string $utc): ?DateTimeImmutable
    {
        if ($utc === null || $utc === '') {
            return null;
        }
        return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(self::zone());
    }

    /** Converts a value typed in the viewer's timezone ("2026-10-20T14:30" or "2026-10-20") to a UTC string. */
    public static function toUtc(?string $local, string $timeIfDateOnly = '23:59'): ?string
    {
        if ($local === null || trim($local) === '') {
            return null;
        }
        $local = trim($local);
        $dt = Validator::parseDateTime($local);
        if ($dt === null) {
            $d = Validator::parseDate($local);
            if ($d === null) {
                return null;
            }
            $dt = Validator::parseDateTime($local . 'T' . $timeIfDateOnly);
        }
        $inZone = new DateTimeImmutable($dt->format('Y-m-d H:i:s'), self::zone());
        return $inZone->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function format(?string $utc, string $style = 'date'): string
    {
        $d = self::local($utc);
        if ($d === null) {
            return '';
        }
        $fil = Translator::locale() === 'fil';
        $month = $fil ? self::MONTHS_FIL[(int) $d->format('n')] : self::MONTHS[(int) $d->format('n')];
        $monthShort = mb_substr($month, 0, 3);
        $day = $fil ? self::DAYS_FIL[(int) $d->format('w')] : self::DAYS[(int) $d->format('w')];
        $dayShort = mb_substr($day, 0, 3);
        $time = $d->format('g:i A');
        $year = $d->format('Y');
        $dom = $d->format('j');
        $thisYear = $year === self::now()->format('Y');

        switch ($style) {
            case 'time':
                return $time;
            case 'date_short':
                return $monthShort . ' ' . $dom . ($thisYear ? '' : ', ' . $year);
            case 'date_long':
                return $day . ', ' . $month . ' ' . $dom . ', ' . $year;
            case 'datetime':
                return $monthShort . ' ' . $dom . ', ' . $year . ', ' . $time;
            case 'datetime_short':
                return $dayShort . ', ' . $monthShort . ' ' . $dom . ', ' . $time;
            case 'input':
                return $d->format('Y-m-d\TH:i');
            case 'input_date':
                return $d->format('Y-m-d');
            case 'iso':
                return $d->format(DATE_ATOM);
            case 'date':
            default:
                return $dayShort . ', ' . $monthShort . ' ' . $dom . ($thisYear ? '' : ', ' . $year);
        }
    }

    /** "3 hours ago", "in 2 days", "yesterday". */
    public static function relative(?string $utc): string
    {
        $d = self::local($utc);
        if ($d === null) {
            return '';
        }
        $now = self::now();
        $diff = $d->getTimestamp() - $now->getTimestamp();
        $abs = abs($diff);
        $future = $diff > 0;
        if ($abs < 60) {
            return t('just now');
        }
        if ($abs < 3600) {
            $m = (int) round($abs / 60);
            return $future ? tp('in :count minute', 'in :count minutes', $m) : tp(':count minute ago', ':count minutes ago', $m);
        }
        $dayDiff = (int) $now->setTime(0, 0)->diff($d->setTime(0, 0))->format('%r%a');
        if ($dayDiff === 0) {
            $h = (int) round($abs / 3600);
            return $future ? tp('in :count hour', 'in :count hours', $h) : tp(':count hour ago', ':count hours ago', $h);
        }
        if ($dayDiff === -1) {
            return t('yesterday');
        }
        if ($dayDiff === 1) {
            return t('tomorrow');
        }
        if (abs($dayDiff) < 7) {
            return $dayDiff > 0 ? tp('in :count day', 'in :count days', $dayDiff) : tp(':count day ago', ':count days ago', -$dayDiff);
        }
        return self::format($utc, 'date_short');
    }

    /**
     * Human due label with a tone: coral when overdue, amber when due within two days.
     * @return array{label:string,tone:string,overdue:bool}
     */
    public static function due(?string $utc): array
    {
        $d = self::local($utc);
        if ($d === null) {
            return ['label' => t('No due date'), 'tone' => 'neutral', 'overdue' => false];
        }
        $now = self::now();
        $seconds = $d->getTimestamp() - $now->getTimestamp();
        $dayDiff = (int) $now->setTime(0, 0)->diff($d->setTime(0, 0))->format('%r%a');
        $time = $d->format('g:i A');
        if ($seconds < 0) {
            $late = $now->setTime(0, 0)->diff($d->setTime(0, 0))->days;
            $label = $late === 0 ? t('Was due today at :time', ['time' => $time]) : tp('Overdue by :count day', 'Overdue by :count days', (int) $late);
            return ['label' => $label, 'tone' => 'coral', 'overdue' => true];
        }
        if ($dayDiff === 0) {
            return ['label' => t('Due today at :time', ['time' => $time]), 'tone' => 'amber', 'overdue' => false];
        }
        if ($dayDiff === 1) {
            return ['label' => t('Due tomorrow at :time', ['time' => $time]), 'tone' => 'amber', 'overdue' => false];
        }
        if ($dayDiff < 7) {
            return ['label' => t('Due :day at :time', ['day' => self::format($utc, 'date'), 'time' => $time]), 'tone' => $dayDiff <= 2 ? 'amber' : 'neutral', 'overdue' => false];
        }
        return ['label' => t('Due :date', ['date' => self::format($utc, 'date')]), 'tone' => 'neutral', 'overdue' => false];
    }

    /** Parts for the "entry date" margin device. @return array{weekday:string,day:string,month:string,iso:string} */
    public static function parts(?string $utc): array
    {
        $d = self::local($utc) ?? self::now();
        $fil = Translator::locale() === 'fil';
        $weekday = $fil ? self::DAYS_FIL[(int) $d->format('w')] : self::DAYS[(int) $d->format('w')];
        $month = $fil ? self::MONTHS_FIL[(int) $d->format('n')] : self::MONTHS[(int) $d->format('n')];
        return [
            'weekday' => mb_substr($weekday, 0, 3),
            'day' => $d->format('j'),
            'month' => mb_substr($month, 0, 3),
            'iso' => $d->format(DATE_ATOM),
        ];
    }

    public static function monthName(int $month): string
    {
        return Translator::locale() === 'fil' ? self::MONTHS_FIL[$month] : self::MONTHS[$month];
    }

    /** @return list<string> Short weekday names starting Monday. */
    public static function weekdayInitials(): array
    {
        $names = Translator::locale() === 'fil' ? self::DAYS_FIL : self::DAYS;
        $order = [1, 2, 3, 4, 5, 6, 0];
        return array_map(static fn ($i) => mb_substr($names[$i], 0, 3), $order);
    }

    public static function startOfTodayUtc(): string
    {
        return self::now()->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function startOfMonthUtc(): string
    {
        return self::now()->modify('first day of this month')->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function greeting(): string
    {
        $hour = (int) self::now()->format('G');
        if ($hour < 12) {
            return t('Good morning');
        }
        if ($hour < 18) {
            return t('Good afternoon');
        }
        return t('Good evening');
    }
}
