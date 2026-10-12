<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Task;
use App\Models\TaskDependency;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Ekspor dan impor satu rencana lengkap sebagai berkas JSON. */
class PlanJsonService
{
    public const APP_KEY = 'sistem-penjadwalan-tugas';
    public const VERSION = 1;

    /** Susun seluruh rencana menjadi array siap ekspor. */
    public function export(Plan $plan): array
    {
        $plan->load(['holidays', 'members', 'tasks.dependencies', 'tasks.members']);

        // Urutan hirarki: task induk lalu sub-tasknya.
        $ordered = [];
        $childrenOf = $plan->tasks->whereNotNull('parent_id')->groupBy('parent_id');
        foreach ($plan->tasks->whereNull('parent_id')->sortBy('sort_order') as $root) {
            $ordered[] = $root;
            foreach ($childrenOf[$root->id] ?? [] as $child) {
                $ordered[] = $child;
            }
        }
        foreach ($plan->tasks as $task) {
            if (!in_array($task, $ordered, true)) {
                $ordered[] = $task;
            }
        }

        $members = $plan->members->sortBy('id')->values();
        $memberKeys = $members->mapWithKeys(fn ($member, int $index): array => [$member->id => $index + 1]);

        $taskKeys = [];
        foreach ($ordered as $index => $task) {
            $taskKeys[$task->id] = $index + 1;
        }

        $tasks = [];
        foreach ($ordered as $task) {
            $tasks[] = [
                'key' => $taskKeys[$task->id],
                'parent_key' => $task->parent_id !== null ? ($taskKeys[$task->parent_id] ?? null) : null,
                'name' => $task->name,
                'duration' => $task->duration,
                'earliest_start' => $task->earliest_start?->toDateString(),
                'priority' => $task->priority,
                'progress' => $task->progress,
                'sort_order' => $task->sort_order,
                'members' => $task->members->map(fn ($member) => $memberKeys[$member->id])->sort()->values()->all(),
                'predecessors' => $task->dependencies()->orderBy('id')->get()->map(fn ($dep): array => [
                    'key' => $taskKeys[$dep->predecessor_id] ?? null,
                    'type' => $dep->type,
                    'lag' => $dep->lag,
                ])->values()->all(),
            ];
        }

        return [
            'app' => self::APP_KEY,
            'version' => self::VERSION,
            'plan' => [
                'name' => $plan->name,
                'description' => $plan->description,
                'start_date' => $plan->start_date->toDateString(),
                'deadline' => $plan->deadline->toDateString(),
                'priority' => $plan->priority,
                'off_saturday' => $plan->off_saturday,
                'off_sunday' => $plan->off_sunday,
            ],
            'holidays' => $plan->holidays->sortBy(['date', 'id'])->map(fn ($holiday): array => [
                'date' => $holiday->date->toDateString(),
                'name' => $holiday->name,
            ])->values()->all(),
            'members' => $members->map(fn ($member, int $index): array => [
                'key' => $index + 1,
                'name' => $member->name,
                'role' => $member->role,
                'contact' => $member->contact,
            ])->values()->all(),
            'tasks' => $tasks,
        ];
    }

    /**
     * Buat rencana baru dari berkas JSON yang sudah didekode.
     *
     * @param array<string, mixed> $payload
     */
    public function import(array $payload): Plan
    {
        $this->validateStructure($payload);

        return DB::transaction(function () use ($payload): Plan {
            $data = $payload['plan'];
            $plan = Plan::create([
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'],
                'deadline' => $data['deadline'],
                'priority' => $data['priority'] ?? Plan::PRIORITY_MEDIUM,
                'off_saturday' => (bool) ($data['off_saturday'] ?? true),
                'off_sunday' => (bool) ($data['off_sunday'] ?? true),
            ]);

            foreach ($payload['holidays'] ?? [] as $holiday) {
                $plan->holidays()->create(['date' => $holiday['date'], 'name' => $holiday['name']]);
            }

            $memberIdByKey = [];
            foreach ($payload['members'] ?? [] as $index => $member) {
                $created = $plan->members()->create([
                    'name' => $member['name'],
                    'role' => $member['role'],
                    'contact' => $member['contact'] ?? null,
                ]);
                $memberIdByKey[$member['key'] ?? $index + 1] = $created->id;
            }

            // Task dibuat dulu tanpa induk, lalu disusun relasinya.
            $taskIdByKey = [];
            $taskPayloads = array_values($payload['tasks'] ?? []);
            foreach ($taskPayloads as $index => $task) {
                $created = $plan->tasks()->create([
                    'name' => $task['name'],
                    'duration' => $task['duration'],
                    'earliest_start' => $task['earliest_start'] ?? null,
                    'priority' => $task['priority'] ?? Plan::PRIORITY_MEDIUM,
                    'progress' => $task['progress'] ?? 0,
                    'sort_order' => $task['sort_order'] ?? ($index + 1),
                ]);
                $taskIdByKey[$task['key'] ?? $index + 1] = $created->id;
            }

            $names = [];
            foreach ($taskPayloads as $index => $task) {
                $id = $taskIdByKey[$task['key'] ?? $index + 1];
                $names[$id] = $task['name'];
            }

            $edges = [];
            foreach ($taskPayloads as $index => $task) {
                $id = $taskIdByKey[$task['key'] ?? $index + 1];
                $created = Task::find($id);

                if (!empty($task['parent_key'])) {
                    $created->parent_id = $taskIdByKey[$task['parent_key']] ?? null;
                    $created->save();
                }

                foreach ($task['members'] ?? [] as $memberKey) {
                    if (isset($memberIdByKey[$memberKey])) {
                        $created->members()->attach($memberIdByKey[$memberKey]);
                    }
                }

                foreach ($task['predecessors'] ?? [] as $pre) {
                    $predecessorId = $taskIdByKey[$pre['key']] ?? null;
                    if ($predecessorId === null) {
                        continue;
                    }
                    $created->dependencies()->create([
                        'predecessor_id' => $predecessorId,
                        'type' => $pre['type'],
                        'lag' => $pre['lag'],
                    ]);
                    $edges[] = ['from' => $predecessorId, 'to' => $id];
                }
            }

            $cycle = DependencyGraph::findCycle($edges);
            if ($cycle !== null) {
                throw ValidationException::withMessages([
                    'file' => DependencyGraph::cycleMessage($cycle, $names),
                ]);
            }

            return $plan;
        });
    }

    /** Validasi struktur berkas dengan pesan bahasa Indonesia. */
    private function validateStructure(array $payload): void
    {
        $fail = function (string $message): void {
            throw ValidationException::withMessages(['file' => $message]);
        };

        if (($payload['app'] ?? null) !== self::APP_KEY) {
            $fail('Struktur file tidak valid: field "app" bukan aplikasi ini.');
        }
        if (!is_array($payload['plan'] ?? null)) {
            $fail('Struktur file tidak valid: field "plan" tidak ditemukan.');
        }

        $plan = $payload['plan'];
        if (!is_string($plan['name'] ?? null) || trim($plan['name']) === '') {
            $fail('Struktur file tidak valid: "plan.name" wajib diisi.');
        }
        foreach (['start_date', 'deadline'] as $field) {
            if (!$this->isValidDate($plan[$field] ?? null)) {
                $fail("Struktur file tidak valid: tanggal \"plan.{$field}\" tidak valid.");
            }
        }
        if ($plan['deadline'] < $plan['start_date']) {
            $fail('Struktur file tidak valid: "plan.deadline" tidak boleh sebelum "plan.start_date".');
        }
        if (isset($plan['priority']) && !in_array($plan['priority'], Plan::PRIORITIES, true)) {
            $fail('Struktur file tidak valid: "plan.priority" tidak dikenal.');
        }
        // Berkas lama boleh memuat field "status"; field itu diabaikan karena
        // status kini dihitung otomatis dari jadwal dan progress.

        if (isset($payload['holidays']) && !is_array($payload['holidays'])) {
            $fail('Struktur file tidak valid: "holidays" harus berupa daftar.');
        }
        foreach ($payload['holidays'] ?? [] as $index => $holiday) {
            if (!is_array($holiday) || !$this->isValidDate($holiday['date'] ?? null)
                || !is_string($holiday['name'] ?? null) || trim($holiday['name']) === '') {
                $fail('Struktur file tidak valid: hari libur ke-'.($index + 1).' tidak lengkap.');
            }
        }

        if (isset($payload['members']) && !is_array($payload['members'])) {
            $fail('Struktur file tidak valid: "members" harus berupa daftar.');
        }
        $memberKeys = [];
        foreach ($payload['members'] ?? [] as $index => $member) {
            if (!is_array($member) || !is_string($member['name'] ?? null) || trim($member['name']) === ''
                || !is_string($member['role'] ?? null) || trim($member['role']) === '') {
                $fail('Struktur file tidak valid: anggota ke-'.($index + 1).' tidak lengkap ("name" dan "role" wajib).');
            }
            $key = $member['key'] ?? $index + 1;
            if (isset($memberKeys[$key])) {
                $fail('Struktur file tidak valid: kunci anggota "'.$key.'" ganda.');
            }
            $memberKeys[$key] = true;
        }

        if (isset($payload['tasks']) && !is_array($payload['tasks'])) {
            $fail('Struktur file tidak valid: "tasks" harus berupa daftar.');
        }

        $taskKeys = [];
        $taskPayloads = array_values($payload['tasks'] ?? []);
        foreach ($taskPayloads as $index => $task) {
            $label = 'task ke-'.($index + 1);
            if (!is_array($task)) {
                $fail("Struktur file tidak valid: {$label} bukan objek.");
            }
            if (!is_string($task['name'] ?? null) || trim($task['name']) === '') {
                $fail("Struktur file tidak valid: {$label} tidak memiliki \"name\".");
            }
            if (!is_int($task['duration'] ?? null) || $task['duration'] < 1) {
                $fail("Struktur file tidak valid: durasi {$label} harus bilangan bulat minimal 1.");
            }
            if (isset($task['earliest_start']) && !$this->isValidDate($task['earliest_start'])) {
                $fail("Struktur file tidak valid: \"earliest_start\" pada {$label} tidak valid.");
            }
            if (isset($task['progress']) && (!is_int($task['progress']) || $task['progress'] < 0 || $task['progress'] > 100)) {
                $fail("Struktur file tidak valid: progress pada {$label} harus antara 0 dan 100.");
            }

            $key = $task['key'] ?? $index + 1;
            if (isset($taskKeys[$key])) {
                $fail('Struktur file tidak valid: kunci task "'.$key.'" ganda.');
            }
            $taskKeys[$key] = true;
        }

        // Referensi silang divalidasi setelah seluruh kunci terkumpul.
        foreach ($taskPayloads as $index => $task) {
            $label = 'task ke-'.($index + 1);

            if (!empty($task['parent_key']) && !isset($taskKeys[$task['parent_key']])) {
                $fail("Struktur file tidak valid: \"parent_key\" pada {$label} tidak ditemukan.");
            }

            foreach ($task['members'] ?? [] as $memberKey) {
                if (!isset($memberKeys[$memberKey])) {
                    $fail("Struktur file tidak valid: anggota \"{$memberKey}\" pada {$label} tidak ditemukan.");
                }
            }

            $predecessorKeys = [];
            foreach ($task['predecessors'] ?? [] as $pre) {
                if (!is_array($pre) || !isset($pre['key']) || !isset($taskKeys[$pre['key']])) {
                    $fail("Struktur file tidak valid: predecessor pada {$label} tidak ditemukan.");
                }
                if (!in_array($pre['type'] ?? null, TaskDependency::TYPES, true)) {
                    $fail("Struktur file tidak valid: jenis dependency pada {$label} tidak dikenal (gunakan FS/SS/FF/SF).");
                }
                if (!is_int($pre['lag'] ?? null) || $pre['lag'] < 0) {
                    $fail("Struktur file tidak valid: lag pada {$label} harus bilangan bulat minimal 0.");
                }
                if (isset($predecessorKeys[$pre['key']])) {
                    $fail("Struktur file tidak valid: predecessor ganda pada {$label}.");
                }
                $predecessorKeys[$pre['key']] = true;
            }
        }
    }

    private function isValidDate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        try {
            new DateTimeImmutable($value);

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
