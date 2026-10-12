@extends('layouts.app')
@section('title', 'Beranda')

@section('content')
{{-- Hero: ringkasan singkat + aksi utama halaman Beranda. --}}
<section class="hero">
    <div class="hero-text">
        <h1 class="hero-title">Rencana Tugasmu</h1>
        <p class="hero-lead">Pecah tugas jadi tahapan kecil, atur urutannya, dan pastikan selesai sebelum deadline.</p>
        <div class="hero-chips">
            <span class="chip"><strong>{{ $plans->count() }}</strong> rencana</span>
            <span class="chip {{ $atRisk > 0 ? 'chip-late' : '' }}"><strong>{{ $atRisk }}</strong> berisiko terlambat</span>
            <span class="chip"><strong>{{ $avgProgress }}%</strong> rata-rata progress</span>
        </div>
    </div>
    <div class="hero-actions">
        <form method="POST" action="{{ route('samples.load') }}" class="inline-form">
            @csrf
            <button type="submit" class="btn">Coba data contoh</button>
        </form>
        <a class="btn" href="{{ route('import.create') }}">Impor dari JSON</a>
    </div>
</section>

@if ($plans->isEmpty())
    <div class="card empty-state">
        <div class="empty-illustration" aria-hidden="true">
            <svg width="96" height="96" viewBox="0 0 96 96" fill="none">
                <rect x="12" y="18" width="72" height="66" rx="12" style="fill: var(--chip); stroke: var(--bar)" stroke-width="3"></rect>
                <path d="M32 10v14M64 10v14" style="stroke: var(--primary)" stroke-width="5" stroke-linecap="round"></path>
                <path d="M12 40h72" style="stroke: var(--bar)" stroke-width="3"></path>
                <path d="M34 62l12 12 20-22" style="stroke: var(--primary)" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        </div>
        <h2>Belum ada rencana</h2>
        <p class="muted">
            Rencanakan tahapan pengerjaan tugasmu: tentukan durasi, urutan, dan penanggung jawabnya,
            lalu lihat jadwalnya lewat Gantt Chart dan jalur kritis.
        </p>
        <div class="empty-actions">
            <a class="btn btn-primary" href="{{ route('plans.create') }}">Buat Tugas Baru</a>
            <form method="POST" action="{{ route('samples.load') }}" class="inline-form">
                @csrf
                <button type="submit" class="btn">Coba data contoh</button>
            </form>
        </div>
    </div>
@else
    <div class="plan-grid">
        @foreach ($plans as $plan)
            @php
                $eval = $evaluations[$plan->id];
                $status = $statuses[$plan->id];
                $progress = $progresses[$plan->id];
                $sisa = (int) now()->startOfDay()->diffInDays($plan->deadline->copy()->startOfDay(), false);
                $strip = match ($status) {
                    \App\Models\Plan::STATUS_DONE => 'done',
                    \App\Models\Plan::STATUS_LATE => 'late',
                    \App\Models\Plan::STATUS_DOING => 'doing',
                    default => 'todo',
                };
            @endphp
            <article class="card plan-card strip-{{ $strip }}">
                <h2 class="plan-card-title">
                    <a href="{{ route('plans.show', $plan) }}">{{ $plan->name }}</a>
                </h2>

                @if ($plan->description)
                    <p class="plan-card-desc">{{ $plan->description }}</p>
                @endif

                <div class="plan-card-badges">
                    @include('partials.status', ['status' => $status])
                    @if ($plan->priority === \App\Models\Plan::PRIORITY_HIGH)
                        <span class="tag tag-priority">Prioritas tinggi</span>
                    @endif
                </div>

                <p class="plan-card-deadline">
                    <svg class="icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="17" rx="3"></rect>
                        <path d="M8 2v4M16 2v4M3 10h18"></path>
                    </svg>
                    <span>
                        {{ \App\Support\Tanggal::format($plan->deadline) }} ·
                        @if ($sisa > 0)
                            {{ $sisa }} hari lagi
                        @elseif ($sisa === 0)
                            hari ini
                        @else
                            terlewat {{ abs($sisa) }} hari
                        @endif
                    </span>
                </p>

                <div class="plan-progress-row">
                    <div class="progress plan-progress" role="progressbar" aria-valuenow="{{ $progress }}"
                         aria-valuemin="0" aria-valuemax="100" aria-label="Progress {{ $plan->name }}">
                        <div class="progress-bar" style="width: {{ $progress }}%"></div>
                    </div>
                    <span class="progress-num">{{ $progress }}%</span>
                </div>

                <p class="plan-card-eval {{ $eval['on_time'] ? 'eval-ok' : 'eval-late' }}">
                    {{ \App\Support\Evaluasi::teks($eval) }}
                </p>

                <div class="plan-card-actions">
                    <a class="btn" href="{{ route('plans.show', $plan) }}">Buka</a>
                </div>
            </article>
        @endforeach
    </div>
@endif
@endsection
