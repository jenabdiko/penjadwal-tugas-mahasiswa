<?php

namespace App\Support;

use DateTimeInterface;
use Exception;

/** Format tanggal bahasa Indonesia, mis. "12 Okt 2026". */
class Tanggal
{
    private const BULAN = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];

    /** "12 Okt 2026" */
    public static function format(DateTimeInterface|string|null $date): string
    {
        $dt = self::parse($date);

        return $dt === null
            ? '-'
            : ((int) $dt->format('j')).' '.self::BULAN[(int) $dt->format('n')].' '.$dt->format('Y');
    }

    /** "12 Okt" */
    public static function singkat(DateTimeInterface|string|null $date): string
    {
        $dt = self::parse($date);

        return $dt === null
            ? '-'
            : ((int) $dt->format('j')).' '.self::BULAN[(int) $dt->format('n')];
    }

    /** "Okt 2026" */
    public static function bulan(DateTimeInterface|string|null $date): string
    {
        $dt = self::parse($date);

        return $dt === null ? '-' : self::BULAN[(int) $dt->format('n')].' '.$dt->format('Y');
    }

    private static function parse(DateTimeInterface|string|null $date): ?DateTimeInterface
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof DateTimeInterface) {
            return $date;
        }

        try {
            return new \DateTimeImmutable($date);
        } catch (Exception) {
            return null;
        }
    }
}
