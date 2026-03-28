<?php
session_start();
include '../koneksi.php';

// 1. CEK LOGIN
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'member') { header("Location: ../login.php"); exit; }

$user_id = $_SESSION['user_id'];

// 2. AMBIL DATA KECAMATAN (DISTRICTS) UNTUK DROPDOWN PROFIL
$q_districts = mysqli_query($conn, "SELECT district_name FROM districts ORDER BY district_name ASC");
$districts = [];
if($q_districts){
    while($d = mysqli_fetch_assoc($q_districts)){
        $districts[] = $d['district_name'];
    }
}

// 3. UPDATE PROFIL
if (isset($_POST['update_profile'])) {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $username = mysqli_real_escape_string($conn, $_POST['username']); 
    $phone = mysqli_real_escape_string($conn, $_POST['phone']); 
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $district = mysqli_real_escape_string($conn, $_POST['district']); 
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $password = $_POST['password'];

    // Gabungkan kecamatan ke dalam alamat jika kecamatan diisi
    $full_address = $address;
    if(!empty($district) && strpos($address, $district) === false) {
        $full_address = $address . "\nKecamatan: " . $district;
    }

    $sql_pass = "";
    if(!empty($password)) {
        $pass_hash = md5($password);
        $sql_pass = ", password='$pass_hash'";
    }

    $query = "UPDATE users SET fullname='$fullname', username='$username', phone='$phone', email='$email', address='$full_address' $sql_pass WHERE id='$user_id'";
    
    if(mysqli_query($conn, $query)) {
        $_SESSION['fullname'] = $fullname;
        echo "<script>alert('Profil berhasil disimpan!'); window.location='index.php';</script>";
    } else {
        echo "<script>alert('Gagal update database.');</script>";
    }
}

// 4. AMBIL DATA USER
$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));
$is_google_user = !empty($user['google_id']);

// Parsing Address untuk mendapatkan District (jika ada)
$user_address_raw = $user['address'] ?? '';
$user_district_extracted = "";
$user_address_clean = $user_address_raw;

if(strpos($user_address_raw, 'Kecamatan: ') !== false) {
    $parts = explode('Kecamatan: ', $user_address_raw);
    $user_address_clean = trim($parts[0]);
    $user_district_extracted = trim($parts[1]);
}

// 5. INFO TOKO, VOUCHER & REFERRAL SETTING
$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));
$voucher_amount = $shop['reg_bonus_amount'] ?? 0;
$ref_active = $shop['referral_active'] ?? 0;
$ref_bonus = $shop['referral_bonus'] ?? 0;

// 6. STATISTIK TRANSAKSI
$cust_name = mysqli_real_escape_string($conn, $user['fullname']); 
$q_stats = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(total_amount) as total_duit, COUNT(*) as total_order FROM transactions WHERE (customer_name LIKE '%$cust_name%' OR user_id='$user_id') AND status != 'cancelled'"));

$total_belanja = $q_stats['total_duit'] ?? 0;
$total_order = $q_stats['total_order'] ?? 0;

// 7. STATISTIK REFERRAL, VOUCHER & DAFTAR TEMAN
$total_ajakan = 0;
$saldo_bonus = 0;
$ref_link = "";
$voucher_eligible = false;
$referred_users = [];

// Cek apakah punya hak voucher (Belum pernah transaksi sukses/pending)
$cek_trx = mysqli_query($conn, "SELECT id FROM transactions WHERE user_id='$user_id' AND status != 'cancelled' LIMIT 1");
if(mysqli_num_rows($cek_trx) == 0 && $voucher_amount > 0) {
    $voucher_eligible = true;
}

if($ref_active) {
    // Menghitung jumlah dan mengambil list data teman yang diundang
    $q_ref_list = mysqli_query($conn, "SELECT fullname, username, created_at FROM users WHERE referred_by='$user_id' ORDER BY created_at DESC");
    $total_ajakan = mysqli_num_rows($q_ref_list);
    
    while($r = mysqli_fetch_assoc($q_ref_list)){
        $referred_users[] = $r;
    }

    $saldo_bonus = $user['balance'] ?? 0;

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    $path = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/\\');
    $ref_link = $protocol . $domainName . $path . "/login.php?ref=" . $user_id;
}

// 8. RIWAYAT TRANSAKSI
$q_history = mysqli_query($conn, "SELECT t.*, 
    (SELECT GROUP_CONCAT(CONCAT(qty, ';;', product_name, ';;', COALESCE(variant_name, '-'), ';;', price, ';;', subtotal) SEPARATOR '||') 
     FROM transaction_details d WHERE d.transaction_id = t.id) as items_data 
    FROM transactions t 
    WHERE (t.customer_name LIKE '%$cust_name%' OR t.user_id='$user_id')
    ORDER BY t.created_at DESC LIMIT 10");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Member Area - <?php echo htmlspecialchars($shop['shop_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } } }
    </script>
    <style>
        body { background-color: #f8fafc; color: #1e293b; min-height: 100vh; padding-bottom: 90px; -webkit-tap-highlight-color: transparent; }
        
        /* BOTTOM NAV BAR */
        .bottom-nav { background: white; border-top: 1px solid #e2e8f0; padding-bottom: env(safe-area-inset-bottom); position: fixed; bottom: 0; left: 0; right: 0; z-index: 40; display: flex; justify-content: space-around; align-items: center; padding-left: 0.5rem; padding-right: 0.5rem; box-shadow: 0 -5px 15px rgba(0,0,0,0.05); }
        .nav-item { color: #94a3b8; font-size: 10px; font-weight: 700; display: flex; flex-direction: column; align-items: center; width: 100%; padding: 10px 0; transition: 0.2s; cursor: pointer; text-decoration: none;}
        .nav-item.active { color: #10b981; }
        .nav-item i { font-size: 22px; margin-bottom: 2px; }

        /* MODAL ANIMATION */
        .modal-content { transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); transform: translateY(100%); }
        .modal-open .modal-content { transform: translateY(0); }
        
        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(0,0,0,0.05); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.2); border-radius: 10px; }
    </style>
</head>
<body>

    <div class="p-6 pb-4 pt-8 max-w-4xl mx-auto bg-white shadow-sm border-b border-gray-100 rounded-b-3xl mb-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center font-bold text-2xl text-white shadow-md relative border-2 border-white">
                    <?php echo substr($user['fullname'], 0, 1); ?>
                    <?php if($is_google_user): ?>
                        <div class="absolute -bottom-1 -right-1 w-6 h-6 bg-white rounded-full flex items-center justify-center shadow-sm border border-gray-100">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="font-extrabold text-xl leading-tight text-gray-800">Halo, <?php echo explode(' ', htmlspecialchars($user['fullname']))[0]; ?>! 👋</h1>
                    <p class="text-xs font-medium text-green-600 bg-green-50 px-2 py-0.5 rounded-md inline-block mt-1">Member Priority</p>
                </div>
            </div>
            <a href="../logout.php" onclick="return confirm('Keluar akun?')" class="w-10 h-10 rounded-full bg-red-50 flex items-center justify-center text-red-500 hover:bg-red-100 transition shadow-sm">
                <i class="bi bi-power text-xl"></i>
            </a>
        </div>
    </div>

    <div class="px-4 space-y-4 max-w-4xl mx-auto">

        <?php if($voucher_eligible): ?>
        <div class="bg-gradient-to-r from-orange-500 to-amber-500 rounded-2xl p-5 relative overflow-hidden shadow-md border border-orange-600">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/20 rounded-full blur-2xl"></div>
            <div class="flex justify-between items-center relative z-10">
                <div>
                    <p class="text-[10px] text-orange-100 uppercase tracking-wider font-bold mb-1">Voucher Pengguna Baru</p>
                    <h2 class="text-2xl font-extrabold text-white">Rp <?php echo number_format($voucher_amount); ?></h2>
                    <p class="text-[10px] text-orange-50 mt-1">Siap digunakan untuk pesanan pertamamu!</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white border border-white/30 shadow-sm backdrop-blur-sm">
                    <i class="bi bi-ticket-perforated-fill text-2xl"></i>
                </div>
            </div>
            <a href="../pesan.php" class="relative z-10 mt-4 block text-center bg-white text-orange-600 font-bold text-xs py-2 rounded-xl shadow-sm hover:bg-orange-50 transition active:scale-95">Pesan & Gunakan Sekarang</a>
        </div>
        <?php endif; ?>

        <?php if($ref_active): ?>
        <div class="bg-gradient-to-r from-emerald-600 to-green-600 rounded-2xl p-5 relative overflow-hidden shadow-lg border border-green-700">
            <div class="absolute -right-6 -top-6 w-32 h-32 bg-white/10 rounded-full blur-3xl"></div>
            
            <div class="flex justify-between items-start relative z-10 mb-4">
                <div>
                    <p class="text-[10px] text-green-100 uppercase tracking-wider font-bold mb-1">Total Poin Referral</p>
                    <h2 class="text-2xl font-extrabold text-white"><?php echo number_format($saldo_bonus); ?> <span class="text-sm font-medium text-green-100">Pts</span></h2>
                    <p class="text-[10px] text-green-200 mt-1">Dapat <?php echo number_format($ref_bonus); ?> Pts tiap ajak teman</p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] text-green-100 uppercase tracking-wider font-bold mb-1">Teman Diajak</p>
                    <h2 class="text-2xl font-extrabold text-white"><?php echo $total_ajakan; ?> <span class="text-sm font-normal text-green-200">Org</span></h2>
                    <?php if($total_ajakan > 0): ?>
                        <button onclick="openRefModal()" class="text-[10px] font-bold bg-white/20 hover:bg-white/30 text-white px-2 py-1 rounded-md mt-1 transition active:scale-95 shadow-sm border border-white/20">Lihat Detail <i class="bi bi-chevron-right text-[9px]"></i></button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-black/20 rounded-xl p-3 flex items-center gap-2 border border-white/20 relative z-10 backdrop-blur-sm">
                <div class="flex-1 overflow-hidden">
                    <p class="text-[10px] text-green-100 mb-0.5">Link Referral Anda:</p>
                    <p class="text-xs text-white truncate font-mono select-all" id="refLinkText"><?php echo htmlspecialchars($ref_link); ?></p>
                </div>
                <button onclick="copyRef()" class="bg-white text-green-700 p-2 rounded-lg transition shadow-md active:scale-95 hover:bg-gray-50">
                    <i class="bi bi-copy"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white border border-gray-200 rounded-2xl p-5 relative overflow-hidden shadow-sm">
            <div class="flex justify-between items-start relative z-10">
                <div>
                    <p class="text-[10px] text-gray-500 uppercase tracking-wider font-bold mb-1">Total Transaksi</p>
                    <h2 class="text-2xl font-extrabold text-gray-800">Rp <?php echo number_format($total_belanja); ?></h2>
                    <div class="flex items-center gap-1 mt-1 bg-green-50 w-fit px-2 py-1 rounded-md text-[10px] text-green-700 font-bold border border-green-100">
                        <i class="bi bi-bag-check-fill"></i> <?php echo $total_order; ?> Pesanan Selesai
                    </div>
                </div>
                <div class="w-12 h-12 rounded-full bg-yellow-50 flex items-center justify-center text-yellow-500 border border-yellow-100 shadow-sm">
                    <i class="bi bi-trophy-fill text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <a href="../pesan.php" class="bg-white p-3 rounded-xl flex items-center gap-3 border border-gray-200 active:scale-95 transition hover:border-green-300 group shadow-sm">
                <div class="w-11 h-11 rounded-xl bg-green-50 text-green-600 flex items-center justify-center group-hover:bg-green-500 group-hover:text-white transition"><i class="bi bi-cart3 text-xl"></i></div>
                <div><h3 class="font-bold text-sm text-gray-800">Belanja</h3><p class="text-[10px] text-gray-500">Pesan Menu</p></div>
            </a>
            <button onclick="openProfile()" class="bg-white p-3 rounded-xl flex items-center gap-3 border border-gray-200 active:scale-95 transition hover:border-blue-300 group shadow-sm text-left">
                <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-500 flex items-center justify-center group-hover:bg-blue-500 group-hover:text-white transition"><i class="bi bi-person-lines-fill text-xl"></i></div>
                <div><h3 class="font-bold text-sm text-gray-800">Profil</h3><p class="text-[10px] text-gray-500">Ubah Data</p></div>
            </button>
        </div>

        <div class="pt-2">
            <div class="flex justify-between items-center mb-3 px-1">
                <h3 class="font-extrabold text-gray-800 text-lg">Riwayat Pesanan</h3>
                <span class="text-[10px] text-gray-500 bg-gray-100 px-2 py-1 rounded-md font-bold">Terakhir</span>
            </div>
            
            <div class="space-y-3 pb-8">
                <?php if(mysqli_num_rows($q_history) > 0): 
                    while($row = mysqli_fetch_assoc($q_history)): 
                        $st = $row['status'];
                        if($st=='completed' || $st=='success') {
                            $badge = "bg-green-50 text-green-600 border-green-200"; $icon = "bi-check-circle-fill"; $label = "Selesai";
                        } elseif ($st=='cancelled') {
                            $badge = "bg-red-50 text-red-500 border-red-200"; $icon = "bi-x-circle-fill"; $label = "Batal";
                        } else {
                            $badge = "bg-yellow-50 text-yellow-600 border-yellow-200"; $icon = "bi-clock-fill"; $label = "Proses";
                        }
                ?>
                <div class="bg-white rounded-2xl p-4 border border-gray-200 relative overflow-hidden transition shadow-sm">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gray-50 border border-gray-100 flex items-center justify-center text-gray-500">
                                <i class="bi bi-receipt"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-sm text-gray-800 font-mono"><?php echo $row['no_invoice']; ?></h4>
                                <p class="text-[10px] text-gray-500 font-medium"><?php echo date('d M Y • H:i', strtotime($row['created_at'])); ?></p>
                            </div>
                        </div>
                        <span class="text-[10px] px-2.5 py-1 rounded-full border <?php echo $badge; ?> font-bold flex items-center gap-1.5">
                            <i class="bi <?php echo $icon; ?>"></i> <?php echo $label; ?>
                        </span>
                    </div>
                    <div class="border-t border-dashed border-gray-200 my-3"></div>
                    <div class="flex justify-between items-end">
                        <div>
                            <p class="text-[10px] text-gray-500 mb-0.5 font-bold uppercase">Total Bayar</p>
                            <p class="font-extrabold text-base text-gray-800">Rp <?php echo number_format($row['total_amount']); ?></p>
                        </div>
                        <button onclick="openTrxDetail(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="px-4 py-2 rounded-xl bg-gray-50 hover:bg-gray-100 text-gray-700 text-xs font-bold transition border border-gray-200 flex items-center gap-1">
                            Detail <i class="bi bi-chevron-right text-[10px]"></i>
                        </button>
                    </div>
                </div>
                <?php endwhile; else: ?>
                    <div class="flex flex-col items-center justify-center py-12 text-gray-400 bg-white rounded-2xl border border-gray-200 border-dashed shadow-sm">
                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                            <i class="bi bi-bag-x text-3xl"></i>
                        </div>
                        <span class="text-sm font-medium">Belum ada riwayat pesanan.</span>
                        <a href="../pesan.php" class="mt-3 text-xs bg-green-500 text-white px-4 py-2 rounded-full font-bold shadow-md">Mulai Pesan</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <nav class="bottom-nav">
        <a href="../index.php" class="nav-item">
            <i class="bi bi-house-door-fill"></i>
            <span>Beranda</span>
        </a>
        <a href="../pesan.php" class="nav-item">
            <i class="bi bi-grid-fill"></i>
            <span>Menu</span>
        </a>
        <a href="javascript:void(0)" onclick="alert('Silakan pesan melalui menu untuk melihat keranjang.')" class="nav-item relative">
            <i class="bi bi-bag-fill"></i>
            <span>Keranjang</span>
        </a>
        <a href="index.php" class="nav-item active">
            <i class="bi bi-person-fill"></i>
            <span>Saya</span>
        </a>
    </nav>

    <div id="modalProfile" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity duration-300" onclick="closeProfile()"></div>
        <div class="absolute bottom-0 w-full max-w-md mx-auto left-0 right-0 bg-white rounded-t-3xl p-6 shadow-2xl modal-content" id="modalProfileContent">
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-6"></div>
            
            <div class="flex items-center gap-3 mb-6">
                <h3 class="text-xl font-extrabold text-gray-800">Edit Profil</h3>
                <?php if($is_google_user): ?>
                    <span class="px-2.5 py-1 rounded-full bg-blue-50 text-blue-600 border border-blue-100 text-[10px] font-bold flex items-center gap-1">
                        <i class="bi bi-google"></i> Terhubung
                    </span>
                <?php endif; ?>
            </div>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1.5 ml-1 uppercase">Nama Lengkap</label>
                    <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" class="w-full bg-gray-50 border border-gray-200 text-gray-800 px-4 py-3 rounded-xl text-sm focus:border-green-500 focus:outline-none transition">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 ml-1 uppercase">Username</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="w-full bg-gray-50 border border-gray-200 text-gray-800 px-4 py-3 rounded-xl text-sm focus:border-green-500 focus:outline-none transition" <?php echo $is_google_user ? 'readonly' : ''; ?>>
                        <p class="text-[9px] text-gray-400 mt-1 ml-1 leading-tight">*Digunakan untuk login manual.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5 ml-1 uppercase">No. WhatsApp</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full bg-gray-50 border border-gray-200 text-gray-800 px-4 py-3 rounded-xl text-sm focus:border-green-500 focus:outline-none transition" placeholder="08xxx">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1.5 ml-1 uppercase">Kecamatan Domisili</label>
                    <div class="relative">
                        <select name="district" class="w-full bg-gray-50 border border-gray-200 text-gray-800 px-4 py-3 rounded-xl text-sm focus:border-green-500 focus:outline-none transition appearance-none">
                            <option value="">-- Pilih Jika Tersedia --</option>
                            <?php foreach($districts as $d): ?>
                                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($user_district_extracted == $d) ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="bi bi-chevron-down absolute right-4 top-3.5 text-gray-400 pointer-events-none"></i>
                    </div>
                    <p class="text-[9px] text-gray-400 mt-1 ml-1 leading-tight">*Biarkan kosong jika area Anda belum terdaftar.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1.5 ml-1 uppercase">Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="w-full bg-gray-50 border border-gray-200 text-gray-800 px-4 py-3 rounded-xl text-sm focus:border-green-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1.5 ml-1 uppercase">Alamat Detail (Jalan, RT/RW)</label>
                    <textarea name="address" rows="2" class="w-full bg-gray-50 border border-gray-200 text-gray-800 px-4 py-3 rounded-xl text-sm focus:border-green-500 focus:outline-none transition" placeholder="Patokan detail rumah Anda..."><?php echo htmlspecialchars($user_address_clean); ?></textarea>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1.5 ml-1 uppercase">Password Baru</label>
                    <input type="password" name="password" class="w-full bg-gray-50 border border-gray-200 text-gray-800 px-4 py-3 rounded-xl text-sm focus:border-green-500 focus:outline-none transition" placeholder="(Opsional) Biarkan kosong jika tidak ubah">
                </div>
                
                <button type="submit" name="update_profile" class="w-full py-3.5 bg-green-600 hover:bg-green-700 text-white font-bold rounded-xl shadow-lg shadow-green-500/30 mt-6 flex items-center justify-center gap-2 transition active:scale-95">
                    <i class="bi bi-floppy"></i> Simpan Perubahan
                </button>
            </form>
            <div class="h-6"></div>
        </div>
    </div>

    <div id="modalReferral" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity duration-300" onclick="closeRefModal()"></div>
        <div class="absolute bottom-0 w-full max-w-md mx-auto left-0 right-0 bg-white rounded-t-3xl p-6 shadow-2xl modal-content" id="modalReferralContent">
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-6"></div>
            
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-extrabold text-gray-800">Daftar Teman</h3>
                <span class="bg-green-100 text-green-700 text-xs font-bold px-3 py-1.5 rounded-lg border border-green-200"><?php echo $total_ajakan; ?> Orang</span>
            </div>

            <div class="max-h-72 overflow-y-auto custom-scrollbar space-y-3 pr-2 mb-6">
                <?php if(count($referred_users) > 0): foreach($referred_users as $ru): ?>
                    <div class="flex items-center gap-3 border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                        <div class="w-11 h-11 rounded-full bg-gradient-to-br from-green-50 to-emerald-100 text-green-600 border border-green-200 flex items-center justify-center font-bold text-lg shrink-0 shadow-sm">
                            <?php echo substr($ru['fullname'], 0, 1); ?>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($ru['fullname']); ?></div>
                            <div class="text-[10px] text-gray-500 mt-0.5">
                                <span class="text-indigo-500 font-medium">@<?php echo htmlspecialchars($ru['username']); ?></span> • Gabung: <?php echo date('d M Y', strtotime($ru['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; else: ?>
                    <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                        <i class="bi bi-people text-4xl mb-2 text-gray-300"></i>
                        <span class="text-xs">Belum ada teman yang diundang.</span>
                    </div>
                <?php endif; ?>
            </div>

            <button onclick="closeRefModal()" class="w-full py-3.5 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold rounded-xl transition border border-gray-200 active:scale-95">Tutup</button>
            <div class="h-4"></div>
        </div>
    </div>

    <div id="modalTrx" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity duration-300" onclick="closeTrxDetail()"></div>
        <div class="absolute bottom-0 w-full max-w-md mx-auto left-0 right-0 bg-white rounded-t-3xl p-6 shadow-2xl modal-content" id="modalTrxContent">
            
            <div class="w-12 h-1.5 bg-gray-200 rounded-full mx-auto mb-6"></div>
            
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-xl font-extrabold text-gray-800">Detail Pesanan</h3>
                    <p class="text-xs text-gray-500 font-mono mt-1 font-bold" id="t-invoice"></p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold border" id="t-status"></span>
            </div>

            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 mb-4 max-h-60 overflow-y-auto custom-scrollbar" id="t-items">
            </div>

            <div class="flex justify-between items-center border-t border-gray-200 pt-4 mb-6">
                <span class="text-gray-500 text-sm font-bold uppercase">Total Bayar</span>
                <span class="text-2xl font-extrabold text-green-600" id="t-total"></span>
            </div>

            <button onclick="closeTrxDetail()" class="w-full py-3.5 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold rounded-xl transition border border-gray-200 active:scale-95">Tutup</button>
            <div class="h-4"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // --- COPY REFERRAL LINK ---
        function copyRef() {
            var copyText = document.getElementById("refLinkText").innerText;
            navigator.clipboard.writeText(copyText).then(function() {
                Swal.fire({ toast: true, position: 'top', icon: 'success', title: 'Link disalin!', showConfirmButton: false, timer: 1500 });
            }, function(err) { console.error('Async: Could not copy text: ', err); });
        }

        // --- PROFILE MODAL ---
        function openProfile() {
            document.getElementById('modalProfile').classList.remove('hidden');
            setTimeout(() => { document.getElementById('modalProfile').classList.add('modal-open'); }, 10);
        }
        function closeProfile() {
            document.getElementById('modalProfile').classList.remove('modal-open');
            setTimeout(() => { document.getElementById('modalProfile').classList.add('hidden'); }, 300);
        }

        // --- REFERRAL (DAFTAR TEMAN) MODAL ---
        function openRefModal() {
            document.getElementById('modalReferral').classList.remove('hidden');
            setTimeout(() => { document.getElementById('modalReferral').classList.add('modal-open'); }, 10);
        }
        function closeRefModal() {
            document.getElementById('modalReferral').classList.remove('modal-open');
            setTimeout(() => { document.getElementById('modalReferral').classList.add('hidden'); }, 300);
        }

        // --- TRANSACTION DETAIL MODAL ---
        function openTrxDetail(data) {
            document.getElementById('modalTrx').classList.remove('hidden');
            setTimeout(() => { document.getElementById('modalTrx').classList.add('modal-open'); }, 10);

            document.getElementById('t-invoice').innerText = data.no_invoice;
            
            let totalBayar = parseInt(data.total_amount);
            document.getElementById('t-total').innerText = totalBayar === 0 ? "Rp 0 (Voucher)" : "Rp " + totalBayar.toLocaleString('id-ID');
            
            const statusEl = document.getElementById('t-status');
            statusEl.innerText = data.status.toUpperCase();
            if(data.status == 'completed' || data.status == 'success') statusEl.className = "px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-green-600 border-green-200";
            else if(data.status == 'pending') statusEl.className = "px-3 py-1 rounded-full text-xs font-bold bg-yellow-50 text-yellow-600 border-yellow-200";
            else statusEl.className = "px-3 py-1 rounded-full text-xs font-bold bg-red-50 text-red-500 border-red-200";

            const itemsContainer = document.getElementById('t-items');
            itemsContainer.innerHTML = '';
            if(data.items_data) {
                const items = data.items_data.split('||');
                items.forEach(itemStr => {
                    const parts = itemStr.split(';;');
                    const qty = parts[0]; const name = parts[1];
                    const variant = parts[2] !== '-' ? `+ ${parts[2]}` : '';
                    const subtotal = parseInt(parts[4]).toLocaleString('id-ID');
                    
                    itemsContainer.innerHTML += `
                        <div class="flex justify-between items-start border-b border-gray-200 last:border-0 pb-3 mb-3 last:mb-0 last:pb-0">
                            <div>
                                <div class="text-sm text-gray-800 font-bold">${name} <span class="text-xs text-green-600 bg-green-50 px-1.5 py-0.5 rounded ml-1">x${qty}</span></div>
                                <div class="text-[10px] text-gray-500 italic mt-0.5">${variant}</div>
                            </div>
                            <div class="text-sm text-gray-800 font-mono font-bold">Rp ${subtotal}</div>
                        </div>`;
                });
            } else { itemsContainer.innerHTML = '<div class="text-center text-gray-500 text-xs">Item data unavailable</div>'; }
        }

        function closeTrxDetail() {
            document.getElementById('modalTrx').classList.remove('modal-open');
            setTimeout(() => { document.getElementById('modalTrx').classList.add('hidden'); }, 300);
        }
    </script>
</body>
</html>