<?php
session_start();
include '../koneksi.php';

// Cek Login Role Dapur
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'dapur') {
    header("Location: ../login.php");
    exit;
}

// Proses Update Status oleh Dapur
if(isset($_POST['update_status'])) {
    $trx_id = $_POST['trx_id'];
    $new_status = $_POST['new_status'];
    mysqli_query($conn, "UPDATE transactions SET status='$new_status' WHERE id='$trx_id'");
    header("Location: index.php"); exit;
}

// Query mengambil data pesanan beserta detail itemnya
$query_base = "SELECT t.*, 
    (SELECT GROUP_CONCAT(CONCAT(qty, ';;', product_name, ';;', COALESCE(variant_name, '-')) SEPARATOR '||') 
     FROM transaction_details d WHERE d.transaction_id = t.id) as items_data 
    FROM transactions t ";

$q_masuk = mysqli_query($conn, $query_base . "WHERE t.status IN ('pending', 'success') ORDER BY t.id ASC");
$q_proses = mysqli_query($conn, $query_base . "WHERE t.status = 'preparing' ORDER BY t.id ASC");
$q_riwayat = mysqli_query($conn, $query_base . "WHERE t.status IN ('ready', 'delivering', 'completed') ORDER BY t.id DESC LIMIT 30");

// Hitung jumlah untuk badge
$count_masuk = mysqli_num_rows($q_masuk);
$count_proses = mysqli_num_rows($q_proses);

// Fungsi Parsing Nama & Lokasi agar Rapi
function parseData($row) {
    $raw_loc = !empty($row['location']) ? $row['location'] : $row['customer_name'];
    $lokasi = $raw_loc;
    if (preg_match('/\((Alamat:.*?)\)/', $raw_loc, $matches)) {
        $lokasi = str_replace("Alamat: ", "", $matches[1]);
    } else {
        $lokasi = str_replace("Alamat: ", "", $lokasi);
    }
    
    // Hilangkan kurung dari nama customer
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
    <title>Penyiapan Pesanan</title>
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
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="flex flex-col h-screen overflow-hidden text-sm">

    <header class="bg-white border-b border-gray-200 px-4 py-3 flex justify-between items-center shrink-0 shadow-sm sticky top-0 z-50">
        <div>
            <h1 class="text-base font-extrabold text-green-700 flex items-center gap-1.5"><i class="bi bi-box-seam"></i> Penyiapan</h1>
            <p class="text-[9px] text-gray-500 font-medium">Semangat, <?php echo htmlspecialchars($_SESSION['fullname']); ?>!</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-center hidden md:block px-3 py-1 bg-gray-50 rounded-lg border border-gray-100">
                <div id="clock" class="text-sm font-mono font-bold text-gray-800 tracking-tight">00:00:00</div>
            </div>
            <a href="../logout.php" onclick="return confirm('Keluar dari sistem?')" class="bg-white text-red-500 border border-red-200 hover:bg-red-50 transition p-2 rounded-lg text-lg flex items-center justify-center shadow-sm">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </header>

    <div class="px-3 py-2 bg-white border-b border-gray-100 shrink-0">
        <div class="relative">
            <i class="bi bi-search absolute left-3 top-2.5 text-gray-400 text-xs"></i>
            <input type="text" id="searchInput" onkeyup="searchOrders()" placeholder="Cari No. Pesanan atau Nama..." class="w-full bg-gray-50 border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs focus:outline-none focus:border-green-500 focus:bg-white transition shadow-inner">
        </div>
    </div>

    <div class="px-3 py-3 shrink-0 flex gap-2 border-b border-gray-200 bg-white w-full">
        <button onclick="switchTab('masuk')" id="btn-masuk" class="tab-btn active flex-1 py-2 rounded-full font-bold text-xs bg-green-500 text-white border border-green-500 shadow-md flex items-center justify-center gap-1.5">
            <i class="bi bi-inbox hidden md:inline"></i> Masuk 
            <?php if($count_masuk>0) echo "<span class='bg-red-500 text-white text-[9px] px-1.5 py-0.5 rounded-full border border-red-600'>$count_masuk</span>"; ?>
        </button>
        <button onclick="switchTab('proses')" id="btn-proses" class="tab-btn flex-1 py-2 rounded-full font-bold text-xs text-gray-500 border border-gray-200 bg-white hover:bg-gray-50 flex items-center justify-center gap-1.5">
            <i class="bi bi-fire hidden md:inline"></i> Proses 
            <?php if($count_proses>0) echo "<span class='bg-orange-500 text-white text-[9px] px-1.5 py-0.5 rounded-full border border-orange-600'>$count_proses</span>"; ?>
        </button>
        <button onclick="switchTab('riwayat')" id="btn-riwayat" class="tab-btn flex-1 py-2 rounded-full font-bold text-xs text-gray-500 border border-gray-200 bg-white hover:bg-gray-50 flex items-center justify-center gap-1.5">
            <i class="bi bi-check2-all hidden md:inline"></i> Riwayat
        </button>
    </div>

    <main class="flex-1 overflow-y-auto custom-scrollbar p-3 bg-[#f8fafc]">
        
        <div id="tab-masuk" class="tab-content grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 fade-in-up">
            <?php if($count_masuk > 0): while($row = mysqli_fetch_assoc($q_masuk)): 
                $is_delivery = (strpos($row['location'] ?? '', 'Alamat:') !== false) || (strpos($row['customer_name'], 'Alamat:') !== false);
                $d = parseData($row);
            ?>
            <div class="bg-white rounded-xl overflow-hidden flex flex-col shadow-sm border border-gray-200 order-card">
                <div class="bg-red-50 border-b border-red-100 p-3 flex justify-between items-start">
                    <div>
                        <h3 class="font-extrabold text-red-700 text-sm font-mono">#<?php echo $row['no_invoice']; ?></h3>
                        <p class="text-[9px] text-red-500/80 mt-0.5 font-medium"><i class="bi bi-clock"></i> Masuk: <?php echo date('H:i', strtotime($row['created_at'])); ?></p>
                    </div>
                    <?php if($is_delivery): ?>
                        <span class="bg-indigo-100 text-indigo-700 text-[9px] font-bold px-2 py-0.5 rounded border border-indigo-200 flex items-center gap-1"><i class="bi bi-truck"></i> DELIVERY</span>
                    <?php else: ?>
                        <span class="bg-emerald-100 text-emerald-700 text-[9px] font-bold px-2 py-0.5 rounded border border-emerald-200 flex items-center gap-1"><i class="bi bi-shop"></i> PICKUP</span>
                    <?php endif; ?>
                </div>
                
                <div class="p-4 flex-1 bg-white">
                    <p class="text-[9px] text-gray-400 font-bold mb-2 uppercase tracking-wider flex items-center gap-1"><i class="bi bi-card-checklist"></i> Item</p>
                    <div class="space-y-2">
                        <?php 
                        if($row['items_data']) {
                            foreach(explode('||', $row['items_data']) as $item) {
                                $it = explode(';;', $item);
                                echo "<div class='flex gap-2 bg-gray-50 p-2 rounded-lg border border-gray-100 items-start'>";
                                echo "<div class='w-6 h-6 shrink-0 bg-white border border-gray-200 text-green-600 rounded flex items-center justify-center font-black text-[10px] shadow-sm'>{$it[0]}x</div>";
                                echo "<div><p class='text-xs font-bold text-gray-800 leading-tight'>{$it[1]}</p>";
                                if($it[2] != '-') echo "<p class='text-[9px] text-gray-500 mt-0.5 font-medium bg-gray-200/50 inline-block px-1.5 py-0.5 rounded'>+ {$it[2]}</p>";
                                echo "</div></div>";
                            }
                        }
                        ?>
                    </div>
                    
                    <div class="mt-4 pt-3 border-t border-gray-100 bg-gray-50/50 -mx-4 px-4 -mb-4 pb-4 space-y-2">
                        <div class="flex items-start gap-2">
                            <div class="w-5 h-5 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center shrink-0 border border-blue-100"><i class="bi bi-person-fill text-[9px]"></i></div>
                            <div>
                                <p class="text-[8px] text-gray-400 uppercase font-bold leading-none">Pelanggan</p>
                                <p class="font-bold text-gray-800 text-xs leading-none mt-1 customer-name"><?php echo htmlspecialchars($d['nama']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <div class="w-5 h-5 rounded-full bg-red-50 text-red-500 flex items-center justify-center shrink-0 border border-red-100"><i class="bi bi-geo-alt-fill text-[9px]"></i></div>
                            <div>
                                <p class="text-[8px] text-gray-400 uppercase font-bold leading-none">Tujuan/Catatan</p>
                                <p class="text-[11px] text-gray-600 leading-tight mt-1"><?php echo htmlspecialchars($d['lokasi']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form method="POST" class="p-3 bg-white border-t border-gray-100 mt-auto">
                    <input type="hidden" name="trx_id" value="<?php echo $row['id']; ?>">
                    <input type="hidden" name="new_status" value="preparing">
                    <button type="submit" name="update_status" class="w-full bg-green-600 hover:bg-green-500 text-white font-bold py-2.5 rounded-lg shadow-md transition active:scale-95 text-xs flex justify-center items-center gap-1.5">
                        <i class="bi bi-box-seam"></i> Mulai Siapkan / Masak
                    </button>
                </form>
            </div>
            <?php endwhile; else: ?>
                <div class="col-span-full flex flex-col items-center justify-center py-16 opacity-50">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3 text-gray-400">
                        <i class="bi bi-inbox text-3xl"></i>
                    </div>
                    <p class="text-gray-500 text-xs font-medium">Belum ada pesanan masuk.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-proses" class="tab-content hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 fade-in-up">
            <?php if($count_proses > 0): while($row = mysqli_fetch_assoc($q_proses)): 
                $is_delivery = (strpos($row['location'] ?? '', 'Alamat:') !== false) || (strpos($row['customer_name'], 'Alamat:') !== false);
                $d = parseData($row);
            ?>
            <div class="bg-white rounded-xl overflow-hidden flex flex-col shadow-sm border border-orange-200 order-card">
                <div class="bg-gradient-to-r from-orange-50 to-amber-50 border-b border-orange-100 p-3 flex justify-between items-start">
                    <div>
                        <h3 class="font-extrabold text-orange-800 text-sm font-mono">#<?php echo $row['no_invoice']; ?></h3>
                        <p class="text-[9px] text-orange-600/80 mt-0.5 font-medium"><i class="bi bi-stopwatch animate-pulse"></i> Sedang disiapkan...</p>
                    </div>
                    <?php if($is_delivery): ?>
                        <span class="bg-indigo-100 text-indigo-700 text-[9px] font-bold px-2 py-0.5 rounded border border-indigo-200 flex items-center gap-1"><i class="bi bi-truck"></i> DELIVERY</span>
                    <?php else: ?>
                        <span class="bg-emerald-100 text-emerald-700 text-[9px] font-bold px-2 py-0.5 rounded border border-emerald-200 flex items-center gap-1"><i class="bi bi-shop"></i> PICKUP</span>
                    <?php endif; ?>
                </div>
                
                <div class="p-4 flex-1 bg-white">
                    <div class="space-y-2">
                        <?php 
                        if($row['items_data']) {
                            foreach(explode('||', $row['items_data']) as $item) {
                                $it = explode(';;', $item);
                                echo "<div class='flex gap-2 bg-gray-50 p-2 rounded-lg border border-gray-100 items-start'>";
                                echo "<div class='w-6 h-6 shrink-0 bg-white border border-gray-200 text-orange-600 rounded flex items-center justify-center font-black text-[10px] shadow-sm'>{$it[0]}x</div>";
                                echo "<div><p class='text-xs font-bold text-gray-800 leading-tight'>{$it[1]}</p>";
                                if($it[2] != '-') echo "<p class='text-[9px] text-gray-500 mt-0.5 font-medium bg-gray-200/50 inline-block px-1.5 py-0.5 rounded'>+ {$it[2]}</p>";
                                echo "</div></div>";
                            }
                        }
                        ?>
                    </div>
                    <div class="mt-3 pt-2 border-t border-gray-100 text-[10px] text-gray-500">
                        Pelanggan: <strong class="text-gray-800 customer-name"><?php echo htmlspecialchars($d['nama']); ?></strong>
                    </div>
                </div>
                
                <form method="POST" class="p-3 bg-white border-t border-gray-100 mt-auto">
                    <input type="hidden" name="trx_id" value="<?php echo $row['id']; ?>">
                    <input type="hidden" name="new_status" value="ready">
                    <button type="submit" name="update_status" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-2.5 rounded-lg shadow-md transition active:scale-95 text-xs flex justify-center items-center gap-1.5">
                        <i class="bi bi-check2-circle text-sm"></i> Tandai Siap Diambil
                    </button>
                </form>
            </div>
            <?php endwhile; else: ?>
                <div class="col-span-full flex flex-col items-center justify-center py-16 opacity-50">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-3 text-gray-400">
                        <i class="bi bi-emoji-smile text-3xl"></i>
                    </div>
                    <p class="text-gray-500 text-xs font-medium">Tidak ada pesanan yang diproses.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="tab-riwayat" class="tab-content hidden fade-in-up">
            <div class="bg-white rounded-xl overflow-hidden shadow-sm border border-gray-200">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-500 text-[9px] uppercase border-b border-gray-200">
                        <tr>
                            <th class="p-3 font-bold tracking-wider">No. Pesanan</th>
                            <th class="p-3 font-bold tracking-wider">Pelanggan</th>
                            <th class="p-3 font-bold tracking-wider text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="historyTableBody">
                        <?php while($row = mysqli_fetch_assoc($q_riwayat)): 
                            $is_delivery = (strpos($row['location'] ?? '', 'Alamat:') !== false) || (strpos($row['customer_name'], 'Alamat:') !== false);
                            $d = parseData($row);
                            
                            // Style Badge Status
                            if($row['status'] == 'ready') {
                                $badge = "bg-blue-100 text-blue-700 border border-blue-200";
                                $status_text = "Menunggu Driver";
                            } elseif($row['status'] == 'delivering') {
                                $badge = "bg-purple-100 text-purple-700 border border-purple-200";
                                $status_text = "Dibawa Driver";
                            } else {
                                $badge = "bg-emerald-100 text-emerald-700 border border-emerald-200";
                                $status_text = "Selesai";
                            }
                        ?>
                        <tr class="hover:bg-gray-50 transition order-row">
                            <td class="p-3">
                                <div class="font-mono font-bold text-gray-800"><?php echo htmlspecialchars($row['no_invoice']); ?></div>
                                <div class="text-[9px] text-gray-400 font-sans mt-0.5"><?php echo date('d M, H:i', strtotime($row['created_at'])); ?></div>
                            </td>
                            <td class="p-3 text-gray-700 font-medium">
                                <span class="customer-name"><?php echo htmlspecialchars($d['nama']); ?></span>
                                <div class="text-[8px] text-gray-400 mt-0.5 max-w-[120px] truncate" title="<?php echo htmlspecialchars($d['lokasi']); ?>"><?php echo htmlspecialchars($d['lokasi']); ?></div>
                            </td>
                            <td class="p-3 text-right">
                                <span class="px-2 py-1 rounded text-[8px] font-bold shadow-sm inline-block <?php echo $badge; ?>"><?php echo strtoupper($status_text); ?></span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <script>
        // Update jam real-time
        setInterval(() => {
            const now = new Date();
            document.getElementById('clock').innerText = now.toLocaleTimeString('en-US', { hour12: false });
        }, 1000);

        // Fungsi pindah tab
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            
            // Reset semua tombol
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('active', 'bg-green-500', 'text-white', 'border-green-500', 'shadow-md');
                el.classList.add('text-gray-500', 'bg-white', 'border-gray-200');
            });
            
            // Tampilkan tab yang dipilih
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            
            // Aktifkan tombol
            const btn = document.getElementById('btn-' + tabId);
            btn.classList.remove('text-gray-500', 'bg-white', 'border-gray-200');
            btn.classList.add('active', 'bg-green-500', 'text-white', 'border-green-500', 'shadow-md');
        }

        // --- FUNGSI PENCARIAN LIVE ---
        function searchOrders() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            
            // Filter Kartu (Tab Masuk & Proses)
            let cards = document.querySelectorAll('.order-card');
            cards.forEach(card => {
                // Mencari teks di dalam kartu (termasuk invoice dan nama)
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

        // Auto-refresh halaman setiap 30 detik (DIPINTARKAN)
        // Jika kotak pencarian sedang diisi, auto-refresh DIBATALKAN agar ketikan tidak hilang
        setInterval(function(){ 
            let searchVal = document.getElementById('searchInput').value;
            if(searchVal.trim() === '') {
                window.location.reload(); 
            }
        }, 30000);
    </script>
</body>
</html>