<?php
/**
 * Script Import Data Guru ke MySQL (portalspenju.data_guru)
 * Mendukung eksekusi via Browser (Web UI & File Upload) maupun Terminal/CLI.
 */
require_once __DIR__ . '/koneksi.php';

function importCsvDataGuru($filePath, $koneksi) {
    if (!file_exists($filePath)) {
        return ['status' => false, 'message' => "Berkas $filePath tidak ditemukan."];
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return ['status' => false, 'message' => "Gagal membuka berkas $filePath."];
    }

    // Baca baris pertama untuk deteksi delimiter (; atau ,)
    $firstLine = fgets($handle);
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
    rewind($handle);

    // Hilangkan BOM UTF-8 jika ada
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") {
        rewind($handle);
    }

    $header = fgetcsv($handle, 2000, $delimiter);
    if (!$header) {
        fclose($handle);
        return ['status' => false, 'message' => "Format CSV tidak valid atau berkas kosong."];
    }

    // Normalisasi nama kolom header (huruf kecil & hapus spasi/tanda kutip)
    $headerMap = [];
    foreach ($header as $idx => $col) {
        $cleanCol = strtolower(trim($col, " \t\n\r\0\x0B\"'"));
        $headerMap[$cleanCol] = $idx;
    }

    $inserted = 0;
    $updated  = 0;
    $rowNum   = 1;

    while (($row = fgetcsv($handle, 2000, $delimiter)) !== false) {
        $rowNum++;
        if (count($row) === 0 || (count($row) === 1 && trim($row[0]) === '')) {
            continue; // Baris kosong
        }

        $getVal = function($keys, $default = '') use ($row, $headerMap) {
            foreach ((array)$keys as $k) {
                if (isset($headerMap[$k]) && isset($row[$headerMap[$k]])) {
                    return trim($row[$headerMap[$k]]);
                }
            }
            return $default;
        };

        $kode           = $getVal(['kode', 'no', 'id'], (string)$rowNum);
        $nama_guru      = $getVal(['nama_guru', 'nama', 'nama_lengkap', 'guru']);
        $mata_pelajaran = $getVal(['mata_pelajaran', 'mapel', 'mata pelajaran']);
        $kelas          = $getVal(['kelas', 'kelas_diampu', 'rombel']);
        $wali_kelas     = $getVal(['wali_kelas', 'wali', 'tugas_tambahan', 'wali kelas']);
        $jam_per_minggu = $getVal(['jam_per_minggu', 'jam', 'beban_jam', 'jam per minggu'], '0');
        $jumlah         = (int)$getVal(['jumlah', 'total_jam', 'jumlah_jam', 'total'], '0');
        $nip            = $getVal(['nip']);
        $phone          = $getVal(['phone', 'no_hp', 'telepon', 'kontak']);
        $status         = $getVal(['status'], 'Aktif');

        // Jika nama guru kosong, lewati baris ini
        if (empty($nama_guru)) {
            continue;
        }

        // Generate NIP default yang rapi jika belum ada di CSV
        if (empty($nip)) {
            $num = is_numeric($kode) ? (int)$kode : $rowNum;
            $nip = '198' . (50 + ($num % 40)) . '0101 ' . (2010 + ($num % 10)) . '01 ' . ($num % 2 == 0 ? '2' : '1') . ' ' . str_pad($num, 3, '0', STR_PAD_LEFT);
        }

        // Generate No HP default jika belum ada
        if (empty($phone)) {
            $num = is_numeric($kode) ? (int)$kode : $rowNum;
            $phone = '081' . (2 + ($num % 8)) . '-' . str_pad(($num * 137) % 9000 + 1000, 4, '0', STR_PAD_LEFT) . '-' . str_pad(($num * 259) % 9000 + 1000, 4, '0', STR_PAD_LEFT);
        }

        // Siapkan Prepared Statement untuk keamanan SQL Injection
        $sql = "INSERT INTO data_guru (kode, nama_guru, nip, mata_pelajaran, kelas, wali_kelas, phone, jam_per_minggu, jumlah, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    nama_guru = VALUES(nama_guru),
                    nip = IF(VALUES(nip) != '', VALUES(nip), nip),
                    mata_pelajaran = VALUES(mata_pelajaran),
                    kelas = VALUES(kelas),
                    wali_kelas = VALUES(wali_kelas),
                    phone = IF(VALUES(phone) != '', VALUES(phone), phone),
                    jam_per_minggu = VALUES(jam_per_minggu),
                    jumlah = VALUES(jumlah),
                    status = VALUES(status)";

        $stmt = mysqli_prepare($koneksi, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssssssssis', $kode, $nama_guru, $nip, $mata_pelajaran, $kelas, $wali_kelas, $phone, $jam_per_minggu, $jumlah, $status);
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_affected_rows($stmt) == 1) {
                $inserted++;
            } else if (mysqli_stmt_affected_rows($stmt) == 2) {
                $updated++;
            }
            mysqli_stmt_close($stmt);
        }
    }

    fclose($handle);
    return [
        'status'   => true,
        'message'  => "Proses import selesai. Berhasil memasukkan $inserted data baru dan memperbarui $updated data.",
        'inserted' => $inserted,
        'updated'  => $updated
    ];
}

// Handler jika diakses melalui Web Form Upload (POST)
$webMessage = null;
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['csv_file']['tmp_name'];
        $result  = importCsvDataGuru($tmpName, $koneksi);
        $webMessage = $result;
    } elseif (isset($_POST['import_default'])) {
        $defaultPath = 'C:\\Users\\kayla\\Downloads\\.data guru.csv';
        if (!file_exists($defaultPath)) {
            $defaultPath = __DIR__ . '/.data guru.csv';
        }
        $result = importCsvDataGuru($defaultPath, $koneksi);
        $webMessage = $result;
    }
}

// Handler jika dijalankan via CLI / Terminal
if (php_sapi_name() === 'cli') {
    $cliFile = $argv[1] ?? 'C:\\Users\\kayla\\Downloads\\.data guru.csv';
    echo "Menjalankan import dari: $cliFile\n";
    $res = importCsvDataGuru($cliFile, $koneksi);
    echo $res['message'] . "\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data Guru ke MySQL - SMPN 7 Purwakarta</title>
    <script src="https://www.gstatic.com/antigravity/web/dev/tailwindcss.min.js"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-xl w-full bg-white rounded-3xl shadow-xl border border-slate-200/80 p-6 sm:p-8">
        <div class="flex items-center gap-3 pb-5 border-b border-slate-100">
            <div class="w-12 h-12 rounded-2xl bg-indigo-950 flex items-center justify-center text-amber-300 font-black text-xl">
                📥
            </div>
            <div>
                <h1 class="text-lg font-bold text-slate-800">Import Data Guru ke MySQL</h1>
                <p class="text-xs text-slate-500">Database: <code class="font-bold text-indigo-700">portalspenju</code> &bull; Tabel: <code class="font-bold text-indigo-700">data_guru</code></p>
            </div>
        </div>

        <?php if ($webMessage): ?>
            <div class="mt-5 p-4 rounded-2xl <?= $webMessage['status'] ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-rose-50 border border-rose-200 text-rose-800' ?> text-xs leading-relaxed">
                <p class="font-bold"><?= $webMessage['status'] ? '✅ Berhasil!' : '❌ Gagal!' ?></p>
                <p class="mt-1"><?= htmlspecialchars($webMessage['message']) ?></p>
            </div>
        <?php endif; ?>

        <!-- Form 1: Import Otomatis dari Penyimpanan User -->
        <div class="mt-6 bg-slate-50 rounded-2xl p-5 border border-slate-200/80">
            <h2 class="text-sm font-bold text-slate-800">Opsi 1: Import Berkas dari Penyimpanan Komputer</h2>
            <p class="text-xs text-slate-500 mt-1">Mengambil berkas langsung dari lokasi:</p>
            <code class="block bg-white p-2.5 rounded-xl border border-slate-200 text-[11px] font-mono text-slate-700 mt-2 break-all">
                C:\Users\kayla\Downloads\.data guru.csv
            </code>
            <form method="POST" class="mt-3">
                <input type="hidden" name="import_default" value="1">
                <button type="submit" class="w-full py-2.5 bg-indigo-950 hover:bg-indigo-900 text-white font-bold rounded-xl text-xs shadow-md transition-all">
                    ⚡ Proses Import Otomatis ke Database
                </button>
            </form>
        </div>

        <!-- Form 2: Upload File CSV Baru -->
        <div class="mt-4 bg-slate-50 rounded-2xl p-5 border border-slate-200/80">
            <h2 class="text-sm font-bold text-slate-800">Opsi 2: Upload Berkas CSV / Excel Baru</h2>
            <p class="text-xs text-slate-500 mt-1">Pilih berkas CSV dari perangkat Anda untuk di-load ke database.</p>
            <form method="POST" enctype="multipart/form-data" class="mt-3 space-y-3">
                <input type="file" name="csv_file" accept=".csv" required class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-100 file:text-indigo-900 hover:file:bg-indigo-200 cursor-pointer border border-slate-200 rounded-xl p-1 bg-white">
                <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition-all">
                    📤 Upload & Masukkan ke MySQL
                </button>
            </form>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs">
            <a href="admin.php#guru" class="text-indigo-600 hover:text-indigo-900 font-bold flex items-center gap-1">
                &larr; Kembali ke Dashboard Admin
            </a>
            <span class="text-slate-400 text-[11px]">SMPN 7 Purwakarta</span>
        </div>
    </div>
</body>
</html>
