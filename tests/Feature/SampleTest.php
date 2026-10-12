<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Services\SchedulerService;
use App\Services\StatusService;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SampleTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testMuatContohMembuatDuaRencana(): void
    {
        Carbon::setTestNow('2026-10-09 08:00:00');

        $response = $this->post('/samples');
        $response->assertRedirect(route('plans.index'));
        $response->assertSessionHas('success');

        $this->assertSame(2, Plan::count());
        $this->assertTrue(Plan::where('name', 'Tugas Besar Pemrograman Web')->exists());
        $this->assertTrue(Plan::where('name', 'Tugas Besar Pemrograman Web (Deadline Mepet)')->exists());

        $plan = Plan::where('name', 'Tugas Besar Pemrograman Web')->firstOrFail();
        $this->assertSame('2026-10-12', $plan->start_date->toDateString());
        $this->assertSame('2026-10-30', $plan->deadline->toDateString());
        $this->assertSame('Tinggi', $plan->priority);
        $this->assertCount(8, $plan->tasks);
        $this->assertCount(4, $plan->members);
        $this->assertCount(0, $plan->holidays);

        // Status tidak lagi disimpan di database.
        $this->assertArrayNotHasKey('status', $plan->getAttributes());

        $taskOne = $plan->tasks()->orderBy('sort_order')->first();
        $this->assertSame('Mencari referensi', $taskOne->name);
        $this->assertSame(100, $taskOne->progress);
        $this->assertArrayNotHasKey('status', $taskOne->getAttributes());
        $this->assertCount(2, $taskOne->members);

        $taskTwo = $plan->tasks()->orderBy('sort_order')->skip(1)->first();
        $this->assertSame(50, $taskTwo->progress);

        // Status dihitung otomatis dari jadwal + progress (hari ini 9 Okt 2026).
        $statuses = $this->statuses($plan);
        $this->assertSame(Plan::STATUS_DONE, $statuses['tasks'][$taskOne->id]);
        $this->assertSame(Plan::STATUS_DOING, $statuses['tasks'][$taskTwo->id]);
        $this->assertSame(Plan::STATUS_DOING, $statuses['plan']);

        $taskEight = $plan->tasks()->orderBy('sort_order')->skip(7)->first();
        $this->assertSame('Latihan presentasi', $taskEight->name);
        $this->assertSame(1, $taskEight->duration);
        $this->assertCount(4, $taskEight->members);
    }

    public function testMuatContohKeduaKaliTidakMenduplikasi(): void
    {
        $this->post('/samples');
        $this->assertSame(2, Plan::count());

        $response = $this->post('/samples');
        $response->assertRedirect(route('plans.index'));
        $response->assertSessionHas('warning');
        $this->assertSame('Data contoh sudah pernah dimuat, tidak dibuat ganda.', session('warning'));

        $this->assertSame(2, Plan::count());
        $this->assertSame(16, \App\Models\Task::count());
    }

    /**
     * Regresi bug "Terlambat 2 hari kerja": baris contoh lama bisa tersimpan
     * dengan Sabtu tetap dihitung hari kerja. Memuat ulang data contoh harus
     * menyamakan pengaturan hari kerja tanpa menduplikasi rencana.
     */
    public function testMuatContohMemperbaikiPengaturanHariKerjaLama(): void
    {
        $stale = Plan::create([
            'name' => 'Tugas Besar Pemrograman Web (Deadline Mepet)',
            'start_date' => '2026-10-12',
            'deadline' => '2026-10-23',
            'priority' => Plan::PRIORITY_HIGH,
            'off_saturday' => false,
            'off_sunday' => true,
        ]);

        $this->post('/samples');

        // Rencana contoh kedua tidak diduplikasi (yang tadinya hilang tetap dibuat).
        $this->assertSame(2, Plan::count());
        $this->assertSame(1, Plan::where('name', 'Tugas Besar Pemrograman Web (Deadline Mepet)')->count());
        $stale->refresh();
        $this->assertTrue($stale->off_saturday);
        $this->assertTrue($stale->off_sunday);
    }

    /** @return array{plan: string, tasks: array<int, string>} */
    private function statuses(Plan $plan): array
    {
        $schedule = app(SchedulerService::class)->schedule($plan);

        return app(StatusService::class)->compute($plan, $schedule);
    }
}
