@extends('layouts.app')
@section('title', $plan ? 'Ubah Rencana' : 'Buat Tugas Baru')

@section('content')
<div class="page-header">
    <div>
        <h1>{{ $plan ? 'Ubah Rencana' : 'Buat Tugas Baru' }}</h1>
        <p class="muted">Isi detail rencana pengerjaan tugas beserta hari kerja dan hari liburnya.</p>
    </div>
</div>

@php
    $action = $plan ? route('plans.update', $plan) : route('plans.store');
    $holidays = old('holidays', $plan
        ? $plan->holidays->map(fn ($h) => ['date' => $h->date->format('Y-m-d'), 'name' => $h->name])->values()->all()
        : []);
@endphp

<p class="form-note"><span class="req">*</span> wajib diisi</p>

<form method="POST" action="{{ $action }}" class="card form">
    @csrf
    @if ($plan)
        @method('PUT')
    @endif

    <h2 class="form-section">Informasi dasar</h2>
    <div class="form-grid">
        <div class="field field-full">
            <label for="name">Nama rencana <span class="req">*</span></label>
            <input type="text" id="name" name="name" maxlength="255"
                   value="{{ old('name', $plan?->name) }}" placeholder="mis. Tugas Besar Pemrograman Web" required>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field field-full">
            <label for="description">Deskripsi</label>
            <textarea id="description" name="description" rows="3"
                      placeholder="mis. Tugas besar pemrograman web kelompok 3">{{ old('description', $plan?->description) }}</textarea>
            <span class="hint">Satu kalimat ringkas tentang rencana ini.</span>
            @error('description') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="priority">Prioritas <span class="req">*</span></label>
            <select id="priority" name="priority" required>
                @foreach (\App\Models\Plan::PRIORITIES as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', $plan?->priority ?? 'Sedang') === $priority)>{{ $priority }}</option>
                @endforeach
            </select>
            <span class="hint">Seberapa mendesak rencana ini.</span>
            @error('priority') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <h2 class="form-section">Waktu</h2>
    <div class="form-grid">
        <div class="field">
            <label for="start_date">Tanggal mulai <span class="req">*</span></label>
            <input type="date" id="start_date" name="start_date"
                   value="{{ old('start_date', $plan ? $plan->start_date->format('Y-m-d') : now()->format('Y-m-d')) }}" required>
            <span class="hint">Hari pertama pengerjaan.</span>
            @error('start_date') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="deadline">Deadline <span class="req">*</span></label>
            <input type="date" id="deadline" name="deadline"
                   value="{{ old('deadline', $plan ? $plan->deadline->format('Y-m-d') : now()->addWeek()->format('Y-m-d')) }}" required>
            <span class="hint">Tugas harus selesai sebelum tanggal ini.</span>
            @error('deadline') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <details class="work-settings">
        <summary>Pengaturan hari kerja (opsional)</summary>

        <div class="field-full checkbox-row work-days">
            <label class="checkbox">
                <input type="checkbox" name="off_saturday" value="1"
                       @checked(old('off_saturday', $plan ? $plan->off_saturday : true))>
                Sabtu libur (tidak dihitung hari kerja)
            </label>
            <label class="checkbox">
                <input type="checkbox" name="off_sunday" value="1"
                       @checked(old('off_sunday', $plan ? $plan->off_sunday : true))>
                Minggu libur (tidak dihitung hari kerja)
            </label>
        </div>

        <h3 class="section-title">Hari Libur Khusus</h3>
        <p class="small muted">Tanggal merah tambahan selain Sabtu/Minggu. Kosongkan baris yang tidak dipakai.</p>

        <div id="holiday-list" class="repeater">
            @foreach ($holidays as $index => $holiday)
                <div class="repeater-row">
                    <input type="date" name="holidays[{{ $index }}][date]" value="{{ $holiday['date'] }}" aria-label="Tanggal hari libur">
                    <input type="text" name="holidays[{{ $index }}][name]" value="{{ $holiday['name'] }}"
                           placeholder="Nama hari libur" aria-label="Nama hari libur">
                    <button type="button" class="btn btn-danger" data-remove-row>Hapus</button>
                </div>
            @endforeach
        </div>
        @error('holidays') <p class="field-error">{{ $message }}</p> @enderror
        @error('holidays.*.date') <p class="field-error">{{ $message }}</p> @enderror
        @error('holidays.*.name') <p class="field-error">{{ $message }}</p> @enderror

        <button type="button" class="btn" id="add-holiday" data-add-row="#holiday-row-tpl" data-target="#holiday-list">
            + Tambah Hari Libur
        </button>
    </details>

    <div class="form-actions">
        <button type="submit" class="btn">Simpan</button>
        <a class="btn" href="{{ $plan ? route('plans.show', $plan) : route('plans.index') }}">Batal</a>
    </div>
</form>

<template id="holiday-row-tpl">
    <div class="repeater-row">
        <input type="date" name="holidays[__IDX__][date]" value="" aria-label="Tanggal hari libur">
        <input type="text" name="holidays[__IDX__][name]" placeholder="Nama hari libur" aria-label="Nama hari libur">
        <button type="button" class="btn btn-danger" data-remove-row>Hapus</button>
    </div>
</template>
@endsection
