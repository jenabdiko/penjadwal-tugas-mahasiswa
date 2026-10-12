<?php

namespace App\Services;

use App\Models\Plan;

/**
 * Status dihitung otomatis dari jadwal, progress, dan tanggal hari ini.
 * Status tidak pernah disimpan di database — selalu dihitung dari hasil
 * penjadwalan SchedulerService.
 *
 * Aturan task daun (dicek berurutan, berhenti pada aturan pertama yang cocok):
 *  1) progress = 100               -> Selesai
 *  2) hari ini > tanggal selesai   -> Terlambat
 *  3) progress > 0 ATAU tanggal mulai <= hari ini <= tanggal selesai -> Sedang Dikerjakan
 *  4) selain itu                   -> Belum Dimulai
 *
 * Task induk dihitung dari sub-tasknya; status rencana dihitung dari seluruh task.
 */
class StatusService
{
    /**
     * Hitung status seluruh task dan status rencana.
     *
     * @param array<string, mixed> $schedule hasil SchedulerService::schedule()
     * @return array{plan: string, tasks: array<int, string>}
     */
    public function compute(Plan $plan, array $schedule): array
    {
        $today = now()->toDateString();

        $childrenOf = [];
        $taskStatuses = [];
        $parentRows = [];

        foreach ($schedule['rows'] as $row) {
            if ($row['is_parent']) {
                $parentRows[] = $row;

                continue;
            }

            $taskStatuses[$row['id']] = $this->forLeaf($row, $today);

            $parentId = $row['task']->parent_id;
            if ($parentId !== null) {
                $childrenOf[$parentId][] = $taskStatuses[$row['id']];
            }
        }

        // Task induk memakai rollup dari sub-tasknya.
        foreach ($parentRows as $row) {
            $taskStatuses[$row['id']] = $this->forParent($childrenOf[$row['id']] ?? []);
        }

        return [
            'plan' => $this->planStatus($plan, array_values($taskStatuses), $today),
            'tasks' => $taskStatuses,
        ];
    }

    /** Status satu task daun dari baris jadwalnya. */
    private function forLeaf(array $row, string $today): string
    {
        if ((int) $row['progress'] >= 100) {
            return Plan::STATUS_DONE;
        }

        $start = $row['start_date'];
        $end = $row['end_date'];

        if ($end !== null && $today > $end) {
            return Plan::STATUS_LATE;
        }

        if ((int) $row['progress'] > 0
            || ($start !== null && $end !== null && $start <= $today && $today <= $end)) {
            return Plan::STATUS_DOING;
        }

        return Plan::STATUS_TODO;
    }

    /** Status task induk (ringkasan) dari daftar status sub-task. */
    private function forParent(array $childStatuses): string
    {
        if ($childStatuses === []) {
            return Plan::STATUS_TODO;
        }

        $allDone = count(array_filter($childStatuses, fn (string $s): bool => $s !== Plan::STATUS_DONE)) === 0;
        if ($allDone) {
            return Plan::STATUS_DONE;
        }

        if (in_array(Plan::STATUS_LATE, $childStatuses, true)) {
            return Plan::STATUS_LATE;
        }

        foreach ($childStatuses as $status) {
            if ($status === Plan::STATUS_DOING || $status === Plan::STATUS_DONE) {
                return Plan::STATUS_DOING;
            }
        }

        return Plan::STATUS_TODO;
    }

    /** Status rencana dari seluruh status task. */
    private function planStatus(Plan $plan, array $taskStatuses, string $today): string
    {
        if ($taskStatuses === []) {
            return Plan::STATUS_TODO;
        }

        $allDone = count(array_filter($taskStatuses, fn (string $s): bool => $s !== Plan::STATUS_DONE)) === 0;
        if ($allDone) {
            return Plan::STATUS_DONE;
        }

        if ($today > $plan->deadline->toDateString()) {
            return Plan::STATUS_LATE;
        }

        foreach ($taskStatuses as $status) {
            if ($status !== Plan::STATUS_TODO) {
                return Plan::STATUS_DOING;
            }
        }

        return Plan::STATUS_TODO;
    }
}
