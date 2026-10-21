<?php
// Tampilkan semua error jika ada kendala
error_reporting(E_ALL);
ini_set('display_errors', 1);

$inputFile = 'data siswa.csv';
$outputFile = 'data_siswa_siap_upload.csv';

echo "<h3>Sedang memproses file...</h3>";

if (!file_exists($inputFile)) {
    die("<h3 style='color:red;'>Error: File '$inputFile' tidak ditemukan di folder yang sama dengan script ini!</h3>");
}

$lines = file($inputFile);
$cleanData = [];
$currentClass = '';

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;
    
    // Tangkap nama kelas (contoh: VIII.A menjadi VIII A)
    if (preg_match('/Kelas\s*:\s*([^;]+)/i', $line, $matches)) {
        $currentClass = trim($matches[1]);
        $currentClass = str_replace('.', ' ', $currentClass); 
    }
    
    // Tangkap baris data siswa yang diawali nomor urut
    $parts = explode(';', $line);
    if (count($parts) >= 3 && is_numeric(trim($parts[0]))) {
        $nama = trim($parts[1]);
        $gender = trim($parts[2]);
        
        if (!empty($nama) && in_array($gender, ['L', 'P'])) {
            $cleanData[] = [
                '',             // nisn (kosong)
                $nama,          // nama_siswa
                $currentClass,  // kelas
                $gender,        // gender
                '',             // tanggal_lahir (kosong)
                '',             // orang_tua (kosong)
                '',             // telepon (kosong)
                '',             // catatan_wali (kosong)
                'Aktif Belajar' // status
            ];
        }
    }
}

// Tulis ke file CSV baru
$fp = fopen($outputFile, 'w');
// Tulis Header kolom phpMyAdmin
fputcsv($fp, ['nisn', 'nama_siswa', 'kelas', 'gender', 'tanggal_lahir', 'orang_tua', 'telepon', 'catatan_wali', 'status']);
// Tulis data siswa
foreach ($cleanData as $row) {
    fputcsv($fp, $row);
}
fclose($fp);
?>

<div style="font-family:sans-serif; padding: 25px; border: 2px solid #059669; max-width: 550px; border-radius: 12px; background: #ecfdf5; margin: 40px auto; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <h2 style="color: #047857; margin-top: 0;">✅ Berhasil Membersihkan Data!</h2>
    <p style="font-size: 14px; color: #374151;">Total berhasil membaca dan memformat <b><?= count($cleanData) ?> data siswa</b>.</p>
    <a href="<?= $outputFile ?>" download style="display:inline-block; padding: 12px 24px; background: #2563eb; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 15px; font-size: 14px;">
        📥 Download File CSV Siap Upload
    </a>
</div>