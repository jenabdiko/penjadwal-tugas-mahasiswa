@extends('layouts.app')
@section('title', 'Impor dari JSON')

@section('content')
<div class="page-header">
    <div>
        <h1>Impor dari JSON</h1>
        <p class="muted">Buat rencana baru dari berkas JSON hasil tombol <strong>Ekspor JSON</strong>.</p>
    </div>
</div>

<div class="card form import-card">
    <p class="form-note"><span class="req">*</span> wajib diisi</p>

    <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="field">
            <label for="file">Berkas JSON <span class="req">*</span></label>
            <input type="file" id="file" name="file" accept=".json,application/json" required>
            <span class="hint">mis. rencana-tugas-besar.json</span>
            @error('file') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <p class="small muted">
            Struktur berkas divalidasi lebih dahulu; pesan kesalahan akan ditampilkan bila formatnya tidak sesuai.
            Impor selalu membuat rencana baru (tidak menimpa rencana lama).
        </p>

        <div class="form-actions">
            <button type="submit" class="btn">Impor Rencana</button>
            <a class="btn" href="{{ route('plans.index') }}">Batal</a>
        </div>
    </form>
</div>
@endsection
