<?php

namespace App\Services;

use App\Models\Plan;

/** Menyiapkan data layout diagram jaringan PERT (SVG) untuk task daun. */
class PertService
{
    public const NODE_WIDTH = 180;
    public const NODE_HEIGHT = 86;
    public const GAP_X = 110;
    public const GAP_Y = 40;
    public const MARGIN = 24;

    /**
     * @param array<string, mixed> $schedule hasil SchedulerService::schedule()
     * @return array{
     *     nodes: list<array>, edges: list<array>, width: int, height: int, has_nodes: bool
     * }
     */
    public function build(Plan $plan, array $schedule): array
    {
        $rows = collect($schedule['rows'])->keyBy('id');
        $leafTasks = $schedule['tasks']; // hanya task daun yang digambar

        // Panah antar node: arah predecessor -> task, label jenis bila bukan FS.
        $edges = [];
        foreach ($plan->tasks()->with('dependencies')->get() as $task) {
            if (!isset($leafTasks[$task->id])) {
                continue;
            }
            foreach ($task->dependencies as $dep) {
                if (!isset($leafTasks[$dep->predecessor_id])) {
                    continue;
                }
                $edges[] = [
                    'from' => $dep->predecessor_id,
                    'to' => $task->id,
                    'type' => $dep->type,
                    'lag' => $dep->lag,
                    'label' => ($dep->type === 'FS' && $dep->lag === 0)
                        ? ''
                        : $dep->type.($dep->lag > 0 ? '+'.$dep->lag : ''),
                ];
            }
        }

        // Kedalaman = panjang rantai predecessor terpanjang (iteratif, anti-siklus).
        $depth = array_fill_keys(array_keys($leafTasks), 0);
        $round = 0;
        $changed = true;
        while ($changed && $round++ <= count($leafTasks)) {
            $changed = false;
            foreach ($edges as $edge) {
                if ($depth[$edge['to']] < $depth[$edge['from']] + 1) {
                    $depth[$edge['to']] = $depth[$edge['from']] + 1;
                    $changed = true;
                }
            }
        }

        // Susun node per kolom (kiri ke kanan), urut nomor tampilan.
        $columns = [];
        foreach ($depth as $id => $level) {
            $columns[$level][] = $id;
        }
        ksort($columns);
        foreach ($columns as $level => $ids) {
            usort($columns[$level], fn (int $a, int $b): int => $rows[$a]['number'] <=> $rows[$b]['number']);
        }

        $nodes = [];
        foreach ($columns as $level => $ids) {
            foreach ($ids as $rowIndex => $id) {
                $row = $rows[$id];
                $info = $leafTasks[$id];
                $name = (string) $row['task']->name;
                $nodes[$id] = [
                    'id' => $id,
                    'number' => $row['number'],
                    'name' => $name,
                    'short_name' => mb_strlen($name) > 24 ? mb_substr($name, 0, 23).'…' : $name,
                    'duration' => $info['duration'],
                    'start_date' => $info['start_date'],
                    'end_date' => $info['end_date'],
                    'critical' => $info['is_critical'],
                    'x' => self::MARGIN + $level * (self::NODE_WIDTH + self::GAP_X),
                    'y' => self::MARGIN + $rowIndex * (self::NODE_HEIGHT + self::GAP_Y),
                    'w' => self::NODE_WIDTH,
                    'h' => self::NODE_HEIGHT,
                ];
            }
        }

        // Hitung path tiap panah (elbow) beserta titik labelnya.
        $maxLevel = $columns === [] ? 0 : max(array_keys($columns));
        foreach ($edges as $index => $edge) {
            $from = $nodes[$edge['from']];
            $to = $nodes[$edge['to']];
            $x1 = $from['x'] + $from['w'];
            $y1 = $from['y'] + intdiv($from['h'], 2);
            $x2 = $to['x'];
            $y2 = $to['y'] + intdiv($to['h'], 2);
            $midX = intdiv($x1 + $x2, 2);
            $edges[$index]['path'] = "M {$x1} {$y1} H {$midX} V {$y2} H {$x2}";
            $edges[$index]['label_x'] = $midX;
            $edges[$index]['label_y'] = intdiv($y1 + $y2, 2) - 8;
        }

        $maxRows = $columns === [] ? 0 : max(array_map('count', $columns));

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'width' => self::MARGIN * 2 + $maxLevel * (self::NODE_WIDTH + self::GAP_X) + self::NODE_WIDTH,
            'height' => self::MARGIN * 2 + max(1, $maxRows) * (self::NODE_HEIGHT + self::GAP_Y) - self::GAP_Y,
            'has_nodes' => $nodes !== [],
        ];
    }
}
