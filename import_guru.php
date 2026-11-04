<?php
/**
 * ==============================================================
 * IMPORT GURU (LEGACY ENTRY POINT)
 * --------------------------------------------------------------
 * Impor guru kini hanya dapat dilakukan oleh Admin yang login
 * melalui menu "Data Guru > Upload Excel/CSV" pada admin.php.
 * Endpoint yang dipakai: api.php?action=import_teachers.
 *
 * File ini dipertahankan sebagai pengalih agar tautan lama tidak
 * menjadi jalur tulis database tanpa autentikasi.
 * ==============================================================
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

require_login('admin');
flash('success', 'Gunakan tombol Upload Excel/CSV pada menu Data Guru untuk mengimpor data pendidik.');
redirect('admin.php#guru');
