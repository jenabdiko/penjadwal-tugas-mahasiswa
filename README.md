# Sistem Penjadwalan Pengerjaan Tugas Mahasiswa

Aplikasi web untuk membantu mahasiswa merencanakan tahapan pengerjaan tugas (individu maupun kelompok) dengan konsep manajemen proyek ala GanttProject: **Task, Durasi, Urutan pengerjaan (dependency), Resource (anggota), Gantt Chart, dan Jalur kritis (Critical Path)**.

Aplikasi ini **bukan** jadwal kuliah dan **bukan** sistem pengumpulan tugas — tidak ada fitur kelas, dosen, pengumpulan berkas, atau nilai.

Dibangun dengan Laravel (Blade server-side), CSS dan JavaScript biasa di `public/` (tanpa Node, Vite, Tailwind, maupun CDN sehingga berjalan offline), database SQLite, locale `id`, dan timezone `Asia/Jakarta`.

## Kebutuhan

- PHP >= 8.3 (dikembangkan dan diuji dengan PHP 8.4) dengan ekstensi:
  - `pdo_sqlite` dan `sqlite3` (wajib — lihat catatan di bawah)
  - `mbstring`, `fileinfo`, `dom`, `openssl` (bawaan instalasi PHP standar / dibutuhkan Laravel)
- Composer 2.x
- Tidak perlu Node.js, npm, atau server database

### Mengaktifkan ekstensi SQLite

Pastikan `pdo_sqlite` aktif di `php -m`. Bila belum, buka file `php.ini` lalu hilangkan tanda `;` pada baris:

```ini
extension=pdo_sqlite
extension=sqlite3
```

Kemudian restart terminal/PHP.

## Instalasi (PowerShell)

```powershell
# 1. Salin berkas konfigurasi
copy .env.example .env

# 2. Buat application key
php artisan key:generate

# 3. Buat dan jalankan migrasi (database SQLite dibuat otomatis)
php artisan migrate

# 4. (Opsional) muat dua rencana contoh
php artisan db:seed

# 5. Jalankan aplikasi
php artisan serve
```

Buka <http://127.0.0.1:8000>.

## Cara Memuat Contoh

- Klik tombol **Coba data contoh** pada Beranda (atau jalankan `php artisan db:seed`).
- Dua rencana contoh dibuat:
  1. **Tugas Besar Pemrograman Web** — mulai Senin 12 Okt 2026, deadline 30 Okt 2026 (jadwal selesai 28 Okt 2026, cadangan 2 hari kerja).
  2. **Tugas Besar Pemrograman Web (Deadline Mepet)** — data sama, deadline 23 Okt 2026 (terlambat 3 hari kerja).
- Bila contoh sudah pernah dimuat, tombol tidak akan membuat data ganda.

## Menjalankan Test

```powershell
php artisan test
```

Test mencakup unit test `SchedulerService` (semua tanggal Contoh 1, total 13 hari kerja, himpunan task kritis, jalur beserta totalnya, evaluasi deadline, Contoh 2 yang terlambat 3 hari kerja, pengaruh hari libur khusus, empat jenis dependency FS/SS/FF/SF, penolakan dependency melingkar/ke diri sendiri, dan rollup task induk) serta feature test (beranda dan semua tab rencana 200, status otomatis dengan waktu dibekukan lewat `Carbon::setTestNow`, Muat Contoh tidak ganda, validasi form rencana/task/anggota, dan ekspor lalu impor JSON menghasilkan data yang sama).

## Status Otomatis

**Status tidak disimpan di database dan tidak bisa diinput manual** — kolom `status` di tabel `plans` dan `tasks` dihapus lewat migrasi. Status selalu dihitung otomatis dari jadwal hasil penjadwalan (CPM), progress, dan tanggal hari ini (timezone Asia/Jakarta). Satu-satunya input pengguna yang memengaruhi status adalah **progress (0–100)**.

Aturan task (dicek berurutan, berhenti pada aturan pertama yang cocok):

1. progress = 100 → **Selesai**
2. hari ini > tanggal selesai task (hasil penjadwalan) → **Terlambat**
3. progress > 0 **atau** tanggal mulai ≤ hari ini ≤ tanggal selesai → **Sedang Dikerjakan**
4. selain itu → **Belum Dimulai**

Task induk (ringkasan) dihitung dari sub-tasknya: semua sub-task Selesai → Selesai; ada sub-task Terlambat → Terlambat; ada sub-task Sedang Dikerjakan atau Selesai → Sedang Dikerjakan; selain itu → Belum Dimulai.

Status rencana: semua task Selesai → Selesai; hari ini > deadline dan belum semua selesai → Terlambat; ada task berstatus Sedang Dikerjakan, Terlambat, atau Selesai → Sedang Dikerjakan; selain itu (termasuk rencana tanpa task) → Belum Dimulai.

Status tampil sebagai badge hanya-baca di Beranda, Ringkasan, dan tabel Task; badge *Selesai* diawali ikon ✓ dan *Terlambat* ikon ⚠ agar status tidak hanya dibedakan lewat warna. Ekspor JSON tidak lagi memuat status; impor berkas lama yang masih berisi status tetap diterima dan field itu diabaikan.

## Palet Warna

Seluruh warna didefinisikan sebagai variabel CSS di `public/css/app.css` dan dipakai di mana pun (termasuk SVG di Blade dan JavaScript Gantt). Palet: **latar halaman abu-abu netral, semua elemen lain pink** — hanya dua hue, tanpa putih murni dan tanpa hue lain. Semua nilai hex/rgb hanya ada di blok `:root`; bagian lain CSS, atribut `style` SVG di Blade, dan JS memakai `var(--nama)`.

- Latar `#C9C9CE` (abu-abu netral, tanpa gradasi), kartu `#F5C2D3`, latar kedua `#EDA9C1`, chip & latar input `#F9D0DD`, tepi `#D98FAA`
- Teks `#3A1226`, teks redup `#6E3550`
- Navbar & footer `#E58FB0` dengan teks `#3A1226`
- Aksen utama `#B03A68` dengan teks `#FFF3F8`, hover `#922F55`
- Turunan pink: plum pekat `#4A1F33`, batang Gantt `#E37DA3`, baris tabel `#F1B5C9`, jalur progress `#E79DB8`, panah dependency `#8A4A64`, gradasi hero `#F2A9C4`
- Badge status (teks + ikon): Selesai ✓ `#4A1F33`/`#FBE0E8`, Terlambat ⚠ `#B03A68`/`#FFF3F8`, Sedang Dikerjakan `#F9D0DD`/`#6B1F42`, Belum Dimulai `#D2D2D7`/`#3B3B42`
- Banner & baris evaluasi: aman `#F9D0DD` tepi `#D98FAA` teks `#4A1F33` ikon ✓; terlambat `#B03A68` teks `#FFF3F8` ikon ⚠

Gaya umum: font `system-ui` (utamakan "Segoe UI"), ukuran dasar 16px, line-height 1.6, judul halaman 32px dan judul bagian 20px, kartu/panel/dialog sudut 14px, tombol & input sudut 10px tinggi 44px dengan transisi 150ms, bayangan kartu sangat lembut, skala jarak kelipatan 8px, lebar konten maksimum 1200px, body flex kolom setinggi viewport sehingga footer selalu menempel di bawah. Semua pasangan teks/latar memenuhi kontras ≥ 4.5:1 (diperiksa terhadap WCAG).

## Daftar Fitur

- **Beranda** — hero gradasi pink lembut berisi judul "Rencana Tugasmu", kalimat pendukung, tiga chip ringkasan (jumlah rencana, jumlah berisiko terlambat, rata-rata progress), dan tombol sekunder **Coba data contoh** / **Impor dari JSON**; daftar rencana berupa grid kartu selebar layar (urut deadline terdekat, yang Selesai paling akhir) dengan strip warna status, nama & deskripsi maksimal 2 baris, badge status + label prioritas, baris deadline + sisa hari, bar progress tipis, baris evaluasi jadwal, dan tombol Buka (seluruh kartu bisa diklik); tampilan kosong dengan ilustrasi SVG.
- **Navbar** — latar pink `#E58FB0`, logo kotak `#F9D0DD` berisi ikon kalender + teks "Penjadwal Tugas", link Beranda (garis bawah pink saat aktif), dan satu tombol utama "+ Buat Tugas Baru". Footer latar sama dengan teks opasitas 0.8, menempel di bawah layar.
- **Satu sumber evaluasi deadline** — semua tampilan yang menyebut cadangan/keterlambatan memakai `App\Support\Evaluasi::teks()` sehingga angka hari kerja selalu sama di Beranda, Ringkasan, dan Jalur Kritis. Migrasi `sync_sample_plan_weekdays` + pemuatan data contoh memperbaiki baris contoh lama yang membuat Contoh 2 salah tampil "Terlambat 2 hari kerja" (seharusnya 3).
- **Form Rencana** — bagian berjudul (Informasi dasar, Waktu), keterangan `* wajib diisi`, placeholder + teks bantu, error di bawah field dalam bahasa manusiawi, dan "Pengaturan hari kerja (opsional)" berupa `<details>` yang tertutup (checkbox Sabtu/Minggu + hari libur khusus). Tidak ada input status.
- **Tab Ringkasan** — banner deadline tenang (ikon + teks, bukan hanya warna), tepat tiga kartu (Perkiraan selesai, Total durasi, Progress keseluruhan), lalu daftar "Segera berakhir".
- **Tab Task** — kolom Nama (dengan teks kecil "Setelah: 2, 3" dan label "Kritis"), Jadwal, Durasi ("2 hari"), Penanggung jawab, Progress (bar tipis + angka), Status (badge), Aksi (Ubah, Tandai selesai/Buka kembali, menu "…" berisi Tambah sub-task dan Hapus). Prioritas hanya tampil sebagai label kecil saat *Tinggi*. Form task: slider progress 0–100 langkah 5 dengan angka persen dan teks bantu "Status dihitung otomatis dari jadwal dan progress."
- **Tab Anggota** — tambah/ubah/hapus anggota (nama, peran, kontak); menghapus anggota melepasnya dari semua task.
- **Tab Gantt Chart** — tabel kiri (No, Nama task, Penanggung jawab, Durasi) + timeline harian yang mengisi lebar kartu (lebar kolom menyesuaikan, minimum 36px, scroll bila perlu), header bulan & tanggal, akhir pekan/hari libur diarsir halus, semua batang pink `#E37DA3` dengan isian progress `#B03A68`, batang ringkasan plum pekat, panah dependency tipis `#8A4A64`, garis **Deadline** solid `#3A1226` berlabel plum pekat dan **Hari ini** putus-putus `#6E3550` beserta label, tinggi baris 40px, legenda lima item, dan saklar **Sorot jalur kritis** yang memberi aksen utama + tepi tebal pada batang kritis.
- **Tab Diagram Jaringan (PERT)** — SVG node per task daun (nama, durasi, tanggal mulai–selesai), disusun per kolom menurut panjang rantai urutan pengerjaan, panah berlabel bila bukan FS, node kritis bergaris tepi tebal + label "Kritis" dengan saklar sorotan.
- **Tab Jalur kritis** — keterangan "Rangkaian task yang kalau telat membuat seluruh tugas ikut telat", tabel seluruh jalur beserta total durasi (jalur kritis ditandai) dan tabel CPM per task (ES/EF dan LS/LF dalam tanggal, slack, status kritis), plus catatan bila ada dependency non-FS/lag > 0.
- **Bahasa awam** — "Dependency" → "Urutan pengerjaan", "Predecessor" → "Dikerjakan setelah", "Lag" → "Jeda (hari kerja)", jenis hubungan ditulis sebagai kalimat (FS = "Mulai setelah tugas itu selesai", SS = "Mulai bersamaan", FF = "Selesai bersamaan", SF = "Selesai setelah tugas itu dimulai").
- **Umpan balik & pencegahan kesalahan** — notifikasi `role="status"` di atas konten yang hilang otomatis dalam 4 detik; konfirmasi hapus lewat dialog yang menyebut nama item dan akibatnya dengan fokus awal di tombol "Batal"; tombol hapus tidak bersebelahan dengan tombol utama.
- **Ekspor/Impor JSON** — ekspor satu rencana lengkap (rencana, hari libur, anggota, task, dependency, penugasan; tanpa status) menjadi berkas `.json`; impor selalu membuat rencana baru dengan validasi struktur berkas, menerima berkas lama yang masih memuat status (field diabaikan).
- **Validasi berbahasa Indonesia** — nama wajib, durasi integer minimal 1, tanggal valid, deadline tidak boleh sebelum tanggal mulai, progress 0–100, lag integer minimal 0, dependency melingkar/ke diri sendiri/duplikat ditolak; pesan error di bawah field terkait, field error bergaris tepi rose tua, fokus pindah ke field error pertama.
- **Aksesibilitas & responsif** — `lang="id"`, kontras ≥ 4.5:1, fokus keyboard 2px offset 2px, target klik ≥ 40px, informasi tidak hanya lewat warna (label "Kritis", teks status), hormati `prefers-reduced-motion`; di bawah 768px navbar rapi, tabel bisa di-scroll horizontal, kartu menumpuk ke bawah.

## Asumsi dan Keputusan Desain

Hal-hal yang tidak dirinci dalam kebutuhan diputuskan dengan cara paling sederhana:

1. **Penyimpanan memakai SQLite dan ekspor/impor JSON** — Laravel adalah framework backend, sehingga aplikasi tetap membutuhkan penyimpanan sisi server. SQLite dipilih agar tidak perlu server database, dan ekspor/impor JSON disediakan untuk memindahkan data antarinstalasi.
2. **Nomor urut (kolom No pada tampilan) adalah nomor tampilan** — penomoran 1..N mengikuti urutan hierarki (task induk lalu sub-tasknya). Nomor yang sama dipakai pada teks "Setelah: 2, 3" dan daftar jalur (mis. `1-2-4-5-7-8`).
3. **Task induk (punya sub-task)** — hanya bisa satu tingkat. Task induk tidak memiliki durasi, urutan pengerjaan, atau penanggung jawab sendiri: semua dihitung dari sub-tasknya (durasi = jumlah hari kerja pada rentang mulai–selesai terluar, progress = rata-rata berbobot durasi, kritis bila ada sub-task kritis). Saat task yang sudah punya predecessor mendapat sub-task pertama, predecessor task induk dihapus dan peringatan ditampilkan.
4. **Status dihitung otomatis, tidak disimpan** — lihat bagian "Status Otomatis". Form tidak menyediakan input status; tombol cepat "Tandai selesai"/"Buka kembali" hanya mengubah progress (pada task induk, seluruh sub-tasknya ikut diubah).
5. **Total jalur vs slack** — total durasi jalur dihitung sederhana (jumlah durasi task pada jalur). Bila ada dependency selain FS atau lag > 0, tab Jalur kritis menampilkan catatan bahwa total jalur bersifat sederhana, sedangkan penanda kritis tetap memakai slack (slack = 0).
6. **Hari kerja** — semua hari kecuali Sabtu/Minggu (bila dicentang) dan tanggal di tabel hari libur, dihitung sebagai nomor hari kerja 1, 2, 3, … mulai dari hari kerja pertama pada/setelah tanggal mulai rencana.
7. **Data contoh** — seluruh task contoh memakai prioritas *Sedang* (prioritas *Tinggi* melekat pada rencananya), dependency FS lag 0, tanpa hari libur khusus, progress task 1 = 100, task 2 = 50, sisanya 0; kontak anggota memakai alamat `*@contoh.id`.
8. **Sesi, cache, dan queue** — memakai driver `file`/`sync` sehingga tidak ada tabel tambahan yang dibutuhkan selain tabel aplikasi.
9. **Penghapusan berantai** — menghapus rencana ikut menghapus task, sub-task, dependency, anggota, dan hari liburnya; menghapus task induk ikut menghapus sub-task dan dependency terkait.
10. **Tanpa autentikasi** — aplikasi bersifat single-user lokal; tidak ada login karena tidak disebutkan dalam kebutuhan.

## Batasan Sistem

- **Tidak mengatur jadwal kuliah** — tidak ada konsep mata kuliah, ruang, dosen, atau jadwal perkuliahan.
- **Bukan sistem pengumpulan tugas** — tidak ada unggah berkas tugas, penilaian, komentar dosen, atau tenggat pengumpulan otomatis.
- **Tanpa komunikasi antaranggota** — kolaborator hanya dicatat sebagai resource/penanggung jawab; tidak ada chat, komentar, atau notifikasi antarpengguna.
- **Tanpa login/multi-pengguna** — semua orang yang mengakses instance yang sama melihat dan mengubah data yang sama.

## Struktur Kode Singkat

```
app/Http/Controllers/   Controller tipis (HTTP saja)
app/Http/Requests/      Validasi form (pesan Indonesia)
app/Services/           SchedulerService (CPM), StatusService (status otomatis),
                        TaskService, GanttService, PertService, PlanJsonService,
                        SampleDataService, DependencyGraph
app/Models/             Plan, Task, Member, Holiday, TaskDependency
resources/views/        Blade (layout, halaman rencana, tab-tabnya, partial badge status)
public/css/app.css      Seluruh gaya + variabel palet (tanpa build)
public/js/              app.js (dialog konfirmasi, notifikasi, saklar, slider),
                        gantt.js (lebar kolom adaptif + panah SVG)
database/migrations/    Skema SQLite (termasuk migrasi penghapusan kolom status)
database/seeders/       DatabaseSeeder → memuat data contoh
tests/Unit/             SchedulerServiceTest
tests/Feature/          Beranda, tab rencana, status otomatis, contoh, validasi, ekspor/impor
```
