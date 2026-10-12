@php
    // Kelompokkan kolom hari per bulan untuk header timeline.
    $months = [];
    foreach ($gantt['days'] as $day) {
        $key = $day['month'];
        $months[$key] = ($months[$key] ?? 0) + 1;
    }
    $dayCount = count($gantt['days']);
@endphp

<div class="toolbar">
    <button type="button" class="switch" role="switch" aria-checked="false"
            data-toggle-target="gantt-chart" data-switch>
        <span class="switch-track" aria-hidden="true"><span class="switch-thumb"></span></span>
        Sorot jalur kritis
    </button>

    <div class="legend" aria-label="Keterangan legenda">
        <span class="legend-item"><span class="swatch swatch-task"></span> Task</span>
        <span class="legend-item legend-critical"><span class="swatch swatch-critical"></span> Task kritis</span>
        <span class="legend-item"><span class="swatch swatch-off"></span> Akhir pekan/libur</span>
        <span class="legend-item"><span class="swatch swatch-deadline"></span> Deadline</span>
        <span class="legend-item"><span class="swatch swatch-today"></span> Hari ini</span>
    </div>
</div>

@if (empty($gantt['bars']))
    <div class="card empty-state">
        <h2>Belum ada task</h2>
        <p class="muted">Tambahkan task terlebih dahulu pada tab Task.</p>
        <div class="empty-actions">
            <a class="btn" href="{{ route('plans.show', ['plan' => $plan, 'tab' => 'tugas', 'action' => 'add']) }}">Tambah Task</a>
        </div>
    </div>
@else
    <div class="gantt" id="gantt-chart" data-days="{{ $dayCount }}">
        <div class="gantt-left">
            <div class="gantt-head-left">
                <div class="gl gl-no">No</div>
                <div class="gl gl-name">Nama task</div>
                <div class="gl gl-member">Penanggung jawab</div>
                <div class="gl gl-dur">Durasi</div>
            </div>
            @foreach ($schedule['rows'] as $row)
                <div class="gantt-row-left">
                    <div class="gl gl-no">{{ $row['number'] }}</div>
                    <div class="gl gl-name">
                        <span class="task-name" style="padding-left: {{ $row['level'] * 12 }}px" title="{{ $row['task']->name }}">
                            {{ $row['task']->name }}
                        </span>
                    </div>
                    <div class="gl gl-member" title="{{ $row['members'] }}">{{ $row['members'] }}</div>
                    <div class="gl gl-dur">{{ $row['duration'] }} hari</div>
                </div>
            @endforeach
        </div>

        <div class="gantt-right" id="gantt-scroll">
            <div class="gantt-timeline" style="width: calc(var(--col-width) * {{ $dayCount }})">
                <div class="gantt-head-right">
                    <div class="gantt-months">
                        @foreach ($months as $label => $count)
                            <div class="gantt-month" style="width: calc(var(--col-width) * {{ $count }})">{{ $label }}</div>
                        @endforeach
                    </div>
                    <div class="gantt-days">
                        @foreach ($gantt['days'] as $day)
                            <div class="gantt-day {{ ($day['is_weekend'] || $day['is_holiday']) ? 'off' : '' }} {{ $day['is_today'] ? 'is-today' : '' }}"
                                 style="width: var(--col-width)">{{ $day['day'] }}</div>
                        @endforeach
                    </div>
                </div>

                <div class="gantt-body">
                    <div class="gantt-grid" aria-hidden="true">
                        @foreach ($gantt['days'] as $index => $day)
                            <div class="gantt-col {{ ($day['is_weekend'] || $day['is_holiday']) ? 'off' : '' }} {{ $day['is_holiday'] ? 'holiday' : '' }}"
                                 style="left: calc(var(--col-width) * {{ $index }}); width: var(--col-width)"></div>
                        @endforeach
                    </div>

                    @foreach ($schedule['rows'] as $row)
                        @php $bar = $gantt['bars'][$row['id']] ?? null; @endphp
                        <div class="gantt-row">
                            @if ($bar)
                                <div id="gantt-bar-{{ $row['id'] }}"
                                     class="gantt-bar {{ $bar['summary'] ? 'is-summary' : '' }} {{ $bar['critical'] ? 'is-critical' : '' }}"
                                     style="left: calc(var(--col-width) * {{ $bar['left'] }}); width: calc(var(--col-width) * {{ $bar['width'] }});"
                                     title="{{ $bar['title'] }}">
                                    @unless ($bar['summary'])
                                        <div class="gantt-bar-progress" style="width: {{ $bar['progress'] }}%"></div>
                                    @endunless
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="gantt-line gantt-deadline" style="left: calc(var(--col-width) * {{ $gantt['deadline'] }})">
                        <span>Deadline</span>
                    </div>
                    @if ($gantt['today'] !== null)
                        <div class="gantt-line gantt-today" style="left: calc(var(--col-width) * {{ $gantt['today'] }})">
                            <span>Hari ini</span>
                        </div>
                    @endif

                    <svg class="gantt-svg" id="gantt-arrows" aria-hidden="true">
                        <defs>
                            <marker id="gantt-arrowhead" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
                                <path d="M0,0 L8,3 L0,6 Z" style="fill: var(--arrow)"></path>
                            </marker>
                        </defs>
                    </svg>
                </div>
            </div>
        </div>
    </div>
@endif

@push('scripts')
    <script>window.GANTT_ARROWS = @json($gantt['arrows']);</script>
    <script src="{{ asset('js/gantt.js') }}"></script>
@endpush
