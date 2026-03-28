<?php
// MATIKAN ERROR AGAR TIDAK MERUSAK JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

try {
    include 'koneksi.php';

    if (!$conn) throw new Exception("Koneksi Database Gagal");

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception("Data kosong");

    // Cek setting potong stok otomatis
    $shop_setting = mysqli_fetch_assoc(mysqli_query($conn, "SELECT inventory_sync FROM shop_settings WHERE id=1"));
    $sync_stock = $shop_setting['inventory_sync'] ?? 1; 

    // 1. DATA UTAMA
    $code   = mysqli_real_escape_string($conn, $input['unique_code']);
    $name   = mysqli_real_escape_string($conn, $input['customer_name']);
    $wa     = isset($input['customer_wa']) ? mysqli_real_escape_string($conn, $input['customer_wa']) : '';
    $total  = (int)$input['total_amount'];
    $method = mysqli_real_escape_string($conn, $input['payment_method']);
    $lokasi = mysqli_real_escape_string($conn, $input['location']);
    
    // TANGKAP USER ID & DISKON
    $user_id = isset($input['user_id']) && $input['user_id'] ? (int)$input['user_id'] : 'NULL';
    $discount = isset($input['discount_amount']) ? (int)$input['discount_amount'] : 0;
    
    // Simpan format gabungan di customer_name untuk backward compatibility (Laporan Admin lama)
    $full_name = "$name ($lokasi)";

    // 2. SIMPAN TRANSAKSI
    $query = "INSERT INTO transactions (no_invoice, user_id, customer_name, customer_wa, location, total_amount, discount_amount, payment_method, status, created_at) 
              VALUES ('$code', $user_id, '$full_name', '$wa', '$lokasi', '$total', '$discount', '$method', 'pending', NOW())";
    
    if (!mysqli_query($conn, $query)) throw new Exception("Gagal Simpan Transaksi: " . mysqli_error($conn));
    
    $trans_id = mysqli_insert_id($conn);

    // 3. SIMPAN ITEM & POTONG STOK
    foreach($input['items'] as $item) {
        $prod_id = (int)$item['id'];
        $prod_name = mysqli_real_escape_string($conn, $item['name']);
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];
        $subtotal = $price * $qty;
        
        $raw_variant = isset($item['variant']) && $item['variant'] != '-' ? $item['variant'] : '';
        
        $toppings_str = "";
        if(!empty($item['toppings'])) {
            foreach($item['toppings'] as $t) {
                $t_name = $t['name'] ?? '';
                if($t_name) $toppings_str .= $t_name . ", ";
            }
            $toppings_str = rtrim($toppings_str, ", ");
        }

        $final_variant = $raw_variant;
        if($toppings_str) {
            if($final_variant) $final_variant .= " + " . $toppings_str;
            else $final_variant = $toppings_str;
        }
        if(!$final_variant) $final_variant = "-";
        
        $final_variant_esc = mysqli_real_escape_string($conn, $final_variant);

        $q_detail = "INSERT INTO transaction_details (transaction_id, product_id, product_name, variant_name, price, qty, subtotal)
                     VALUES ('$trans_id', '$prod_id', '$prod_name', '$final_variant_esc', '$price', '$qty', '$subtotal')";
        
        if (!mysqli_query($conn, $q_detail)) throw new Exception("Gagal Simpan Item Detail");

        if ($sync_stock == 1) {
            if ($raw_variant) {
                $v_name_esc = mysqli_real_escape_string($conn, $raw_variant);
                $q_stok = "UPDATE product_variants SET stock = stock - $qty WHERE product_id = '$prod_id' AND variant_name = '$v_name_esc'";
                mysqli_query($conn, $q_stok);
            } else {
                $q_stok = "UPDATE products SET stock = stock - $qty WHERE id = '$prod_id'";
                mysqli_query($conn, $q_stok);
            }
        }
    }

    ob_clean();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>