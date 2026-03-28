<?php
// admin/export_excel.php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../koneksi.php';

// Cek Login
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') { header("Location: ../login.php"); exit; }

// Ambil Periode
$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end_date   = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

// Nama File saat didownload
$filename = "Laporan_Transaksi_" . $start_date . "_sd_" . $end_date . ".xls";

// Header untuk memaksa download sebagai Excel
header("Content-Type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

// Query Data (Sama persis dengan laporan.php agar datanya sinkron)
$query = "SELECT t.*, 
    (SELECT GROUP_CONCAT(CONCAT(qty, ';;', product_name, ';;', COALESCE(variant_name, '-'), ';;', price, ';;', subtotal) SEPARATOR '||') 
     FROM transaction_details d WHERE d.transaction_id = t.id) as items_data 
    FROM transactions t 
    WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date' 
    ORDER BY t.created_at DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html>
<head>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid black; padding: 8px; vertical-align: top; }
        th { background-color: #f2f2f2; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .str { mso-number-format:"\@"; } /* Agar angka 0 di depan tidak hilang */
    </style>
</head>
<body>
    <center>
        <h2>LAPORAN TRANSAKSI</h2>
        <p>Periode: <?php echo date('d/m/Y', strtotime($start_date)) . " s/d " . date('d/m/Y', strtotime($end_date)); ?></p>
    </center>

    <table>
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">No Invoice</th>
                <th width="15%">Tanggal</th>
                <th width="20%">Pelanggan</th>
                <th width="30%">Detail Item (Qty x Nama + Varian)</th>
                <th width="10%">Status</th>
                <th width="15%">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $grand_total_omset = 0;
            if(mysqli_num_rows($result) > 0):
                while($row = mysqli_fetch_assoc($result)): 
                    // Hitung total hanya jika status sukses/completed
                    if($row['status'] == 'completed' || $row['status'] == 'success') {
                        $grand_total_omset += $row['total_amount'];
                    }

                    // Formatting Item agar rapi di dalam sel Excel
                    $item_list_html = "";
                    if($row['items_data']) {
                        $items = explode('||', $row['items_data']);
                        foreach($items as $itemStr) {
                            $parts = explode(';;', $itemStr);
                            $qty = $parts[0];
                            $name = $parts[1];
                            $variant = ($parts[2] !== '-') ? " (".$parts[2].")" : "";
                            // Output: 1x Kopi Susu (Gula Aren)
                            $item_list_html .= "{$qty}x {$name}{$variant}<br>";
                        }
                    }
            ?>
            <tr>
                <td class="text-center"><?php echo $no++; ?></td>
                <td class="str"><?php echo $row['no_invoice']; ?></td>
                <td><?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                <td><?php echo $row['customer_name']; ?></td>
                <td><?php echo $item_list_html; ?></td>
                <td class="text-center"><?php echo strtoupper($row['status']); ?></td>
                <td class="text-right"><?php echo number_format($row['total_amount'], 0, ',', '.'); ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr>
                <td colspan="7" class="text-center">Tidak ada data pada periode ini.</td>
            </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="text-right" style="font-weight:bold;">TOTAL OMSET (LUNAS)</td>
                <td class="text-right" style="font-weight:bold;"><?php echo number_format($grand_total_omset, 0, ',', '.'); ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>