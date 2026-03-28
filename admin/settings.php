<?php
include '../koneksi.php';

$alert_title = ""; $alert_msg = ""; $alert_type = "";

// --- PROSES: SIMPAN / UPDATE KECAMATAN (DISTRICT) ---
if (isset($_POST['save_district'])) {
    $id = isset($_POST['district_id']) ? intval($_POST['district_id']) : 0;
    $district_name = mysqli_real_escape_string($conn, $_POST['district_name']);
    $delivery_fee = intval($_POST['delivery_fee']);
    
    if (!empty($district_name)) {
        if($id > 0) {
            mysqli_query($conn, "UPDATE districts SET district_name='$district_name', delivery_fee='$delivery_fee' WHERE id='$id'");
            $alert_type = "success"; $alert_title = "Berhasil"; $alert_msg = "Data kecamatan diperbarui.";
        } else {
            mysqli_query($conn, "INSERT INTO districts (district_name, delivery_fee) VALUES ('$district_name', '$delivery_fee')");
            $alert_type = "success"; $alert_title = "Berhasil"; $alert_msg = "Kecamatan baru ditambahkan.";
        }
    }
}

// --- PROSES: HAPUS KECAMATAN ---
if (isset($_GET['del_district'])) {
    $id = intval($_GET['del_district']);
    mysqli_query($conn, "DELETE FROM districts WHERE id='$id'");
    header("Location: settings.php?tab=pengiriman"); exit;
}

// --- PROSES: TAMBAH BANNER SLIDER ---
if (isset($_POST['upload_banner'])) {
    if (!empty($_FILES['banner_files']['name'][0])) {
        $total = count($_FILES['banner_files']['name']);
        if (!file_exists("../assets/images/banners/")) mkdir("../assets/images/banners/", 0777, true);
        
        for($i=0; $i<$total; $i++) {
            $tmp = $_FILES['banner_files']['tmp_name'][$i];
            if ($tmp != "") {
                $newname = "banner_".time()."_".$i.".jpg";
                if(move_uploaded_file($tmp, "../assets/images/banners/".$newname)){
                    $path = "assets/images/banners/".$newname;
                    mysqli_query($conn, "INSERT INTO shop_banners (image) VALUES ('$path')");
                }
            }
        }
        $alert_type="success"; $alert_title="Berhasil"; $alert_msg="Banner berhasil ditambahkan";
    }
}

// --- PROSES: HAPUS BANNER ---
if (isset($_GET['del_banner'])) {
    $id = intval($_GET['del_banner']);
    $img = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM shop_banners WHERE id='$id'"));
    if($img && file_exists("../".$img['image'])) unlink("../".$img['image']);
    mysqli_query($conn, "DELETE FROM shop_banners WHERE id='$id'");
    header("Location: settings.php?tab=tampilan"); exit;
}

// --- PROSES: SIMPAN / UPDATE KARTU MENU ---
if (isset($_POST['save_card'])) {
    $id = isset($_POST['card_id']) ? mysqli_real_escape_string($conn, $_POST['card_id']) : '';
    $title = mysqli_real_escape_string($conn, $_POST['card_title']);
    $icon = mysqli_real_escape_string($conn, $_POST['card_icon']);
    $link = mysqli_real_escape_string($conn, $_POST['card_link']);
    
    if(!empty($id)) {
        mysqli_query($conn, "UPDATE home_cards SET title='$title', icon='$icon', link='$link' WHERE id='$id'");
        $alert_type="success"; $alert_title="Berhasil"; $alert_msg="Kartu menu diperbarui";
    } else {
        mysqli_query($conn, "INSERT INTO home_cards (title, icon, link) VALUES ('$title', '$icon', '$link')");
        $alert_type="success"; $alert_title="Berhasil"; $alert_msg="Kartu menu ditambahkan";
    }
}

// --- PROSES: HAPUS KARTU ---
if (isset($_GET['del_card'])) {
    $id = intval($_GET['del_card']);
    mysqli_query($conn, "DELETE FROM home_cards WHERE id='$id'");
    header("Location: settings.php?tab=tampilan"); exit;
}

// --- PROSES: SIMPAN PENGATURAN UTAMA ---
if (isset($_POST['simpan_settings'])) {
    $name = mysqli_real_escape_string($conn, $_POST['shop_name']);
    $show_name = isset($_POST['show_shop_name']) ? 1 : 0; 
    $wa = mysqli_real_escape_string($conn, $_POST['wa_number']);
    $rtext = mysqli_real_escape_string($conn, $_POST['running_text']);
    $stext = mysqli_real_escape_string($conn, $_POST['service_text']);
    
    $maps = mysqli_real_escape_string($conn, $_POST['maps_link']);
    $reviews = mysqli_real_escape_string($conn, $_POST['review_link']);
    $g_rating = mysqli_real_escape_string($conn, $_POST['google_rating']);
    $g_reviews = mysqli_real_escape_string($conn, $_POST['google_reviews']);
    
    $c_text = mysqli_real_escape_string($conn, $_POST['community_text']);
    $c_link = mysqli_real_escape_string($conn, $_POST['community_link']);
    
    $halal = isset($_POST['show_halal']) ? 1 : 0;
    $halal_id = mysqli_real_escape_string($conn, $_POST['halal_id']);
    $halal_link = mysqli_real_escape_string($conn, $_POST['halal_link']);
    
    $logo_sql = "";
    if (!empty($_FILES['logo_upload']['name'])) {
        $target = "../assets/images/logo.png";
        if (!file_exists("../assets/images/")) mkdir("../assets/images/", 0777, true);
        if(move_uploaded_file($_FILES["logo_upload"]["tmp_name"], $target)) $logo_sql = ", logo='assets/images/logo.png'";
    }

    $is_closed = isset($_POST['is_closed']) ? 1 : 0;
    $close_msg = mysqli_real_escape_string($conn, $_POST['close_message']);
    
    if(isset($_POST['day_id'])){
        foreach($_POST['day_id'] as $idx => $did){
            $status = isset($_POST['day_open'][$did]) ? 1 : 0;
            $buka = mysqli_real_escape_string($conn, $_POST['time_open'][$idx]);
            $tutup = mysqli_real_escape_string($conn, $_POST['time_close'][$idx]);
            mysqli_query($conn, "UPDATE shop_hours SET is_open='$status', open_time='$buka', close_time='$tutup' WHERE id='$did'");
        }
    }

    $fee = floatval($_POST['service_fee']); $fee_lbl = mysqli_real_escape_string($conn, $_POST['fee_label']);
    $ongkir = floatval($_POST['delivery_fee']); $qris = mysqli_real_escape_string($conn, $_POST['qris_data']);
    $chat = isset($_POST['enable_chat']) ? 1 : 0; $print_w = $_POST['printer_width'] ?? 58; 
    $autoprint = isset($_POST['auto_print']) ? 1 : 0; $inv_sync = isset($_POST['inventory_sync']) ? 1 : 0;
    $allow_manual_reg = isset($_POST['allow_manual_reg']) ? 1 : 0;
    $bonus = floatval($_POST['reg_bonus_amount']); $ref_act = isset($_POST['referral_active']) ? 1 : 0; $ref_bon = floatval($_POST['referral_bonus']);
    $g_client = $_POST['google_client_id'] ?? ''; $g_secret = $_POST['google_client_secret'] ?? ''; $g_uri = $_POST['google_redirect_uri'] ?? '';

    $query = "UPDATE shop_settings SET 
              shop_name='$name', show_shop_name='$show_name', wa_number='$wa', running_text='$rtext', service_text='$stext',
              maps_link='$maps', review_link='$reviews', google_rating='$g_rating', google_reviews='$g_reviews',
              community_text='$c_text', community_link='$c_link',
              show_halal='$halal', halal_id='$halal_id', halal_link='$halal_link',
              is_closed='$is_closed', close_message='$close_msg',
              service_fee='$fee', fee_label='$fee_lbl', delivery_fee='$ongkir', qris_data='$qris',
              enable_chat='$chat', auto_print='$autoprint', inventory_sync='$inv_sync', allow_manual_reg='$allow_manual_reg',
              reg_bonus_amount='$bonus', referral_active='$ref_act', referral_bonus='$ref_bon',
              google_client_id='$g_client', google_client_secret='$g_secret', google_redirect_uri='$g_uri'
              $logo_sql WHERE id=1";
    
    if(mysqli_query($conn, $query)) {
        $alert_type = "success"; $alert_title = "Berhasil"; $alert_msg = "Pengaturan tersimpan.";
    } else {
        $alert_type = "error"; $alert_title = "Gagal"; $alert_msg = mysqli_error($conn);
    }
}

$settings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));
$hours = mysqli_query($conn, "SELECT * FROM shop_hours");
$banners = mysqli_query($conn, "SELECT * FROM shop_banners ORDER BY id DESC");
$cards = mysqli_query($conn, "SELECT * FROM home_cards ORDER BY sort_order ASC, id ASC");
$districts = mysqli_query($conn, "SELECT * FROM districts ORDER BY district_name ASC");

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'umum';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan - POS PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } } }
    </script>
    <style>
        body { background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
        
        .glass-input { background: #ffffff; border: 1px solid #e2e8f0; color: #1e293b; transition: all 0.3s; }
        .glass-input:focus { border-color: #10b981; outline: none; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
        .glass-input::placeholder { color: #94a3b8; }
        
        .tab-btn { font-weight: 700; border-bottom: 3px solid transparent; transition: all 0.3s; color: #64748b; }
        .tab-btn:hover { color: #1e293b; }
        .tab-btn.active { border-color: #10b981; color: #059669; }
        
        .custom-scrollbar::-webkit-scrollbar { height: 6px; width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar:hover::-webkit-scrollbar-thumb { background: #94a3b8; }
    </style>
</head>
<body class="flex overflow-hidden text-sm">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 ml-0 md:ml-64 h-screen overflow-y-auto p-4 md:p-8 relative pb-24 custom-scrollbar">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 border-b border-gray-200 pb-5">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-600 text-[10px] font-bold uppercase tracking-widest mb-3">
                    <i class="bi bi-gear-fill"></i> Konfigurasi Sistem
                </div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-gray-800 tracking-tight">Pengaturan Toko</h2>
                <p class="text-gray-500 text-xs mt-1 font-medium">Sesuaikan informasi dasar, tampilan, hingga metode pembayaran.</p>
            </div>
            <button onclick="document.getElementById('formSettings').submit()" class="hidden md:flex px-6 py-2.5 font-extrabold text-white rounded-xl bg-green-600 hover:bg-green-500 shadow-md shadow-green-600/20 active:scale-95 items-center gap-2 transition-all">
                <i class="bi bi-floppy2-fill"></i> Simpan Semua
            </button>
        </div>

        <div class="flex gap-2 overflow-x-auto pb-4 mb-4 border-b border-gray-200 custom-scrollbar">
            <button onclick="switchTab('umum')" id="btn-umum" class="tab-btn px-4 py-2 text-sm flex items-center gap-2 whitespace-nowrap <?php echo $active_tab=='umum'?'active':''; ?>"><i class="bi bi-shop"></i> Umum</button>
            <button onclick="switchTab('tampilan')" id="btn-tampilan" class="tab-btn px-4 py-2 text-sm flex items-center gap-2 whitespace-nowrap <?php echo $active_tab=='tampilan'?'active':''; ?>"><i class="bi bi-palette"></i> Tampilan</button>
            <button onclick="switchTab('operasional')" id="btn-operasional" class="tab-btn px-4 py-2 text-sm flex items-center gap-2 whitespace-nowrap <?php echo $active_tab=='operasional'?'active':''; ?>"><i class="bi bi-clock"></i> Operasional</button>
            <button onclick="switchTab('keuangan')" id="btn-keuangan" class="tab-btn px-4 py-2 text-sm flex items-center gap-2 whitespace-nowrap <?php echo $active_tab=='keuangan'?'active':''; ?>"><i class="bi bi-wallet2"></i> Keuangan</button>
            <button onclick="switchTab('pengiriman')" id="btn-pengiriman" class="tab-btn px-4 py-2 text-sm flex items-center gap-2 whitespace-nowrap <?php echo $active_tab=='pengiriman'?'active':''; ?>"><i class="bi bi-truck"></i> Pengiriman</button>
            <button onclick="switchTab('sistem')" id="btn-sistem" class="tab-btn px-4 py-2 text-sm flex items-center gap-2 whitespace-nowrap <?php echo $active_tab=='sistem'?'active':''; ?>"><i class="bi bi-cpu"></i> Sistem</button>
        </div>

        <form id="formSettings" method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="simpan_settings" value="true">

            <div id="tab-umum" class="tab-content <?php echo $active_tab=='umum'?'':'hidden'; ?> animate-fade-in">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-5">
                        <h3 class="text-blue-600 font-extrabold flex items-center gap-2 mb-2"><i class="bi bi-card-heading"></i> Identitas Toko</h3>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Nama Toko / Bisnis</label>
                            <input type="text" name="shop_name" value="<?php echo htmlspecialchars($settings['shop_name']); ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-gray-800">
                        </div>
                        
                        <div class="flex items-center justify-between bg-gray-50 p-4 rounded-xl border border-gray-200">
                            <div>
                                <span class="text-sm font-bold text-gray-800 block">Tampilkan Teks Nama Toko</span>
                                <span class="text-[10px] text-gray-500">Muncul di header aplikasi pembeli.</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="show_shop_name" class="sr-only peer" <?php echo (!isset($settings['show_shop_name']) || $settings['show_shop_name']) ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-green-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">WhatsApp Admin (Penerima Order)</label>
                            <input type="text" name="wa_number" value="<?php echo htmlspecialchars($settings['wa_number']); ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-gray-800" placeholder="628123456789">
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Logo Toko (Opsional)</label>
                            <div class="flex gap-4 items-center bg-gray-50 p-3 rounded-xl border border-gray-200">
                                <?php if(!empty($settings['logo'])): ?>
                                    <div class="w-14 h-14 rounded-lg bg-white flex items-center justify-center border border-gray-200 shadow-sm shrink-0 p-1">
                                        <img src="../<?php echo htmlspecialchars($settings['logo']); ?>?v=<?php echo time(); ?>" class="w-full h-full object-contain">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="logo_upload" class="glass-input w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer bg-white p-1 rounded-lg">
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-5">
                        <h3 class="text-indigo-600 font-extrabold flex items-center gap-2 mb-2"><i class="bi bi-megaphone"></i> Informasi Header & Halal</h3>
                        
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Teks Pelayanan (Bawah Header)</label>
                            <input type="text" name="service_text" value="<?php echo htmlspecialchars($settings['service_text']); ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-medium text-gray-700" placeholder="Melayani Makan di Tempat & Delivery">
                        </div>
                        
                        <div class="flex items-center justify-between bg-indigo-50/50 p-4 rounded-xl border border-indigo-100">
                            <div>
                                <span class="text-sm font-bold text-indigo-900 block">Tampilkan Logo Halal?</span>
                                <span class="text-[10px] text-indigo-700/70">Sertifikasi produk di header.</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="show_halal" class="sr-only peer" <?php echo $settings['show_halal']?'checked':''; ?>>
                                <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-indigo-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                            </label>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">ID Halal</label>
                                <input type="text" name="halal_id" value="<?php echo htmlspecialchars($settings['halal_id'] ?? ''); ?>" class="glass-input w-full px-3 py-2.5 rounded-xl text-xs font-medium" placeholder="ID324100...">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Link Cek Halal</label>
                                <input type="text" name="halal_link" value="<?php echo htmlspecialchars($settings['halal_link'] ?? ''); ?>" class="glass-input w-full px-3 py-2.5 rounded-xl text-xs font-medium" placeholder="https://bpjph...">
                            </div>
                        </div>

                        <hr class="border-gray-100 my-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Running Text (Promo Pengumuman)</label>
                            <textarea name="running_text" rows="2" class="glass-input w-full px-4 py-3 rounded-xl font-medium text-sm text-gray-700 leading-relaxed"><?php echo htmlspecialchars($settings['running_text'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-operasional" class="tab-content <?php echo $active_tab=='operasional'?'':'hidden'; ?> animate-fade-in">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1 bg-white p-6 rounded-2xl border border-red-200 shadow-sm relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 opacity-5 text-red-600"><i class="bi bi-exclamation-triangle text-9xl"></i></div>
                        <h3 class="text-red-600 font-extrabold flex items-center gap-2 mb-6 relative z-10"><i class="bi bi-exclamation-triangle-fill"></i> Mode Darurat</h3>
                        
                        <div class="flex items-center justify-between bg-red-50 p-4 rounded-xl border border-red-100 mb-5 relative z-10">
                            <div>
                                <span class="text-sm font-bold text-red-800 block">Tutup Toko Sekarang</span>
                                <span class="text-[10px] text-red-600/80">Tutup paksa di luar jadwal.</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_closed" class="sr-only peer" <?php echo $settings['is_closed'] ? 'checked' : ''; ?>>
                                <div class="w-11 h-6 bg-red-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-red-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-red-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                            </label>
                        </div>
                        
                        <div class="relative z-10">
                            <label class="block text-xs font-bold text-red-500 uppercase tracking-wide mb-1.5 ml-1">Pesan saat Toko Tutup</label>
                            <input type="text" name="close_message" value="<?php echo htmlspecialchars($settings['close_message']); ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-gray-800 focus:border-red-400 focus:ring-red-100">
                        </div>
                    </div>
                    
                    <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
                        <h3 class="text-green-600 font-extrabold flex items-center gap-2 mb-4"><i class="bi bi-calendar-week-fill"></i> Jadwal Operasional</h3>
                        <p class="text-xs text-gray-500 mb-6">Atur jam buka dan tutup harian. Jika toko tutup, pembeli tidak bisa memesan.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                            <?php while($h = mysqli_fetch_assoc($hours)): ?>
                            <div class="flex items-center gap-3 border-b border-gray-100 pb-3">
                                <input type="hidden" name="day_id[]" value="<?php echo $h['id']; ?>">
                                
                                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                    <input type="checkbox" name="day_open[<?php echo $h['id']; ?>]" class="sr-only peer" <?php echo $h['is_open'] ? 'checked' : ''; ?>>
                                    <div class="w-10 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-green-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all"></div>
                                </label>
                                
                                <div class="w-20 font-bold text-gray-800 text-sm"><?php echo $h['day_name']; ?></div>
                                
                                <div class="flex items-center gap-1.5 flex-1 justify-end">
                                    <input type="time" name="time_open[]" value="<?php echo $h['open_time']; ?>" class="glass-input px-2 py-1.5 rounded-lg text-xs font-bold w-20 text-center text-gray-700">
                                    <span class="text-gray-400 text-xs font-bold">-</span>
                                    <input type="time" name="time_close[]" value="<?php echo $h['close_time']; ?>" class="glass-input px-2 py-1.5 rounded-lg text-xs font-bold w-20 text-center text-gray-700">
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-keuangan" class="tab-content <?php echo $active_tab=='keuangan'?'':'hidden'; ?> animate-fade-in">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-5">
                        <h3 class="text-emerald-600 font-extrabold flex items-center gap-2 mb-2"><i class="bi bi-cash-coin"></i> Biaya Tambahan</h3>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Biaya Layanan (Rp)</label>
                                <input type="number" name="service_fee" value="<?php echo $settings['service_fee']; ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-gray-800" placeholder="0">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Label Biaya</label>
                                <input type="text" name="fee_label" value="<?php echo htmlspecialchars($settings['fee_label']); ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-medium text-gray-700" placeholder="Cth: Pajak PB1 / PPN">
                            </div>
                        </div>
                        <hr class="border-gray-100">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Ongkir Default (Opsional)</label>
                            <input type="number" name="delivery_fee" value="<?php echo $settings['delivery_fee']; ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-gray-800" placeholder="0">
                            <p class="text-[10px] text-gray-400 mt-1.5 ml-1">*Dipakai jika pelanggan tidak memilih kecamatan khusus.</p>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4">
                        <h3 class="text-blue-600 font-extrabold flex items-center gap-2 mb-2"><i class="bi bi-qr-code-scan"></i> QRIS Pembayaran</h3>
                        <p class="text-xs text-gray-500">Paste kode string QRIS toko Anda agar pembeli bisa scan pembayaran otomatis sesuai nominal tagihan.</p>
                        <textarea name="qris_data" rows="5" class="glass-input w-full px-4 py-3 rounded-xl font-mono text-xs text-gray-600 bg-gray-50 leading-relaxed" placeholder="00020101021126610016ID.CO.TELKOMSEL..."><?php echo htmlspecialchars($settings['qris_data']); ?></textarea>
                    </div>
                </div>
            </div>

            <div id="tab-sistem" class="tab-content <?php echo $active_tab=='sistem'?'':'hidden'; ?> animate-fade-in">
                <div class="space-y-6">
                    
                    <h3 class="text-gray-800 font-extrabold text-lg">Fitur Modul</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-white p-4 rounded-xl flex items-center justify-between border-l-4 border-l-blue-500 shadow-sm border border-gray-200 border-l-solid">
                            <div><h4 class="font-bold text-gray-800 text-sm">Live Chat</h4><p class="text-[10px] text-gray-500">Tombol WA di user.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="enable_chat" class="sr-only peer" <?php echo $settings['enable_chat'] ? 'checked' : ''; ?>>
                                <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all"></div>
                            </label>
                        </div>
                        
                        <div class="bg-white p-4 rounded-xl flex items-center justify-between border-l-4 border-l-purple-500 shadow-sm border border-gray-200 border-l-solid">
                            <div><h4 class="font-bold text-gray-800 text-sm">Auto Print</h4><p class="text-[10px] text-gray-500">Cetak otomatis (Kasir).</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="auto_print" class="sr-only peer" <?php echo $settings['auto_print'] ? 'checked' : ''; ?>>
                                <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-purple-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all"></div>
                            </label>
                        </div>
                        
                        <div class="bg-white p-4 rounded-xl flex items-center justify-between border-l-4 border-l-green-500 shadow-sm border border-gray-200 border-l-solid">
                            <div><h4 class="font-bold text-gray-800 text-sm">Inventory Sync</h4><p class="text-[10px] text-gray-500">Potong stok otomatis.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="inventory_sync" class="sr-only peer" <?php echo $settings['inventory_sync'] ? 'checked' : ''; ?>>
                                <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-green-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all"></div>
                            </label>
                        </div>
                        
                        <div class="bg-white p-4 rounded-xl flex items-center justify-between border-l-4 border-l-orange-500 shadow-sm border border-gray-200 border-l-solid">
                            <div><h4 class="font-bold text-gray-800 text-sm">Reg. Manual</h4><p class="text-[10px] text-gray-500">Boleh daftar manual.</p></div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="allow_manual_reg" class="sr-only peer" <?php echo (!isset($settings['allow_manual_reg']) || $settings['allow_manual_reg']) ? 'checked' : ''; ?>>
                                <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-orange-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all"></div>
                            </label>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white p-6 rounded-2xl border border-amber-200 bg-amber-50/30 shadow-sm">
                            <h4 class="font-extrabold mb-4 text-sm text-amber-600 flex items-center gap-2"><i class="bi bi-gift-fill"></i> Loyalty & Referral</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5 ml-1">Voucher Daftar Baru (Rp)</label>
                                    <input type="number" name="reg_bonus_amount" value="<?php echo $settings['reg_bonus_amount']; ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-amber-600 bg-white">
                                </div>
                                <div class="flex items-center justify-between border-t border-amber-200/50 pt-3">
                                    <span class="text-xs font-bold text-gray-700">Aktifkan Program Referral?</span>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="referral_active" class="sr-only peer" <?php echo ($settings['referral_active'] ?? 0) ? 'checked' : ''; ?>>
                                        <div class="w-11 h-6 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-amber-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                                    </label>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wide mb-1.5 ml-1">Komisi per Ajakan (Rp)</label>
                                    <input type="number" name="referral_bonus" value="<?php echo $settings['referral_bonus'] ?? 0; ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-gray-800 bg-white">
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white p-6 rounded-2xl border border-blue-200 bg-blue-50/30 shadow-sm">
                            <h4 class="font-extrabold mb-4 text-sm text-blue-600 flex items-center gap-2"><i class="bi bi-google"></i> Google Login API</h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1 ml-1">Client ID</label>
                                    <input type="text" name="google_client_id" value="<?php echo htmlspecialchars($settings['google_client_id'] ?? ''); ?>" class="glass-input w-full px-3 py-2 rounded-lg text-xs font-mono text-gray-600 bg-white">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1 ml-1">Client Secret</label>
                                    <input type="text" name="google_client_secret" value="<?php echo htmlspecialchars($settings['google_client_secret'] ?? ''); ?>" class="glass-input w-full px-3 py-2 rounded-lg text-xs font-mono text-gray-600 bg-white">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1 ml-1">Redirect URI</label>
                                    <input type="text" name="google_redirect_uri" value="<?php echo htmlspecialchars($settings['google_redirect_uri'] ?? ''); ?>" class="glass-input w-full px-3 py-2 rounded-lg text-xs font-mono text-gray-600 bg-white">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="hidden">
                <input type="text" name="maps_link" value="<?php echo htmlspecialchars($settings['maps_link']); ?>">
                <input type="text" name="review_link" value="<?php echo htmlspecialchars($settings['review_link']); ?>">
                <input type="text" name="google_rating" value="<?php echo htmlspecialchars($settings['google_rating'] ?? ''); ?>">
                <input type="text" name="google_reviews" value="<?php echo htmlspecialchars($settings['google_reviews'] ?? ''); ?>">
                <input type="text" name="community_text" value="<?php echo htmlspecialchars($settings['community_text'] ?? ''); ?>">
                <input type="text" name="community_link" value="<?php echo htmlspecialchars($settings['community_link'] ?? ''); ?>">
            </div>
        </form>

        <div id="tab-pengiriman" class="tab-content <?php echo $active_tab=='pengiriman'?'':'hidden'; ?> animate-fade-in space-y-6">
            <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
                <h3 class="text-indigo-600 font-extrabold mb-2 flex items-center gap-2 text-lg"><i class="bi bi-truck"></i> Daftar Kecamatan & Ongkir</h3>
                <p class="text-xs text-gray-500 mb-6 font-medium">Tambahkan daftar kecamatan yang Anda layani beserta tarif ongkos kirimnya. Data ini akan muncul di form pesanan pembeli.</p>
                
                <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-8 bg-gray-50 p-5 rounded-2xl border border-gray-200 items-end">
                    <input type="hidden" name="district_id" id="editDistrictId" value="">
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Nama Kecamatan</label>
                        <input type="text" name="district_name" id="editDistrictName" class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-gray-800" placeholder="Contoh: Karawang Barat" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Ongkos Kirim (Rp)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-2.5 font-bold text-gray-400">Rp</span>
                            <input type="number" name="delivery_fee" id="editDistrictFee" class="glass-input w-full pl-9 pr-4 py-2.5 rounded-xl font-bold text-blue-600" placeholder="10000" required>
                        </div>
                    </div>
                    <div class="flex gap-2 md:col-span-1">
                        <button type="submit" name="save_district" id="btnSaveDistrict" class="bg-green-600 hover:bg-green-500 text-white px-4 py-2.5 rounded-xl font-bold transition shadow-md flex-1 h-[42px]">Tambah</button>
                        <button type="button" id="btnCancelDistrict" onclick="cancelEditDistrict()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2.5 rounded-xl font-bold transition hidden flex-1 h-[42px]">Batal</button>
                    </div>
                </form>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if(mysqli_num_rows($districts) > 0): while($d = mysqli_fetch_assoc($districts)): ?>
                    <div class="bg-white border border-gray-200 p-4 rounded-2xl flex justify-between items-center group hover:shadow-md hover:border-blue-200 transition-all">
                        <div>
                            <div class="text-sm font-extrabold text-gray-800"><?php echo htmlspecialchars($d['district_name']); ?></div>
                            <div class="text-xs text-blue-600 font-mono font-bold mt-1">Rp <?php echo number_format($d['delivery_fee']); ?></div>
                        </div>
                        <div class="flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button type="button" onclick="editDistrict('<?php echo $d['id']; ?>', '<?php echo htmlspecialchars(addslashes($d['district_name'])); ?>', '<?php echo $d['delivery_fee']; ?>')" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white transition flex items-center justify-center"><i class="bi bi-pencil text-xs"></i></button>
                            <a href="?del_district=<?php echo $d['id']; ?>" class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white transition flex items-center justify-center" onclick="return confirm('Hapus kecamatan ini?')"><i class="bi bi-trash text-xs"></i></a>
                        </div>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="col-span-full text-center text-gray-400 font-medium py-8 border border-dashed border-gray-300 rounded-xl bg-gray-50">Belum ada data kecamatan ditambahkan.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="tab-tampilan" class="tab-content <?php echo $active_tab=='tampilan'?'':'hidden'; ?> animate-fade-in space-y-6">
            
            <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
                <h3 class="text-blue-600 font-extrabold mb-4 flex items-center gap-2"><i class="bi bi-link-45deg"></i> Link Eksternal & Review Maps</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1"><i class="bi bi-geo-alt"></i> Link Google Maps (Alamat Toko)</label>
                        <input type="text" form="formSettings" name="maps_link" value="<?php echo htmlspecialchars($settings['maps_link'] ?? ''); ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-medium" placeholder="https://maps.app.goo.gl/...">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1"><i class="bi bi-star"></i> Link Review (Untuk tombol di Beranda)</label>
                        <input type="text" form="formSettings" name="review_link" value="<?php echo htmlspecialchars($settings['review_link'] ?? ''); ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-medium" placeholder="https://g.page/r/...">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-5 border-t border-gray-100 pt-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1"><i class="bi bi-star-fill text-yellow-400"></i> Angka Rating Google</label>
                        <input type="text" form="formSettings" name="google_rating" value="<?php echo htmlspecialchars($settings['google_rating'] ?? '5.0'); ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-black text-gray-800" placeholder="4.9">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1"><i class="bi bi-people-fill text-blue-400"></i> Total Jumlah Ulasan</label>
                        <input type="text" form="formSettings" name="google_reviews" value="<?php echo htmlspecialchars($settings['google_reviews'] ?? '0'); ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-gray-800" placeholder="150">
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
                <h3 class="text-green-600 font-extrabold mb-2 flex items-center gap-2"><i class="bi bi-whatsapp"></i> Footer Komunitas (Bawah Beranda)</h3>
                <p class="text-xs text-gray-500 mb-5 font-medium">Kotak banner untuk mengajak pelanggan bergabung ke grup WhatsApp toko Anda.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Teks / Judul Ajakan</label>
                        <input type="text" form="formSettings" name="community_text" value="<?php echo htmlspecialchars($settings['community_text'] ?? 'Gabung Komunitas Kami'); ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-gray-800" placeholder="Contoh: Info Promo di Grup WA">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Link Grup WhatsApp</label>
                        <input type="text" form="formSettings" name="community_link" value="<?php echo htmlspecialchars($settings['community_link'] ?? ''); ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-medium" placeholder="https://chat.whatsapp.com/...">
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
                <h3 class="text-purple-600 font-extrabold mb-4 flex items-center gap-2"><i class="bi bi-images"></i> Banner Slider Utama</h3>
                
                <form method="POST" enctype="multipart/form-data" class="flex gap-3 items-end mb-6 bg-purple-50 p-4 rounded-2xl border border-purple-100">
                    <div class="flex-1">
                        <label class="block text-[10px] font-bold text-purple-600 uppercase tracking-wide mb-2 ml-1">Upload Gambar Banner (Bisa pilih banyak file)</label>
                        <input type="file" name="banner_files[]" multiple accept="image/*" class="glass-input w-full text-xs bg-white rounded-lg p-1 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-purple-100 file:text-purple-700 hover:file:bg-purple-200 cursor-pointer" required>
                    </div>
                    <button type="submit" name="upload_banner" class="px-5 py-2.5 bg-purple-600 text-white rounded-xl hover:bg-purple-500 font-bold text-sm h-[44px] shadow-md active:scale-95 transition-transform"><i class="bi bi-cloud-arrow-up"></i> Upload</button>
                </form>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php 
                    $b_count = 0;
                    while($b = mysqli_fetch_assoc($banners)): $b_count++; ?>
                    <div class="relative group rounded-xl overflow-hidden border border-gray-200 shadow-sm aspect-video bg-gray-100">
                        <img src="../<?php echo $b['image']; ?>" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <a href="?del_banner=<?php echo $b['id']; ?>" class="bg-red-500 text-white w-10 h-10 rounded-full flex items-center justify-center hover:bg-red-600 shadow-lg transform scale-50 group-hover:scale-100 transition-transform" onclick="return confirm('Hapus banner ini?')"><i class="bi bi-trash"></i></a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php if($b_count == 0) echo '<div class="col-span-full text-center text-gray-400 font-medium py-8 border border-dashed border-gray-300 rounded-xl bg-gray-50">Belum ada banner slider yang diupload.</div>'; ?>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-sm">
                <h3 class="text-indigo-600 font-extrabold mb-2 flex items-center gap-2"><i class="bi bi-grid-fill"></i> Tombol Shortcut (Beranda)</h3>
                <p class="text-xs text-gray-500 mb-5 font-medium">Atur 4 kotak menu pintasan yang ada di halaman depan pembeli. (Cari referensi ikon di <a href="https://icons.getbootstrap.com/" target="_blank" class="text-indigo-500 underline font-bold">Bootstrap Icons</a>)</p>
                
                <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-6 bg-gray-50 p-5 rounded-2xl border border-gray-200 items-end">
                    <input type="hidden" name="card_id" id="editCardId" value="">
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Judul Teks</label>
                        <input type="text" id="editCardTitle" name="card_title" class="glass-input w-full px-4 py-2.5 rounded-xl text-sm font-bold text-gray-800" placeholder="Cth: Pesan" required>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Nama Ikon</label>
                        <input type="text" id="editCardIcon" name="card_icon" class="glass-input w-full px-4 py-2.5 rounded-xl text-sm font-mono text-indigo-600" placeholder="bi-cart-fill" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Link Target / Action</label>
                        <input type="text" id="editCardLink" name="card_link" class="glass-input w-full px-4 py-2.5 rounded-xl text-sm font-medium" placeholder="javascript:scrollToMenu()" required>
                    </div>
                    <div class="flex gap-2 md:col-span-1">
                        <button type="submit" name="save_card" id="btnSaveCard" class="bg-green-600 hover:bg-green-500 text-white rounded-xl font-bold text-sm h-[42px] flex-1 transition shadow-md">Simpan</button>
                        <button type="button" id="btnCancelEdit" onclick="cancelEditCard()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-xl font-bold text-sm h-[42px] flex-1 hidden transition">Batal</button>
                    </div>
                </form>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <?php while($c = mysqli_fetch_assoc($cards)): ?>
                    <div class="bg-white border border-gray-200 text-gray-800 p-4 rounded-2xl flex flex-col items-center text-center relative group shadow-sm hover:shadow-md hover:border-indigo-200 transition-all">
                        <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl mb-2 group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                            <i class="bi <?php echo htmlspecialchars($c['icon']); ?>"></i>
                        </div>
                        <span class="text-sm font-extrabold text-gray-800"><?php echo htmlspecialchars($c['title']); ?></span>
                        <span class="text-[9px] text-gray-400 font-mono truncate w-full mt-1 px-2"><?php echo htmlspecialchars($c['link']); ?></span>
                        
                        <div class="absolute -top-3 -right-2 flex gap-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button type="button" onclick="editCard('<?php echo $c['id']; ?>', '<?php echo htmlspecialchars(addslashes($c['title'])); ?>', '<?php echo htmlspecialchars(addslashes($c['icon'])); ?>', '<?php echo htmlspecialchars(addslashes($c['link'])); ?>')" class="bg-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center shadow-md hover:bg-blue-600"><i class="bi bi-pencil text-xs"></i></button>
                            <a href="?del_card=<?php echo $c['id']; ?>" class="bg-red-500 text-white w-8 h-8 rounded-full flex items-center justify-center shadow-md hover:bg-red-600" onclick="return confirm('Hapus kartu ini?')"><i class="bi bi-trash text-xs"></i></a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="md:hidden mt-8 pb-8">
            <button onclick="document.getElementById('formSettings').submit()" class="w-full py-3.5 rounded-xl bg-green-600 text-white font-extrabold shadow-lg shadow-green-500/30 active:scale-95 transition-transform flex justify-center items-center gap-2">
                <i class="bi bi-floppy2-fill"></i> Simpan Pengaturan
            </button>
        </div>

    </main>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            document.getElementById('btn-' + tabId).classList.add('active');
        }

        // JS UNTUK EDIT MENU SHORTCUT
        function editCard(id, title, icon, link) {
            document.getElementById('editCardId').value = id;
            document.getElementById('editCardTitle').value = title;
            document.getElementById('editCardIcon').value = icon;
            document.getElementById('editCardLink').value = link;
            
            const btnSave = document.getElementById('btnSaveCard');
            btnSave.innerText = 'Update';
            btnSave.classList.replace('bg-green-600', 'bg-blue-600');
            btnSave.classList.replace('hover:bg-green-500', 'hover:bg-blue-500');
            
            document.getElementById('btnCancelEdit').classList.remove('hidden');
            document.getElementById('editCardTitle').focus();
            window.scrollTo({ top: document.getElementById('editCardTitle').offsetTop - 100, behavior: 'smooth' });
        }

        function cancelEditCard() {
            document.getElementById('editCardId').value = '';
            document.getElementById('editCardTitle').value = '';
            document.getElementById('editCardIcon').value = '';
            document.getElementById('editCardLink').value = '';
            
            const btnSave = document.getElementById('btnSaveCard');
            btnSave.innerText = 'Simpan';
            btnSave.classList.replace('bg-blue-600', 'bg-green-600');
            btnSave.classList.replace('hover:bg-blue-500', 'hover:bg-green-500');
            
            document.getElementById('btnCancelEdit').classList.add('hidden');
        }

        // JS UNTUK EDIT KECAMATAN
        function editDistrict(id, name, fee) {
            document.getElementById('editDistrictId').value = id;
            document.getElementById('editDistrictName').value = name;
            document.getElementById('editDistrictFee').value = fee;
            
            const btnSave = document.getElementById('btnSaveDistrict');
            btnSave.innerText = 'Update';
            btnSave.classList.replace('bg-green-600', 'bg-blue-600');
            btnSave.classList.replace('hover:bg-green-500', 'hover:bg-blue-500');
            
            document.getElementById('btnCancelDistrict').classList.remove('hidden');
            document.getElementById('editDistrictName').focus();
            window.scrollTo({ top: document.getElementById('editDistrictName').offsetTop - 100, behavior: 'smooth' });
        }

        function cancelEditDistrict() {
            document.getElementById('editDistrictId').value = '';
            document.getElementById('editDistrictName').value = '';
            document.getElementById('editDistrictFee').value = '';
            
            const btnSave = document.getElementById('btnSaveDistrict');
            btnSave.innerText = 'Tambah';
            btnSave.classList.replace('bg-blue-600', 'bg-green-600');
            btnSave.classList.replace('hover:bg-blue-500', 'hover:bg-green-500');
            
            document.getElementById('btnCancelDistrict').classList.add('hidden');
        }

        <?php if($alert_type != ""): ?>
        Swal.fire({
            title: '<?php echo $alert_title; ?>',
            text: '<?php echo $alert_msg; ?>',
            icon: '<?php echo $alert_type; ?>',
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Oke',
            background: '#ffffff',
            color: '#1e293b'
        }).then((result) => {
            if (result.isConfirmed && '<?php echo $alert_type; ?>' == 'success') {
                window.location.href = 'settings.php?tab=<?php echo isset($_GET['tab']) ? $_GET['tab'] : (isset($_POST['save_card']) || isset($_POST['upload_banner']) ? 'tampilan' : (isset($_POST['save_district']) ? 'pengiriman' : 'umum')); ?>';
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>