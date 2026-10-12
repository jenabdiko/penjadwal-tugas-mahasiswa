<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Plan;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskFlowTest extends TestCase
{
    use RefreshDatabase;

    public function testSubTaskPertamaMenghapusPredecessorTaskInduk(): void
    {
        $plan = $this->newPlan();
        $service = app(TaskService::class);

        $taskA = $service->save($plan, null, $this->taskData(['name' => 'A']))['task'];
        $taskB = $service->save($plan, null, $this->taskData([
            'name' => 'B',
            'predecessors' => [['task_id' => $taskA->id, 'type' => 'FS', 'lag' => 0]],
        ]))['task'];

        $this->assertSame(1, $taskB->dependencies()->count());

        // B mendapat sub-task pertama: predecessor B dihapus + ada peringatan.
        $result = $service->save($plan, null, $this->taskData([
            'name' => 'B1',
            'parent_id' => $taskB->id,
        ]));

        $this->assertNotSame([], $result['warnings']);
        $this->assertStringContainsString('otomatis dihapus', $result['warnings'][0]);
        $this->assertSame(0, $taskB->fresh()->dependencies()->count());
    }

    public function testTaskIndukTidakBolehMempunyaiPredecessor(): void
    {
        $plan = $this->newPlan();
        $service = app(TaskService::class);

        $taskA = $service->save($plan, null, $this->taskData(['name' => 'A']))['task'];
        $parent = $service->save($plan, null, $this->taskData(['name' => 'Induk']))['task'];
        $service->save($plan, null, $this->taskData(['name' => 'Anak', 'parent_id' => $parent->id]));

        // Mengubah task induk dengan predecessor: langsung dibersihkan.
        $service->save($plan, $parent->fresh(), $this->taskData([
            'name' => 'Induk',
            'predecessors' => [['task_id' => $taskA->id, 'type' => 'FS', 'lag' => 0]],
        ]));

        $this->assertSame(0, $parent->fresh()->dependencies()->count());
    }

    public function testHapusTaskIndukIkutMenghapusSubTaskDanDependency(): void
    {
        $plan = $this->newPlan();
        $service = app(TaskService::class);

        $parent = $service->save($plan, null, $this->taskData(['name' => 'Induk']))['task'];
        $child = $service->save($plan, null, $this->taskData(['name' => 'Anak', 'parent_id' => $parent->id]))['task'];
        $other = $service->save($plan, null, $this->taskData([
            'name' => 'Lain',
            'predecessors' => [['task_id' => $child->id, 'type' => 'FS', 'lag' => 0]],
        ]))['task'];

        $response = $this->delete(route('tasks.destroy', $parent));
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('plans.show', ['plan' => $plan, 'tab' => 'tugas']));

        // Induk dan sub-tasknya ikut terhapus; task "Lain" tetap ada.
        $this->assertSame(1, $plan->tasks()->count());
        $this->assertSame('Lain', $plan->tasks()->first()->name);
        $this->assertSame(0, \App\Models\TaskDependency::count());
        $this->assertSame(0, $other->fresh()->dependencies()->count());
    }

    public function testHapusAnggotaMelepasnyaDariTask(): void
    {
        $plan = $this->newPlan();
        $service = app(TaskService::class);

        $member = $plan->members()->create(['name' => 'Ani', 'role' => 'Ketua', 'contact' => 'ani@contoh.id']);
        $task = $service->save($plan, null, $this->taskData([
            'name' => 'Tugas Anggota',
            'members' => [$member->id],
        ]))['task'];

        $this->assertSame(1, $task->members()->count());

        $response = $this->delete(route('members.destroy', $member));
        $response->assertSessionHasNoErrors();

        $this->assertSame(0, Member::count());
        $this->assertSame(0, $task->fresh()->members()->count());
    }

    public function testHapusRencanaMenghapusSeluruhDataTerkait(): void
    {
        $plan = $this->newPlan();
        $service = app(TaskService::class);

        $plan->members()->create(['name' => 'Budi', 'role' => 'Programmer', 'contact' => null]);
        $plan->holidays()->create(['date' => '2026-10-14', 'name' => 'Libur']);
        $taskA = $service->save($plan, null, $this->taskData(['name' => 'A']))['task'];
        $service->save($plan, null, $this->taskData([
            'name' => 'B',
            'predecessors' => [['task_id' => $taskA->id, 'type' => 'FS', 'lag' => 0]],
        ]));

        $response = $this->delete(route('plans.destroy', $plan));
        $response->assertRedirect(route('plans.index'));
        $response->assertSessionHasNoErrors();

        $this->assertSame(0, Plan::count());
        $this->assertSame(0, \App\Models\Task::count());
        $this->assertSame(0, \App\Models\TaskDependency::count());
        $this->assertSame(0, \App\Models\Member::count());
        $this->assertSame(0, \App\Models\Holiday::count());
    }

    public function testTambahTaskLewatFormBerhasil(): void
    {
        $plan = $this->newPlan();
        $member = $plan->members()->create(['name' => 'Citra', 'role' => 'Dokumentasi', 'contact' => null]);
        $first = app(TaskService::class)->save($plan, null, $this->taskData(['name' => 'Pertama']))['task'];

        $response = $this->post(route('tasks.store', $plan), [
            'name' => 'Kedua',
            'duration' => 3,
            'earliest_start' => '2026-10-20',
            'priority' => 'Tinggi',
            'progress' => 10,
            'members' => [$member->id],
            'predecessors' => [
                ['task_id' => $first->id, 'type' => 'SS', 'lag' => 1],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('plans.show', ['plan' => $plan, 'tab' => 'tugas']));

        $task = $plan->tasks()->firstWhere('name', 'Kedua');
        $this->assertNotNull($task);
        $this->assertSame(3, $task->duration);
        $this->assertSame('2026-10-20', $task->earliest_start->toDateString());
        $this->assertSame(10, $task->progress);
        $this->assertArrayNotHasKey('status', $task->getAttributes());
        $this->assertSame(2, $task->sort_order);
        $this->assertSame([$member->id], $task->members()->pluck('members.id')->all());

        $dependency = $task->dependencies()->first();
        $this->assertSame($first->id, $dependency->predecessor_id);
        $this->assertSame('SS', $dependency->type);
        $this->assertSame(1, $dependency->lag);
    }

    // ------------------------------------------------------------------

    private function newPlan(): Plan
    {
        return Plan::create([
            'name' => 'Rencana Alur',
            'start_date' => '2026-10-12',
            'deadline' => '2026-10-31',
            'priority' => 'Tinggi',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function taskData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Task',
            'duration' => 1,
            'earliest_start' => null,
            'parent_id' => null,
            'priority' => 'Sedang',
            'progress' => 0,
            'members' => [],
            'predecessors' => [],
        ], $overrides);
    }
}
