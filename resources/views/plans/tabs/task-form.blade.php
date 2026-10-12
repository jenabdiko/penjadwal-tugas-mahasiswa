@php
    $editing = $formMode === 'edit' && $editTask !== null;
    $task = $editing ? $editTask : null;
    $isParentTask = $task !== null && isset($schedule['parents'][$task->id]);
    $actionUrl = $editing ? route('tasks.update', $task) : route('tasks.store', $plan);

    // Baris predecessor: dari input lama atau data task saat ini.
    $predRows = old('predecessors');
    if ($predRows === null && $task !== null) {
        $predRows = $task->dependencies->map(fn ($dep) => [
            'task_id' => $dep->predecessor_id,
            'type' => $dep->type,
            'lag' => $dep->lag,
        ])->values()->all();
    }
    $predRows = is_array($predRows) ? $predRows : [];

    // Penanggung jawab terpilih.
    $memberIds = old('members');
    if ($memberIds === null && $task !== null) {
        $memberIds = $task->members->pluck('id')->all();
    }
    $memberIds = array_map('intval', is_array($memberIds) ? $memberIds : []);

    // Opsi induk: hanya task utama (dan bukan dirinya sendiri).
    $parentOptions = $taskOptions
        ->filter(fn ($option) => $option->parent_id === null && $option->id !== ($task?->id))
        ->values();

    // Opsi predecessor: hanya task daun, tanpa dirinya sendiri.
    $leafIds = $taskOptions->pluck('id')->diff($taskOptions->pluck('parent_id')->filter()->unique())->values();
    $predOptions = $taskOptions
        ->filter(fn ($option) => $leafIds->contains($option->id) && $option->id !== ($task?->id))
        ->values();

    // Teks awam untuk jenis hubungan (dipakai di label dan menu).
    $relLabels = [
        'FS' => 'Mulai setelah tugas itu selesai',
        'SS' => 'Mulai bersamaan',
        'FF' => 'Selesai bersamaan',
        'SF' => 'Selesai setelah tugas itu dimulai',
    ];

    $oldParent = old('parent_id');
    $progress = (int) old('progress', $task?->progress ?? 0);
@endphp

<div class="card" id="task-form">
    <h2 class="form-heading">
        @if ($editing)
            Ubah Task: {{ $task->name }}
        @elseif ($addParentId)
            Tambah Sub-task dari "{{ $taskOptions->firstWhere('id', $addParentId)?->name }}"
        @else
            Tambah Task
        @endif
    </h2>

    <p class="form-note"><span class="req">*</span> wajib diisi</p>

    <form method="POST" action="{{ $actionUrl }}" class="form">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <h3 class="form-section">Informasi dasar</h3>
        <div class="form-grid">
            <div class="field field-full">
                <label for="task-name">Nama task <span class="req">*</span></label>
                <input type="text" id="task-name" name="name" maxlength="255"
                       value="{{ old('name', $task?->name) }}" placeholder="mis. Menulis laporan" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="task-priority">Prioritas <span class="req">*</span></label>
                <select id="task-priority" name="priority" required>
                    @foreach (\App\Models\Plan::PRIORITIES as $priority)
                        <option value="{{ $priority }}" @selected(old('priority', $task?->priority ?? 'Sedang') === $priority)>{{ $priority }}</option>
                    @endforeach
                </select>
                <span class="hint">Seberapa mendesak task ini.</span>
                @error('priority') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            @unless ($isParentTask)
                <div class="field">
                    <label for="task-progress">Progress <span class="req">*</span></label>
                    <div class="slider-row">
                        <input type="range" id="task-progress" name="progress" min="0" max="100" step="5"
                               value="{{ $progress }}" list="progress-ticks">
                        <output class="slider-value" id="progress-output" for="task-progress">{{ $progress }}%</output>
                    </div>
                    <datalist id="progress-ticks">
                        @foreach ([0, 25, 50, 75, 100] as $tick)
                            <option value="{{ $tick }}"></option>
                        @endforeach
                    </datalist>
                    <span class="hint">Status dihitung otomatis dari jadwal dan progress.</span>
                    @error('progress') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @endunless
        </div>

        @unless ($isParentTask)
            <h3 class="form-section">Waktu</h3>
            <div class="form-grid">
                <div class="field">
                    <label for="task-duration">Durasi (hari kerja) <span class="req">*</span></label>
                    <input type="number" id="task-duration" name="duration" min="1" step="1"
                           value="{{ old('duration', $task?->duration ?? 1) }}" required>
                    <span class="hint">mis. 2 = dikerjakan 2 hari kerja.</span>
                    @error('duration') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="task-earliest">Tanggal mulai paling awal</label>
                    <input type="date" id="task-earliest" name="earliest_start"
                           value="{{ old('earliest_start', $task?->earliest_start?->format('Y-m-d')) }}">
                    <span class="hint">Opsional; task tidak boleh mulai sebelum tanggal ini.</span>
                    @error('earliest_start') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="field field-full">
                    <label for="task-parent">Induk task</label>
                    <select id="task-parent" name="parent_id">
                        <option value="">— Task utama —</option>
                        @foreach ($parentOptions as $option)
                            <option value="{{ $option->id }}"
                                @selected((string) ($oldParent ?? $addParentId ?? $task?->parent_id) === (string) $option->id)>
                                {{ $schedule['numbers'][$option->id] ?? '?' }}. {{ $option->name }}
                            </option>
                        @endforeach
                    </select>
                    <span class="hint">Kosongkan untuk task utama. Sub-task maksimal satu tingkat.</span>
                    @error('parent_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <h3 class="form-section">Urutan pengerjaan (opsional)</h3>
            <p class="small muted">Task ini dikerjakan setelah task lain selesai. Jenis hubungan ditulis dengan kalimat, jeda dalam hari kerja.</p>

            <div id="pred-list" class="repeater repeater-pred">
                @foreach ($predRows as $index => $row)
                    <div class="repeater-row">
                        <select name="predecessors[{{ $index }}][task_id]" aria-label="Dikerjakan setelah task">
                            <option value="">— Pilih task —</option>
                            @foreach ($predOptions as $option)
                                <option value="{{ $option->id }}" @selected((string) old("predecessors.{$index}.task_id", $row['task_id'] ?? '') === (string) $option->id)>
                                    {{ $schedule['numbers'][$option->id] ?? '?' }}. {{ $option->name }}
                                </option>
                            @endforeach
                        </select>
                        <select name="predecessors[{{ $index }}][type]" aria-label="Jenis hubungan"
                                data-rel-select>
                            @foreach ($relLabels as $code => $label)
                                <option value="{{ $code }}" data-rel-label="{{ $label }}"
                                    @selected(old("predecessors.{$index}.type", $row['type'] ?? 'FS') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="predecessors[{{ $index }}][lag]" min="0" step="1"
                               value="{{ old("predecessors.{$index}.lag", $row['lag'] ?? 0) }}" placeholder="0"
                               aria-label="Jeda (hari kerja)" title="Jeda (hari kerja)">
                        <button type="button" class="btn btn-danger" data-remove-row>Hapus</button>
                    </div>
                @endforeach
            </div>
            <p class="small muted">Jumlah di kolom terakhir = Jeda (hari kerja).</p>
            @error('predecessors') <p class="field-error">{{ $message }}</p> @enderror
            @error('predecessors.*') <p class="field-error">{{ $message }}</p> @enderror

            <button type="button" class="btn" data-add-row="#pred-row-tpl" data-target="#pred-list">
                + Tambah Urutan Pengerjaan
            </button>

            <h3 class="form-section">Penanggung jawab (opsional)</h3>
            @if ($plan->members->isEmpty())
                <p class="muted">Belum ada anggota. Tambahkan anggota pada tab <strong>Anggota</strong> terlebih dahulu.</p>
            @else
                <div class="checkbox-group">
                    @foreach ($plan->members as $member)
                        <label class="checkbox">
                            <input type="checkbox" name="members[]" value="{{ $member->id }}"
                                   @checked(in_array($member->id, $memberIds, true))>
                            {{ $member->name }} <span class="muted">({{ $member->role }})</span>
                        </label>
                    @endforeach
                </div>
                @error('members') <p class="field-error">{{ $message }}</p> @enderror
                @error('members.*') <p class="field-error">{{ $message }}</p> @enderror
            @endif
        @else
            <p class="note">Task ini adalah ringkasan karena punya sub-task: durasi, urutan pengerjaan, dan penanggung jawab dihitung dari sub-tasknya.</p>
        @endunless

        <div class="form-actions">
            <button type="submit" class="btn">Simpan</button>
            <a class="btn" href="{{ route('plans.show', ['plan' => $plan, 'tab' => 'tugas']) }}">Batal</a>
        </div>
    </form>
</div>

<template id="pred-row-tpl">
    <div class="repeater-row">
        <select name="predecessors[__IDX__][task_id]" aria-label="Dikerjakan setelah task">
            <option value="">— Pilih task —</option>
            @foreach ($predOptions as $option)
                <option value="{{ $option->id }}">{{ $schedule['numbers'][$option->id] ?? '?' }}. {{ $option->name }}</option>
            @endforeach
        </select>
        <select name="predecessors[__IDX__][type]" aria-label="Jenis hubungan" data-rel-select>
            @foreach ($relLabels as $code => $label)
                <option value="{{ $code }}" data-rel-label="{{ $label }}">{{ $label }}</option>
            @endforeach
        </select>
        <input type="number" name="predecessors[__IDX__][lag]" min="0" step="1" value="0" placeholder="0"
               aria-label="Jeda (hari kerja)" title="Jeda (hari kerja)">
        <button type="button" class="btn btn-danger" data-remove-row>Hapus</button>
    </div>
</template>
