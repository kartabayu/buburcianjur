<?php
session_start();
include '../koneksi.php';

// (BARIS AUTO FIX SQL SUDAH DIHAPUS KARENA KOLOM SUDAH DIBUAT MANUAL)

// Cek Login Role Driver
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'driver') {
    header("Location: ../login.php");
    exit;
}

// Proses Update Status oleh Driver
if(isset($_POST['update_status'])) {
    $trx_id = $_POST['trx_id'];
    $new_status = $_POST['new_status'];
    mysqli_query($conn, "UPDATE transactions SET status='$new_status' WHERE id='$trx_id'");
    header("Location: index.php"); exit;
}

// Query mengambil data pesanan (Mendukung data lama dan baru)
$query_base = "SELECT t.*, 
    (SELECT GROUP_CONCAT(CONCAT(qty, ';;', product_name) SEPARATOR '||') 
     FROM transaction_details d WHERE d.transaction_id = t.id) as items_data 
    FROM transactions t WHERE (t.location LIKE '%Alamat:%' OR t.customer_name LIKE '%Alamat:%') ";

// Filter status
$q_siap = mysqli_query($conn, $query_base . "AND t.status = 'ready' ORDER BY t.id ASC");
$q_jalan = mysqli_query($conn, $query_base . "AND t.status = 'delivering' ORDER BY t.id ASC");
$q_riwayat = mysqli_query($conn, $query_base . "AND t.status = 'completed' ORDER BY t.id DESC LIMIT 30");

$count_siap = mysqli_num_rows($q_siap);
$count_jalan = mysqli_num_rows($q_jalan);
$count_riwayat = mysqli_num_rows($q_riwayat);

// Fungsi Parsing Nama & Lokasi (Agar data lama tidak berantakan)
function parseData($row) {
    $raw_loc = !empty($row['location']) ? $row['location'] : $row['customer_name'];
    $lokasi = $raw_loc;
    if (preg_match('/\((Alamat:.*?)\)/', $raw_loc, $matches)) {
        $lokasi = str_replace("Alamat: ", "", $matches[1]);
    } else {
        $lokasi = str_replace("Alamat: ", "", $lokasi);
    }
    
    $nama = preg_replace('/ \(.*\)/', '', $row['customer_name']);
    $wa = !empty($row['customer_wa']) ? $row['customer_wa'] : '-';
    
    return ['nama' => $nama, 'lokasi' => $lokasi, 'wa' => $wa];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Rider Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } } }
    </script>
    <style>
        body { background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
        
        /* Custom Scrollbar */
        .hide-scroll::-webkit-scrollbar { display: none; }
        .hide-scroll { -ms-overflow-style: none; scrollbar-width: none; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        /* Animasi Tab */
        .tab-btn { transition: all 0.3s ease; }
        .tab-btn.active { background-color: #10b981; color: white; border-color: #10b981; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3); }
        
        .fade-in-up { animation: fadeInUp 0.3s ease-out; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="flex flex-col h-screen overflow-hidden text-sm">

    <header class="bg-white border-b border-gray-200 px-4 py-3 flex justify-between items-center shrink-0 shadow-sm sticky top-0 z-50">
        <div>
            <h1 class="text-base font-extrabold text-green-700 flex items-center gap-1.5"><i class="bi bi-scooter"></i> Rider App</h1>
            <p class="text-[9px] text-gray-500 font-medium">Hati-hati di jalan, <?php echo htmlspecialchars($_SESSION['fullname']); ?>!</p>
        </div>
        <div class="flex gap-3 items-center">
            <div class="text-center hidden md:block px-3 py-1 bg-gray-50 rounded-lg border border-gray-100">
                <div class="text-[8px] text-gray-500 font-bold uppercase tracking-wider">Total Antar</div>
                <div class="text-sm font-black text-green-600 leading-none mt-0.5"><?php echo $count_riwayat; ?> <span class="text-[8px] text-gray-400 font-medium">Selesai</span></div>
            </div>
            <a href="../logout.php" onclick="return confirm('Keluar dari sistem?')" class="bg-white text-red-500 border border-red-200 hover:bg-red-50 transition p-2 rounded-lg text-lg flex items-center justify-center shadow-sm">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </header>

    <div class="px-3 py-2 bg-white border-b border-gray-100 shrink-0">
        <div class="relative">
            <i class="bi bi-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
            <input type="text" id="searchInput" onkeyup="searchOrders()" placeholder="Cari Pelanggan, Lokasi, atau Invoice..." class="w-full bg-gray-50 border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs focus:outline-none focus:border-green-500 focus:bg-white transition shadow-inner">
        </div>
    </div>

    <div class="px-3 py-3 shrink-0 flex gap-2 border-b border-gray-200 bg-white w-full">
        <button onclick="switchTab('siap')" id="btn-siap" class="tab-btn active flex-1 py-2 rounded-full font-bold text-xs bg-green-500 text-white border border-green-500 shadow-md flex items-center justify-center gap-1.5">
            <i class="bi bi-box2 hidden md:inline"></i> Siap PickUp 
            <?php if($count_siap>0) echo "<span class='bg-red-500 text-white text-[9px] px-1.5 py-0.5 rounded-full border border-red-600 shadow-sm'>$count_siap</span>"; ?>
        </button>
        <button onclick="switchTab('jalan')" id="btn-jalan" class="tab-btn flex-1 py-2 rounded-full font-bold text-xs text-gray-500 border border-gray-200 bg-white hover:bg-gray-50 flex items-center justify-center gap-1.5">
            <i class="bi bi-send hidden md:inline"></i> Diantar 
            <?php if($count_jalan>0) echo "<span class='bg-blue-500 text-white text-[9px] px-1.5 py-0.5 rounded-full border border-blue-600 shadow-sm'>$count_jalan</span>"; ?>
        </button>
        <button onclick="switchTab('riwayat')" id="btn-riwayat" class="tab-btn flex-1 py-2 rounded-full font-bold text-xs text-gray-500 border border-gray-200 bg-white hover:bg-gray-50 flex items-center justify-center gap-1.5">
            <i class="bi bi-clock-history hidden md:inline"></i> Riwayat
        </button>
    </div>

    <main class="flex-1 overflow-y-auto custom-scrollbar p-3 bg-[#f8fafc]">
        
        <div id="tab-siap" class="tab-content grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 fade-in-up">
            <?php if($count_siap > 0): while($row = mysqli_fetch_assoc($q_siap)): 
                 $d = parseData($row);
            ?>
            <div class="bg-white rounded-xl overflow-hidden flex flex-col shadow-sm border border-gray-200 order-card">
                <div class="bg-amber-50 border-b border-amber-100 p-3 flex justify-between items-start">
                    <div>
                        <h3 class="font-extrabold text-amber-800 text-sm font-mono">#<?php echo $row['no_invoice']; ?></h3>
                        <p class="text-[9px] text-amber-600/80 mt-0.5 font-medium"><i class="bi bi-box-seam"></i> Selesai packing, siap antar!</p>
                    </div>
                    <span class="bg-amber-100 text-amber-700 text-[8px] font-bold px-2 py-0.5 rounded border border-amber-200">PICK UP</span>
                </div>
                
                <div class="p-4 flex-1 bg-white space-y-3">
                    <div class="flex items-start gap-2">
                        <div class="w-6 h-6 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0 border border-blue-100"><i class="bi bi-person-fill text-[10px]"></i></div>
                        <div>
                            <p class="text-[8px] text-gray-400 uppercase font-bold leading-none">Pelanggan</p>
                            <p class="font-bold text-gray-800 text-xs leading-tight mt-0.5 customer-name"><?php echo htmlspecialchars($d['nama']); ?></p>
                            <p class="text-[10px] text-blue-600 font-mono mt-0.5"><?php echo htmlspecialchars($d['wa']); ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-2 border-t border-gray-100 pt-3">
                        <div class="w-6 h-6 rounded-full bg-red-50 text-red-500 flex items-center justify-center shrink-0 border border-red-100"><i class="bi bi-geo-alt-fill text-[10px]"></i></div>
                        <div>
                            <p class="text-[8px] text-gray-400 uppercase font-bold leading-none">Alamat Tujuan</p>
                            <p class="text-[11px] text-gray-600 leading-tight mt-0.5 customer-loc"><?php echo htmlspecialchars($d['lokasi']); ?></p>
                        </div>
                    </div>

                    <div class="bg-gray-50 p-2.5 rounded-lg border border-gray-100 mt-1">
                        <p class="text-[8px] text-gray-500 mb-1.5 font-bold uppercase">Item dibawa:</p>
                        <div class="text-[10px] text-gray-700 space-y-1 font-medium">
                            <?php 
                            if($row['items_data']) {
                                foreach(explode('||', $row['items_data']) as $item) {
                                    $it = explode(';;', $item);
                                    echo "<div class='flex gap-1.5'><span class='text-green-600 font-black'>{$it[0]}x</span> <span>{$it[1]}</span></div>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <form method="POST" class="p-3 bg-white border-t border-gray-100 mt-auto">
                    <input type="hidden" name="trx_id" value="<?php echo $row['id']; ?>">
                    <input type="hidden" name="new_status" value="delivering">
                    <button type="submit" name="update_status" class="w-full bg-amber-500 hover:bg-amber-400 text-white font-extrabold py-2.5 rounded-lg shadow-md transition active:scale-95 text-xs uppercase tracking-wider flex justify-center items-center gap-1.5">
                        <i class="bi bi-scooter text-sm"></i> Pick Up & Antar
                    </button>
                </form>
            </div>
            <?php endwhile; else: ?>
                <div class="col-span-full flex flex-col items-center justify-center py-16 opacity-50">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3 text-gray-400">
                        <i class="bi bi-box2 text-3xl"></i>
                    </div>
                    <p class="text-gray-500 text-xs font-medium">Belum ada paket siap diantar.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-jalan" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 gap-4 fade-in-up">
            <?php if($count_jalan > 0): while($row = mysqli_fetch_assoc($q_jalan)): 
                 $d = parseData($row);
            ?>
            <div class="bg-white rounded-xl overflow-hidden flex flex-col shadow-sm border border-blue-200 order-card">
                <div class="bg-blue-50 border-b border-blue-100 p-3 flex justify-between items-center">
                    <div>
                        <h3 class="font-extrabold text-blue-800 text-sm font-mono">#<?php echo $row['no_invoice']; ?></h3>
                        <p class="text-[9px] text-blue-600 mt-0.5 font-medium flex items-center gap-1"><span class="animate-pulse inline-block w-1.5 h-1.5 bg-green-500 rounded-full"></span> Otw ke lokasi...</p>
                    </div>
                    <a href="https://maps.google.com/?q=<?php echo urlencode($d['lokasi']); ?>" target="_blank" class="bg-white hover:bg-blue-100 transition text-blue-600 text-[9px] font-bold px-2 py-1.5 rounded border border-blue-200 flex items-center gap-1 shadow-sm">
                        <i class="bi bi-map"></i> Maps
                    </a>
                </div>
                
                <div class="flex flex-col md:flex-row gap-0 p-0 bg-white relative z-10 flex-1">
                    <div class="flex-1 space-y-3 p-4">
                        <div>
                            <p class="text-[8px] text-gray-400 uppercase font-bold leading-none">Pelanggan</p>
                            <p class="font-bold text-gray-800 text-xs leading-tight mt-1 flex items-center justify-between customer-name">
                                <?php echo htmlspecialchars($d['nama']); ?> 
                                <a href="https://wa.me/<?php echo htmlspecialchars($d['wa']); ?>" target="_blank" class="text-[9px] bg-green-50 text-green-600 border border-green-200 px-1.5 py-0.5 rounded hover:bg-green-500 hover:text-white transition"><i class="bi bi-whatsapp"></i> Chat</a>
                            </p>
                        </div>
                        <div class="border-t border-gray-100 pt-3">
                            <p class="text-[8px] text-gray-400 uppercase font-bold leading-none">Alamat Tujuan</p>
                            <p class="text-[11px] text-gray-600 leading-tight mt-1 customer-loc"><?php echo htmlspecialchars($d['lokasi']); ?></p>
                        </div>
                    </div>
                    
                    <div class="w-full md:w-36 bg-gray-50 border-t md:border-t-0 md:border-l border-gray-100 p-3 flex flex-col justify-center items-center shrink-0">
                        <p class="text-[8px] text-gray-500 uppercase font-bold text-center">Tagihan</p>
                        <p class="text-lg font-black text-green-600 mt-0.5 leading-none">Rp <?php echo number_format($row['total_amount']); ?></p>
                        <p class="text-[9px] font-bold px-1.5 py-0.5 bg-white border border-gray-200 rounded text-gray-600 mt-1.5"><?php echo strtoupper($row['payment_method']); ?></p>
                        <?php if($row['payment_method'] == 'cash'): ?>
                            <p class="text-[8px] text-red-500 mt-1 font-bold italic">*Tagih Tunai</p>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" class="p-3 bg-white border-t border-gray-100 mt-auto flex flex-col md:flex-row gap-2">
                    <input type="hidden" name="trx_id" value="<?php echo $row['id']; ?>">
                    <input type="hidden" name="new_status" value="completed">
                    <button type="submit" name="update_status" class="flex-1 bg-green-600 hover:bg-green-500 text-white font-extrabold py-2.5 rounded-lg shadow-md transition active:scale-95 text-xs flex justify-center items-center gap-1.5">
                        <i class="bi bi-check-circle-fill text-sm"></i> Sudah Sampai
                    </button>
                    <button type="button" onclick="alert('Hubungi Kasir/Admin untuk lapor kendala')" class="w-full md:w-auto bg-white hover:bg-red-50 text-red-500 font-bold py-2 rounded-lg transition text-[10px] border border-red-200 px-3 flex justify-center items-center gap-1">
                        <i class="bi bi-exclamation-triangle"></i> Kendala
                    </button>
                </form>
            </div>
            <?php endwhile; else: ?>
                <div class="col-span-full flex flex-col items-center justify-center py-16 opacity-50">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3 text-gray-400">
                        <i class="bi bi-scooter text-3xl"></i>
                    </div>
                    <p class="text-gray-500 text-xs font-medium">Belum ada pesanan yang dibawa.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-riwayat" class="tab-content hidden fade-in-up">
            <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-200">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-500 text-[8px] uppercase border-b border-gray-200">
                        <tr>
                            <th class="p-3 font-bold tracking-wider">Pelanggan & Tujuan</th>
                            <th class="p-3 font-bold tracking-wider">Tagihan</th>
                            <th class="p-3 font-bold tracking-wider text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php while($row = mysqli_fetch_assoc($q_riwayat)): 
                            $d = parseData($row);
                            // PERBAIKAN WARNING: Pakai created_at karena tabel transactions belum punya updated_at
                            $waktu_selesai = date('d M, H:i', strtotime($row['created_at'])); 
                        ?>
                        <tr class="hover:bg-gray-50 transition order-row">
                            <td class="p-3">
                                <div class="font-bold text-gray-800 text-xs customer-name"><?php echo htmlspecialchars($d['nama']); ?></div>
                                <div class="text-[9px] text-gray-500 mt-0.5 mb-1 font-mono">#<?php echo htmlspecialchars($row['no_invoice']); ?></div>
                                <div class="text-[8px] text-gray-400 truncate max-w-[150px] customer-loc" title="<?php echo htmlspecialchars($d['lokasi']); ?>"><?php echo htmlspecialchars($d['lokasi']); ?></div>
                            </td>
                            <td class="p-3">
                                <div class="font-mono font-bold text-green-600">Rp <?php echo number_format($row['total_amount']); ?></div>
                                <div class="text-[8px] text-gray-400 mt-0.5"><?php echo $waktu_selesai; ?></div>
                            </td>
                            <td class="p-3 text-right">
                                <span class="px-2 py-1 rounded text-[8px] font-bold bg-green-100 text-green-700 border border-green-200 inline-flex items-center gap-1 shadow-sm"><i class="bi bi-check2-all"></i> Delivered</span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        // Fungsi pindah tab
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('active', 'bg-green-500', 'text-white', 'border-green-500', 'shadow-md');
                el.classList.add('text-gray-500', 'bg-white', 'border-gray-200');
            });
            
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            
            const btn = document.getElementById('btn-' + tabId);
            btn.classList.remove('text-gray-500', 'bg-white', 'border-gray-200');
            btn.classList.add('active', 'bg-green-500', 'text-white', 'border-green-500', 'shadow-md');
        }

        // --- FUNGSI PENCARIAN LIVE ---
        function searchOrders() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            
            // Filter Kartu (Tab Siap & Jalan)
            let cards = document.querySelectorAll('.order-card');
            cards.forEach(card => {
                let text = card.innerText.toLowerCase();
                if(text.includes(input)) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });

            // Filter Baris Tabel (Tab Riwayat)
            let rows = document.querySelectorAll('.order-row');
            rows.forEach(row => {
                let text = row.innerText.toLowerCase();
                if(text.includes(input)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            });
        }

        // Auto-refresh pintar (batal refresh jika driver sedang mengetik pencarian)
        setInterval(function(){ 
            let searchVal = document.getElementById('searchInput').value;
            if(searchVal.trim() === '') {
                window.location.reload(); 
            }
        }, 30000);
    </script>
</body>
</html>