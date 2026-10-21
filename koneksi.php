<?php
$koneksi = mysqli_connect("localhost", "root", "", "portalspenju");

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
?>