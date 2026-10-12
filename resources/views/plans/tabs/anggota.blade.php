@php
    $editingMember = $editMember !== null;
    $member = $editingMember ? $editMember : null;
    $memberAction = $editingMember ? route('members.update', $member) : route('members.store', $plan);
@endphp

<div class="toolbar">
    <span class="muted small">{{ $plan->members->count() }} anggota terdaftar</span>
</div>

<div class="grid-2">
    <div class="card">
        <h2 class="form-heading">{{ $editingMember ? 'Ubah Anggota' : 'Tambah Anggota' }}</h2>
        <p class="form-note"><span class="req">*</span> wajib diisi</p>
        <form method="POST" action="{{ $memberAction }}" class="form">
            @csrf
            @if ($editingMember)
                @method('PUT')
            @endif

            <div class="field">
                <label for="member-name">Nama <span class="req">*</span></label>
                <input type="text" id="member-name" name="name" maxlength="255"
                       value="{{ old('name', $member?->name) }}" placeholder="mis. Ani" required>
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="member-role">Peran <span class="req">*</span></label>
                <input type="text" id="member-role" name="role" maxlength="255"
                       value="{{ old('role', $member?->role) }}" placeholder="mis. Ketua" required>
                <span class="hint">mis. Ketua, Programmer, Dokumentasi, Tester.</span>
                @error('role') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="field">
                <label for="member-contact">Kontak</label>
                <input type="text" id="member-contact" name="contact" maxlength="255"
                       value="{{ old('contact', $member?->contact) }}" placeholder="mis. ani@contoh.id">
                @error('contact') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Simpan</button>
                @if ($editingMember)
                    <a class="btn" href="{{ route('plans.show', ['plan' => $plan, 'tab' => 'anggota']) }}">Batal</a>
                @endif
            </div>
        </form>
    </div>

    <div class="card">
        <h2 class="section-title">Daftar Anggota</h2>
        @if ($plan->members->isEmpty())
            <div class="empty-state empty-state-inline">
                <p class="muted">Belum ada anggota. Tambahkan anggota pertama lewat form di sebelah.</p>
            </div>
        @else
            <div class="table-wrap">
                <table class="table table-compact">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Peran</th>
                            <th>Kontak</th>
                            <th class="col-actions">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plan->members as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->role }}</td>
                                <td class="muted">{{ $item->contact ?: '-' }}</td>
                                <td class="col-actions">
                                    <a class="btn btn-sm"
                                       href="{{ route('plans.show', ['plan' => $plan, 'tab' => 'anggota', 'action' => 'edit', 'member' => $item->id]) }}">Ubah</a>
                                    <form method="POST" action="{{ route('members.destroy', $item) }}" class="inline-form"
                                          data-confirm="Hapus anggota &quot;{{ $item->name }}&quot;? Ia akan dilepas dari semua task. Tindakan ini tidak dapat dibatalkan.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
