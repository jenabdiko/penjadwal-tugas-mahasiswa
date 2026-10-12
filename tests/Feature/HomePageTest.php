<?php

namespace Tests\Feature;

use App\Services\SampleDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function testBerandaDapatDitampilkan(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Rencana Tugasmu')
            ->assertSee('Buat Tugas Baru')
            ->assertSee('Coba data contoh')
            ->assertSee('Impor dari JSON')
            ->assertDontSee('Muat Contoh');
    }

    public function testBerandaMenampilkanKosonganYangMembimbing(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Belum ada rencana');
    }

    public function testBerandaMenampilkanRencanaContohDanEvaluasiDeadline(): void
    {
        app(SampleDataService::class)->load();

        $this->get('/')
            ->assertOk()
            ->assertSee(SampleDataService::PLAN_ONE)
            ->assertSee(SampleDataService::PLAN_TWO)
            // Nilai evaluasi: Contoh 1 cadangan 2 hari kerja, Contoh 2 terlambat 3 hari kerja
            // (teks tampilan seragam lewat App\Support\Evaluasi::teks).
            ->assertSee('cadangan 2 hari kerja')
            ->assertSee('terlambat 3 hari kerja')
            ->assertDontSee('terlambat 2 hari kerja')
            ->assertSee('Buka');
    }
}
