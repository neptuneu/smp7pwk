# Sistem Manajemen Akademik Sekolah — SMPN 7 Purwakarta

Backend PHP native + MySQL untuk mengelola data guru, siswa, rombel, mata pelajaran,
nilai, presensi, jurnal mengajar, dan arsip. Seluruh portal (Admin, Guru, Siswa)
memakai satu database dan satu lapisan API dengan sesi serta hak akses yang ketat.

---

## 1. Yang berubah

| Sebelum | Sesudah |
|---|---|
| Tabel dashboard diisi array JavaScript (`teachersData`, `studentsData`, `mapelData`, `rombelList`, `teacherInputsMonitoring`, `archivedStudents`, `archivedTeachers`) | Tidak ada lagi array data dummy. Semua tabel diisi dari MySQL melalui `api.php` (AJAX). |
| Metrik ditulis manual (864 siswa, 32 guru, 11 mapel) | Dihitung dengan `COUNT()`, `AVG()`, dan `GROUP BY` di database. |
| Login guru membandingkan kata sandi teks biasa | Login terpusat `login.php` memakai `password_verify()`; registrasi memakai `password_hash()`. |
| Data nilai/presensi terpisah dari rombel | Relasi Foreign Key: `pengampu`, `nilai_siswa`, `jurnal_guru`, `presensi` → `data_siswa`/`rombel`/`mata_pelajaran`/`data_guru`. |
| Halaman admin/guru dapat dibuka tanpa login | Setiap halaman dan endpoint memanggil `require_login()` + validasi role + CSRF. |
| CRUD guru/siswa mayoritas di frontend | Semua CRUD lewat `api.php` dengan prepared statement (`mysqli_prepare`). |

---

## 2. Struktur berkas

```
.
├── config.php                  Konfigurasi DB, sesi, sekolah
├── koneksi.php                 Koneksi MySQLi + helper prepared statement
├── auth.php                    session_start, guard role, CSRF, idle timeout
├── login.php                   Login terpusat (admin/guru/siswa)
├── register.php                Registrasi admin/guru/siswa
├── logout.php                  Hapus sesi
├── api.php                     SATU endpoint JSON untuk semua operasi
├── admin.php                   Dashboard admin (guard role admin)
├── guru.php                    Portal guru (guard role guru)
├── murid.php                   Portal siswa (guard role siswa)
├── init_akademik.php           Pemeriksa status instalasi (CLI/web admin)
├── views/
│   ├── admin_body.php          Markup dashboard admin
│   └── guru_body.php           Markup portal guru
├── assets/
│   ├── admin-app.js            Controller admin (fetch API + render)
│   ├── guru-app.js             Controller guru (nilai/presensi/jurnal)
│   └── murid-app.js            Controller siswa
├── database/
│   ├── schema.sql              STRUKTUR TABEL LENGKAP (impor ini)
│   ├── migrate_legacy.php      Migrasi data lama ke skema baru
│   └── backup_legacy_portalspenju.sql  Backup database lama (lokal)
└── storage/
    └── sessions/               Penyimpanan sesi (fallback ke temp server)
```

> `import_guru.php` lama sekarang hanya mengalihkan admin ke fitur upload CSV
> resmi. `bersihin_csv.php` adalah utilitas CSV lokal di luar alur aplikasi.

---

## 3. Instalasi

### Prasyarat
- PHP 8.1+ (diuji PHP 8.2.12)
- MySQL 8 / MariaDB 10.4+
- Ekstensi `mysqli`, `mbstring`

### Langkah
1. Sesuaikan kredensial database pada `config.php`
   (atau set environment variable `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).
2. Impor skema:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
   Atau impor `database/schema.sql` lewat phpMyAdmin.
3. Buka `http://localhost/.../login.php`.
4. Login sebagai admin seed:
   - **Username:** `admin`
   - **Password:** `admin12345`
   - Segera ganti kata sandi melalui menu Data Guru / registrasi admin baru,
     lalu nonaktifkan akun seed bila perlu.
5. Periksa status tabel lewat `init_akademik.php` (login admin) atau:
   ```bash
   php init_akademik.php
   ```

### Migrasi dari database lama
Skrip migrasi mengharapkan tabel lama sudah di-rename:

```sql
RENAME TABLE data_siswa TO legacy_data_siswa,
             data_guru  TO legacy_data_guru,
             nilai_siswa TO legacy_nilai_siswa,
             jurnal_guru TO legacy_jurnal_guru;
```

Lalu:

```bash
mysql -u root -p < database/schema.sql
php database/migrate_legacy.php   # bila memigrasikan data lama
```

Migrasi akan:
- membuat akun `users` untuk setiap guru (username = NIP, password `guru12345`);
- memindahkan profil ke `data_guru` / `data_siswa` beserta `rombel_id`;
- membuat penugasan `pengampu` dari string mapel/kelas lama;
- menetapkan 27 wali kelas dari teks "Wali Kelas VIII.A";
- memindahkan nilai dan jurnal bila ada.

> **Peringatan:** migrasi menghapus isi tabel tujuan. Skrip menolak berjalan bila
> sudah ada data, kecuali dijalankan dengan `--force`.

---

## 4. Skema database & relasi

Ringkasan tabel pada `database/schema.sql`:

| Tabel | Fungsi | Foreign Key utama |
|---|---|---|
| `users` | Akun autentikasi (`password_hash`, role, status) | — |
| `data_guru` | Profil pendidik | `user_id → users.id` |
| `rombel` | Rombongan belajar & wali kelas | `wali_guru_id → data_guru.id` |
| `data_siswa` | Buku induk siswa | `user_id → users.id`, `rombel_id → rombel.id` |
| `mata_pelajaran` | Mapel, KKM, koordinator | `koordinator_guru_id → data_guru.id` |
| `pengampu` | Penugasan guru × mapel × rombel | `guru_id`, `mapel_id`, `rombel_id` |
| `nilai_siswa` | Nilai per jenis penilaian | `siswa_id`, `mapel_id`, `guru_id`, `rombel_id` |
| `jurnal_guru` | Agenda mengajar | `guru_id`, `mapel_id`, `rombel_id` |
| `presensi` | Kehadiran per pertemuan | `siswa_id`, `rombel_id`, `mapel_id`, `guru_id` |
| `arsip_siswa` / `arsip_guru` | Arsip alumni/mutasi/pensiun | `siswa_id` / `guru_id` |
| `pengaturan` | Identitas sekolah & periode | — |

**Sinkronisasi rombel → portal guru:**
- `data_siswa.rombel_id` adalah sumber kebenaran penempatan siswa.
- Admin memindahkan siswa (`bulk_students` / `save_student`) → `rombel_id` berubah.
- Guru membuka lembar nilai/presensi untuk sebuah rombel → query mengambil siswa
  dengan `rombel_id` tersebut, sehingga daftar siswa langsung ikut berubah.
- Penugasan mengajar guru disimpan di `pengampu`, bukan di string kelas.
- Wali kelas disimpan di `rombel.wali_guru_id`, bukan teks.

---

## 5. Autentikasi & keamanan

- **Registrasi** (`register.php`):
  - role `admin` memerlukan `ADMIN_REGISTRATION_CODE` (default `SMPN7-ADMIN-2026`,
    ubah di `config.php` / environment variable);
  - role `guru` menyimpan profil ke `data_guru`;
  - role `siswa` menyimpan profil ke `data_siswa`;
  - password disimpan dengan `password_hash(..., PASSWORD_DEFAULT)`;
  - seluruh proses memakai transaction + prepared statement.
- **Login** (`login.php`):
  - menerima username, NIP guru, atau NISN siswa;
  - memvalidasi dengan `password_verify()`;
  - `password_needs_rehash()` untuk upgrade hash;
  - regenerasi session ID (`session_regenerate_id(true)`);
  - rate limit 5 percobaan / 5 menit per sesi;
  - pesan error generik agar tidak membocorkan akun.
- **Sesi** (`auth.php`):
  - cookie `HttpOnly`, `SameSite=Lax`, `Secure` bila HTTPS;
  - fingerprint User-Agent, idle timeout 2 jam, regenerasi tiap 15 menit;
  - `require_login('admin'|'guru'|'siswa')` di setiap halaman;
  - API mengembalikan `401` bila sesi habis dan `403` bila role tidak sesuai.
- **CSRF**:
  - setiap form memuat `csrf_token`;
  - setiap request AJAX mengirim header `X-CSRF-Token`;
  - `verify_csrf()` menolak request tanpa token yang sah.
- **SQL Injection**:
  - semua query yang melibatkan input memakai `mysqli_prepare` + `bind_param`
    melalui `db_select()`, `db_select_one()`, `db_execute()`, `db_insert()`.

---

## 6. Endpoint API (`api.php`)

Semua request: `POST`, menyertakan `action`, sesi login, dan CSRF token.
Respons standar: `{ "success": bool, "message": string, "data": ... }`.

### Admin
| Action | Keterangan |
|---|---|
| `admin_bootstrap` | Statistik, guru, siswa, mapel, rombel, monitoring, arsip, pengaturan, aktivitas |
| `save_teacher` / `delete_teacher` / `import_teachers` | CRUD & impor guru |
| `save_student` / `delete_student` / `bulk_students` / `import_students` | CRUD, pindah rombel massal, impor siswa |
| `save_mapel` / `delete_mapel` | CRUD mata pelajaran |
| `save_rombel` / `delete_rombel` / `save_rombel_wali` | CRUD rombel & wali kelas |
| `rombel_detail` | Rekap mapel & siswa dalam satu rombel |
| `monitoring_detail` | Pratinjau hasil input guru |
| `save_archive_student` / `delete_archive_student` / `restore_archive_student` | Arsip siswa |
| `save_archive_teacher` / `delete_archive_teacher` / `restore_archive_teacher` | Arsip guru |
| `save_settings` | Simpan identitas sekolah & periode |

### Guru
| Action | Keterangan |
|---|---|
| `guru_bootstrap` | Profil guru, penugasan, kelas/mapel yang diizinkan |
| `get_academic_data` | Siswa + kolom nilai + jurnal + presensi untuk rombel & mapel |
| `save_grade_cell` / `delete_grade_column` | Simpan/hapus nilai |
| `save_journal` / `delete_journal` | Jurnal mengajar |
| `save_attendance_cell` / `add_attendance_session` / `delete_attendance_session` | Presensi |
| `get_wali_ledger` | Ledger seluruh mapel untuk wali kelas |
| `get_semester_recap` / `save_student_note` | Rekap semester & catatan wali |

### Siswa
| Action | Keterangan |
|---|---|
| `student_bootstrap` | Profil, nilai per mapel, presensi, catatan wali |

---

## 7. Akun & kata sandi default

| Peran | Username | Kata sandi |
|---|---|---|
| Admin seed | `admin` | `admin12345` |
| Guru hasil migrasi | NIP masing-masing | `guru12345` |
| Siswa hasil migrasi | NISN (bila tersedia) | `siswa12345` |

**Wajib** meminta seluruh pengguna mengganti kata sandi setelah login pertama.
Guru juga dapat dibuat lewat `register.php` atau menu Admin; admin yang membuat
akun guru/siswa otomatis membuatkan akun `users` dengan kata sandi default.

---

## 8. Catatan data lama

Database lama berisi 923 siswa, tetapi 918 di antaranya tidak memiliki NISN.
Skema baru mengizinkan `nisn` bernilai `NULL` (unik untuk nilai non-NULL) agar
data tetap utuh. Guru tetap dapat mengisi nilai/presensi karena API memakai
`student_id` (bukan NISN) sebagai kunci. Lengkapi NISN melalui menu
**Admin → Data Siswa → Edit** agar siswa dapat login ke portal siswa.

---

## 9. Pengujian cepat

Dengan PHP built-in server:

```bash
php -S 127.0.0.1:8088 -t .
```

Lalu buka:
- `http://127.0.0.1:8088/login.php`
- Login admin → dashboard
- Login guru (NIP + `guru12345`) → pilih kelas → input nilai/presensi/jurnal
- Login siswa (NISN + `siswa12345`) → lihat rapor

Cek status database:

```bash
php init_akademik.php
```

---

## 10. Catatan cakupan

- Repositori modul ajar pada tab **Materi** sengaja ditampilkan sebagai placeholder
  yang jujur (bukan data dummy). Nilai, presensi, dan jurnal sudah terhubung penuh.
- Tabel `siswa`, `absensi_siswa`, dan `materi_modul` dari database lama tidak dipakai
  oleh aplikasi baru dan dapat dihapus setelah backup.
- Untuk produksi, gunakan HTTPS, ganti `ADMIN_REGISTRATION_CODE`, nonaktifkan
  `APP_DEBUG`, dan batasi akses ke folder `database/` serta `storage/`.
