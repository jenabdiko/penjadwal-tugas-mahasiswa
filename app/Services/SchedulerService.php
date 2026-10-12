<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Task;
use App\Models\TaskDependency;
use DateTimeImmutable;

/**
 * Perhitungan jadwal ala CPM (Critical Path Method) untuk satu rencana.
 *
 * Service ini murni perhitungan: menerima plan beserta task, dependency,
 * dan hari libur, tanpa mengakses request HTTP.
 */
class SchedulerService
{
    /** Batas jumlah jalur yang dievaluasi agar perhitungan tetap ringan. */
    private const MAX_PATHS = 2000;

    /** Batas iterasi pencarian hari kerja supaya tidak pernah loop tak berujung. */
    private const MAX_ITERATION = 200000;

    private Plan $plan;

    /** @var array<string, true> kumpulan tanggal hari libur (Y-m-d) */
    private array $holidaySet = [];

    /** @var list<string> daftar hari kerja (Y-m-d) sejak hari kerja pertama rencana */
    private array $workingDays = [];

    /**
     * Hitung seluruh jadwal rencana: tanggal task, ES/EF/LS/LF, slack,
     * critical path, jalur, rollup task induk, dan evaluasi deadline.
     *
     * @return array{
     *     tasks: array<int, array>,
     *     parents: array<int, array>,
     *     numbers: array<int, int>,
     *     rows: list<array>,
     *     project: array
     * }
     */
    public function schedule(Plan $plan): array
    {
        $this->plan = $plan;
        $this->workingDays = [];
        $this->holidaySet = [];
        foreach ($plan->holidays as $holiday) {
            $this->holidaySet[$holiday->date->toDateString()] = true;
        }

        $all = $plan->tasks()->with(['dependencies', 'members'])->orderBy('sort_order')->get();
        $byId = $all->keyBy('id');

        // ---------------------------------------------------------------
        // Penomoran tampilan: task induk, lalu sub-tasknya (hirarki 1 tingkat)
        // ---------------------------------------------------------------
        $childrenOf = $all->whereNotNull('parent_id')->groupBy('parent_id');
        $ordered = [];
        $numbers = [];
        $placed = [];
        $n = 0;
        foreach ($all->whereNull('parent_id') as $root) {
            $ordered[] = $root;
            $numbers[$root->id] = ++$n;
            $placed[$root->id] = true;
            foreach ($childrenOf[$root->id] ?? [] as $child) {
                $ordered[] = $child;
                $numbers[$child->id] = ++$n;
                $placed[$child->id] = true;
            }
        }
        foreach ($all as $task) { // task yatim tetap tampil agar tidak hilang
            if (!isset($placed[$task->id])) {
                $ordered[] = $task;
                $numbers[$task->id] = ++$n;
            }
        }

        // Task daun = task tanpa sub-task; hanya task daun yang dijadwalkan.
        $parentsById = [];
        foreach ($all as $task) {
            if (($childrenOf[$task->id] ?? collect())->isNotEmpty()) {
                $parentsById[$task->id] = true;
            }
        }
        $leafIds = [];
        foreach ($ordered as $task) {
            if (!isset($parentsById[$task->id])) {
                $leafIds[] = $task->id;
            }
        }
        $leafSet = array_fill_keys($leafIds, true);

        // ---------------------------------------------------------------
        // Graf dependency di antara task daun
        // ---------------------------------------------------------------
        $incoming = [];
        $outgoing = [];
        foreach ($all as $task) {
            if (!isset($leafSet[$task->id])) {
                continue;
            }
            foreach ($task->dependencies as $dep) {
                if (!isset($leafSet[$dep->predecessor_id]) || $dep->predecessor_id === $task->id) {
                    continue; // pertahanan: induk tidak jadi predecessor, tanpa self-loop
                }
                $incoming[$task->id][] = $dep;
                $outgoing[$dep->predecessor_id][] = $dep;
            }
        }

        // ---------------------------------------------------------------
        // Forward pass: ES dan EF
        // ---------------------------------------------------------------
        $order = $this->topologicalOrder($leafIds, $incoming, $outgoing);
        $es = [];
        $ef = [];
        foreach ($order as $id) {
            $task = $byId[$id];
            // ES awal = 1, atau nomor hari kerja pertama pada/setelah earliest_start
            $start = 1;
            if ($task->earliest_start !== null) {
                $start = $this->firstWorkingNumber($task->earliest_start->toDateString());
            }
            foreach ($incoming[$id] ?? [] as $dep) {
                $pid = $dep->predecessor_id;
                if (!isset($ef[$pid])) {
                    continue; // pertahanan bila data siklus lolos validasi
                }
                $required = match ($dep->type) {
                    TaskDependency::TYPE_SS => $es[$pid] + $dep->lag,
                    TaskDependency::TYPE_FF => $ef[$pid] + $dep->lag - $task->duration + 1,
                    TaskDependency::TYPE_SF => $es[$pid] + $dep->lag - $task->duration + 1,
                    default => $ef[$pid] + 1 + $dep->lag, // FS
                };
                $start = max($start, $required);
            }
            $es[$id] = max(1, $start);
            $ef[$id] = $es[$id] + $task->duration - 1;
        }

        // ---------------------------------------------------------------
        // Backward pass: LF, LS, slack
        // ---------------------------------------------------------------
        $projectEndNumber = $ef === [] ? 0 : max($ef);
        $lf = [];
        $ls = [];
        foreach (array_reverse($order) as $id) {
            $task = $byId[$id];
            $limit = null;
            foreach ($outgoing[$id] ?? [] as $dep) {
                $sid = $dep->task_id;
                if (!isset($lf[$sid])) {
                    continue; // successor belum dihitung (data siklus lolos)
                }
                $allowed = match ($dep->type) {
                    TaskDependency::TYPE_SS => $ls[$sid] - $dep->lag + $task->duration - 1,
                    TaskDependency::TYPE_FF => $lf[$sid] - $dep->lag,
                    TaskDependency::TYPE_SF => $lf[$sid] - $dep->lag + $task->duration - 1,
                    default => $ls[$sid] - 1 - $dep->lag, // FS
                };
                $limit = $limit === null ? $allowed : min($limit, $allowed);
            }
            // Task tanpa successor: LF = akhir proyek (E)
            $lf[$id] = $limit ?? $projectEndNumber;
            $ls[$id] = $lf[$id] - $task->duration + 1;
        }

        $taskInfo = [];
        foreach ($leafIds as $id) {
            $slack = $ls[$id] - $es[$id];
            $taskInfo[$id] = [
                'number' => $numbers[$id],
                'duration' => $byId[$id]->duration,
                'progress' => $byId[$id]->progress,
                'es' => $es[$id],
                'ef' => $ef[$id],
                'ls' => $ls[$id],
                'lf' => $lf[$id],
                'slack' => $slack,
                'is_critical' => $slack === 0,
                'start_date' => $this->dateAt($es[$id]),
                'end_date' => $this->dateAt($ef[$id]),
                'ls_date' => $this->dateAt($ls[$id]),
                'lf_date' => $this->dateAt($lf[$id]),
            ];
        }

        // ---------------------------------------------------------------
        // Rollup task induk dari sub-tasknya
        // ---------------------------------------------------------------
        $parents = [];
        foreach ($parentsById as $id => $true) {
            $childInfos = [];
            foreach (($childrenOf[$id] ?? collect()) as $child) {
                if (isset($taskInfo[$child->id])) {
                    $childInfos[] = $taskInfo[$child->id];
                } elseif (isset($parents[$child->id])) { // pertahanan bila nesting > 1 tingkat lolos
                    $childInfos[] = $parents[$child->id];
                }
            }
            if ($childInfos === []) {
                continue;
            }
            $start = min(array_column($childInfos, 'start_date'));
            $end = max(array_column($childInfos, 'end_date'));

            // Progress induk = rata-rata sub-task berbobot durasi
            $weight = 0;
            $total = 0;
            foreach ($childInfos as $info) {
                $weight += $info['duration'];
                $total += $info['duration'] * $info['progress'];
            }

            $parents[$id] = [
                'number' => $numbers[$id],
                'duration' => $this->countWorkingDays($start, $end),
                'progress' => $weight > 0 ? (int) round($total / $weight) : 0,
                'is_critical' => collect($childInfos)->contains(fn (array $i): bool => $i['is_critical']),
                'start_date' => $start,
                'end_date' => $end,
                'es' => null,
                'ef' => null,
                'ls' => null,
                'lf' => null,
                'slack' => null,
            ];
        }

        // ---------------------------------------------------------------
        // Jalur dari task awal ke task akhir
        // ---------------------------------------------------------------
        $starts = array_values(array_filter($leafIds, fn (int $id): bool => !isset($incoming[$id])));
        $ends = array_values(array_filter($leafIds, fn (int $id): bool => !isset($outgoing[$id])));
        $paths = $this->enumeratePaths($starts, $ends, $outgoing, $numbers, $byId);
        usort($paths, fn (array $a, array $b): int => $b['total'] <=> $a['total'] ?: strcmp($a['label'], $b['label']));
        $maxTotal = $paths === [] ? 0 : $paths[0]['total'];
        $criticalPaths = array_values(array_filter($paths, fn (array $p): bool => $p['total'] === $maxTotal));

        // ---------------------------------------------------------------
        // Data proyek dan evaluasi deadline
        // ---------------------------------------------------------------
        if ($taskInfo !== []) {
            $minEs = min(array_column($taskInfo, 'es'));
            $projectStart = $this->dateAt($minEs);
            $projectEnd = $this->dateAt($projectEndNumber);
            $totalWorkingDays = $this->countWorkingDays($projectStart, $projectEnd);
        } else {
            $projectStart = $plan->start_date->toDateString();
            $projectEnd = $plan->start_date->toDateString();
            $totalWorkingDays = 0;
        }

        $deadline = $plan->deadline->toDateString();
        if ($projectEnd <= $deadline) {
            $spare = $this->countWorkingDays($this->nextDay($projectEnd), $deadline);
            $deadlineEval = [
                'on_time' => true,
                'message' => '✓ Jadwal dapat selesai tepat waktu.',
                'detail' => "Cadangan waktu: {$spare} hari kerja",
                'days' => $spare,
            ];
        } else {
            $late = $this->countWorkingDays($this->nextDay($deadline), $projectEnd);
            $deadlineEval = [
                'on_time' => false,
                'message' => '⚠ Jadwal pengerjaan melewati deadline.',
                'detail' => "Terlambat {$late} hari kerja",
                'days' => $late,
            ];
        }

        $hasNonFs = false;
        foreach ($all as $task) {
            foreach ($task->dependencies as $dep) {
                if ($dep->type !== TaskDependency::TYPE_FS || $dep->lag > 0) {
                    $hasNonFs = true;
                    break 2;
                }
            }
        }

        return [
            'tasks' => $taskInfo,
            'parents' => $parents,
            'numbers' => $numbers,
            'rows' => $this->buildRows($ordered, $numbers, $taskInfo, $parents, $childrenOf),
            'project' => [
                'start_date' => $projectStart,
                'end_date' => $projectEnd,
                'total_working_days' => $totalWorkingDays,
                'paths' => $paths,
                'critical_paths' => $criticalPaths,
                'critical_task_ids' => array_keys(array_filter($taskInfo, fn (array $i): bool => $i['is_critical'])),
                'max_path_total' => $maxTotal,
                'has_non_fs_or_lag' => $hasNonFs,
                'deadline' => $deadlineEval,
            ],
        ];
    }

    /**
     * Baris tampilan tabel task: nomor, tanggal, durasi, predecessor,
     * penanggung jawab, dan penanda kritis.
     *
     * @return list<array>
     */
    private function buildRows($ordered, array $numbers, array $taskInfo, array $parents, $childrenOf): array
    {
        $rows = [];
        foreach ($ordered as $task) {
            $isParent = isset($parents[$task->id]);
            $info = $isParent ? $parents[$task->id] : ($taskInfo[$task->id] ?? null);
            if ($info === null) {
                continue;
            }

            // Predecessor ditulis "2 (FS)" atau "5 (SS+1)" memakai nomor tampilan.
            $predParts = [];
            if (!$isParent) {
                foreach ($task->dependencies as $dep) {
                    $no = $numbers[$dep->predecessor_id] ?? '?';
                    $predParts[] = $no.' ('.$dep->type.($dep->lag > 0 ? '+'.$dep->lag : '').')';
                }
            }

            // Task induk memakai gabungan penanggung jawab sub-tasknya.
            if ($isParent) {
                $names = [];
                foreach (($childrenOf[$task->id] ?? collect()) as $child) {
                    foreach ($child->members as $member) {
                        $names[$member->id] = $member->name;
                    }
                }
                sort($names);
                $memberNames = array_values($names);
            } else {
                $memberNames = $task->members->pluck('name')->all();
            }

            $rows[] = [
                'task' => $task,
                'id' => $task->id,
                'number' => $numbers[$task->id],
                'level' => $task->parent_id ? 1 : 0,
                'is_parent' => $isParent,
                'start_date' => $info['start_date'],
                'end_date' => $info['end_date'],
                'duration' => $info['duration'],
                'progress' => $info['progress'],
                'is_critical' => $info['is_critical'],
                'predecessor_label' => $predParts === [] ? '-' : implode(', ', $predParts),
                'members' => implode(', ', $memberNames) ?: '-',
            ];
        }

        return $rows;
    }

    /**
     * Susun urutan topologis task daun (Kahn). Sisa node yang terdeteksi
     * siklus tetap dimasukkan apa adanya agar perhitungan tetap selesai.
     *
     * @param list<int> $ids
     * @param array<int, list<TaskDependency>> $incoming
     * @param array<int, list<TaskDependency>> $outgoing
     * @return list<int>
     */
    private function topologicalOrder(array $ids, array $incoming, array $outgoing): array
    {
        $indegree = [];
        foreach ($ids as $id) {
            $indegree[$id] = count($incoming[$id] ?? []);
        }

        $queue = array_values(array_filter($ids, fn (int $id): bool => $indegree[$id] === 0));
        $order = [];
        $done = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            if (isset($done[$id])) {
                continue;
            }
            $done[$id] = true;
            $order[] = $id;
            foreach ($outgoing[$id] ?? [] as $dep) {
                $succ = $dep->task_id;
                if (!isset($indegree[$succ]) || isset($done[$succ])) {
                    continue;
                }
                $indegree[$succ]--;
                if ($indegree[$succ] === 0) {
                    $queue[] = $succ;
                }
            }
        }

        foreach ($ids as $id) {
            if (!isset($done[$id])) {
                $order[] = $id;
            }
        }

        return $order;
    }

    /**
     * Enumerasi semua jalur task awal -> task akhir.
     *
     * @param list<int> $starts
     * @param list<int> $ends
     * @param array<int, list<TaskDependency>> $outgoing
     * @param array<int, int> $numbers
     * @return list<array{ids: list<int>, label: string, total: int}>
     */
    private function enumeratePaths(array $starts, array $ends, array $outgoing, array $numbers, $byId): array
    {
        $paths = [];
        $endSet = array_fill_keys($ends, true);
        $isEnd = fn (int $id): bool => isset($endSet[$id]);

        $walk = function (int $id, array $path) use (&$walk, &$paths, $outgoing, $isEnd, $numbers, $byId): void {
            if (count($paths) >= self::MAX_PATHS) {
                return;
            }
            if ($isEnd($id)) {
                $total = 0;
                $labels = [];
                foreach ($path as $taskId) {
                    $total += $byId[$taskId]->duration;
                    $labels[] = $numbers[$taskId] ?? '?';
                }
                $paths[] = [
                    'ids' => $path,
                    'label' => implode('-', $labels),
                    'total' => $total,
                ];

                return;
            }
            $nextIds = [];
            foreach ($outgoing[$id] ?? [] as $dep) {
                $nextIds[] = $dep->task_id;
            }
            if ($nextIds === []) {
                return;
            }
            foreach (array_unique($nextIds) as $next) {
                if (in_array($next, $path, true)) {
                    continue; // cegah jalur melingkar
                }
                $walk($next, [...$path, $next]);
            }
        };

        foreach ($starts as $start) {
            if (count($paths) >= self::MAX_PATHS) {
                break;
            }
            $walk($start, [$start]);
        }

        return $paths;
    }

    // ------------------------------------------------------------------
    // Hari kerja
    // ------------------------------------------------------------------

    /** Apakah tanggal ini hari kerja? (Sabtu/Minggu opsional + hari libur khusus) */
    private function isWorkingDay(string $date): bool
    {
        $weekday = (int) (new DateTimeImmutable($date))->format('N');
        if ($weekday === 6 && $this->plan->off_saturday) {
            return false;
        }
        if ($weekday === 7 && $this->plan->off_sunday) {
            return false;
        }

        return !isset($this->holidaySet[$date]);
    }

    /** Tambah satu hari kerja ke daftar; false bila gagal (pengaman). */
    private function advance(): bool
    {
        $candidate = $this->workingDays === []
            ? $this->plan->start_date->toDateString()
            : $this->nextDay(end($this->workingDays));

        $guard = 0;
        while (!$this->isWorkingDay($candidate) && $guard++ < self::MAX_ITERATION) {
            $candidate = $this->nextDay($candidate);
        }
        if ($guard >= self::MAX_ITERATION) {
            return false;
        }
        $this->workingDays[] = $candidate;

        return true;
    }

    /** Konversi nomor hari kerja (1-based) menjadi tanggal. */
    private function dateAt(int $number): string
    {
        if ($number < 1) {
            $number = 1;
        }
        $guard = 0;
        while (count($this->workingDays) < $number && $guard++ < self::MAX_ITERATION) {
            if (!$this->advance()) {
                break;
            }
        }

        return $this->workingDays[$number - 1] ?? $this->plan->start_date->toDateString();
    }

    /** Nomor hari kerja pertama pada setelah tanggal tersebut (minimal 1). */
    private function firstWorkingNumber(string $date): int
    {
        $guard = 0;
        while (($this->workingDays === [] || end($this->workingDays) < $date) && $guard++ < self::MAX_ITERATION) {
            if (!$this->advance()) {
                break;
            }
        }
        foreach ($this->workingDays as $index => $day) {
            if ($day >= $date) {
                return $index + 1;
            }
        }

        return max(1, count($this->workingDays));
    }

    /** Jumlah hari kerja dari $from sampai $to (inklusif). */
    private function countWorkingDays(string $from, string $to): int
    {
        if ($from > $to) {
            return 0;
        }
        $count = 0;
        $current = $from;
        $guard = 0;
        while ($current <= $to && $guard++ < self::MAX_ITERATION) {
            if ($this->isWorkingDay($current)) {
                $count++;
            }
            $current = $this->nextDay($current);
        }

        return $count;
    }

    private function nextDay(string $date): string
    {
        return (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');
    }
}
