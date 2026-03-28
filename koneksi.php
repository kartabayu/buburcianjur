<?php
// Konfigurasi Database
$host = "localhost"; 
$user = "root";
$pass = "";
$db   = "pefsdzhc_berkahmulia";

// Membuat koneksi ke database
$conn = mysqli_connect($host, $user, $pass, $db);

// Cek koneksi
if (!$conn) {
    // Jika gagal, tampilkan pesan error
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}

// Set charset agar support emoji atau karakter khusus
mysqli_set_charset($conn, "utf8");

// Optional: Uncomment baris bawah ini untuk tes, kalau muncul tulisan "Berhasil" berarti aman.
// echo "Koneksi Berhasil!";
?>