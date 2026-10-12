@php
    $project = $schedule['project'];
    $deadlineEval = $project['deadline'];
    $bannerIcon = $deadlineEval['on_time'] ? '✓' : '⚠';
    // Satu sumber teks Evaluasi::teks(); ikon bulat di depan sudah menggantikan glyph-nya.
    $bannerText = preg_replace('/^[✓⚠]\s*/u', '', \App\Support\Evaluasi::teks($deadlineEval));
    $hasTasks = !empty($schedule['rows']);
@endphp

@if (! $hasTasks)
    <div class="card empty-state">
        <h2>Belum ada task</h2>
        <p class="muted">Tambahkan task pertama untuk mulai menyusun rencana pengerjaan dan melihat ringkasannya.</p>
        <div class="empty-actions">
            <a class="btn" href="{{ route('plans.show', ['plan' => $plan, 'tab' => 'tugas', 'action' => 'add']) }}">Tambah Task</a>
        </div>
    </div>
@else
    <div class="banner {{ $deadlineEval['on_time'] ? 'banner-ok' : 'banner-late' }}">
        <span class="banner-icon" aria-hidden="true">{{ $bannerIcon }}</span>
        <div class="banner-body">
            <strong>{{ $bannerText }}</strong>
        </div>
    </div>

    <div class="grid-3">
        <div class="card stat">
            <span class="stat-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="17" rx="3"></rect>
                    <path d="M8 2v4M16 2v4M3 10h18"></path>
                </svg>
            </span>
            <div class="stat-value">{{ \App\Support\Tanggal::format($project['end_date']) }}</div>
            <div class="stat-label">Perkiraan Selesai</div>
        </div>
        <div class="card stat">
            <span class="stat-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 7v5l3 2"></path>
                </svg>
            </span>
            <div class="stat-value">{{ $project['total_working_days'] }} hari kerja</div>
            <div class="stat-label">Total Durasi</div>
        </div>
        <div class="card stat">
            <span class="stat-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 20V10M10 20V4M16 20v-7M22 20H2"></path>
                </svg>
            </span>
            <div class="stat-value">{{ $summary['progress'] }}%</div>
            <div class="stat-label">Progress Keseluruhan</div>
            <div class="progress progress-lg">
                <div class="progress-bar" style="width: {{ $summary['progress'] }}%"></div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">Segera Berakhir</h2>
        <p class="small muted">Task yang berakhir dalam 3 hari ke depan dan belum selesai.</p>
        @if (empty($summary['due_soon']))
            <p class="muted">Tidak ada task yang berakhir dalam 3 hari ke depan.</p>
        @else
            <div class="table-wrap">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Task</th>
                            <th>Selesai Pada</th>
                            <th>Progress</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['due_soon'] as $row)
                            <tr>
                                <td>{{ $row['number'] }}</td>
                                <td>{{ $row['task']->name }}</td>
                                <td class="nowrap">{{ \App\Support\Tanggal::format($row['end_date']) }}</td>
                                <td>{{ $row['progress'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif
