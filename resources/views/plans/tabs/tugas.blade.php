@php
    $showTaskForm = $formMode !== null;

    $relLabels = [
        'FS' => 'Mulai setelah tugas itu selesai',
        'SS' => 'Mulai bersamaan',
        'FF' => 'Selesai bersamaan',
        'SF' => 'Selesai setelah tugas itu dimulai',
    ];
@endphp

<div class="toolbar">
    @if ($showTaskForm)
        <a class="btn" href="{{ route('plans.show', ['plan' => $plan, 'tab' => 'tugas']) }}">Tutup Form</a>
    @else
        <a class="btn" href="{{ route('plans.show', ['plan' => $plan, 'tab' => 'tugas', 'action' => 'add']) }}">+ Tambah Task</a>
    @endif
    <span class="muted small">
        {{ count($schedule['tasks']) }} task dijadwalkan ·
        {{ count($schedule['parents']) }} task induk ·
        {{ count($schedule['project']['critical_task_ids']) }} task kritis
    </span>
</div>

@if ($showTaskForm)
    @include('plans.tabs.task-form')
@endif

@if (empty($schedule['rows']))
    <div class="card empty-state">
        <h2>Belum ada task</h2>
        <p class="muted">Tambahkan task pertama untuk mulai menyusun rencana pengerjaan.</p>
        <div class="empty-actions">
            <a class="btn" href="{{ route('plans.show', ['plan' => $plan, 'tab' => 'tugas', 'action' => 'add']) }}">Tambah Task Pertama</a>
        </div>
    </div>
@else
    <div class="table-wrap">
        <table class="table table-tasks">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Jadwal</th>
                    <th>Durasi</th>
                    <th>Penanggung jawab</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th class="col-actions">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($schedule['rows'] as $row)
                    @php
                        $task = $row['task'];
                        $status = $taskStatuses[$row['id']] ?? \App\Models\Plan::STATUS_TODO;
                        $isDone = $status === \App\Models\Plan::STATUS_DONE;

                        // Nomor predecessor untuk teks kecil "Setelah: 2, 3".
                        $predNumbers = [];
                        if (! $row['is_parent']) {
                            foreach ($task->dependencies as $dep) {
                                $predNumbers[] = $schedule['numbers'][$dep->predecessor_id] ?? '?';
                            }
                        }
                    @endphp
                    <tr class="{{ $row['is_parent'] ? 'row-parent' : '' }}">
                        <td>
                            <div class="task-name-row" style="padding-left: {{ $row['level'] * 20 }}px">
                                <span class="task-no">{{ $row['number'] }}</span>
                                <span class="task-name">{{ $task->name }}</span>
                                @if ($task->priority === \App\Models\Plan::PRIORITY_HIGH)
                                    <span class="tag tag-priority" title="Prioritas tinggi">Tinggi</span>
                                @endif
                            </div>
                            <div class="task-sub">
                                @if ($predNumbers !== [])
                                    <span class="small muted">Setelah: {{ implode(', ', $predNumbers) }}</span>
                                @endif
                                @if ($row['is_critical'])
                                    <span class="tag tag-critical">Kritis</span>
                                @endif
                            </div>
                        </td>
                        <td class="nowrap">{{ \App\Support\Tanggal::singkat($row['start_date']) }} - {{ \App\Support\Tanggal::singkat($row['end_date']) }}</td>
                        <td class="nowrap">{{ $row['duration'] }} hari</td>
                        <td>{{ $row['members'] }}</td>
                        <td>
                            <div class="progress-cell">
                                <div class="progress progress-thin" role="progressbar" aria-valuenow="{{ $row['progress'] }}"
                                     aria-valuemin="0" aria-valuemax="100" aria-label="Progress {{ $task->name }}">
                                    <div class="progress-bar" style="width: {{ $row['progress'] }}%"></div>
                                </div>
                                <span class="progress-num">{{ $row['progress'] }}%</span>
                            </div>
                        </td>
                        <td>@include('partials.status', ['status' => $status])</td>
                        <td class="col-actions">
                            <a class="btn btn-sm"
                               href="{{ route('plans.show', ['plan' => $plan, 'tab' => 'tugas', 'action' => 'edit', 'task' => $row['id']]) }}">Ubah</a>

                            <form method="POST" action="{{ route('tasks.progress', $row['id']) }}" class="inline-form">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="progress" value="{{ $isDone ? 0 : 100 }}">
                                <button type="submit" class="btn btn-sm">{{ $isDone ? 'Buka kembali' : 'Tandai selesai' }}</button>
                            </form>

                            <details class="menu">
                                <summary class="btn btn-sm" aria-label="Aksi lainnya untuk {{ $task->name }}">…</summary>
                                <div class="menu-pop">
                                    @if ($row['level'] === 0)
                                        <a class="menu-item"
                                           href="{{ route('plans.show', ['plan' => $plan, 'tab' => 'tugas', 'action' => 'add', 'parent' => $row['id']]) }}">Tambah sub-task</a>
                                    @endif
                                    <form method="POST" action="{{ route('tasks.destroy', $row['id']) }}" class="inline-form"
                                          data-confirm="Hapus task &quot;{{ $task->name }}&quot;{{ $row['is_parent'] ? ' beserta seluruh sub-task' : '' }} dan urutan pengerjaannya? Tindakan ini tidak dapat dibatalkan.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="menu-item menu-item-danger">Hapus</button>
                                    </form>
                                </div>
                            </details>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
