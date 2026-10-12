<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Services\SchedulerService;
use App\Services\SampleDataService;
use App\Services\StatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tes status otomatis (dicek berurutan, berhenti pada aturan pertama yang cocok):
 * 1) progress 100 -> Selesai; 2) hari ini > tanggal selesai -> Terlambat;
 * 3) progress > 0 ATAU hari ini di dalam jadwal -> Sedang Dikerjakan;
 * 4) selain itu -> Belum Dimulai.
 */
class StatusTest extends TestCase
{
    use RefreshDatabase;

    private SchedulerService $scheduler;
    private StatusService $status;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = app(SchedulerService::class);
        $this->status = app(StatusService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testContohSatuPada9Oktober(): void
    {
        Carbon::setTestNow('2026-10-09 08:00:00');
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();

        $statuses = $this->compute($plan);

        $this->assertSame(Plan::STATUS_DONE, $statuses['tasks'][$this->taskId($plan, 1)]);
        $this->assertSame(Plan::STATUS_DOING, $statuses['tasks'][$this->taskId($plan, 2)]);
        foreach ([3, 4, 5, 6, 7, 8] as $number) {
            $this->assertSame(Plan::STATUS_TODO, $statuses['tasks'][$this->taskId($plan, $number)], "task {$number}");
        }
        $this->assertSame(Plan::STATUS_DOING, $statuses['plan']);
    }

    public function testContohSatuPada22Oktober(): void
    {
        Carbon::setTestNow('2026-10-22 08:00:00');
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();

        $statuses = $this->compute($plan);

        $expected = [
            1 => Plan::STATUS_DONE,
            2 => Plan::STATUS_LATE,
            3 => Plan::STATUS_LATE,
            4 => Plan::STATUS_LATE,
            5 => Plan::STATUS_DOING,
            6 => Plan::STATUS_DOING,
            7 => Plan::STATUS_TODO,
            8 => Plan::STATUS_TODO,
        ];
        foreach ($expected as $number => $status) {
            $this->assertSame($status, $statuses['tasks'][$this->taskId($plan, $number)], "task {$number}");
        }
        $this->assertSame(Plan::STATUS_DOING, $statuses['plan']);
    }

    public function testRencanaTerlambatSetelahDeadlineTaskBelumSelesai(): void
    {
        Carbon::setTestNow('2026-11-02 08:00:00');
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();

        $statuses = $this->compute($plan);

        $this->assertSame(Plan::STATUS_LATE, $statuses['plan']);
    }

    public function testRencanaSelesaiBilaSemuaProgress100(): void
    {
        Carbon::setTestNow('2026-11-02 08:00:00');
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();

        $plan->tasks()->update(['progress' => 100]);

        $statuses = $this->compute($plan);

        foreach ($statuses['tasks'] as $status) {
            $this->assertSame(Plan::STATUS_DONE, $status);
        }
        $this->assertSame(Plan::STATUS_DONE, $statuses['plan']);
    }

    public function testRencanaTanpaTaskBerstatusBelumDimulai(): void
    {
        Carbon::setTestNow('2026-11-02 08:00:00');
        $plan = $this->newPlan();

        $statuses = $this->compute($plan);

        $this->assertSame([], $statuses['tasks']);
        $this->assertSame(Plan::STATUS_TODO, $statuses['plan']);
    }

    public function testStatusTaskIndukDariSubTask(): void
    {
        // Semua sub-task belum masuk jadwal dan progress 0 -> induk Belum Dimulai.
        Carbon::setTestNow('2026-10-09 08:00:00');
        $plan = $this->newPlan();
        $parent = $this->makeTask($plan, ['name' => 'Induk']);
        $this->makeTask($plan, ['name' => 'Anak Satu', 'parent_id' => $parent->id, 'duration' => 2]);
        $this->makeTask($plan, ['name' => 'Anak Dua', 'parent_id' => $parent->id, 'duration' => 3]);

        $statuses = $this->compute($plan);
        $this->assertSame(Plan::STATUS_TODO, $statuses['tasks'][$parent->id]);

        // Satu sub-task Sedang Dikerjakan -> induk Sedang Dikerjakan.
        $plan->tasks()->where('name', 'Anak Satu')->update(['progress' => 50]);
        $statuses = $this->compute($plan);
        $this->assertSame(Plan::STATUS_DOING, $statuses['tasks'][$parent->id]);

        // Ada sub-task Terlambat -> induk Terlambat (dicek sebelum aturan lain).
        Carbon::setTestNow('2026-11-02 08:00:00');
        $statuses = $this->compute($plan);
        $this->assertSame(Plan::STATUS_LATE, $statuses['tasks'][$parent->id]);

        // Semua sub-task Selesai -> induk Selesai.
        $plan->tasks()->update(['progress' => 100]);
        $statuses = $this->compute($plan);
        $this->assertSame(Plan::STATUS_DONE, $statuses['tasks'][$parent->id]);
    }

    public function testFormTaskTidakMenerimaInputStatus(): void
    {
        Carbon::setTestNow('2026-10-09 08:00:00');
        $plan = $this->newPlan();

        $this->post(route('tasks.store', $plan), [
            'name' => 'Tugas A',
            'duration' => 2,
            'priority' => 'Sedang',
            'status' => 'Selesai', // field lama diabaikan, bukan divalidasi
            'progress' => 40,
        ])->assertSessionHasNoErrors();

        $task = $plan->tasks()->firstWhere('name', 'Tugas A');
        $this->assertSame(40, $task->progress);
        $this->assertArrayNotHasKey('status', $task->getAttributes());

        // Status dihitung: progress 40, jadwal 12-13 Okt, hari ini 9 Okt -> Sedang Dikerjakan.
        $statuses = $this->compute($plan);
        $this->assertSame(Plan::STATUS_DOING, $statuses['tasks'][$task->id]);
    }

    public function testTombolCepatMengubahProgress(): void
    {
        Carbon::setTestNow('2026-10-09 08:00:00');
        $plan = $this->newPlan();
        $task = $this->makeTask($plan, ['name' => 'Tugas B', 'progress' => 50]);

        $this->patch(route('tasks.progress', $task), ['progress' => 100])
            ->assertSessionHasNoErrors();
        $this->assertSame(100, $task->fresh()->progress);

        $this->patch(route('tasks.progress', $task), ['progress' => 0])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $task->fresh()->progress);

        $this->patch(route('tasks.progress', $task), ['progress' => 150])
            ->assertSessionHasErrors('progress');
        $this->assertSame(0, $task->fresh()->progress);
    }

    // ------------------------------------------------------------------

    /** @return array{plan: string, tasks: array<int, string>} */
    private function compute(Plan $plan): array
    {
        return $this->status->compute($plan, $this->scheduler->schedule($plan));
    }

    /** id task berdasarkan nomor tampilan. */
    private function taskId(Plan $plan, int $number): int
    {
        foreach ($this->scheduler->schedule($plan)['rows'] as $row) {
            if ($row['number'] === $number) {
                return $row['id'];
            }
        }
        $this->fail("Task nomor {$number} tidak ditemukan.");

        return 0;
    }

    private function newPlan(): Plan
    {
        return Plan::create([
            'name' => 'Rencana Status',
            'start_date' => '2026-10-12',
            'deadline' => '2026-10-31',
            'priority' => Plan::PRIORITY_HIGH,
            'off_saturday' => true,
            'off_sunday' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeTask(Plan $plan, array $overrides = []): \App\Models\Task
    {
        return $plan->tasks()->create(array_merge([
            'name' => 'Task',
            'duration' => 1,
            'priority' => Plan::PRIORITY_MEDIUM,
            'progress' => 0,
            'sort_order' => $plan->tasks()->count() + 1,
        ], $overrides));
    }
}
