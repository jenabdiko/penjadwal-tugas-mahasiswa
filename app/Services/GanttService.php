<?php

namespace App\Services;

use App\Models\Plan;
use App\Support\Tanggal;
use DateTimeImmutable;

/** Menyiapkan data geometri Gantt Chart: kolom hari, batang task, dan panah dependency.
 *  Posisi dalam satuan kolom hari; lebar kolom hari diatur oleh CSS/JS (--col-width). */
class GanttService
{
    /** Lebar kolom hari minimum (px) — dijaga oleh JS pada saat resize. */
    public const COL_WIDTH = 36;

    /** Batas jumlah kolom hari sebagai pengaman. */
    private const MAX_DAYS = 1200;

    /**
     * @param array<string, mixed> $schedule hasil SchedulerService::schedule()
     * @return array{
     *     days: list<array>, width: int,
     *     bars: array<int, array>, arrows: list<array>,
     *     deadline: int, today: int|null
     * }
     */
    public function build(Plan $plan, array $schedule): array
    {
        $start = $plan->start_date->toDateString();
        $end = (new DateTimeImmutable(max(
            $schedule['project']['end_date'],
            $plan->deadline->toDateString()
        )))->modify('+2 days')->format('Y-m-d');

        $holidaySet = [];
        foreach ($plan->holidays as $holiday) {
            $holidaySet[$holiday->date->toDateString()] = true;
        }

        // Kolom hari dari tanggal mulai sampai akhir timeline.
        $days = [];
        $indexOf = [];
        $today = now()->toDateString();
        $current = $start;
        $guard = 0;
        while ($current <= $end && $guard++ < self::MAX_DAYS) {
            $weekday = (int) (new DateTimeImmutable($current))->format('N');
            $days[] = [
                'date' => $current,
                'day' => (int) (new DateTimeImmutable($current))->format('j'),
                'month' => Tanggal::bulan($current),
                'is_weekend' => ($weekday === 6 && $plan->off_saturday)
                    || ($weekday === 7 && $plan->off_sunday),
                'is_holiday' => isset($holidaySet[$current]),
                'is_today' => $current === $today,
            ];
            $indexOf[$current] = count($days) - 1;
            $current = (new DateTimeImmutable($current))->modify('+1 day')->format('Y-m-d');
        }
        $lastIndex = max(0, count($days) - 1);

        $positionOf = function (string $date) use ($indexOf, $lastIndex): int {
            if (isset($indexOf[$date])) {
                return $indexOf[$date];
            }

            return $date < $start ? 0 : $lastIndex;
        };

        // Batang per task (task induk memakai bar ringkasan).
        $bars = [];
        foreach ($schedule['rows'] as $row) {
            if ($row['start_date'] === null || $row['end_date'] === null) {
                continue;
            }
            $from = $positionOf($row['start_date']);
            $to = $positionOf($row['end_date']);
            $bars[$row['id']] = [
                'id' => $row['id'],
                'left' => $from,
                'width' => $to - $from + 1,
                'progress' => $row['progress'],
                'critical' => $row['is_critical'],
                'summary' => $row['is_parent'],
                'title' => implode("\n", [
                    $row['task']->name,
                    Tanggal::format($row['start_date']).' - '.Tanggal::format($row['end_date']),
                    'Durasi: '.$row['duration'].' hari kerja',
                    'Progress: '.$row['progress'].'%',
                    'Penanggung jawab: '.$row['members'],
                ]),
            ];
        }

        // Panah dependency di antara task yang punya batang.
        $arrows = [];
        foreach ($plan->tasks()->with('dependencies')->get() as $task) {
            foreach ($task->dependencies as $dep) {
                if (!isset($bars[$dep->predecessor_id], $bars[$task->id])) {
                    continue;
                }
                $arrows[] = [
                    'from' => $dep->predecessor_id,
                    'to' => $task->id,
                    'type' => $dep->type,
                    'lag' => $dep->lag,
                ];
            }
        }

        return [
            'days' => $days,
            'col_width' => self::COL_WIDTH,
            'width' => count($days) * self::COL_WIDTH,
            'bars' => $bars,
            'arrows' => $arrows,
            'deadline' => $positionOf($plan->deadline->toDateString()),
            'today' => $indexOf[$today] ?? null,
        ];
    }
}
