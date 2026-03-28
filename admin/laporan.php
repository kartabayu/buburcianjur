<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../koneksi.php';

// Cek Login
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'kasir'])) { 
    header("Location: ../login.php"); 
    exit; 
}

// AMBIL DATA TOKO & SETTING BIAYA
$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));
$label_biaya = isset($shop['fee_label']) ? $shop['fee_label'] : 'Biaya Layanan';

// Variabel SweetAlert
$swal_script = "";

// --- LOGIC 1: HAPUS TRANSAKSI + RESTOCK (Hanya Admin) ---
if (isset($_POST['delete_trx']) && $_SESSION['role'] == 'admin') {
    $trx_id = intval($_POST['delete_id']);
    $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM transactions WHERE id='$trx_id'"));
    
    // Jika status bukan 'cancelled', kembalikan stok
    if ($cek['status'] != 'cancelled') {
        $items = mysqli_query($conn, "SELECT * FROM transaction_details WHERE transaction_id='$trx_id'");
        while($item = mysqli_fetch_assoc($items)) {
            $pid = $item['product_id'];
            $qty = $item['qty'];
            $vname = mysqli_real_escape_string($conn, explode(' +', $item['variant_name'])[0]);
            
            mysqli_query($conn, "UPDATE product_variants SET stock = stock + $qty WHERE product_id='$pid' AND variant_name='$vname'");
            if(mysqli_affected_rows($conn) == 0) {
                mysqli_query($conn, "UPDATE products SET stock = stock + $qty WHERE id='$pid'");
            }
        }
    }

    if(mysqli_query($conn, "DELETE FROM transaction_details WHERE transaction_id='$trx_id'") && mysqli_query($conn, "DELETE FROM transactions WHERE id='$trx_id'")) {
        $swal_script = "Swal.fire({icon:'success', title:'Terhapus!', text:'Data transaksi dihapus.', background:'#ffffff', color:'#1e293b', confirmButtonColor:'#ef4444', timer:1500, showConfirmButton:false}).then(()=>{ window.location='laporan.php?tab=history'; });";
    }
}

// --- LOGIC 2: UPDATE STATUS (Manual Bypass / Dari Modal) ---
if (isset($_POST['update_status'])) {
    $trx_id = intval($_POST['trx_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT status FROM transactions WHERE id='$trx_id'"));
    
    if($cek['status'] != $new_status) {
        mysqli_query($conn, "UPDATE transactions SET status='$new_status' WHERE id='$trx_id'");
        
        // Logic Restock jika diubah menjadi Batal
        if($new_status == 'cancelled' && $cek['status'] != 'cancelled') {
            $items = mysqli_query($conn, "SELECT * FROM transaction_details WHERE transaction_id='$trx_id'");
            while($item = mysqli_fetch_assoc($items)) {
                $pid = $item['product_id'];
                $qty = $item['qty'];
                $vname = mysqli_real_escape_string($conn, explode(' +', $item['variant_name'])[0]);
                
                mysqli_query($conn, "UPDATE product_variants SET stock = stock + $qty WHERE product_id='$pid' AND variant_name='$vname'");
                if(mysqli_affected_rows($conn) == 0) {
                    mysqli_query($conn, "UPDATE products SET stock = stock + $qty WHERE id='$pid'");
                }
            }
        }
    }
    // Jika update dari live tracking, kembali ke tab live
    $target_tab = isset($_POST['from_live']) ? 'live' : 'history';
    $swal_script = "Swal.fire({icon:'success', title:'Berhasil', text:'Status diperbarui.', background:'#ffffff', color:'#1e293b', confirmButtonColor:'#10b981', timer:1500, showConfirmButton:false}).then(()=>{ window.location='laporan.php?tab=$target_tab'; });";
}

// FILTER TANGGAL UNTUK RIWAYAT
$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end_date   = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'live';

// QUERY LIVE MONITOR (Pesanan yang BELUM selesai)
$q_live = mysqli_query($conn, "SELECT t.*, 
    (SELECT GROUP_CONCAT(CONCAT(qty, ';;', product_name, ';;', COALESCE(variant_name, '-'), ';;', price, ';;', subtotal) SEPARATOR '||') 
     FROM transaction_details d WHERE d.transaction_id = t.id) as items_data 
    FROM transactions t WHERE t.status NOT IN ('completed', 'cancelled') ORDER BY t.id DESC");

// QUERY RIWAYAT (Berdasarkan tanggal)
$query_history = "SELECT t.*, 
    (SELECT GROUP_CONCAT(CONCAT(qty, ';;', product_name, ';;', COALESCE(variant_name, '-'), ';;', price, ';;', subtotal) SEPARATOR '||') 
     FROM transaction_details d WHERE d.transaction_id = t.id) as items_data 
    FROM transactions t 
    WHERE DATE(t.created_at) BETWEEN '$start_date' AND '$end_date' 
    ORDER BY t.created_at DESC";
$result_history = mysqli_query($conn, $query_history);

// DATA KARTU ATAS (Berdasarkan tanggal filter)
$q_omset = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as total FROM transactions WHERE (status='completed' OR status='success') AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'"));
$total_omset = $q_omset['total'] ?? 0;
$q_trx = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as jumlah FROM transactions WHERE status!='cancelled' AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'"));
$total_transaksi = $q_trx['jumlah'] ?? 0;
$q_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as jumlah FROM transactions WHERE status='pending' AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'"));
$total_pending = $q_pending['jumlah'] ?? 0;

// FUNGSI BADGE STATUS TRACKING (Versi Light Mode)
function getStatusBadge($status) {
    switch ($status) {
        case 'pending': return '<span class="px-2.5 py-1 rounded bg-gray-100 text-gray-500 border border-gray-300 text-[10px] font-bold tracking-wider"><i class="bi bi-hourglass-split"></i> MENUNGGU</span>';
        case 'success': return '<span class="px-2.5 py-1 rounded bg-yellow-50 text-yellow-600 border border-yellow-200 text-[10px] font-bold tracking-wider"><i class="bi bi-wallet2"></i> DIBAYAR (ANTRI)</span>';
        case 'preparing': return '<span class="px-2.5 py-1 rounded bg-orange-50 text-orange-600 border border-orange-200 text-[10px] font-bold tracking-wider"><i class="bi bi-fire animate-pulse"></i> DIMASAK / PACKING</span>';
        case 'ready': return '<span class="px-2.5 py-1 rounded bg-cyan-50 text-cyan-600 border border-cyan-200 text-[10px] font-bold tracking-wider"><i class="bi bi-box-seam"></i> SIAP PICKUP/KIRIM</span>';
        case 'delivering': return '<span class="px-2.5 py-1 rounded bg-blue-50 text-blue-600 border border-blue-200 text-[10px] font-bold tracking-wider"><i class="bi bi-scooter animate-pulse"></i> DIBAWA DRIVER</span>';
        case 'completed': return '<span class="px-2.5 py-1 rounded bg-emerald-50 text-emerald-600 border border-emerald-200 text-[10px] font-bold tracking-wider"><i class="bi bi-check-all text-sm"></i> SELESAI/LUNAS</span>';
        case 'cancelled': return '<span class="px-2.5 py-1 rounded bg-red-50 text-red-600 border border-red-200 text-[10px] font-bold tracking-wider"><i class="bi bi-x-circle"></i> BATAL</span>';
        default: return '<span class="px-2.5 py-1 rounded bg-gray-100 text-gray-600 border border-gray-300 text-[10px] font-bold">'.$status.'</span>';
    }
}

// Fungsi Border Kiri untuk Card Live (Versi Light Mode)
function getStatusBorder($status) {
    if($status == 'preparing') return 'border-l-orange-500';
    if($status == 'delivering') return 'border-l-blue-500';
    if($status == 'ready') return 'border-l-cyan-500';
    if($status == 'success') return 'border-l-yellow-500';
    return 'border-l-gray-400';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - <?php echo htmlspecialchars($shop['shop_name']); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } } }</script>
    <style>
        body { background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
        
        .glass-input { background: #ffffff; border: 1px solid #e2e8f0; color: #1e293b; transition: all 0.3s; }
        .glass-input:focus { border-color: #10b981; outline: none; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
        
        .tab-btn { opacity: 0.5; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .tab-btn:hover { opacity: 0.8; }
        .tab-btn.active { opacity: 1; border-color: #10b981; color: #059669; }

        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar:hover::-webkit-scrollbar-thumb { background: #94a3b8; }
        
        #receiptTemplate { position: fixed; left: -9999px; top: 0; width: 320px; background: white; color: black; z-index: -1; padding: 25px 20px; font-family: 'Courier New', Courier, monospace; }
    </style>
</head>
<body class="flex overflow-hidden text-sm">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 ml-0 md:ml-64 h-screen overflow-y-auto p-4 md:p-8 relative pb-24 custom-scrollbar">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4 border-b border-gray-200 pb-5">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 border border-blue-200 text-blue-600 text-[10px] font-bold uppercase tracking-widest mb-3">
                    <i class="bi bi-broadcast"></i> Control Tower
                </div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-gray-800 tracking-tight">Laporan & Live Monitor</h2>
                <p class="text-gray-500 text-xs mt-1 font-medium">Pantau pergerakan pesanan, hapus data & cetak struk.</p>
            </div>
            <label class="flex items-center gap-2 cursor-pointer bg-white px-3 py-2 rounded-lg border border-gray-200 shadow-sm hover:bg-gray-50 transition" id="autoRefreshContainer">
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Auto Refresh (15s)</span>
                <input type="checkbox" id="autoRefreshToggle" checked class="accent-green-500 w-4 h-4 cursor-pointer">
            </label>
        </div>

        <div class="flex gap-6 border-b border-gray-200 mb-6 px-2">
            <button onclick="switchTab('live')" id="btn-live" class="tab-btn pb-3 px-2 font-extrabold text-sm <?php echo $active_tab == 'live' ? 'active' : ''; ?> flex items-center gap-2">
                <i class="bi bi-activity"></i> Live Monitor 
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500 border border-white"></span>
                </span>
            </button>
            <button onclick="switchTab('history')" id="btn-history" class="tab-btn pb-3 px-2 font-extrabold text-sm <?php echo $active_tab == 'history' ? 'active' : ''; ?> flex items-center gap-2">
                <i class="bi bi-wallet2"></i> Riwayat & Keuangan
            </button>
        </div>

        <div id="content-live" class="<?php echo $active_tab == 'live' ? '' : 'hidden'; ?> space-y-4">
            <div class="grid grid-cols-1 gap-4">
                <?php if(mysqli_num_rows($q_live) > 0): while($row = mysqli_fetch_assoc($q_live)): 
                    $is_delivery = (strpos($row['location'] ?? '', 'Alamat:') !== false) || (strpos($row['customer_name'], 'Alamat:') !== false);
                    $raw_loc = !empty($row['location']) ? $row['location'] : $row['customer_name'];
                    $dest = $is_delivery ? str_replace("Alamat: ", "", $raw_loc) : $raw_loc;
                    if(preg_match('/\((.*?)\)/', $dest, $matches)) { $dest = $matches[1]; }
                    $dest = str_replace("Alamat: ", "", $dest);
                    $nama_bersih = explode('(', $row['customer_name'])[0];
                ?>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:shadow-md transition-all border-l-4 <?php echo getStatusBorder($row['status']); ?>">
                    <div class="flex-1 w-full">
                        <div class="flex items-center gap-3 mb-3 flex-wrap">
                            <h3 class="text-lg font-black text-gray-800 font-mono tracking-tight">#<?php echo $row['no_invoice']; ?></h3>
                            <?php echo getStatusBadge($row['status']); ?>
                            <?php if($is_delivery): ?>
                                <span class="bg-blue-50 text-blue-600 text-[10px] font-bold px-2 py-1 rounded-md border border-blue-200 flex items-center gap-1"><i class="bi bi-truck"></i> DELIVERY</span>
                            <?php else: ?>
                                <span class="bg-purple-50 text-purple-600 text-[10px] font-bold px-2 py-1 rounded-md border border-purple-200 flex items-center gap-1"><i class="bi bi-shop"></i> PICKUP</span>
                            <?php endif; ?>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-gray-500 font-medium">
                            <div><i class="bi bi-person text-gray-400 mr-1"></i> <strong class="text-gray-800"><?php echo htmlspecialchars($nama_bersih); ?></strong> <br> <i class="bi bi-telephone text-gray-400 mt-1.5 mr-1"></i> <?php echo htmlspecialchars($row['customer_wa'] ?? '-'); ?></div>
                            <div class="truncate" title="<?php echo htmlspecialchars($dest); ?>"><i class="bi bi-geo-alt text-gray-400 mr-1"></i> Tujuan/Meja: <br> <span class="text-gray-700 font-bold ml-4 block truncate mt-0.5"><?php echo htmlspecialchars($dest); ?></span></div>
                            <div><i class="bi bi-clock text-gray-400 mr-1"></i> Masuk: <strong class="text-gray-800 bg-gray-100 px-1 rounded"><?php echo date('H:i', strtotime($row['created_at'])); ?></strong> <br> <i class="bi bi-cash text-gray-400 mt-1.5 mr-1"></i> Rp <?php echo number_format($row['total_amount']); ?> <span class="text-[9px] bg-gray-200 text-gray-600 px-1 rounded ml-1 font-bold"><?php echo strtoupper($row['payment_method']); ?></span></div>
                        </div>
                    </div>
                    
                    <div class="flex flex-row md:flex-col gap-2 w-full md:w-48 shrink-0 border-t md:border-t-0 md:border-l border-gray-100 pt-4 md:pt-0 md:pl-4">
                        <button onclick='openDetail(<?php echo json_encode($row); ?>)' class="flex-1 bg-white hover:bg-gray-50 text-gray-700 font-bold py-2 rounded-xl border border-gray-200 shadow-sm transition text-xs flex items-center justify-center gap-2 active:scale-95">
                            <i class="bi bi-eye"></i> Detail
                        </button>
                        <form method="POST" class="flex-1">
                            <input type="hidden" name="trx_id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="update_status" value="true">
                            <input type="hidden" name="from_live" value="true">
                            <div class="relative">
                                <select name="status" onchange="this.form.submit()" class="w-full px-2 py-2 rounded-xl text-[10px] font-extrabold uppercase tracking-wider text-center cursor-pointer appearance-none bg-indigo-50 border border-indigo-200 text-indigo-600 hover:bg-indigo-100 transition shadow-sm pr-6">
                                    <option value="" disabled selected>Ubah Status</option>
                                    <option value="pending">Menunggu</option>
                                    <option value="preparing">Dimasak</option>
                                    <option value="ready">Siap PickUp</option>
                                    <option value="delivering">Diantar</option>
                                    <option value="completed">Selesai/Lunas</option>
                                    <option value="cancelled">Batalkan</option>
                                </select>
                                <i class="bi bi-chevron-down absolute right-2.5 top-2.5 text-indigo-500 pointer-events-none text-xs"></i>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <div class="bg-white p-12 rounded-3xl flex flex-col items-center justify-center text-gray-400 border border-dashed border-gray-300">
                        <i class="bi bi-emoji-smile text-5xl mb-3 text-gray-300"></i>
                        <p class="font-medium text-sm">Dapur sedang sepi. Belum ada pesanan masuk.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="content-history" class="<?php echo $active_tab == 'history' ? '' : 'hidden'; ?>">
            
            <div class="flex justify-end mb-4">
                <form class="bg-white p-1.5 rounded-xl flex items-center gap-2 flex-wrap md:flex-nowrap shadow-sm border border-gray-200">
                    <input type="hidden" name="tab" value="history">
                    <div class="relative">
                        <i class="bi bi-calendar-event absolute left-3 top-2 text-gray-400 text-xs"></i>
                        <input type="date" id="dateStart" name="start" value="<?php echo $start_date; ?>" class="bg-transparent border-none text-gray-700 font-medium text-xs focus:ring-0 pl-8 pr-2 py-1.5 outline-none cursor-pointer">
                    </div>
                    <span class="text-gray-400 font-bold">-</span>
                    <div class="relative">
                        <i class="bi bi-calendar-event absolute left-3 top-2 text-gray-400 text-xs"></i>
                        <input type="date" id="dateEnd" name="end" value="<?php echo $end_date; ?>" class="bg-transparent border-none text-gray-700 font-medium text-xs focus:ring-0 pl-8 pr-2 py-1.5 outline-none cursor-pointer">
                    </div>
                    
                    <button type="submit" class="bg-green-600 hover:bg-green-500 text-white px-4 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 shadow-sm">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                    <button type="button" onclick="exportToExcel()" class="bg-emerald-50 hover:bg-emerald-100 text-emerald-600 px-4 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-2 border border-emerald-200 shadow-sm">
                        <i class="bi bi-file-earmark-excel-fill"></i> Excel
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
                <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex justify-between items-center group hover:shadow-md transition">
                    <div>
                        <p class="text-gray-500 text-xs mb-1 font-bold uppercase tracking-wider">Omset Lunas</p>
                        <h3 class="text-2xl font-black text-emerald-600 tracking-tight">Rp <?php echo number_format($total_omset); ?></h3>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-500 text-xl"><i class="bi bi-wallet2"></i></div>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex justify-between items-center group hover:shadow-md transition">
                    <div>
                        <p class="text-gray-500 text-xs mb-1 font-bold uppercase tracking-wider">Pesanan Pending</p>
                        <h3 class="text-2xl font-black text-amber-500 tracking-tight"><?php echo $total_pending; ?> <span class="text-sm font-bold text-gray-400">Trx</span></h3>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-500 text-xl"><i class="bi bi-hourglass-split"></i></div>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm flex justify-between items-center group hover:shadow-md transition">
                    <div>
                        <p class="text-gray-500 text-xs mb-1 font-bold uppercase tracking-wider">Total Transaksi</p>
                        <h3 class="text-2xl font-black text-blue-600 tracking-tight"><?php echo $total_transaksi; ?> <span class="text-sm font-bold text-gray-400">Trx</span></h3>
                    </div>
                    <div class="w-12 h-12 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-500 text-xl"><i class="bi bi-receipt"></i></div>
                </div>
            </div>

            <div class="bg-white rounded-2xl overflow-hidden border border-gray-200 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50/50 border-b border-gray-200 text-gray-500 text-xs uppercase font-bold tracking-wider">
                            <tr><th class="p-4">Invoice / Tanggal</th><th class="p-4">Pelanggan</th><th class="p-4">Total Tagihan</th><th class="p-4">Status</th><th class="p-4 text-center">Aksi</th></tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-gray-100 text-gray-700">
                            <?php if(mysqli_num_rows($result_history) > 0): while($row = mysqli_fetch_assoc($result_history)): 
                                $nama_bersih = explode('(', $row['customer_name'])[0];
                                
                                // LOGIKA LABEL DISKON/POIN
                                $display_total = "Rp " . number_format($row['total_amount']);
                                $is_discounted = false;
                                if ((isset($row['discount_amount']) && $row['discount_amount'] > 0) || $row['total_amount'] == 0) {
                                    $is_discounted = true;
                                }
                                if ($is_discounted) {
                                    $display_total .= "<br><span class='text-[9px] text-purple-600 bg-purple-50 px-2 py-0.5 rounded border border-purple-200 mt-1 font-bold inline-block'><i class='bi bi-ticket-perforated'></i> Pakai Promo</span>";
                                }
                            ?>
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="p-4">
                                    <div class="font-bold text-gray-800 mb-1 font-mono tracking-tight">#<?php echo $row['no_invoice']; ?></div>
                                    <div class="text-[10px] text-gray-400 font-medium"><i class="bi bi-calendar-event"></i> <?php echo date('d M Y - H:i', strtotime($row['created_at'])); ?></div>
                                </td>
                                <td class="p-4">
                                    <div class="text-gray-800 font-bold"><?php echo htmlspecialchars($nama_bersih); ?></div>
                                    <div class="text-[10px] text-gray-500 font-medium max-w-[150px] truncate" title="<?php echo htmlspecialchars($row['location'] ?? $row['customer_name']); ?>"><?php echo htmlspecialchars($row['location'] ?? $row['customer_name']); ?></div>
                                </td>
                                <td class="p-4 font-black text-gray-800 tracking-tight"><?php echo $display_total; ?></td>
                                <td class="p-4"><?php echo getStatusBadge($row['status']); ?></td>
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button onclick='openDetail(<?php echo json_encode($row); ?>)' class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white border border-blue-100 hover:border-blue-600 transition flex items-center justify-center shadow-sm" title="Lihat Detail"><i class="bi bi-eye-fill"></i></button>
                                        <?php if($_SESSION['role'] == 'admin'): ?>
                                        <form method="POST" onsubmit="confirmDelete(event)">
                                            <input type="hidden" name="delete_trx" value="true"><input type="hidden" name="delete_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-600 hover:text-white border border-red-100 hover:border-red-600 transition flex items-center justify-center shadow-sm" title="Hapus"><i class="bi bi-trash-fill"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; else: ?><tr><td colspan="5" class="p-10 text-center text-gray-400 font-medium border border-dashed border-gray-200 rounded-xl m-4">Belum ada data transaksi di tanggal ini.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <div id="modalDetail" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="closeDetail()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl transform transition-all scale-95 opacity-0 border border-gray-200 flex flex-col max-h-[90vh]" id="modalContent">
                
                <div class="p-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/80 rounded-t-2xl sticky top-0 z-10">
                    <h3 class="text-lg font-extrabold text-gray-800 flex items-center gap-2"><i class="bi bi-receipt text-green-600"></i> Detail Pesanan</h3>
                    <button onclick="closeDetail()" class="w-8 h-8 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-100 transition shadow-sm"><i class="bi bi-x-lg text-sm"></i></button>
                </div>
                
                <div class="p-6 space-y-5 overflow-y-auto custom-scrollbar flex-1">
                    <div class="flex justify-between items-end border-b border-gray-100 pb-4">
                        <div>
                            <span class="text-xs text-gray-500 font-bold uppercase tracking-wider block mb-1">No. Invoice</span>
                            <span class="text-xl font-black text-gray-800 font-mono tracking-tight" id="d-invoice"></span>
                        </div>
                        <div class="text-right">
                            <span class="text-[10px] text-gray-400 font-medium block">Tanggal</span>
                            <span class="text-xs font-bold text-gray-600" id="d-tanggal-modal"></span>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-100 shadow-inner">
                        <p class="text-[10px] text-gray-400 uppercase tracking-widest font-extrabold mb-3">Rincian Barang</p>
                        <div id="d-items" class="space-y-3 text-sm text-gray-700"></div>
                    </div>

                    <div class="space-y-2.5 pt-2">
                        <div class="flex justify-between items-center text-sm font-bold text-gray-500"><span>Subtotal Item</span><span id="d-subtotal"></span></div>
                        <div class="flex justify-between items-center text-sm font-bold text-orange-500" id="row-fee-popup" style="display:none;"><span><?php echo $label_biaya; ?></span> <span id="d-fee"></span></div>
                        <div class="flex justify-between items-center text-sm text-purple-600 font-bold bg-purple-50 p-2 rounded-lg border border-purple-100" id="row-voucher-popup" style="display:none;"><span><i class="bi bi-tag-fill mr-1"></i> Diskon / Poin</span> <span id="d-voucher"></span></div>
                        
                        <div class="flex justify-between items-center text-2xl font-black text-green-600 pt-3 border-t border-gray-200 mt-2"><span>Total</span><span id="d-total"></span></div>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mt-2">
                        <p class="text-[10px] text-blue-500 uppercase tracking-widest font-extrabold mb-2">Ubah Status</p>
                        <form method="POST" class="flex gap-2">
                            <input type="hidden" name="update_status" value="true"><input type="hidden" name="trx_id" id="d-id">
                            <div class="relative flex-1">
                                <select name="status" id="d-status" class="w-full bg-white border border-blue-200 rounded-lg pl-3 pr-8 py-2.5 text-sm font-bold text-gray-700 appearance-none cursor-pointer focus:outline-none focus:border-blue-500 shadow-sm">
                                    <option value="pending" class="text-gray-500">Menunggu (Pending)</option>
                                    <option value="preparing" class="text-orange-500">Dimasak/Packing</option>
                                    <option value="ready" class="text-cyan-600">Siap Diambil (Ready)</option>
                                    <option value="delivering" class="text-blue-500">Sedang Diantar</option>
                                    <option value="completed" class="text-green-600">Selesai (Lunas)</option>
                                    <option value="cancelled" class="text-red-500">Batal / Refund</option>
                                </select>
                                <i class="bi bi-chevron-down absolute right-3 top-3 text-blue-400 pointer-events-none text-xs"></i>
                            </div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white w-11 h-[42px] flex items-center justify-center rounded-lg transition shadow-md active:scale-95" title="Simpan Status"><i class="bi bi-floppy2-fill"></i></button>
                            <button type="button" id="btnPrint" onclick="printStruk()" class="hidden bg-gray-800 hover:bg-gray-700 text-white px-4 h-[42px] rounded-lg text-xs font-bold transition shadow-md items-center gap-1.5 active:scale-95"><i class="bi bi-printer"></i> Cetak</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="receiptTemplate">
        <div style="text-align:center; margin-bottom:10px;">
            <h2 style="font-weight:bold; font-size:18px; margin:0"><?php echo strtoupper($shop['shop_name']); ?></h2>
            <div style="font-size:10px; margin-top:2px;"><?php echo $shop['address']; ?></div>
            <div style="font-size:10px;">Telp: <?php echo $shop['phone']; ?></div>
        </div>
        <div style="border-bottom:1px dashed #000; margin-bottom:8px;"></div>
        <div style="font-size:11px; line-height:1.4;">
            <div>Kode: <span id="r-kode" style="font-weight:bold;"></span></div>
            <div>Tgl : <span id="r-date"></span></div>
            <div>Nama: <span id="r-name"></span></div>
            <div id="r-loc" style="font-style:italic;"></div>
        </div>
        <div style="border-bottom:1px dashed #000; margin:8px 0;"></div>
        <div id="r-items" style="font-size:11px; margin-bottom:10px;"></div>
        <div style="border-bottom:1px dashed #000; margin-bottom:8px;"></div>
        <div style="font-size:11px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:2px;"><span>Subtotal</span><span id="r-subtotal"></span></div>
            <div id="r-fees-row" style="display:flex; justify-content:space-between; margin-bottom:2px; display:none;"><span><?php echo $label_biaya; ?></span><span id="r-fees"></span></div>
            <div id="r-voucher-row" style="display:flex; justify-content:space-between; margin-bottom:2px; display:none;"><span>Diskon/Poin</span><span id="r-voucher"></span></div>

            <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:14px; margin-top:5px;"><span>TOTAL</span><span id="r-total"></span></div>
            <div style="display:flex; justify-content:space-between; margin-top:5px; font-weight:bold; font-style:italic;"><span>STATUS</span><span>LUNAS</span></div>
        </div>
        <div style="text-align:center; margin-top:15px; font-size:10px; font-weight:bold;">
            <div id="r-note-voucher" style="display:none; color:blue; margin-bottom:5px;">-- Menggunakan Poin/Voucher --</div>
            TERIMA KASIH
            <div style="font-size:10px; margin-top:2px;">Harap simpan struk ini sebagai bukti pembayaran yang sah.</div>
        </div>
    </div>

    <script>
        let currentData = null; 
        <?php if($swal_script) echo $swal_script; ?>

        // Logika Tab Control
        function switchTab(tab) {
            document.getElementById('btn-live').classList.remove('active');
            document.getElementById('btn-history').classList.remove('active');
            document.getElementById('btn-' + tab).classList.add('active');

            document.getElementById('content-live').classList.add('hidden');
            document.getElementById('content-history').classList.add('hidden');
            document.getElementById('content-' + tab).classList.remove('hidden');

            const autoRefreshContainer = document.getElementById('autoRefreshContainer');
            if(tab === 'live') {
                autoRefreshContainer.style.display = 'flex';
            } else {
                autoRefreshContainer.style.display = 'none';
            }

            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.history.pushState({}, '', url);
        }

        // Auto Refresh Logic untuk Live Monitor (Setiap 15 detik)
        setInterval(function() {
            const isLive = !document.getElementById('content-live').classList.contains('hidden');
            const isAutoOn = document.getElementById('autoRefreshToggle').checked;
            if (isLive && isAutoOn && document.getElementById('modalDetail').classList.contains('hidden')) {
                window.location.reload();
            }
        }, 15000);

        function exportToExcel() {
            const startDate = document.getElementById('dateStart').value;
            const endDate = document.getElementById('dateEnd').value;
            window.location.href = `export_excel.php?start=${startDate}&end=${endDate}`;
        }

        function openDetail(data) {
            currentData = data; 
            document.getElementById('modalDetail').classList.remove('hidden');
            setTimeout(() => { document.getElementById('modalContent').classList.remove('scale-95', 'opacity-0'); }, 10);

            document.getElementById('d-id').value = data.id;
            document.getElementById('d-invoice').innerText = data.no_invoice;
            
            // Format tanggal untuk modal
            const d = new Date(data.created_at);
            const tglFormatted = ("0" + d.getDate()).slice(-2) + "/" + ("0"+(d.getMonth()+1)).slice(-2) + "/" + d.getFullYear() + " " + ("0" + d.getHours()).slice(-2) + ":" + ("0" + d.getMinutes()).slice(-2);
            document.getElementById('d-tanggal-modal').innerText = tglFormatted;
            
            let st = data.status;
            if(st === 'success') st = 'completed'; // success dan completed dianggap lunas
            document.getElementById('d-status').value = st;

            const btnPrint = document.getElementById('btnPrint');
            if (st === 'completed') {
                btnPrint.classList.remove('hidden');
                btnPrint.classList.add('flex');
            } else {
                btnPrint.classList.add('hidden');
                btnPrint.classList.remove('flex');
            }

            const itemsContainer = document.getElementById('d-items'); 
            itemsContainer.innerHTML = '';
            let subtotalCalc = 0;

            if(data.items_data) {
                const items = data.items_data.split('||');
                items.forEach(itemStr => {
                    const parts = itemStr.split(';;');
                    const qty = parseInt(parts[0]);
                    const name = parts[1];
                    const variant = parts[2] !== '-' ? `+ ${parts[2]}` : ''; 
                    const price = parseInt(parts[3]);
                    const subItem = parseInt(parts[4]);
                    subtotalCalc += subItem;

                    itemsContainer.innerHTML += `
                    <div class="flex justify-between items-start border-b border-gray-200 pb-2 mb-2 last:border-0">
                        <div>
                            <div class="font-extrabold text-gray-800">${name}</div>
                            <div class="text-xs text-gray-500 italic">${variant}</div>
                            <div class="text-xs text-gray-400 font-bold mt-1 bg-gray-100 px-1.5 py-0.5 rounded inline-block">${qty} x Rp ${price.toLocaleString('id-ID')}</div>
                        </div>
                        <div class="text-sm font-black text-gray-700">Rp ${subItem.toLocaleString('id-ID')}</div>
                    </div>`;
                });
            }

            let grandTotal = parseInt(data.total_amount);
            let serviceFee = 0; let diskon = 0;

            if(data.discount_amount && parseInt(data.discount_amount) > 0) {
                diskon = parseInt(data.discount_amount);
                serviceFee = (grandTotal + diskon) - subtotalCalc;
            } else {
                if (grandTotal < subtotalCalc) { diskon = subtotalCalc - grandTotal; } 
                else if (grandTotal > subtotalCalc) { serviceFee = grandTotal - subtotalCalc; }
            }

            document.getElementById('d-subtotal').innerText = "Rp " + subtotalCalc.toLocaleString('id-ID');
            document.getElementById('d-total').innerText = "Rp " + grandTotal.toLocaleString('id-ID');

            const rowFeePopup = document.getElementById('row-fee-popup');
            if(serviceFee > 0) { rowFeePopup.style.display = 'flex'; document.getElementById('d-fee').innerText = "Rp " + serviceFee.toLocaleString('id-ID'); } else { rowFeePopup.style.display = 'none'; }

            const rowVoucherPopup = document.getElementById('row-voucher-popup');
            if(diskon > 0) { rowVoucherPopup.style.display = 'flex'; document.getElementById('d-voucher').innerText = "-Rp " + diskon.toLocaleString('id-ID'); } else { rowVoucherPopup.style.display = 'none'; }
        }

        function closeDetail() {
            document.getElementById('modalContent').classList.add('scale-95', 'opacity-0');
            setTimeout(() => { document.getElementById('modalDetail').classList.add('hidden'); }, 200);
        }

        function confirmDelete(e) {
            e.preventDefault(); const form = e.target;
            Swal.fire({ title:'Hapus Permanen?', text:"Stok barang akan dikembalikan jika status pesanan bukan 'Batal'.", icon:'warning', background:'#ffffff', color:'#1e293b', showCancelButton:true, confirmButtonColor:'#ef4444', cancelButtonColor:'#94a3b8', confirmButtonText:'Ya, Hapus!' }).then((result) => { if (result.isConfirmed) form.submit(); });
        }

        function printStruk() {
            if(!currentData) return;
            document.getElementById('r-kode').innerText = currentData.no_invoice;
            document.getElementById('r-date').innerText = new Date(currentData.created_at).toLocaleString('id-ID');
            
            let rawName = currentData.customer_name;
            let splitName = rawName.split('(');
            document.getElementById('r-name').innerText = splitName[0].trim();
            
            // Lokasi dari DB yang baru, atau fallback ke splitName jika db lama
            let finalLoc = currentData.location ? currentData.location : (splitName[1] ? "(" + splitName[1] : "");
            document.getElementById('r-loc').innerText = finalLoc;

            const rItems = document.getElementById('r-items'); rItems.innerHTML = '';
            let subtotalCalc = 0;

            if(currentData.items_data) {
                const items = currentData.items_data.split('||');
                items.forEach(itemStr => {
                    const parts = itemStr.split(';;');
                    const qty = parseInt(parts[0]);
                    const name = parts[1];
                    const variant = parts[2] !== '-' ? `+ ${parts[2]}` : '';
                    const price = parseInt(parts[3]);
                    const subItem = parseInt(parts[4]);
                    subtotalCalc += subItem;

                    rItems.innerHTML += `
                    <div style="margin-bottom:8px;">
                        <div style="font-weight:bold;">${name}</div>
                        <div style="font-size:10px; font-style:italic; margin-bottom:2px;">${variant}</div>
                        <div style="display:flex; justify-content:space-between;">
                            <span>${qty} x ${price.toLocaleString('id-ID')}</span>
                            <span>${subItem.toLocaleString('id-ID')}</span>
                        </div>
                    </div>`;
                });
            }

            let grandTotal = parseInt(currentData.total_amount);
            let serviceFee = 0; let diskon = 0;

            if(currentData.discount_amount && parseInt(currentData.discount_amount) > 0) {
                diskon = parseInt(currentData.discount_amount);
                serviceFee = (grandTotal + diskon) - subtotalCalc;
            } else {
                if (grandTotal < subtotalCalc) { diskon = subtotalCalc - grandTotal; } 
                else if (grandTotal > subtotalCalc) { serviceFee = grandTotal - subtotalCalc; }
            }

            document.getElementById('r-subtotal').innerText = "Rp " + subtotalCalc.toLocaleString('id-ID');
            document.getElementById('r-total').innerText = "Rp " + grandTotal.toLocaleString('id-ID');
            
            const rowFees = document.getElementById('r-fees-row');
            if(serviceFee > 0) { rowFees.style.display = 'flex'; document.getElementById('r-fees').innerText = "Rp " + serviceFee.toLocaleString('id-ID'); } else { rowFees.style.display = 'none'; }

            const rowVoucher = document.getElementById('r-voucher-row');
            const noteVoucher = document.getElementById('r-note-voucher');
            if(diskon > 0) {
                rowVoucher.style.display = 'flex'; document.getElementById('r-voucher').innerText = "-Rp " + diskon.toLocaleString('id-ID');
                noteVoucher.style.display = 'block';
            } else {
                rowVoucher.style.display = 'none'; noteVoucher.style.display = 'none';
            }

            html2canvas(document.querySelector("#receiptTemplate")).then(canvas => {
                let link = document.createElement('a');
                link.download = `Struk_${currentData.no_invoice}.jpg`;
                link.href = canvas.toDataURL("image/jpeg", 0.9);
                link.click();
            });
        }
        
        // Panggil saat pertama load untuk menyesuaikan toggle auto refresh
        window.onload = function() {
            switchTab('<?php echo $active_tab; ?>');
        }
    </script>
</body>
</html>