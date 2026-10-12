<?php

namespace Database\Seeders;

use App\Services\SampleDataService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Logika yang sama dengan tombol "Muat Contoh" pada aplikasi.
        app(SampleDataService::class)->load();
    }
}
