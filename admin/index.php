<?php
// 1. SET TIMEZONE & KONEKSI
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../koneksi.php';

// Cek Login Admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'kasir'])) {
    header("Location: ../login.php");
    exit;
}

// --- FILTER TANGGAL ---
// Default: Dari tanggal 1 bulan ini sampai Hari Ini
$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end_date   = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');


// --- LOGIC DATA REAL ---

// A. DATA SNAPSHOT (TIDAK TERPENGARUH TANGGAL)
// ----------------------------------
// Total Produk
$q1 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT count(*) as t FROM products"));
$total_produk = $q1['t'];

// Stok Menipis (< 5)
$q2a = mysqli_fetch_assoc(mysqli_query($conn, "SELECT count(*) as t FROM products WHERE product_type='non_retail' AND stock <= 5"));
$q2b = mysqli_fetch_assoc(mysqli_query($conn, "SELECT count(*) as t FROM product_variants WHERE stock <= 5"));
$stok_tipis = $q2a['t'] + $q2b['t'];

// Total Staff
$q3 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT count(*) as t FROM users WHERE role != 'member'"));
$total_user = $q3['t'];


// B. DATA KEUANGAN (TERPENGARUH FILTER TANGGAL)
// ----------------------------------

// 1. Total Omset (Pemasukan) sesuai Periode
$q_omset = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as total FROM transactions WHERE status != 'cancelled' AND DATE(created_at) BETWEEN '$start_date' AND '$end_date'"));
$total_omset = $q_omset['total'] ?? 0;

// 2. Total Pengeluaran (Biaya) sesuai Periode
$total_pengeluaran = 0;
$cek_tabel = mysqli_query($conn, "SHOW TABLES LIKE 'expenses'");
if(mysqli_num_rows($cek_tabel) > 0){
    $q_expense = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN '$start_date' AND '$end_date'"));
    $total_pengeluaran = $q_expense['total'] ?? 0;
}

// 3. Laba Bersih
$laba_bersih = $total_omset - $total_pengeluaran;


// C. INDIKATOR PERFORMA (HARI INI vs KEMARIN) - Tetap menampilkan performa harian
// ----------------------------------
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime("-1 days"));

$q_today = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as total FROM transactions WHERE DATE(created_at) = '$today' AND status != 'cancelled'"));
$omset_today = $q_today['total'] ?? 0;

$q_yest = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as total FROM transactions WHERE DATE(created_at) = '$yesterday' AND status != 'cancelled'"));
$omset_yest = $q_yest['total'] ?? 0;

$persen = 0;
$icon_persen = "bi-dash";
$color_persen = "text-gray-500";
$bg_persen = "bg-gray-100";
$persen_text = "0%";

if ($omset_yest > 0) {
    $diff = $omset_today - $omset_yest;
    $persen = round(($diff / $omset_yest) * 100);
} else if ($omset_today > 0) {
    $persen = 100;
}

if ($persen > 0) {
    $icon_persen = "bi-arrow-up-right";
    $color_persen = "text-emerald-700";
    $bg_persen = "bg-emerald-100";
    $persen_text = "+" . $persen . "%";
} elseif ($persen < 0) {
    $icon_persen = "bi-arrow-down-right";
    $color_persen = "text-rose-700";
    $bg_persen = "bg-rose-100";
    $persen_text = $persen . "%";
}


// D. CHART DATA (DINAMIS SESUAI TANGGAL)
// ----------------------------------
$chart_labels = [];
$chart_data = [];

// Loop tanggal dari start sampai end
$begin = new DateTime($start_date);
$end   = new DateTime($end_date);
$end->modify('+1 day'); // Biar tanggal terakhir ikutan

$interval = DateInterval::createFromDateString('1 day');
$period   = new DatePeriod($begin, $interval, $end);

foreach ($period as $dt) {
    $d = $dt->format("Y-m-d");
    $label = $dt->format("d M"); // Tgl Bulan (contoh: 15 Feb)
    
    $q_chart = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as t FROM transactions WHERE DATE(created_at) = '$d' AND status != 'cancelled'"));
    
    $chart_labels[] = $label;
    $chart_data[] = $q_chart['t'] ?? 0;
}


// E. TRANSAKSI TERAKHIR
// ----------------------------------
$q_recent = mysqli_query($conn, "SELECT * FROM transactions ORDER BY created_at DESC LIMIT 5");

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;
    $string = array('y' => 'thn', 'm' => 'bln', 'w' => 'mgg', 'd' => 'hr', 'h' => 'jam', 'i' => 'mnt', 's' => 'dtk');
    foreach ($string as $k => &$v) {
        if ($diff->$k) { $v = $diff->$k . ' ' . $v; } else { unset($string[$k]); }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' lalu' : 'Baru saja';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - POS PRO</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        /* MODE TERANG / LIGHT MODE */
        body { background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar:hover::-webkit-scrollbar-thumb { background: #94a3b8; }
    </style>
</head>
<body class="flex overflow-hidden text-sm">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 ml-0 md:ml-64 h-screen overflow-y-auto p-4 md:p-8 relative pb-24 custom-scrollbar">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-5 border-b border-gray-200 pb-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-green-50 border border-green-200 text-green-600 text-[10px] font-bold uppercase tracking-widest mb-3">
                    <i class="bi bi-rocket-takeoff-fill"></i> Selamat Bekerja
                </div>
                <h2 class="text-3xl font-extrabold text-gray-800 tracking-tight">Overview</h2>
                <p class="text-gray-500 text-xs mt-1 font-medium">Laporan periode: <span class="text-green-600 font-bold"><?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></span></p>
            </div>
            
            <form class="bg-white p-1.5 rounded-xl flex items-center gap-2 shadow-sm border border-gray-200 w-full md:w-auto">
                <div class="relative flex-1 md:flex-none">
                    <i class="bi bi-calendar-event absolute left-3 top-2 text-gray-400 text-xs"></i>
                    <input type="date" name="start" value="<?php echo $start_date; ?>" class="bg-transparent border-none text-gray-700 font-medium text-xs focus:ring-0 pl-8 pr-2 py-1.5 outline-none cursor-pointer w-full">
                </div>
                <span class="text-gray-400 font-bold">-</span>
                <div class="relative flex-1 md:flex-none">
                    <i class="bi bi-calendar-event absolute left-3 top-2 text-gray-400 text-xs"></i>
                    <input type="date" name="end" value="<?php echo $end_date; ?>" class="bg-transparent border-none text-gray-700 font-medium text-xs focus:ring-0 pl-8 pr-2 py-1.5 outline-none cursor-pointer w-full">
                </div>
                <button type="submit" class="bg-green-600 hover:bg-green-500 text-white px-4 py-1.5 rounded-lg flex items-center justify-center transition shadow-sm font-bold text-xs shrink-0">
                    Filter
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
            
            <div class="bg-white border border-gray-200 p-6 rounded-2xl relative overflow-hidden group hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="absolute right-0 top-0 p-5 opacity-[0.03] group-hover:scale-110 group-hover:rotate-12 transition-transform duration-500"><i class="bi bi-wallet2 text-7xl text-gray-900"></i></div>
                <div class="relative z-10">
                    <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-500 flex items-center justify-center text-xl border border-emerald-100 mb-3"><i class="bi bi-graph-up"></i></div>
                    <p class="text-gray-500 text-xs mb-1 font-bold uppercase tracking-wider">Total Pemasukan</p>
                    <h3 class="text-3xl font-black text-gray-800 tracking-tight mt-1">Rp <?php echo number_format($total_omset); ?></h3>
                    <div class="mt-3 flex items-center gap-2">
                        <span class="text-[10px] <?php echo $color_persen; ?> <?php echo $bg_persen; ?> px-2 py-1 rounded-md font-bold flex items-center gap-1 border border-white">
                            <i class="bi <?php echo $icon_persen; ?>"></i> <?php echo $persen_text; ?>
                        </span>
                        <span class="text-[9px] text-gray-400 font-medium">vs Kemarin</span>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 p-6 rounded-2xl relative overflow-hidden group hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="absolute right-0 top-0 p-5 opacity-[0.03] group-hover:scale-110 group-hover:rotate-12 transition-transform duration-500"><i class="bi bi-cart-dash text-7xl text-gray-900"></i></div>
                <div class="relative z-10">
                    <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-500 flex items-center justify-center text-xl border border-rose-100 mb-3"><i class="bi bi-arrow-down-right-circle"></i></div>
                    <p class="text-gray-500 text-xs mb-1 font-bold uppercase tracking-wider">Total Pengeluaran</p>
                    <h3 class="text-3xl font-black text-gray-800 tracking-tight mt-1">Rp <?php echo number_format($total_pengeluaran); ?></h3>
                    <div class="mt-3">
                        <a href="pengeluaran.php" class="inline-flex items-center gap-1.5 text-[10px] text-rose-500 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-lg transition font-bold border border-rose-200">
                            Kelola Biaya <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 p-6 rounded-2xl relative overflow-hidden group hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                <div class="absolute right-0 top-0 p-5 opacity-[0.03] group-hover:scale-110 group-hover:-rotate-12 transition-transform duration-500"><i class="bi bi-graph-up-arrow text-7xl text-gray-900"></i></div>
                <div class="relative z-10">
                    <div class="w-10 h-10 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center text-xl border border-amber-100 mb-3"><i class="bi bi-cash-coin"></i></div>
                    <p class="text-gray-500 text-xs mb-1 font-bold uppercase tracking-wider">Laba Bersih (Profit)</p>
                    <h3 class="text-3xl font-black text-amber-500 tracking-tight mt-1">Rp <?php echo number_format($laba_bersih); ?></h3>
                    <div class="mt-3 text-[10px] text-gray-500 font-medium bg-gray-50 w-fit px-2 py-1 rounded-md border border-gray-100">
                        <i class="bi bi-info-circle"></i> Omset dikurangi Pengeluaran
                    </div>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            
            <div class="bg-white p-4 md:p-5 rounded-2xl flex items-center justify-between border border-gray-200 shadow-sm hover:shadow-md transition group">
                <div>
                    <p class="text-gray-400 text-[10px] font-bold uppercase tracking-wider mb-1">Total Menu</p>
                    <h4 class="text-2xl font-black text-gray-800 leading-none"><?php echo $total_produk; ?></h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center border border-blue-100 group-hover:scale-110 transition"><i class="bi bi-box-seam text-lg"></i></div>
            </div>

            <div class="bg-white p-4 md:p-5 rounded-2xl flex items-center justify-between border border-gray-200 shadow-sm hover:shadow-md transition group">
                <div>
                    <p class="text-gray-400 text-[10px] font-bold uppercase tracking-wider mb-1">Total Staff</p>
                    <h4 class="text-2xl font-black text-gray-800 leading-none"><?php echo $total_user; ?></h4>
                </div>
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-500 flex items-center justify-center border border-purple-100 group-hover:scale-110 transition"><i class="bi bi-people text-lg"></i></div>
            </div>

            <div class="bg-white p-4 md:p-5 rounded-2xl flex items-center justify-between border transition shadow-sm group <?php echo ($stok_tipis > 0) ? 'border-red-300 bg-red-50' : 'border-gray-200 hover:shadow-md'; ?>">
                <div>
                    <p class="text-gray-400 text-[10px] font-bold uppercase tracking-wider mb-1">Stok Menipis</p>
                    <h4 class="text-2xl font-black leading-none <?php echo ($stok_tipis > 0) ? 'text-red-500' : 'text-gray-800'; ?>"><?php echo $stok_tipis; ?></h4>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center group-hover:scale-110 border transition <?php echo ($stok_tipis > 0) ? 'bg-red-100 text-red-500 border-red-200' : 'bg-gray-50 text-gray-400 border-gray-100'; ?>"><i class="bi bi-exclamation-triangle text-lg"></i></div>
            </div>

            <?php if($_SESSION['role'] == 'admin'): ?>
            <a href="settings.php" class="bg-white hover:bg-green-50 p-4 md:p-5 rounded-2xl flex flex-col items-center justify-center text-center gap-2 cursor-pointer group border border-gray-200 shadow-sm transition">
                <i class="bi bi-gear text-xl text-gray-400 group-hover:text-green-500 group-hover:rotate-90 transition-all duration-300"></i>
                <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider group-hover:text-green-600 transition">Pengaturan</span>
            </a>
            <?php endif; ?>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-2 bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h3 class="font-extrabold text-gray-800 text-lg">Grafik Penjualan</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Pergerakan omset sesuai periode filter.</p>
                    </div>
                    <div class="bg-green-50 px-3 py-1.5 rounded-lg border border-green-200 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> 
                        <span class="text-[10px] font-bold text-green-600 uppercase tracking-wider">Omset Harian</span>
                    </div>
                </div>
                <div class="h-72 w-full mt-4">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm flex flex-col">
                <div class="flex justify-between items-center mb-6 shrink-0">
                    <h3 class="font-extrabold text-gray-800 text-lg">Transaksi Baru</h3>
                    <a href="laporan.php" class="text-[10px] font-bold text-green-600 hover:text-green-700 hover:bg-green-50 px-3 py-1.5 rounded-lg transition border border-transparent hover:border-green-100 uppercase tracking-wider">Lihat Semua</a>
                </div>
                
                <div class="space-y-3 flex-1 overflow-y-auto custom-scrollbar pr-2">
                    <?php 
                    if(mysqli_num_rows($q_recent) > 0) {
                        while($trx = mysqli_fetch_assoc($q_recent)): 
                            $tid = $trx['id'];
                            $q_item = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(qty) as qty FROM transaction_details WHERE transaction_id='$tid'"));
                            $qty = $q_item['qty'] ?? 0;
                            
                            $cust = explode('(', $trx['customer_name'])[0];
                            if(strlen($cust) > 15) $cust = substr($cust, 0, 15) . '..';
                    ?>
                    <div class="flex items-center gap-3 p-3 bg-gray-50 hover:bg-gray-100 border border-gray-100 rounded-xl transition group cursor-default">
                        <div class="w-10 h-10 shrink-0 rounded-lg bg-white border border-gray-200 flex items-center justify-center text-gray-500 group-hover:text-green-500 group-hover:border-green-300 transition-colors shadow-sm">
                            <i class="bi bi-receipt-cutoff"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-gray-800 font-mono truncate"><?php echo $trx['no_invoice']; ?></h4>
                            <p class="text-[10px] text-gray-500 mt-0.5 truncate"><?php echo htmlspecialchars($cust); ?> • <?php echo $qty; ?> Item</p>
                        </div>
                        <div class="text-right shrink-0 pl-2">
                            <div class="text-xs font-black text-green-600">+Rp <?php echo number_format($trx['total_amount']); ?></div>
                            <div class="text-[9px] text-gray-400 mt-0.5 font-medium"><?php echo time_elapsed_string($trx['created_at']); ?></div>
                        </div>
                    </div>
                    <?php 
                        endwhile; 
                    } else {
                        echo '<div class="flex flex-col items-center justify-center h-full py-10 opacity-50"><i class="bi bi-inbox text-3xl text-gray-400 mb-2"></i><p class="text-center text-gray-500 text-xs font-medium">Belum ada transaksi</p></div>';
                    }
                    ?>
                </div>
            </div>

        </div>

    </main>

    <script>
        // Set up Chart.js dengan style LIGHT MODE
        const ctx = document.getElementById('salesChart').getContext('2d');
        const labels = <?php echo json_encode($chart_labels); ?>;
        const dataVal = <?php echo json_encode($chart_data); ?>;

        // Custom Font untuk Chart
        Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";

        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Omset (Rp)',
                    data: dataVal,
                    borderColor: '#10b981', // Emerald-500 (Green)
                    backgroundColor: (context) => {
                        const chartCtx = context.chart.ctx;
                        const gradient = chartCtx.createLinearGradient(0, 0, 0, 300);
                        gradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)'); // Green transparent
                        gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');
                        return gradient;
                    },
                    borderWidth: 3,
                    tension: 0.4, // Membuat kurva melengkung halus
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#10b981',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointHoverBackgroundColor: '#10b981',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.95)',
                        titleColor: '#1e293b',
                        bodyColor: '#475569',
                        borderColor: 'rgba(0,0,0,0.1)',
                        borderWidth: 1,
                        padding: 12,
                        displayColors: false,
                        titleFont: { size: 13, weight: 'bold' },
                        bodyFont: { size: 12, weight: 'bold' },
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)', borderDash: [5, 5], drawBorder: false },
                        ticks: { 
                            color: '#64748b',
                            font: { size: 10, weight: '600' },
                            padding: 10,
                            callback: function(value) {
                                if(value === 0) return '0';
                                return value >= 1000000 ? (value/1000000) + ' Jt' : (value >= 1000 ? (value/1000) + 'k' : value);
                            }
                        },
                        border: { display: false }
                    },
                    x: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: '#64748b', font: { size: 10, weight: '600' }, padding: 10 },
                        border: { display: false }
                    }
                }
            }
        });
    </script>
</body>
</html>