<?php

namespace App\Services;

/** Deteksi siklus pada graf dependency antar task. */
class DependencyGraph
{
    /**
     * Cari siklus pada graf dependency (arah: predecessor -> task).
     *
     * @param list<array{from: int, to: int}> $edges
     * @return list<int>|null daftar id task yang membentuk siklus,
     *                       node pertama diulang di akhir (A -> B -> C -> A)
     */
    public static function findCycle(array $edges): ?array
    {
        $adj = [];
        foreach ($edges as $edge) {
            $adj[$edge['from']][$edge['to']] = true;
        }

        $state = []; // 0 = belum, 1 = dalam stack, 2 = selesai
        $path = [];

        $visit = function (int $node) use (&$visit, &$state, &$path, $adj): ?array {
            $state[$node] = 1;
            $path[] = $node;

            foreach (array_keys($adj[$node] ?? []) as $next) {
                $status = $state[$next] ?? 0;
                if ($status === 1) {
                    $start = array_search($next, $path, true);
                    $cycle = array_slice($path, (int) $start);
                    $cycle[] = $next;

                    return $cycle;
                }
                if ($status === 0) {
                    $found = $visit($next);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }

            array_pop($path);
            $state[$node] = 2;

            return null;
        };

        foreach (array_keys($adj) as $node) {
            if (($state[$node] ?? 0) === 0) {
                $cycle = $visit($node);
                if ($cycle !== null) {
                    return $cycle;
                }
            }
        }

        return null;
    }

    /**
     * Pesan penolakan siklus berbahasa Indonesia memakai nama task.
     *
     * @param list<int> $cycle
     * @param array<int, string> $names
     */
    public static function cycleMessage(array $cycle, array $names): string
    {
        // Putar siklus agar selalu diawali node terkecil: A -> B -> C -> A
        $ids = $cycle;
        if (count($ids) > 1 && $ids[0] === $ids[count($ids) - 1]) {
            $ids = array_slice($ids, 0, -1);
        }
        if ($ids !== []) {
            $offset = (int) array_search(min($ids), $ids, true);
            $ids = [...array_slice($ids, $offset), ...array_slice($ids, 0, $offset)];
            $ids[] = $ids[0];
        }

        $labels = array_map(fn (int $id): string => $names[$id] ?? ('#'.$id), $ids);

        return 'Dependency melingkar tidak diperbolehkan: '.implode(' -> ', $labels);
    }
}
