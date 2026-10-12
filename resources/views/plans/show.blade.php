@extends('layouts.app')
@section('title', $plan->name)

@section('content')
@php
    $tabLabels = [
        'ringkasan' => 'Ringkasan',
        'tugas' => 'Task',
        'anggota' => 'Anggota',
        'gantt' => 'Gantt Chart',
        'jaringan' => 'Diagram Jaringan',
        'kritis' => 'Jalur Kritis',
    ];
    $taskCount = $plan->tasks()->count();
@endphp

<nav class="breadcrumb" aria-label="Lokasi halaman">
    <a href="{{ route('plans.index') }}">Beranda</a>
    <span class="crumb-sep" aria-hidden="true">/</span>
    <span aria-current="page">{{ $plan->name }}</span>
</nav>

<div class="card page-header plan-header">
    <div class="plan-header-main">
        <h1>{{ $plan->name }}</h1>
        @if ($plan->description)
            <p class="plan-desc">{{ $plan->description }}</p>
        @endif
        <p class="plan-info">
            <span>{{ \App\Support\Tanggal::singkat($plan->start_date) }} - {{ \App\Support\Tanggal::format($plan->deadline) }}</span>
            <span class="dot" aria-hidden="true">·</span>
            <span>Prioritas {{ $plan->priority }}</span>
            <span class="dot" aria-hidden="true">·</span>
            @include('partials.status', ['status' => $planStatus])
        </p>
    </div>
    <div class="page-actions">
        <a class="btn" href="{{ route('plans.edit', $plan) }}">Ubah</a>
        <details class="menu">
            <summary class="btn" aria-label="Aksi lainnya">Lainnya</summary>
            <div class="menu-pop">
                <a class="menu-item" href="{{ route('plans.export', $plan) }}">Ekspor JSON</a>
                <form method="POST" action="{{ route('plans.destroy', $plan) }}" class="inline-form"
                      data-confirm="Hapus rencana &quot;{{ $plan->name }}&quot; beserta {{ $taskCount }} task di dalamnya? Tindakan ini tidak dapat dibatalkan.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="menu-item menu-item-danger">Hapus</button>
                </form>
            </div>
        </details>
    </div>
</div>

<nav class="tabs" aria-label="Bagian rencana">
    @foreach ($tabLabels as $key => $label)
        <a class="tab {{ $tab === $key ? 'active' : '' }}"
           href="{{ route('plans.show', ['plan' => $plan, 'tab' => $key]) }}"
           @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>

<div class="tab-content">
    @include('plans.tabs.'.$tab)
</div>
@endsection
