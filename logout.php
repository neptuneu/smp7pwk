<?php
/**
 * Keluar dari sesi aktif (semua peran).
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

logout_user();
session_start();
flash('success', 'Anda telah keluar dari sistem.');
redirect('login.php');
