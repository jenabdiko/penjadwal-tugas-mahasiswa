<div class="toolbar">
    <button type="button" class="switch" role="switch" aria-checked="true"
            data-toggle-target="pert-diagram" data-initial="on" data-switch>
        <span class="switch-track" aria-hidden="true"><span class="switch-thumb"></span></span>
        Sorot jalur kritis
    </button>
    <span class="muted small">Node disusun dari kiri ke kanan menurut panjang rantai urutan pengerjaan.</span>
</div>

<div class="card diagram-wrap" id="pert-diagram">
    @unless ($pert['has_nodes'])
        <div class="empty-state">
            <h2>Belum ada task</h2>
            <p class="muted">Tambahkan task terlebih dahulu pada tab Task.</p>
            <div class="empty-actions">
                <a class="btn" href="{{ route('plans.show', ['plan' => $plan, 'tab' => 'tugas', 'action' => 'add']) }}">Tambah Task</a>
            </div>
        </div>
    @else
        <div class="svg-scroll">
            <svg width="{{ $pert['width'] }}" height="{{ $pert['height'] }}"
                 viewBox="0 0 {{ $pert['width'] }} {{ $pert['height'] }}" role="img"
                 aria-label="Diagram jaringan PERT">
                <defs>
                    <marker id="pert-arrowhead" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
                        <path d="M0,0 L8,3 L0,6 Z" style="fill: var(--arrow)"></path>
                    </marker>
                </defs>

                @foreach ($pert['edges'] as $edge)
                    <path class="pert-edge" d="{{ $edge['path'] }}" marker-end="url(#pert-arrowhead)"></path>
                    @if ($edge['label'] !== '')
                        <text class="pert-edge-label" x="{{ $edge['label_x'] }}" y="{{ $edge['label_y'] }}">{{ $edge['label'] }}</text>
                    @endif
                @endforeach

                @foreach ($pert['nodes'] as $node)
                    <g class="pert-node {{ $node['critical'] ? 'is-critical' : '' }}">
                        <title>{{ $node['number'] }}. {{ $node['name'] }} — durasi {{ $node['duration'] }} hari, {{ \App\Support\Tanggal::format($node['start_date']) }} s/d {{ \App\Support\Tanggal::format($node['end_date']) }}</title>
                        <rect x="{{ $node['x'] }}" y="{{ $node['y'] }}" width="{{ $node['w'] }}" height="{{ $node['h'] }}" rx="10"></rect>
                        <text class="pert-title" x="{{ $node['x'] + 14 }}" y="{{ $node['y'] + 26 }}">{{ $node['number'] }}. {{ $node['short_name'] }}</text>
                        <text class="pert-meta" x="{{ $node['x'] + 14 }}" y="{{ $node['y'] + 50 }}">Durasi: {{ $node['duration'] }} hari</text>
                        <text class="pert-meta" x="{{ $node['x'] + 14 }}" y="{{ $node['y'] + 72 }}">
                            {{ \App\Support\Tanggal::singkat($node['start_date']) }} – {{ \App\Support\Tanggal::singkat($node['end_date']) }}
                        </text>
                        @if ($node['critical'])
                            <text class="pert-badge" x="{{ $node['x'] + $node['w'] - 14 }}" y="{{ $node['y'] + 26 }}" text-anchor="end">Kritis</text>
                        @endif
                    </g>
                @endforeach
            </svg>
        </div>
    @endunless
</div>
