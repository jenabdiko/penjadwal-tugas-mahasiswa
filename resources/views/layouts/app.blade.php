<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Beranda') · Penjadwal Tugas</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="navbar">
    <div class="container navbar-inner">
        <a class="brand" href="{{ route('plans.index') }}">
            <span class="brand-mark" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="17" rx="3"></rect>
                    <path d="M8 2v4M16 2v4M3 10h18"></path>
                </svg>
            </span>
            <span class="brand-text">Penjadwal Tugas</span>
        </a>
        <nav class="nav" aria-label="Navigasi utama">
            <a class="nav-link {{ request()->routeIs('plans.index') ? 'active' : '' }}"
               href="{{ route('plans.index') }}"
               @if (request()->routeIs('plans.index')) aria-current="page" @endif>Beranda</a>
            <a class="btn btn-primary" href="{{ route('plans.create') }}">+ Buat Tugas Baru</a>
        </nav>
    </div>
</header>

<main class="container main">
    @if (session('success'))
        <div class="alert alert-success" role="status">
            <span>{{ session('success') }}</span>
            <button type="button" class="alert-close" aria-label="Tutup notifikasi">×</button>
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning" role="status">
            <span>{{ session('warning') }}</span>
            <button type="button" class="alert-close" aria-label="Tutup notifikasi">×</button>
        </div>
    @endif

    @if (session('warnings'))
        <div class="alert alert-warning" role="status">
            <div><strong>Peringatan:</strong></div>
            <ul class="alert-list">
                @foreach (session('warnings') as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
            <button type="button" class="alert-close" aria-label="Tutup notifikasi">×</button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error" role="status">
            <div><strong>Terjadi kesalahan:</strong></div>
            <ul class="alert-list">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="alert-close" aria-label="Tutup notifikasi">×</button>
        </div>
    @endif

    @yield('content')
</main>

<footer class="footer">
    <div class="container">
        Sistem Penjadwalan Pengerjaan Tugas Mahasiswa — perencanaan tahapan tugas (bukan jadwal kuliah, bukan sistem pengumpulan tugas).
    </div>
</footer>

{{-- Dialog konfirmasi hapus: menyebut nama item dan akibatnya, fokus awal di "Batal". --}}
<dialog class="confirm-dialog" id="confirm-dialog" aria-labelledby="confirm-dialog-title">
    <h2 class="confirm-title" id="confirm-dialog-title">Konfirmasi hapus</h2>
    <p class="confirm-message" id="confirm-dialog-message"></p>
    <div class="dialog-actions">
        <button type="button" class="btn" id="confirm-dialog-cancel">Batal</button>
        <button type="button" class="btn btn-danger" id="confirm-dialog-ok">Hapus</button>
    </div>
</dialog>

<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
