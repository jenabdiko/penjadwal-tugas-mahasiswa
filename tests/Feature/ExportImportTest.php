<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Services\SampleDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ExportImportTest extends TestCase
{
    use RefreshDatabase;

    public function testEksporMenghasilkanBerkasJsonLengkap(): void
    {
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();

        $response = $this->get(route('plans.export', $plan));
        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.json', (string) $response->headers->get('content-disposition'));

        $payload = json_decode($response->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertSame('sistem-penjadwalan-tugas', $payload['app']);
        $this->assertSame(SampleDataService::PLAN_ONE, $payload['plan']['name']);
        $this->assertSame('2026-10-12', $payload['plan']['start_date']);
        $this->assertSame('2026-10-30', $payload['plan']['deadline']);
        $this->assertCount(4, $payload['members']);
        $this->assertCount(8, $payload['tasks']);
        $this->assertCount(0, $payload['holidays']);

        // Seluruh dependency contoh berjenis FS dengan lag 0.
        foreach ($payload['tasks'] as $task) {
            foreach ($task['predecessors'] as $predecessor) {
                $this->assertSame('FS', $predecessor['type']);
                $this->assertSame(0, $predecessor['lag']);
            }
        }
    }

    public function testEksporLaluImporMenghasilkanDataYangSama(): void
    {
        app(SampleDataService::class)->load();
        $plan = Plan::where('name', SampleDataService::PLAN_ONE)->firstOrFail();

        $exportResponse = $this->get(route('plans.export', $plan));
        $exportResponse->assertOk();
        $original = $exportResponse->getContent();
        $originalPayload = json_decode($original, true);

        $file = UploadedFile::fake()->createWithContent('rencana.json', $original);
        $response = $this->post(route('import.store'), ['file' => $file]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        // Impor selalu membuat rencana baru (2 contoh + 1 hasil impor).
        $this->assertSame(3, Plan::count());

        $imported = Plan::where('name', SampleDataService::PLAN_ONE)->orderByDesc('id')->firstOrFail();
        $this->assertNotSame($plan->id, $imported->id);

        // Data sama: hasil ekspor rencana hasil impor identik dengan ekspor aslinya.
        $reExport = $this->get(route('plans.export', $imported));
        $reExport->assertOk();
        $this->assertSame($originalPayload, json_decode($reExport->getContent(), true));

        // Hubungan inti ikut sama.
        $this->assertSame(8, $imported->tasks()->count());
        $this->assertSame(4, $imported->members()->count());
        // Contoh 1 punya 8 dependency: 2→1, 3→2, 4→2, 5→4, 6→3, 7→5, 7→6, 8→7.
        $this->assertSame(8, $imported->tasks()->with('dependencies')->get()->sum(fn ($task) => $task->dependencies->count()));
        $this->assertSame('2026-10-30', $imported->deadline->toDateString());
    }

    public function testImporBerkasLamaDenganStatusTetapDiterima(): void
    {
        // Berkas ekspor versi lama masih memuat field "status"; field itu diabaikan.
        $payload = [
            'app' => 'sistem-penjadwalan-tugas',
            'version' => 1,
            'plan' => [
                'name' => 'Rencana Lama',
                'description' => null,
                'start_date' => '2026-10-12',
                'deadline' => '2026-10-30',
                'priority' => 'Tinggi',
                'status' => 'Selesai',
                'off_saturday' => true,
                'off_sunday' => true,
            ],
            'holidays' => [],
            'members' => [],
            'tasks' => [
                [
                    'key' => 1,
                    'parent_key' => null,
                    'name' => 'Task Lama',
                    'duration' => 2,
                    'earliest_start' => null,
                    'priority' => 'Sedang',
                    'status' => 'Selesai',
                    'progress' => 50,
                    'sort_order' => 1,
                    'members' => [],
                    'predecessors' => [],
                ],
            ],
        ];

        $file = UploadedFile::fake()->createWithContent('lama.json', json_encode($payload));
        $response = $this->post(route('import.store'), ['file' => $file]);
        $response->assertSessionHasNoErrors();

        $plan = Plan::where('name', 'Rencana Lama')->firstOrFail();
        $task = $plan->tasks()->firstOrFail();

        // Status lama diabaikan: tidak disimpan, progress tetap apa adanya.
        $this->assertArrayNotHasKey('status', $plan->getAttributes());
        $this->assertArrayNotHasKey('status', $task->getAttributes());
        $this->assertSame(50, $task->progress);
    }

    public function testImporBerkasBukanJsonDitolak(): void
    {
        $file = UploadedFile::fake()->createWithContent('rusak.json', 'ini bukan json {rusak');
        $response = $this->post(route('import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('Berkas JSON tidak valid', session('errors')->first('file'));
        $this->assertSame(0, Plan::count());
    }

    public function testImporStrukturTidakValidDitolak(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'salah.json',
            json_encode(['foo' => 'bar'])
        );
        $response = $this->post(route('import.store'), ['file' => $file]);

        $response->assertSessionHasErrors('file');
        $this->assertStringContainsString('Struktur file tidak valid', session('errors')->first('file'));
        $this->assertSame(0, Plan::count());
    }

    public function testImporTanpaBerkarDitolak(): void
    {
        $response = $this->post(route('import.store'), []);

        $response->assertSessionHasErrors('file');
        $this->assertSame('Pilih berkas JSON terlebih dahulu.', session('errors')->first('file'));
    }
}
