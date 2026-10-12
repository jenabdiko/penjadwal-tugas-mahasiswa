<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Logika penyimpanan task: anggota, predecessor, dan aturan task induk. */
class TaskService
{
    /**
     * Simpan task baru atau perubahan task beserta anggota dan predecessor.
     *
     * @param array<string, mixed> $data data tervalidasi dari form
     * @return array{task: Task, warnings: list<string>}
     */
    public function save(Plan $plan, ?Task $task, array $data): array
    {
        $isNew = $task === null;
        $predecessors = $this->normalizePredecessors($data['predecessors'] ?? []);
        $memberIds = $this->validateMemberIds($plan, $data['members'] ?? []);

        return DB::transaction(function () use ($plan, $task, $data, $isNew, $predecessors, $memberIds): array {
            $warnings = [];

            $task = $task ?? new Task(['plan_id' => $plan->id]);
            if ($isNew) {
                $task->sort_order = (int) $plan->tasks()->max('sort_order') + 1;
            }

            // Status tidak disimpan: hanya progress yang diisi (0-100).
            $progress = Task::clampProgress((int) ($data['progress'] ?? 0));

            $task->name = (string) $data['name'];
            $task->duration = (int) $data['duration'];
            $task->earliest_start = !empty($data['earliest_start']) ? $data['earliest_start'] : null;
            $task->parent_id = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
            $task->priority = (string) $data['priority'];
            $task->progress = $progress;
            $task->save();

            $task->members()->sync($memberIds);

            // Task induk (punya sub-task) tidak boleh memiliki predecessor.
            if ($task->children()->exists()) {
                $task->dependencies()->delete();
            } else {
                $this->syncPredecessors($plan, $task, $predecessors);
            }

            // Bila task baru menjadi sub-task pertama: induknya kehilangan predecessor.
            if ($task->parent_id !== null) {
                $parent = Task::find($task->parent_id);
                $siblingCount = $parent ? $parent->children()->where('id', '!=', $task->id)->count() : 1;
                if ($parent && $siblingCount === 0 && $parent->dependencies()->exists()) {
                    $parent->dependencies()->delete();
                    $warnings[] = "Predecessor task \"{$parent->name}\" otomatis dihapus karena \"{$parent->name}\" kini menjadi ringkasan sub-task. Silakan susun ulang alur pada sub-tasknya.";
                }
            }

            return ['task' => $task, 'warnings' => $warnings];
        });
    }

    /** Hapus task beserta seluruh sub-task dan dependency terkait. */
    public function delete(Task $task): void
    {
        foreach ($task->children as $child) {
            $this->delete($child);
        }
        $task->delete();
    }

    /**
     * Validasi daftar predecessor dari form.
     *
     * @return list<array{task_id: int, type: string, lag: int}>
     */
    private function normalizePredecessors(mixed $list): array
    {
        if (!is_array($list)) {
            return [];
        }

        $rows = [];
        foreach ($list as $row) {
            if (!is_array($row) || !isset($row['task_id'])) {
                continue;
            }
            $rows[] = [
                'task_id' => (int) $row['task_id'],
                'type' => (string) ($row['type'] ?? 'FS'),
                'lag' => (int) ($row['lag'] ?? 0),
            ];
        }

        return $rows;
    }

    /** @return list<int> id anggota yang benar-benar milik rencana ini */
    private function validateMemberIds(Plan $plan, mixed $members): array
    {
        if (!is_array($members)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $members)));
        if ($ids === []) {
            return [];
        }

        $valid = $plan->members()->whereIn('id', $ids)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if (count($valid) !== count($ids)) {
            throw ValidationException::withMessages([
                'members' => 'Ada anggota yang tidak berada dalam rencana ini.',
            ]);
        }

        return $valid;
    }

    /**
     * Ganti seluruh predecessor task dengan aturan: tanpa duplikat,
     * tanpa task induk, dan tanpa siklus.
     *
     * @param list<array{task_id: int, type: string, lag: int}> $predecessors
     */
    private function syncPredecessors(Plan $plan, Task $task, array $predecessors): void
    {
        $names = $plan->tasks()->pluck('name', 'id')->map(fn ($n): string => (string) $n)->all();
        $parentIds = $plan->tasks()->whereNotNull('parent_id')->pluck('parent_id')->unique()->flip()->all();

        $edges = [];
        $seen = [];
        foreach ($predecessors as $pre) {
            $predId = $pre['task_id'];

            if ($predId === $task->id) {
                throw ValidationException::withMessages([
                    'predecessors' => DependencyGraph::cycleMessage([$task->id, $task->id], $names),
                ]);
            }
            if (!isset($names[$predId])) {
                throw ValidationException::withMessages([
                    'predecessors' => 'Task predecessor tidak ditemukan dalam rencana ini.',
                ]);
            }
            if (isset($parentIds[$predId])) {
                throw ValidationException::withMessages([
                    'predecessors' => "Task induk \"{$names[$predId]}\" tidak boleh dijadikan predecessor. Gunakan sub-tasknya.",
                ]);
            }
            if (isset($seen[$predId])) {
                throw ValidationException::withMessages([
                    'predecessors' => "Dependency duplikat: \"{$names[$predId]}\" sudah menjadi predecessor task ini.",
                ]);
            }
            $seen[$predId] = true;
            $edges[] = ['from' => $predId, 'to' => $task->id];
        }

        // Kumpulkan dependency lama selain milik task ini, lalu cek siklus.
        foreach ($plan->tasks()->with('dependencies')->get() as $other) {
            if ($other->id === $task->id) {
                continue;
            }
            foreach ($other->dependencies as $dep) {
                $edges[] = ['from' => $dep->predecessor_id, 'to' => $other->id];
            }
        }

        $cycle = DependencyGraph::findCycle($edges);
        if ($cycle !== null) {
            throw ValidationException::withMessages([
                'predecessors' => DependencyGraph::cycleMessage($cycle, $names),
            ]);
        }

        $task->dependencies()->delete();
        foreach ($predecessors as $pre) {
            $task->dependencies()->create([
                'predecessor_id' => $pre['task_id'],
                'type' => $pre['type'],
                'lag' => $pre['lag'],
            ]);
        }
    }
}
