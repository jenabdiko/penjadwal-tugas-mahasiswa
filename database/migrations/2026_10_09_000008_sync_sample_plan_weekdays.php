<?php

use App\Services\SampleDataService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaikan data contoh lama.
 *
 * Baris contoh yang tersimpan sebelum definisi saat ini dibuat bisa memiliki
 * pengaturan hari kerja yang berbeda (mis. Contoh 2 tersimpan dengan Sabtu
 * tetap dihitung hari kerja), sehingga evaluasi deadline di Beranda menampilkan
 * "Terlambat 2 hari kerja" padahal seharusnya "Terlambat 3 hari kerja".
 * Migrasi ini menyamakan pengaturan hari kedua contoh dengan definisi
 * SampleDataService saat ini (Sabtu & Minggu libur).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')
            ->whereIn('name', [SampleDataService::PLAN_ONE, SampleDataService::PLAN_TWO])
            ->where(function ($query): void {
                $query->where('off_saturday', false)->orWhere('off_sunday', false);
            })
            ->update([
                'off_saturday' => true,
                'off_sunday' => true,
            ]);
    }

    public function down(): void
    {
        // Perbaikan data: nilai lama tidak diketahui, tidak bisa dibatalkan.
    }
};
