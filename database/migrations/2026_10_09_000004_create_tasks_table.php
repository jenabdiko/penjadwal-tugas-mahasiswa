<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('duration')->default(1);
            $table->date('earliest_start')->nullable();
            $table->string('priority')->default('Sedang');
            $table->string('status')->default('Belum Dimulai');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->index(['plan_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
