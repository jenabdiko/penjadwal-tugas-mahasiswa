<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Task;
use App\Services\SampleDataService;
use App\Services\SchedulerService;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SchedulerServiceTest extends TestCase
{
    use RefreshDatabase;

    private SchedulerService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = app(SchedulerService::class);
    }

    // ------------------------------------------------------------------
    // Contoh 1
    // ------------------------------------------------------------------

    public function testContohSatuSemuaTanggalDanTotal(): void
    {
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();
        $s = $this->scheduler->schedule($plan);

        $expected = [
            1 => ['2026-10-12', '2026-10-13'],
            2 => ['2026-10-14', '2026-10-14'],
            3 => ['2026-10-15', '2026-10-20'],
            4 => ['2026-10-15', '2026-10-21'],
            5 => ['2026-10-22', '2026-10-23'],
            6 => ['2026-10-21', '2026-10-22'],
            7 => ['2026-10-26', '2026-10-27'],
            8 => ['2026-10-28', '2026-10-28'],
        ];

        foreach ($expected as $number => [$start, $end]) {
            $id = $this->taskIdByNumber($s, $number);
            $this->assertSame($start, $s['tasks'][$id]['start_date'], "Tanggal mulai task {$number}");
            $this->assertSame($end, $s['tasks'][$id]['end_date'], "Tanggal selesai task {$number}");
        }

        $this->assertSame(13, $s['project']['total_working_days']);
        $this->assertSame('2026-10-28', $s['project']['end_date']);
    }

    public function testContohSatuCriticalPathDanJalur(): void
    {
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();
        $s = $this->scheduler->schedule($plan);

        $criticalNumbers = collect($s['project']['critical_task_ids'])
            ->map(fn (int $id): int => $s['tasks'][$id]['number'])
            ->sort()
            ->values()
            ->all();
        $this->assertSame([1, 2, 4, 5, 7, 8], $criticalNumbers);

        // Task 3 dan 6 tidak kritis, slack masing-masing 1 hari kerja.
        $this->assertSame(1, $s['tasks'][$this->taskIdByNumber($s, 3)]['slack']);
        $this->assertSame(1, $s['tasks'][$this->taskIdByNumber($s, 6)]['slack']);
        $this->assertFalse($s['tasks'][$this->taskIdByNumber($s, 3)]['is_critical']);
        $this->assertFalse($s['tasks'][$this->taskIdByNumber($s, 6)]['is_critical']);

        $paths = collect($s['project']['paths'])->keyBy('label');
        $this->assertCount(2, $paths);
        $this->assertSame(12, $paths['1-2-3-6-7-8']['total'] ?? null);
        $this->assertSame(13, $paths['1-2-4-5-7-8']['total'] ?? null);

        $criticalLabels = array_column($s['project']['critical_paths'], 'label');
        $this->assertSame(['1-2-4-5-7-8'], $criticalLabels);
        $this->assertSame(13, $s['project']['max_path_total']);
    }

    public function testContohSatuEvaluasiDeadlineTepatWaktu(): void
    {
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();
        $eval = $this->scheduler->schedule($plan)['project']['deadline'];

        $this->assertTrue($eval['on_time']);
        $this->assertSame('✓ Jadwal dapat selesai tepat waktu.', $eval['message']);
        $this->assertSame('Cadangan waktu: 2 hari kerja', $eval['detail']);
        $this->assertSame(2, $eval['days']);
    }

    // ------------------------------------------------------------------
    // Contoh 2
    // ------------------------------------------------------------------

    public function testContohDuaMelewatiDeadline(): void
    {
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_TWO)->firstOrFail();
        $s = $this->scheduler->schedule($plan);
        $eval = $s['project']['deadline'];

        $this->assertFalse($eval['on_time']);
        $this->assertSame('⚠ Jadwal pengerjaan melewati deadline.', $eval['message']);
        $this->assertSame('Terlambat 3 hari kerja', $eval['detail']);
        $this->assertSame(3, $eval['days']);
        $this->assertSame('2026-10-28', $s['project']['end_date']);
    }

    // ------------------------------------------------------------------
    // Hari libur khusus
    // ------------------------------------------------------------------

    public function testHariLiburKhususMenggeserJadwal(): void
    {
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();
        $plan->holidays()->create(['date' => '2026-10-14', 'name' => 'Libur Khusus']);

        $s = $this->scheduler->schedule($plan);

        // 14 Okt dilewati: task 2 bergeser ke 15 Okt, jadwal akhir mundur 1 hari kerja.
        $this->assertSame('2026-10-15', $s['tasks'][$this->taskIdByNumber($s, 2)]['start_date']);
        $this->assertSame('2026-10-15', $s['tasks'][$this->taskIdByNumber($s, 2)]['end_date']);
        $this->assertSame('2026-10-29', $s['project']['end_date']);

        foreach ($s['tasks'] as $info) {
            $this->assertNotSame('2026-10-14', $info['start_date']);
            $this->assertNotSame('2026-10-14', $info['end_date']);
        }
    }

    // ------------------------------------------------------------------
    // Empat jenis dependency
    // ------------------------------------------------------------------

    public function testEmpatJenisDependency(): void
    {
        $plan = $this->newPlan();
        $taskA = $plan->tasks()->create([
            'name' => 'A', 'duration' => 3, 'sort_order' => 1,
            'priority' => Plan::PRIORITY_MEDIUM, 'progress' => 0,
        ]);
        $taskB = $plan->tasks()->create([
            'name' => 'B', 'duration' => 2, 'sort_order' => 2,
            'priority' => Plan::PRIORITY_MEDIUM, 'progress' => 0,
        ]);
        $dependency = $taskB->dependencies()->create([
            'predecessor_id' => $taskA->id, 'type' => 'FS', 'lag' => 0,
        ]);

        $cases = [
            ['FS', 0, '2026-10-15', '2026-10-16'],
            ['SS', 1, '2026-10-13', '2026-10-14'],
            ['FF', 0, '2026-10-13', '2026-10-14'],
            ['SF', 0, '2026-10-12', '2026-10-13'],
        ];

        foreach ($cases as [$type, $lag, $start, $end]) {
            $dependency->update(['type' => $type, 'lag' => $lag]);
            $s = $this->scheduler->schedule($plan->fresh());

            $idA = $this->taskIdByName($s, 'A');
            $idB = $this->taskIdByName($s, 'B');

            // Task A selalu 12-14 Okt (durasi 3 hari kerja).
            $this->assertSame('2026-10-12', $s['tasks'][$idA]['start_date']);
            $this->assertSame('2026-10-14', $s['tasks'][$idA]['end_date']);

            $this->assertSame($start, $s['tasks'][$idB]['start_date'], "Mulai B untuk {$type} lag {$lag}");
            $this->assertSame($end, $s['tasks'][$idB]['end_date'], "Selesai B untuk {$type} lag {$lag}");
        }
    }

    // ------------------------------------------------------------------
    // Dependency melingkar dan ke diri sendiri
    // ------------------------------------------------------------------

    public function testDependencyMelingkarDitolak(): void
    {
        $plan = $this->newPlan();
        $tasks = [];
        foreach (['A', 'B', 'C'] as $index => $name) {
            $tasks[$name] = $plan->tasks()->create([
                'name' => $name, 'duration' => 1, 'sort_order' => $index + 1,
                'priority' => Plan::PRIORITY_MEDIUM, 'progress' => 0,
            ]);
        }

        $service = app(TaskService::class);
        $service->save($plan, $tasks['B'], $this->taskData($tasks['B'], [[$tasks['A']->id, 'FS', 0]]));
        $service->save($plan, $tasks['C'], $this->taskData($tasks['C'], [[$tasks['B']->id, 'FS', 0]]));

        try {
            $service->save($plan, $tasks['A'], $this->taskData($tasks['A'], [[$tasks['C']->id, 'FS', 0]]));
            $this->fail('Seharusnya dependency melingkar ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Dependency melingkar tidak diperbolehkan: A -> B -> C -> A',
                $e->errors()['predecessors'][0] ?? ''
            );
        }

        // Dependency yang lolos tidak membuat service loop tak berujung.
        $s = $this->scheduler->schedule($plan);
        $this->assertArrayHasKey('project', $s);
    }

    public function testDependencyKeDiriSendiriDitolak(): void
    {
        $plan = $this->newPlan();
        $task = $plan->tasks()->create([
            'name' => 'A', 'duration' => 1, 'sort_order' => 1,
            'priority' => Plan::PRIORITY_MEDIUM, 'progress' => 0,
        ]);

        try {
            app(TaskService::class)->save($plan, $task, $this->taskData($task, [[$task->id, 'FS', 0]]));
            $this->fail('Seharusnya dependency ke diri sendiri ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Dependency melingkar tidak diperbolehkan: A -> A',
                $e->errors()['predecessors'][0] ?? ''
            );
        }
    }

    // ------------------------------------------------------------------
    // Rollup task induk
    // ------------------------------------------------------------------

    public function testRollupTaskIndukDariSubTask(): void
    {
        $plan = $this->newPlan();
        $parent = $plan->tasks()->create([
            'name' => 'Induk', 'duration' => 1, 'sort_order' => 1,
            'priority' => Plan::PRIORITY_MEDIUM, 'progress' => 0,
        ]);
        $child1 = $plan->tasks()->create([
            'name' => 'Anak Satu', 'duration' => 2, 'sort_order' => 2, 'parent_id' => $parent->id,
            'priority' => Plan::PRIORITY_MEDIUM, 'progress' => 100,
        ]);
        $child2 = $plan->tasks()->create([
            'name' => 'Anak Dua', 'duration' => 3, 'sort_order' => 3, 'parent_id' => $parent->id,
            'priority' => Plan::PRIORITY_MEDIUM, 'progress' => 0,
        ]);
        $child2->dependencies()->create(['predecessor_id' => $child1->id, 'type' => 'FS', 'lag' => 0]);

        $s = $this->scheduler->schedule($plan);

        // Task induk tidak dijadwalkan sendiri; hanya task daun.
        $this->assertArrayNotHasKey($parent->id, $s['tasks']);
        $this->assertArrayHasKey($parent->id, $s['parents']);

        $rollup = $s['parents'][$parent->id];
        $this->assertSame('2026-10-12', $rollup['start_date']);
        $this->assertSame('2026-10-16', $rollup['end_date']);
        $this->assertSame(5, $rollup['duration']);
        $this->assertSame(40, $rollup['progress']); // (2*100 + 3*0) / 5
        $this->assertTrue($rollup['is_critical']);

        // Baris tampilan: durasi rollup, tanpa predecessor.
        $row = collect($s['rows'])->firstWhere('id', $parent->id);
        $this->assertTrue($row['is_parent']);
        $this->assertSame(5, $row['duration']);
        $this->assertSame('-', $row['predecessor_label']);
        $this->assertSame(0, $row['level']);

        // Sub-task menjorok satu tingkat.
        $childRow = collect($s['rows'])->firstWhere('id', $child1->id);
        $this->assertSame(1, $childRow['level']);
        $this->assertFalse($childRow['is_parent']);
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function newPlan(string $deadline = '2026-10-31'): Plan
    {
        return Plan::create([
            'name' => 'Rencana Uji',
            'start_date' => '2026-10-12',
            'deadline' => $deadline,
            'priority' => Plan::PRIORITY_HIGH,
            'off_saturday' => true,
            'off_sunday' => true,
        ]);
    }

    /**
     * Data task untuk TaskService::save, dengan predecessor
     * berupa [id, jenis, lag].
     *
     * @param list<array{0: int, 1: string, 2: int}> $predecessors
     * @return array<string, mixed>
     */
    private function taskData(Task $task, array $predecessors): array
    {
        return [
            'name' => $task->name,
            'duration' => $task->duration,
            'earliest_start' => null,
            'parent_id' => $task->parent_id,
            'priority' => $task->priority,
            'progress' => $task->progress,
            'members' => $task->members()->pluck('id')->all(),
            'predecessors' => array_map(
                fn (array $pre): array => ['task_id' => $pre[0], 'type' => $pre[1], 'lag' => $pre[2]],
                $predecessors
            ),
        ];
    }

    /** @return int id task berdasarkan nomor tampilan */
    private function taskIdByNumber(array $schedule, int $number): int
    {
        foreach ($schedule['rows'] as $row) {
            if ($row['number'] === $number) {
                return $row['id'];
            }
        }
        $this->fail("Task nomor {$number} tidak ditemukan pada jadwal.");

        return 0; // tidak tercapai
    }

    /** @return int id task berdasarkan nama */
    private function taskIdByName(array $schedule, string $name): int
    {
        foreach ($schedule['rows'] as $row) {
            if ($row['task']->name === $name) {
                return $row['id'];
            }
        }
        $this->fail("Task \"{$name}\" tidak ditemukan pada jadwal.");

        return 0; // tidak tercapai
    }
}
