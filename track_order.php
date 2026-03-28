<?php
include 'koneksi.php';
header('Content-Type: application/json');

// Tangkap inputan dari user
$data = json_decode(file_get_contents("php://input"), true);
$keyword = isset($data['keyword']) ? mysqli_real_escape_string($conn, trim($data['keyword'])) : '';

if(empty($keyword)) {
    echo json_encode(['status' => 'error', 'message' => 'Masukkan nomor invoice atau WA']);
    exit;
}

// Cari di database berdasarkan No Invoice ATAU Nomor WA (Ambil yang paling terbaru)
$query = "SELECT no_invoice, status, created_at, total_amount, location, customer_name FROM transactions WHERE no_invoice='$keyword' OR customer_wa='$keyword' ORDER BY id DESC LIMIT 1";
$q = mysqli_query($conn, $query);

if(mysqli_num_rows($q) > 0) {
    $row = mysqli_fetch_assoc($q);
    echo json_encode(['status' => 'success', 'data' => $row]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Pesanan tidak ditemukan. Coba cek lagi nomornya.']);
}
?>