{{-- Badge status (hanya-baca): dihitung otomatis dari jadwal dan progress.
     Selesai/Terlambat diawali ikon agar status tidak hanya dibedakan lewat warna. --}}
@php
    $slug = \Illuminate\Support\Str::slug($status);
    $ikon = match ($status) {
        \App\Models\Plan::STATUS_DONE => '✓ ',
        \App\Models\Plan::STATUS_LATE => '⚠ ',
        default => '',
    };
@endphp
<span class="badge status-{{ $slug }}">{{ $ikon }}{{ $status }}</span>
