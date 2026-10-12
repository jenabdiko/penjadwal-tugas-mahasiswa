<?php

namespace App\Support;

/**
 * Teks evaluasi deadline.
 *
 * SATU-satunya sumber teks untuk semua tampilan yang menyebut keterlambatan
 * atau cadangan (Beranda, tab Ringkasan, tab Jalur Kritis), sehingga angka
 * hari kerja yang tampil selalu berasal dari SchedulerService dan tidak pernah
 * dihitung ulang oleh view.
 */
class Evaluasi
{
    /**
     * @param array{on_time: bool, days: int, message: string, detail: string} $eval
     *        hasil SchedulerService::schedule() → schedule['project']['deadline']
     *
     * @return string "✓ Jadwal tepat waktu · cadangan 2 hari kerja"
     *                "⚠ Melewati deadline · terlambat 3 hari kerja"
     */
    public static function teks(array $eval): string
    {
        return $eval['on_time']
            ? '✓ Jadwal tepat waktu · cadangan '.$eval['days'].' hari kerja'
            : '⚠ Melewati deadline · terlambat '.$eval['days'].' hari kerja';
    }
}
