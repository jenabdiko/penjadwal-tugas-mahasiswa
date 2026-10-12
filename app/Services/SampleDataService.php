<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

/**
 * Membuat dua rencana contoh sesuai spesifikasi.
 * Rencana dengan nama yang sudah ada tidak dibuat ganda.
 */
class SampleDataService
{
    public const PLAN_ONE = 'Tugas Besar Pemrograman Web';
    public const PLAN_TWO = 'Tugas Besar Pemrograman Web (Deadline Mepet)';

    /**
     * Muat data contoh.
     *
     * @return array{created: list<string>, skipped: list<string>}
     */
    public function load(): array
    {
        $created = [];
        $skipped = [];

        foreach ($this->examples() as $example) {
            if (Plan::where('name', $example['name'])->exists()) {
                // Data contoh lama bisa tersimpan dengan pengaturan hari kerja
                // versi sebelumnya; samakan agar evaluasi deadline konsisten.
                Plan::where('name', $example['name'])->update([
                    'off_saturday' => true,
                    'off_sunday' => true,
                ]);
                $skipped[] = $example['name'];

                continue;
            }
            $this->makePlan($example);
            $created[] = $example['name'];
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @return list<array{name: string, deadline: string, tasks: list<array>, members: list<array>}>
     */
    private function examples(): array
    {
        // [nama, durasi, predecessor (nomor), penanggung jawab, progress]
        $tasks = [
            ['Mencari referensi', 2, [], ['Ani', 'Citra'], 100],
            ['Membuat outline', 1, [1], ['Ani'], 50],
            ['Menulis laporan', 4, [2], ['Citra'], 0],
            ['Membuat program', 5, [2], ['Budi'], 0],
            ['Pengujian', 2, [4], ['Dika'], 0],
            ['Membuat presentasi', 2, [3], ['Ani'], 0],
            ['Revisi', 2, [5, 6], ['Ani', 'Budi', 'Citra', 'Dika'], 0],
            ['Latihan presentasi', 1, [7], ['Ani', 'Budi', 'Citra', 'Dika'], 0],
        ];

        $members = [
            ['Ani', 'Ketua', 'ani@contoh.id'],
            ['Budi', 'Programmer', 'budi@contoh.id'],
            ['Citra', 'Dokumentasi', 'citra@contoh.id'],
            ['Dika', 'Tester', 'dika@contoh.id'],
        ];

        return [
            ['name' => self::PLAN_ONE, 'deadline' => '2026-10-30', 'tasks' => $tasks, 'members' => $members],
            ['name' => self::PLAN_TWO, 'deadline' => '2026-10-23', 'tasks' => $tasks, 'members' => $members],
        ];
    }

    private function makePlan(array $example): Plan
    {
        return DB::transaction(function () use ($example): Plan {
            $plan = Plan::create([
                'name' => $example['name'],
                'description' => 'Contoh rencana pengerjaan tugas besar pemrograman web secara berkelompok.',
                'start_date' => '2026-10-12',
                'deadline' => $example['deadline'],
                'priority' => Plan::PRIORITY_HIGH,
                'off_saturday' => true,
                'off_sunday' => true,
            ]);

            $memberIds = [];
            foreach ($example['members'] as [$name, $role, $contact]) {
                $member = $plan->members()->create([
                    'name' => $name,
                    'role' => $role,
                    'contact' => $contact,
                ]);
                $memberIds[$name] = $member->id;
            }

            $taskByNumber = [];
            foreach ($example['tasks'] as $index => [$name, $duration, $predecessors, $members, $progress]) {
                $task = $plan->tasks()->create([
                    'name' => $name,
                    'duration' => $duration,
                    'priority' => Plan::PRIORITY_MEDIUM,
                    'progress' => $progress,
                    'sort_order' => $index + 1,
                ]);
                foreach ($members as $memberName) {
                    $task->members()->attach($memberIds[$memberName]);
                }
                $taskByNumber[$index + 1] = $task;
            }

            foreach ($example['tasks'] as $index => [, , $predecessors]) {
                foreach ($predecessors as $predecessorNumber) {
                    $taskByNumber[$index + 1]->dependencies()->create([
                        'predecessor_id' => $taskByNumber[$predecessorNumber]->id,
                        'type' => 'FS',
                        'lag' => 0,
                    ]);
                }
            }

            return $plan;
        });
    }
}
