<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    use RefreshDatabase;

    public function testFormRencanaWajibNamaDanInputLamaTetapTersimpan(): void
    {
        $response = $this->post('/plans', [
            'name' => '',
            'description' => 'Deskripsi yang diisi pengguna',
            'start_date' => 'bukan-tanggal',
            'deadline' => '2026-10-30',
            'priority' => 'Sedang',
        ]);

        $response->assertSessionHasErrors(['name', 'start_date']);
        $this->assertSame('Nama rencana wajib diisi.', session('errors')->first('name'));
        $this->assertSame('Tanggal mulai harus tanggal yang valid.', session('errors')->first('start_date'));

        // Input lama tetap terisi saat gagal.
        $response->assertSessionHasInput('description', 'Deskripsi yang diisi pengguna');
        $this->assertSame(0, Plan::count());
    }

    public function testDeadlineTidakBolehSebelumTanggalMulai(): void
    {
        $response = $this->post('/plans', [
            'name' => 'Rencana Uji',
            'start_date' => '2026-10-30',
            'deadline' => '2026-10-01',
            'priority' => 'Tinggi',
        ]);

        $response->assertSessionHasErrors('deadline');
        $this->assertSame(
            'Deadline tidak boleh sebelum tanggal mulai.',
            session('errors')->first('deadline')
        );
        $this->assertSame(0, Plan::count());
    }

    public function testFormTaskMemvalidasiNamaDurasiDanProgress(): void
    {
        $plan = $this->newPlan();

        $response = $this->post(route('tasks.store', $plan), [
            'name' => '',
            'duration' => 0,
            'priority' => 'Sedang',
            'progress' => 150,
        ]);

        $response->assertSessionHasErrors(['name', 'duration', 'progress']);
        $this->assertSame('Durasi harus bilangan bulat minimal 1.', session('errors')->first('duration'));
        $this->assertSame('Progress harus antara 0 dan 100.', session('errors')->first('progress'));
        $response->assertSessionHasInput('duration', 0);
        $this->assertSame(0, $plan->tasks()->count());
    }

    public function testLagDependencyHarusBilanganBulatMinimalNol(): void
    {
        $plan = $this->newPlan();
        $predecessor = $this->makeTask($plan, 'A');
        $task = $this->makeTask($plan, 'B');

        $response = $this->post(route('tasks.store', $plan), [
            'name' => 'B',
            'duration' => 2,
            'priority' => 'Sedang',
            'progress' => 0,
            'predecessors' => [
                ['task_id' => $predecessor->id, 'type' => 'FS', 'lag' => -1],
            ],
        ]);

        $response->assertSessionHasErrors('predecessors.0.lag');
        $this->assertSame(
            'Lag harus bilangan bulat minimal 0.',
            session('errors')->first('predecessors.0.lag')
        );
        $this->assertSame(0, $task->fresh()->dependencies()->count());
    }

    public function testJenisDependencyHarusSalahSatuDariEmpat(): void
    {
        $plan = $this->newPlan();
        $predecessor = $this->makeTask($plan, 'A');

        $response = $this->post(route('tasks.store', $plan), [
            'name' => 'B',
            'duration' => 2,
            'priority' => 'Sedang',
            'progress' => 0,
            'predecessors' => [
                ['task_id' => $predecessor->id, 'type' => 'XX', 'lag' => 0],
            ],
        ]);

        $response->assertSessionHasErrors('predecessors.0.type');
        // Task "A" tetap ada, task "B" dengan jenis tidak valid tidak dibuat.
        $this->assertSame(1, Task::count());
        $this->assertNull(Task::where('name', 'B')->first());
    }

    public function testFormAnggotaWajibNamaDanPeran(): void
    {
        $plan = $this->newPlan();

        $response = $this->post(route('members.store', $plan), [
            'name' => '',
            'role' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'role']);
        $this->assertSame('Nama anggota wajib diisi.', session('errors')->first('name'));
        $this->assertSame(0, $plan->members()->count());
    }

    public function testStatusTidakBisaDiinputManual(): void
    {
        $plan = $this->newPlan();

        // Field "status" lama tidak lagi divalidasi dan tidak disimpan.
        $this->post(route('tasks.store', $plan), [
            'name' => 'Tugas A',
            'duration' => 2,
            'priority' => 'Sedang',
            'status' => 'Selesai',
            'progress' => 40,
        ])->assertSessionHasNoErrors();

        $task = $plan->tasks()->firstWhere('name', 'Tugas A');
        $this->assertSame(40, $task->progress);
        $this->assertArrayNotHasKey('status', $task->getAttributes());

        // Progress tetap satu-satunya input: 50 saat disimpan, 0 saat dibuka kembali.
        $this->put(route('tasks.update', $task), [
            'name' => 'Tugas A',
            'duration' => 2,
            'priority' => 'Sedang',
            'progress' => 50,
        ])->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame(50, $task->progress);
        $this->assertArrayNotHasKey('status', $task->getAttributes());

        $this->put(route('tasks.update', $task), [
            'name' => 'Tugas A',
            'duration' => 2,
            'priority' => 'Sedang',
            'progress' => 0,
        ])->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame(0, $task->progress);
        $this->assertArrayNotHasKey('status', $task->getAttributes());
    }

    // ------------------------------------------------------------------

    private function newPlan(): Plan
    {
        return Plan::create([
            'name' => 'Rencana Uji',
            'start_date' => '2026-10-12',
            'deadline' => '2026-10-31',
            'priority' => 'Tinggi',
        ]);
    }

    private function makeTask(Plan $plan, string $name): Task
    {
        return app(TaskService::class)->save($plan, null, [
            'name' => $name,
            'duration' => 1,
            'earliest_start' => null,
            'parent_id' => null,
            'priority' => 'Sedang',
            'progress' => 0,
            'members' => [],
            'predecessors' => [],
        ])['task'];
    }
}
