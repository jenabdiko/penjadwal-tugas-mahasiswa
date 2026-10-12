<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Services\SampleDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanPagesTest extends TestCase
{
    use RefreshDatabase;

    public function testHalamanFormRencanaDapatDitampilkan(): void
    {
        $this->get('/plans/create')
            ->assertOk()
            ->assertSee('Buat Tugas Baru')
            ->assertDontSee('name="status"', false);
    }

    public function testSemuaTabRencanaMengembalikan200(): void
    {
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();

        foreach (['ringkasan', 'tugas', 'anggota', 'gantt', 'jaringan', 'kritis'] as $tab) {
            $this->get(route('plans.show', ['plan' => $plan, 'tab' => $tab]))
                ->assertOk()
                ->assertSee('Gantt Chart'); // navigasi tab selalu tampil
        }
    }

    public function testTabRencanaTampilanGanttDanDiagramBerisi(): void
    {
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();

        $this->get(route('plans.show', ['plan' => $plan, 'tab' => 'gantt']))
            ->assertOk()
            ->assertSee('Sorot jalur kritis')
            ->assertSee('Deadline');

        $this->get(route('plans.show', ['plan' => $plan, 'tab' => 'jaringan']))
            ->assertOk()
            ->assertSee('Durasi: 2 hari');

        $this->get(route('plans.show', ['plan' => $plan, 'tab' => 'kritis']))
            ->assertOk()
            ->assertSee('1-2-4-5-7-8')
            ->assertSee('Jalur Kritis');
    }

    public function testTabTidakDikenalJatuhKeRingkasan(): void
    {
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();

        $this->get(route('plans.show', ['plan' => $plan, 'tab' => 'tidak-ada']))
            ->assertOk()
            ->assertSee('Perkiraan Selesai');
    }

    public function testHalamanRencanaTanpaTaskTetap200(): void
    {
        $plan = Plan::create([
            'name' => 'Rencana Kosong',
            'start_date' => '2026-10-12',
            'deadline' => '2026-10-30',
            'priority' => 'Sedang',
        ]);

        foreach (['ringkasan', 'tugas', 'anggota', 'gantt', 'jaringan', 'kritis'] as $tab) {
            $this->get(route('plans.show', ['plan' => $plan, 'tab' => $tab]))->assertOk();
        }
    }
}
