@php
    $project = $schedule['project'];
    $criticalLabels = array_column($project['critical_paths'], 'label');
    $bannerIcon = $project['deadline']['on_time'] ? '✓' : '⚠';
    // Satu sumber teks Evaluasi::teks(); ikon bulat di depan sudah menggantikan glyph-nya.
    $bannerText = preg_replace('/^[✓⚠]\s*/u', '', \App\Support\Evaluasi::teks($project['deadline']));
@endphp

<div class="page-intro">
    <h2 class="section-title">Jalur kritis</h2>
    <p class="small muted">Rangkaian task yang kalau telat membuat seluruh tugas ikut telat.</p>
</div>

@if ($project['has_non_fs_or_lag'])
    <div class="alert alert-info" role="note">
        <span>Catatan: ada hubungan berjenis selain FS atau jeda lebih dari 0, sehingga <strong>total durasi jalur adalah perhitungan sederhana</strong> (jumlah durasi task pada jalur). Penanda task kritis tetap memakai slack hasil forward/backward pass.</span>
    </div>
@endif

<div class="grid-2">
    <div class="card">
        <h3 class="section-title">Semua Jalur</h3>
        <p class="small muted">Nomor mengikuti kolom No pada tab Task. Jalur dengan total terpanjang = jalur kritis.</p>
        <div class="table-wrap">
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Jalur</th>
                        <th>Total Durasi</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($project['paths'] as $index => $path)
                        @php
                            $names = [];
                            foreach ($path['ids'] as $taskId) {
                                $row = collect($schedule['rows'])->firstWhere('id', $taskId);
                                if ($row) {
                                    $names[] = $row['number'].'. '.$row['task']->name;
                                }
                            }
                            $isCritical = in_array($path['label'], $criticalLabels, true);
                        @endphp
                        <tr class="{{ $isCritical ? 'row-critical' : '' }}">
                            <td>{{ $index + 1 }}</td>
                            <td class="nowrap">
                                <strong>{{ $path['label'] }}</strong>
                                <div class="small muted" title="{{ implode(' → ', $names) }}">{{ implode(' → ', $names) }}</div>
                            </td>
                            <td class="nowrap">{{ $path['total'] }} hari</td>
                            <td>
                                @if ($isCritical)
                                    <span class="tag tag-critical">Kritis</span>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">Belum ada jalur (belum ada task daun berurutan pengerjaan).</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3 class="section-title">Jadwal Per Task (CPM)</h3>
        <p class="small muted">ES/EF = jadwal paling awal; LS/LF = jadwal paling akhir; slack = LS − ES (hari kerja).</p>
        <div class="table-wrap">
            <table class="table table-compact">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Task</th>
                        <th>ES</th>
                        <th>EF</th>
                        <th>LS</th>
                        <th>LF</th>
                        <th>Slack</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schedule['rows'] as $row)
                        @if ($row['is_parent'])
                            @continue
                        @endif
                        @php $info = $schedule['tasks'][$row['id']]; @endphp
                        <tr class="{{ $info['is_critical'] ? 'row-critical' : '' }}">
                            <td>{{ $row['number'] }}</td>
                            <td class="nowrap">{{ $row['task']->name }}</td>
                            <td class="nowrap">{{ \App\Support\Tanggal::format($info['start_date']) }}</td>
                            <td class="nowrap">{{ \App\Support\Tanggal::format($info['end_date']) }}</td>
                            <td class="nowrap">{{ \App\Support\Tanggal::format($info['ls_date']) }}</td>
                            <td class="nowrap">{{ \App\Support\Tanggal::format($info['lf_date']) }}</td>
                            <td>{{ $info['slack'] }} hari</td>
                            <td>
                                @if ($info['is_critical'])
                                    <span class="tag tag-critical">Kritis</span>
                                @else
                                    <span class="muted">Tidak kritis</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">Belum ada task.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="banner {{ $project['deadline']['on_time'] ? 'banner-ok' : 'banner-late' }}">
    <span class="banner-icon" aria-hidden="true">{{ $bannerIcon }}</span>
    <div class="banner-body">
        <strong>{{ $bannerText }}</strong>
    </div>
</div>
