<?php
session_start();
include 'koneksi.php';

// 1. AMBIL SETTINGS
$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));

// 2. AMBIL DATA KECAMATAN & ONGKIR UNTUK JAVASCRIPT
$q_districts = mysqli_query($conn, "SELECT district_name, delivery_fee FROM districts ORDER BY district_name ASC");
$districts = [];
$district_fees_js = [];

if($q_districts){
    while($d = mysqli_fetch_assoc($q_districts)){
        $districts[] = $d;
        $district_fees_js[$d['district_name']] = (int)$d['delivery_fee']; // Simpan ongkir per nama kecamatan
    }
}
$district_fees_json = json_encode($district_fees_js);

// 3. CEK LOGIN MEMBER & ELIGIBILITAS VOUCHER
$member_name = ""; $member_phone = ""; $user_id = "null";
$voucher_amount = 0; $voucher_eligible = false;
$is_member = false;

if(isset($_SESSION['role']) && $_SESSION['role'] == 'member') {
    $uid = $_SESSION['user_id'];
    $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$uid'"));
    $member_name = $u['fullname']; $member_phone = $u['phone'] ?? ''; 
    $user_id = $uid;
    $is_member = true;

    $cek_trx = mysqli_query($conn, "SELECT id FROM transactions WHERE user_id='$uid' AND status != 'cancelled' LIMIT 1");
    if(mysqli_num_rows($cek_trx) == 0) {
        $voucher_eligible = true;
        $voucher_amount = $shop['reg_bonus_amount'];
    }
}

// 4. KATEGORI & FORMAT LAYANAN
$q_cat = mysqli_query($conn, "SELECT DISTINCT category FROM products WHERE product_status='ready' ORDER BY category ASC");
$service_text_formatted = str_replace([', ', ','], ' ○ ', $shop['service_text']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Menu - <?php echo htmlspecialchars($shop['shop_name']); ?></title>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#ffffff">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($shop['logo']); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.5.0/qrcode.min.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } } }
    </script>

    <style>
        /* Padding bottom ditambahkan agar tidak tertutup tombol keranjang melayang */
        body { background-color: #f8fafc; color: #1e293b; overflow-x: hidden; -webkit-tap-highlight-color: transparent; padding-bottom: 140px; }
        .hide-scroll::-webkit-scrollbar { display: none; }
        
        /* HEADER STYLE */
        .top-header { background: white; padding: 12px 16px 8px 16px; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 50; }
        .btn-outline { background: white; border: 1.5px solid #16a34a; color: #16a34a; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: 0.2s; }
        .btn-outline:hover { background: #f0fdf4; }
        
        /* PRODUK */
        .product-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .product-card:active { transform: scale(0.98); }
        .sold-out-layer { position: absolute; inset: 0; background: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center; z-index: 10; backdrop-filter: blur(2px); }
        
        /* BOTTOM NAV */
        .nav-item { color: #94a3b8; font-size: 10px; font-weight: 700; display: flex; flex-direction: column; align-items: center; width: 100%; padding: 10px 0; transition: 0.2s; }
        .nav-item.active { color: #10b981; }
        
        /* MODAL & STRUK */
        #receiptTemplate { position: fixed; left: -9999px; top: 0; width: 350px; background: white; color: black; z-index: -1; padding: 20px; font-family: monospace; }
        input:checked + div { border-color: #10b981; background-color: rgba(16, 185, 129, 0.1); }
        .pb-safe { padding-bottom: env(safe-area-inset-bottom, 20px); }
        select option { background-color: white; color: #1e293b; }
    </style>
</head>
<body> 

    <header class="top-header">
        <div class="max-w-5xl mx-auto">
            <div class="flex justify-between items-center mb-3 pb-1 gap-2">
                <?php if(!empty($shop['maps_link'])): ?>
                    <a href="<?php echo htmlspecialchars($shop['maps_link']); ?>" target="_blank" class="flex items-center gap-1 text-gray-500 text-[11px] hover:text-green-600 flex-1 min-w-0">
                        <i class="bi bi-geo-alt text-gray-400 shrink-0"></i>
                        <span class="truncate"><?php echo htmlspecialchars($shop['shop_name']); ?> - Klik untuk lihat Peta</span>
                    </a>
                <?php else: ?>
                    <div class="flex-1"></div>
                <?php endif; ?>

                <?php if($shop['show_halal']): ?>
                <a href="<?php echo !empty($shop['halal_link']) ? htmlspecialchars($shop['halal_link']) : '#'; ?>" target="_blank" class="flex items-center gap-1 shrink-0 hover:opacity-80 transition bg-purple-50/80 border border-purple-100 px-1.5 py-0.5 rounded ml-auto">
                    <img src="https://www.simplify.web.id/wp-content/uploads/2025/08/logo-halal.webp" class="h-3.5 object-contain" alt="Halal">
                    <span class="text-[9px] font-bold" style="color: #6a2a88;"><?php echo !empty($shop['halal_id']) ? htmlspecialchars($shop['halal_id']) : 'ID32410028681530925'; ?></span>
                </a>
                <?php endif; ?>
            </div>

            <div class="flex justify-between items-center mb-2">
                <div class="flex items-center gap-2">
                    <a href="index.php"><img src="<?php echo htmlspecialchars($shop['logo']); ?>?v=<?php echo time(); ?>" class="h-10 w-auto object-contain" alt="Logo"></a>
                    <?php if(isset($shop['show_shop_name']) && $shop['show_shop_name'] == 1): ?>
                        <h1 class="font-extrabold text-green-900 text-[15px] md:text-lg leading-none tracking-tight"><a href="index.php"><?php echo htmlspecialchars($shop['shop_name']); ?></a></h1>
                    <?php endif; ?>
                </div>
                
                <div class="flex items-center gap-2">
                    <button onclick="document.getElementById('searchInput').focus()" class="text-gray-700 hover:text-green-600 transition p-1">
                        <i class="bi bi-search text-xl font-bold"></i>
                    </button>
                    
                    <?php if($is_member): ?>
                        <a href="member/index.php" class="btn-outline px-3 py-1.5 gap-1.5 text-[11px]">
                            <i class="bi bi-person-fill text-sm"></i> <span class="hidden md:inline">Akun</span><span class="md:hidden">Akun</span>
                        </a>
                        <a href="logout.php" onclick="return confirm('Keluar dari akun?')" class="w-8 h-8 flex items-center justify-center text-red-500 hover:bg-red-50 rounded-full transition ml-1">
                            <i class="bi bi-power text-xl"></i>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn-outline px-3 py-1.5 gap-1.5 text-[11px]">
                            <i class="bi bi-person-fill text-sm"></i> Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="pt-2 border-t border-gray-100 overflow-x-auto hide-scroll">
                <div class="text-[10px] text-gray-500 font-medium whitespace-nowrap w-full">
                    <?php echo $service_text_formatted; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="px-4 mt-6 space-y-6 max-w-5xl mx-auto min-h-[70vh]">

        <?php if($voucher_eligible): ?>
        <div class="bg-gradient-to-r from-orange-50 to-yellow-50 border border-orange-200 rounded-xl p-3 flex items-center justify-between shadow-sm max-w-3xl mx-auto">
            <div>
                <span class="text-orange-600 text-xs font-bold"><i class="bi bi-ticket-perforated-fill"></i> Voucher Member Baru</span>
                <p class="text-gray-600 text-[10px] mt-0.5">Diskon Rp <?php echo number_format($voucher_amount); ?> otomatis di keranjang.</p>
            </div>
        </div>
        <?php endif; ?>

        <div id="productSection">
            <div class="relative mb-6 max-w-lg mx-auto">
                <i class="bi bi-search absolute left-4 top-3.5 text-gray-400"></i>
                <input type="text" id="searchInput" onkeyup="checkSecretLogin(this)" placeholder="Cari menu favoritmu..." class="w-full pl-11 pr-4 py-3 bg-white border border-gray-200 rounded-2xl text-sm focus:outline-none focus:border-green-500 transition shadow-sm text-center">
            </div>

            <div class="flex gap-2 overflow-x-auto justify-start md:justify-center hide-scroll pb-2 mb-6">
                <button class="px-5 py-2 rounded-full bg-green-500 text-white text-xs font-bold category-btn whitespace-nowrap shadow-md" data-cat="all">Semua Menu</button>
                <?php while($c = mysqli_fetch_assoc($q_cat)): ?>
                    <button class="px-5 py-2 rounded-full bg-white border border-gray-200 text-gray-600 text-xs font-medium category-btn whitespace-nowrap hover:bg-gray-50" data-cat="<?php echo htmlspecialchars($c['category']); ?>"><?php echo htmlspecialchars($c['category']); ?></button>
                <?php endwhile; ?>
            </div>
            
            <div class="flex flex-wrap justify-center gap-3 md:gap-4" id="productGrid">
                <?php
                $q_prod = mysqli_query($conn, "SELECT * FROM products ORDER BY product_status ASC, name ASC");
                while($p = mysqli_fetch_assoc($q_prod)):
                    $img = ($p['image']=='default.png') ? "https://placehold.co/400x400/e2e8f0/94a3b8?text=".urlencode($p['name']) : (filter_var($p['image'], FILTER_VALIDATE_URL) ? $p['image'] : $p['image']);
                    $isRetail = ($p['product_type'] == 'retail');
                    $isSoldOut = ($p['product_status'] == 'habis');
                    
                    $variantsData = "[]"; $toppingsData = "[]"; $priceDisplay = $p['base_price']; $totalStock = $p['stock'];

                    if($isRetail) {
                        $qv = mysqli_query($conn, "SELECT * FROM product_variants WHERE product_id='{$p['id']}'");
                        $vars = []; $prices = [];
                        while($v = mysqli_fetch_assoc($qv)) { $vars[] = $v; $prices[] = $v['price']; }
                        $variantsData = json_encode($vars);
                        if(count($vars) > 0) { $priceDisplay = min($prices); $totalStock = array_sum(array_column($vars, 'stock')); } 
                        else { $priceDisplay = 0; $totalStock = 0; }
                        if($totalStock <= 0) $isSoldOut = true;
                    } else {
                        $qm = mysqli_query($conn, "SELECT * FROM product_modifiers WHERE product_id='{$p['id']}'");
                        $mods = []; while($m = mysqli_fetch_assoc($qm)) $mods[] = $m;
                        $toppingsData = json_encode($mods);
                        if($p['stock'] <= 0) $isSoldOut = true;
                    }
                ?>
                <div class="product-card relative group category-item flex flex-col cursor-pointer overflow-hidden w-[calc(50%-0.375rem)] sm:w-[calc(33.33%-0.5rem)] md:w-[calc(25%-0.75rem)] lg:w-[calc(20%-0.8rem)]" onclick='openProductModal(<?php echo htmlspecialchars(json_encode($p)); ?>, <?php echo htmlspecialchars($variantsData); ?>, <?php echo htmlspecialchars($toppingsData); ?>)' data-category="<?php echo htmlspecialchars($p['category']); ?>">
                    <div class="aspect-square bg-gray-100 relative">
                        <img src="<?php echo htmlspecialchars($img); ?>" class="w-full h-full object-cover" loading="lazy">
                        <?php if($isSoldOut): ?><div class="sold-out-layer"><span class="font-bold text-gray-800 tracking-widest text-xs bg-white/90 px-3 py-1 rounded shadow">HABIS</span></div><?php endif; ?>
                    </div>
                    
                    <div class="p-3 flex flex-col flex-1">
                        <h3 class="font-bold text-gray-800 text-xs md:text-sm leading-tight mb-1 flex-1"><?php echo htmlspecialchars($p['name']); ?></h3>
                        <div class="mt-1 flex flex-col items-start">
                            <div class="flex items-center gap-1">
                                <div class="text-gray-800 font-extrabold text-xs md:text-sm"><?php echo $isRetail ? "Rp".number_format($priceDisplay,0,',','.')."+" : "Rp".number_format($priceDisplay,0,',','.'); ?></div>
                                <?php if($p['original_price'] > $priceDisplay): ?><div class="text-[9px] text-gray-400 line-through">Rp<?php echo number_format($p['original_price']); ?></div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <nav class="bottom-nav fixed bottom-0 w-full z-40 bg-transparent flex justify-center pointer-events-none">
        <div class="w-full max-w-2xl bg-white border-t border-gray-200 flex justify-around items-center px-2 pb-safe shadow-[0_-5px_15px_rgba(0,0,0,0.05)] md:rounded-t-2xl pointer-events-auto">
            <a href="index.php" class="nav-item">
                <i class="bi bi-house-door-fill text-[22px]"></i>
                <span class="mt-1">Beranda</span>
            </a>
            <a href="pesan.php" class="nav-item active">
                <i class="bi bi-grid-fill text-[22px]"></i>
                <span class="mt-1">Menu</span>
            </a>
            
            <a href="javascript:void(0)" onclick="openCartModal()" class="nav-item">
                <div class="relative inline-block">
                    <i class="bi bi-bag-fill text-[22px]"></i>
                    <div id="navCartBadge" class="absolute -top-1.5 -right-2 w-4 h-4 bg-red-500 text-white text-[9px] rounded-full flex items-center justify-center font-bold hidden border border-white shadow-sm">0</div>
                </div>
                <span class="mt-1">Keranjang</span>
            </a>

            <a href="<?php echo $is_member ? 'member/index.php' : 'login.php'; ?>" class="nav-item">
                <i class="bi bi-person-fill text-[22px]"></i>
                <span class="mt-1">Saya</span>
            </a>
        </div>
    </nav>

    <div id="floatingCart" class="fixed bottom-24 left-0 right-0 z-30 transform translate-y-48 transition-transform duration-300 flex justify-center pointer-events-none hidden">
        <div class="bg-green-600 text-white rounded-xl p-3 shadow-2xl flex justify-between items-center w-[calc(100%-2rem)] max-w-md cursor-pointer pointer-events-auto" onclick="openCartModal()">
            <div class="flex items-center gap-3"><div class="bg-white/20 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"><span id="floatCount">0</span></div><span class="font-bold" id="floatTotal">Rp 0</span></div>
            <div class="text-xs font-bold bg-black/20 px-3 py-1.5 rounded-lg">Checkout <i class="bi bi-chevron-right"></i></div>
        </div>
    </div>

    <div id="productModal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity opacity-0" id="modalBackdrop" onclick="closeModal('productModal')"></div>
        <div class="absolute inset-0 flex items-end md:items-center justify-center pointer-events-none p-0 md:p-4">
            <div id="productModalContent" class="bg-white w-full md:max-w-xl h-[90vh] md:h-auto md:max-h-[85vh] rounded-t-3xl md:rounded-2xl shadow-2xl flex flex-col overflow-hidden pointer-events-auto transform translate-y-full md:translate-y-0 md:scale-95 md:opacity-0 transition-all duration-300 relative border border-gray-200">
                <button onclick="closeModal('productModal')" class="absolute top-4 right-4 z-[60] w-8 h-8 bg-white/90 backdrop-blur-md text-gray-800 rounded-full flex items-center justify-center hover:bg-gray-100 transition shadow-md border border-gray-200"><i class="bi bi-x-lg text-sm font-bold"></i></button>
                <div class="w-full h-56 bg-gray-100 relative shrink-0"><img id="modalImg" src="" class="w-full h-full object-cover"></div>
                <div class="flex flex-col flex-1 min-h-0 bg-white relative z-10 rounded-t-3xl -mt-5">
                    <div class="p-5 border-b border-gray-100 relative shrink-0 pt-6">
                        <h2 id="modalName" class="text-xl font-bold text-gray-800 leading-tight mb-1 pr-8"></h2>
                        <div class="flex items-center justify-between mt-2">
                            <span id="modalPriceDisplay" class="text-lg font-bold text-green-600"></span>
                            <button onclick="askAdmin()" class="text-[10px] flex items-center gap-1 bg-green-50 text-green-600 px-2 py-1 rounded border border-green-200 font-bold hover:bg-green-600 hover:text-white transition"><i class="bi bi-whatsapp"></i> Tanya Admin</button>
                        </div>
                    </div>
                    <div class="p-5 overflow-y-auto custom-scrollbar flex-1 space-y-5 pb-8 text-gray-800">
                        <div><p class="text-xs font-bold text-gray-500 uppercase mb-1">Deskripsi</p><p id="modalDesc" class="text-sm text-gray-600 leading-relaxed"></p></div>
                        <div id="optionsContainer" class="space-y-4"></div>
                        <div><label class="text-xs font-bold text-gray-500 uppercase">Catatan Pesanan (Opsional)</label><input type="text" id="modalNote" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm mt-1 focus:border-green-500 focus:outline-none text-gray-800" placeholder="Contoh: Pedas, dipisah..."></div>
                    </div>
                    <div class="p-4 border-t border-gray-200 bg-white shrink-0 pb-safe shadow-[0_-5px_15px_rgba(0,0,0,0.05)]">
                        <div class="flex gap-3 items-center">
                            <div class="flex items-center bg-gray-50 rounded-xl border border-gray-200 h-12 shrink-0">
                                <button onclick="adjustQty(-1)" class="w-10 h-full text-gray-500 hover:text-gray-800 rounded-l-xl"><i class="bi bi-dash text-lg"></i></button>
                                <span id="modalQty" class="text-base font-bold w-8 text-center text-gray-800">1</span>
                                <button onclick="adjustQty(1)" class="w-10 h-full text-green-600 hover:text-green-700 rounded-r-xl"><i class="bi bi-plus text-lg font-bold"></i></button>
                            </div>
                            <button onclick="addToCartFromModal()" class="flex-1 bg-green-600 hover:bg-green-500 active:scale-95 text-white rounded-xl h-12 px-3 flex justify-between items-center transition shadow-lg px-4"><span class="text-sm font-bold">Tambah</span><span id="btnTotalPrice" class="text-sm font-bold">Rp 0</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="cartModal" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity opacity-0" id="cartBackdrop" onclick="closeModal('cartModal')"></div>
        <div class="absolute inset-0 flex items-end md:items-center justify-center p-0 md:p-4 pointer-events-none">
            <div class="bg-white w-full md:max-w-xl h-[95vh] md:h-auto md:max-h-[85vh] rounded-t-3xl md:rounded-2xl shadow-2xl flex flex-col transition-all duration-300 transform translate-y-full md:translate-y-0 md:scale-95 md:opacity-0 pointer-events-auto border border-gray-200" id="cartModalContent">
                <div class="flex justify-between items-center p-5 border-b border-gray-200 shrink-0 bg-gray-50 rounded-t-3xl md:rounded-t-2xl">
                    <h3 class="font-bold text-xl text-gray-800">Keranjang Saya</h3>
                    <button onclick="closeModal('cartModal')" class="w-8 h-8 rounded-full bg-white flex items-center justify-center text-gray-600 hover:bg-gray-100 border border-gray-200 transition shadow-sm"><i class="bi bi-x-lg text-sm"></i></button>
                </div>
                
                <div class="flex-1 overflow-y-auto custom-scrollbar bg-white" id="checkoutScrollArea">
                    <div id="cartListUI" class="p-5 space-y-3 pb-4 border-b border-gray-100"></div>
                    
                    <div class="bg-white mt-2 pb-6">
                        <div class="grid grid-cols-2 text-center border-b border-gray-100 sticky top-0 bg-white z-10 p-3 gap-3">
                            <div onclick="setOrderType('pickup')" id="tabPickup" class="py-2.5 text-sm font-bold cursor-pointer bg-green-50 text-green-600 rounded-lg border border-green-200 transition"><i class="bi bi-shop mr-1"></i> Ambil / Makan</div>
                            <div onclick="setOrderType('delivery')" id="tabDelivery" class="py-2.5 text-sm font-bold cursor-pointer text-gray-500 rounded-lg border border-transparent hover:bg-gray-50 transition"><i class="bi bi-truck mr-1"></i> Delivery</div>
                        </div>
                        <div class="p-5 space-y-5">
                            <div class="space-y-4">
                                <div><label class="text-xs font-bold text-gray-500 ml-1 uppercase">Nama</label><input type="text" id="custName" value="<?php echo htmlspecialchars($member_name); ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none" placeholder="Nama Kamu (Wajib)"></div>
                                <div><label class="text-xs font-bold text-gray-500 ml-1 uppercase">No. WhatsApp</label><input type="text" id="custContact" value="<?php echo htmlspecialchars($member_phone); ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none" placeholder="08xxxxxxxx"></div>
                                
                                <div id="deliveryInput" class="hidden space-y-4">
                                    <div>
                                        <label class="text-xs font-bold text-gray-500 ml-1 uppercase">Kecamatan Tujuan</label>
                                        <div class="relative">
                                            <select id="custDistrict" onchange="updateDeliveryFee()" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none appearance-none">
                                                <option value="" disabled selected>-- Pilih Kecamatan --</option>
                                                <?php foreach($districts as $d): ?>
                                                    <option value="<?php echo htmlspecialchars($d['district_name']); ?>"><?php echo htmlspecialchars($d['district_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="bi bi-chevron-down absolute right-4 top-3.5 text-gray-400 pointer-events-none"></i>
                                        </div>
                                    </div>
                                    <div><label class="text-xs font-bold text-gray-500 ml-1 uppercase">Alamat Lengkap</label><textarea id="custAddress" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 h-20 focus:border-green-500 focus:outline-none" placeholder="Patokan, RT/RW..."></textarea></div>
                                </div>

                                <div id="pickupInput"><label class="text-xs font-bold text-gray-500 ml-1 uppercase">No. Meja / Waktu Ambil</label><input type="text" id="custTable" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none" placeholder="Isi jika makan di tempat"></div>
                            </div>
                            <div class="pt-2">
                                <label class="text-xs font-bold text-gray-500 ml-1 uppercase mb-2 block">Pembayaran</label>
                                <div class="flex gap-2">
                                    <label class="flex-1 cursor-pointer"><input type="radio" name="payMethod" value="cash" class="peer sr-only" checked onchange="toggleQR(false)"><div class="text-center py-3 rounded-xl bg-gray-50 border border-gray-200 peer-checked:border-green-500 peer-checked:bg-green-50 text-xs text-gray-500 peer-checked:text-green-600 font-bold transition shadow-sm">Bayar Tunai</div></label>
                                    <label class="flex-1 cursor-pointer"><input type="radio" name="payMethod" value="qris" class="peer sr-only" onchange="toggleQR(true)"><div class="text-center py-3 rounded-xl bg-gray-50 border border-gray-200 peer-checked:border-green-500 peer-checked:bg-green-50 text-xs text-gray-500 peer-checked:text-green-600 font-bold transition shadow-sm">QRIS / Transfer</div></label>
                                </div>
                            </div>
                            <div id="qrArea" class="hidden bg-white p-4 rounded-xl flex flex-col items-center justify-center shadow-inner border border-gray-200 mt-2">
                                <div id="qris-container"></div>
                                <p class="text-gray-800 text-xs font-bold mt-2 text-center" id="qrNominalDisplay">Scan untuk bayar</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-5 border-t border-gray-200 bg-white shrink-0 z-20 md:rounded-b-2xl shadow-[0_-10px_20px_rgba(0,0,0,0.05)] pb-safe">
                    <div class="space-y-1.5 mb-4 px-1">
                        <div class="flex justify-between text-sm text-gray-500"><span>Subtotal</span><span id="sumSubtotal" class="font-medium text-gray-800">Rp 0</span></div>
                        <?php if($shop['service_fee'] > 0): ?><div class="flex justify-between text-sm text-gray-500"><span><?php echo htmlspecialchars($shop['fee_label']); ?></span><span class="text-gray-800">+Rp <?php echo number_format($shop['service_fee']); ?></span></div><?php endif; ?>
                        <div id="rowOngkir" class="flex justify-between text-sm text-gray-500 hidden"><span>Ongkir</span><span id="feeOngkir" class="text-gray-800">+Rp 0</span></div>
                        <div id="rowVoucher" class="flex justify-between text-sm text-green-600 font-bold hidden"><span>Diskon / Voucher</span><span id="sumVoucher">-Rp 0</span></div>
                        <div class="flex justify-between text-xl font-bold text-gray-800 pt-3 border-t border-gray-200 mt-2"><span>Total</span><span id="sumTotal" class="text-green-600">Rp 0</span></div>
                    </div>
                    <button type="button" onclick="processOrder()" class="w-full py-4 rounded-xl bg-green-600 hover:bg-green-500 text-white font-bold shadow-lg shadow-green-600/30 flex justify-center gap-2 transition active:scale-95 text-base"><i class="bi bi-whatsapp text-lg leading-none"></i> Buat Pesanan</button>
                </div>
            </div>
        </div>
    </div>

    <div id="receiptTemplate">
        <div style="text-align:center; margin-bottom:10px;">
            <h2 style="font-weight:bold; font-size:18px; margin:0"><?php echo htmlspecialchars($shop['shop_name']); ?></h2>
            <div style="font-size:12px; font-weight:bold; margin-top:5px;">KODE: <span id="recUniqueCode"></span></div>
        </div>
        <div style="border-bottom:1px dashed #000; margin-bottom:8px;"></div>
        <div style="font-size:11px; line-height:1.4;"><div>Tgl: <span id="recDate"></span></div><div>Nama: <span id="recName"></span></div><div>Lokasi: <span id="recLoc"></span></div></div>
        <div style="border-bottom:1px dashed #000; margin:8px 0;"></div>
        <div id="receiptItems" style="font-size:11px; margin-bottom:10px;"></div>
        <div style="border-bottom:1px dashed #000; margin-bottom:8px;"></div>
        <div style="font-size:11px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:2px;"><span>Subtotal</span><span id="recSub"></span></div>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px; font-style:italic;"><span><?php echo htmlspecialchars($shop['fee_label']); ?></span><span id="recFee"></span></div>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px;" id="recDelRow"><span>Ongkir</span><span id="recDel"></span></div>
            <div style="display:flex; justify-content:space-between; margin-bottom:2px;" id="recVoucherRow"><span>Voucher</span><span id="recVoucher"></span></div>
            <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:14px; margin-top:5px;"><span>TOTAL BAYAR</span><span id="recTotal"></span></div>
        </div>
        <div style="text-align:center; margin-top:15px; font-size:10px; font-weight:bold;">SCAN QRIS PEMBAYARAN</div>
        <div id="receiptQR" style="text-align:center; margin:5px 0;"></div>
        <div style="border:2px solid #000; padding:8px; margin-top:10px; text-align:center;"><div style="font-weight:bold; font-size:12px; color:red;">PENTING!</div><div style="font-size:10px; margin-top:2px;">Segera lakukan pembayaran &</div><div style="font-size:10px;">Konfirmasi ke Admin</div><div style="font-size:9px; margin-top:3px;">WhatsApp: <?php echo htmlspecialchars($shop['wa_number']); ?></div></div>
    </div>

    <script>
        const CONFIG = {
            qris: <?php echo json_encode(trim($shop['qris_data'])); ?>,
            wa: "<?php echo htmlspecialchars($shop['wa_number']); ?>",
            serviceFee: <?php echo $shop['service_fee']; ?>,
            voucherAmount: <?php echo $voucher_amount; ?>,
            voucherEligible: <?php echo $voucher_eligible ? 'true' : 'false'; ?>,
            userId: <?php echo $user_id; ?>
        };

        const DISTRICT_FEES = <?php echo $district_fees_json; ?>;
        let currentDeliveryFee = 0;

        let cart = JSON.parse(localStorage.getItem('pos_cart')) || [];
        let currentProduct = null; let currentQty = 1; let orderType = 'pickup';

        window.onload = function() { 
            if(cart.length > 0) renderCartFloating();
        };

        function checkSecretLogin(input) {
            const term = input.value.toLowerCase();
            document.querySelectorAll('.category-item').forEach(item => { item.style.display = item.textContent.toLowerCase().includes(term) ? 'flex' : 'none'; });
            if (term === 'login admin') { window.location.href = 'login.php'; }
        }

        function generateUniqueCode() { const c='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; let r=''; for(let i=0;i<5;i++) r+=c.charAt(Math.floor(Math.random()*c.length)); return 'ORD-'+r; }
        function toCRC16(i){let c=0xffff;for(let k=0;k<i.length;k++){c^=i.charCodeAt(k)<<8;for(let j=0;j<8;j++)c=c&0x8000?(c<<1)^0x1021:c<<1}let h=(c&0xffff).toString(16).toUpperCase();return h.length===3?"0"+h:h}
        function makeString(q,{nominal}){if(!q)return"";let m=q.slice(0,-4).replace("010211","010212"),p=m.split("5802ID"),a="54"+(nominal<10?"0"+nominal:nominal.toString()).length+nominal+"5802ID",o=p[0].trim()+a+p[1].trim();return o+toCRC16(o)}
        function generateDynamicQRIS(amount,id,size=150){const c=document.getElementById(id);if(!c||!CONFIG.qris)return;c.innerHTML="";QRCode.toCanvas(makeString(CONFIG.qris,{nominal:amount}),{margin:1,width:size},function(e,v){if(!e){v.style.width="100%";v.style.height="auto";c.appendChild(v)}});if(id==="qris-container")document.getElementById('qrNominalDisplay').innerText="Scan Bayar: Rp "+amount.toLocaleString('id-ID')}

        function openProductModal(product, variants, toppings) {
            currentProduct = product; currentQty = 1;
            document.getElementById('modalName').innerText = product.name;
            document.getElementById('modalDesc').innerText = product.description || "Tidak ada deskripsi";
            document.getElementById('modalImg').src = product.image.includes('http') ? product.image : (product.image == 'default.png' ? 'https://placehold.co/400x400' : product.image);
            document.getElementById('modalQty').innerText = 1;
            document.getElementById('modalNote').value = "";
            
            const container = document.getElementById('optionsContainer');
            container.innerHTML = '';

            if(product.product_type === 'retail') {
                document.getElementById('modalPriceDisplay').innerText = "Varian";
                let html = `<p class="text-xs font-bold text-gray-500 uppercase mb-2">Pilih Varian</p><div class="space-y-2">`;
                variants.forEach((v, i) => {
                    html += `<label class="flex justify-between p-3 bg-gray-50 rounded-xl border border-gray-200 cursor-pointer items-center transition hover:bg-gray-100"><div class="flex items-center gap-3"><input type="radio" name="vOpt" value="${v.id}" data-name="${v.variant_name}" data-price="${v.price}" ${i===0?'checked':''} onchange="updatePrice()" class="w-4 h-4 accent-green-500"><span class="text-sm font-semibold text-gray-700">${v.variant_name}</span></div><span class="text-xs font-bold text-green-600">Rp ${parseInt(v.price).toLocaleString()}</span></label>`;
                });
                html += `</div>`;
                container.innerHTML = html;
            } else {
                document.getElementById('modalPriceDisplay').innerText = "Rp " + parseInt(product.base_price).toLocaleString();
                if(toppings.length>0) {
                    let html = `<p class="text-xs font-bold text-gray-500 uppercase mb-2">Tambahan (Topping)</p><div class="grid grid-cols-1 gap-2">`;
                    toppings.forEach(t => {
                        html += `<label class="flex justify-between p-3 bg-gray-50 rounded-xl border border-gray-200 cursor-pointer items-center transition hover:bg-gray-100"><div class="flex items-center gap-3"><input type="checkbox" class="tCheck w-4 h-4 accent-green-500" value="${t.id}" data-name="${t.modifier_name}" data-price="${t.extra_price}" onchange="updatePrice()"><span class="text-sm font-semibold text-gray-700">${t.modifier_name}</span></div><span class="text-xs font-bold text-green-600">+${parseInt(t.extra_price).toLocaleString()}</span></label>`;
                    });
                    html += `</div>`;
                    container.innerHTML = html;
                }
            }
            updatePrice();
            const modal = document.getElementById('productModal');
            const backdrop = document.getElementById('modalBackdrop');
            const content = document.getElementById('productModalContent');
            modal.classList.remove('hidden'); void modal.offsetWidth;
            backdrop.classList.remove('opacity-0');
            content.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
        }

        function updatePrice() {
            let price = 0;
            if(currentProduct.product_type === 'retail') {
                const sel = document.querySelector('input[name="vOpt"]:checked');
                if(sel) price = parseInt(sel.dataset.price);
            } else { price = parseInt(currentProduct.base_price); }
            document.querySelectorAll('.tCheck:checked').forEach(cb => price += parseInt(cb.dataset.price));
            document.getElementById('btnTotalPrice').innerText = "Rp " + (price * currentQty).toLocaleString('id-ID');
            return price;
        }

        function adjustQty(n) {
            currentQty += n; if(currentQty<1) currentQty=1;
            document.getElementById('modalQty').innerText = currentQty;
            updatePrice();
        }

        function addToCartFromModal() {
            let finalPrice = updatePrice();
            let vName = null; let tList = [];
            if(currentProduct.product_type === 'retail') {
                const sel = document.querySelector('input[name="vOpt"]:checked');
                if(sel) vName = sel.dataset.name;
            }
            document.querySelectorAll('.tCheck:checked').forEach(cb => tList.push({name: cb.dataset.name, price: parseInt(cb.dataset.price)}));

            let uid = currentProduct.id + (vName||'') + JSON.stringify(tList);
            let exist = cart.find(i => i.uid === uid);
            if(exist) exist.qty += currentQty;
            else cart.push({ uid, id: currentProduct.id, name: currentProduct.name, price: finalPrice, qty: currentQty, variant: vName, toppings: tList, note: document.getElementById('modalNote').value });

            localStorage.setItem('pos_cart', JSON.stringify(cart));
            renderCartFloating(); closeModal('productModal');
            Swal.fire({icon:'success', title:'Masuk Keranjang', toast:true, position:'top', showConfirmButton:false, timer:1000, background:'#ffffff', color:'#1e293b'});
        }

        function askAdmin() {
            let msg = `Halo Admin, stok *${currentProduct.name}* masih ada?`;
            window.open(`https://wa.me/${CONFIG.wa}?text=${encodeURIComponent(msg)}`, '_blank');
        }

        function renderCartFloating() {
            let count = cart.reduce((n, i) => n + i.qty, 0);
            let total = cart.reduce((n, i) => n + (i.price * i.qty), 0); 
            
            const badge = document.getElementById('navCartBadge');
            if(count > 0) { badge.innerText = count; badge.classList.remove('hidden'); } 
            else { badge.classList.add('hidden'); }
            
            const el = document.getElementById('floatingCart');
            if(el) { 
                if(count > 0) { 
                    el.classList.remove('hidden', 'translate-y-48'); 
                    document.getElementById('floatCount').innerText = count; 
                    document.getElementById('floatTotal').innerText = "Rp " + total.toLocaleString('id-ID'); 
                } else { el.classList.add('hidden'); } 
            }
        }

        function openCartModal() {
            if(cart.length === 0) { Swal.fire({title:'Kosong', text:'Keranjang belanja masih kosong', icon:'info', confirmButtonColor:'#10b981'}); return; }
            const con = document.getElementById('cartListUI');
            con.innerHTML = '';
            
            cart.forEach((item, i) => {
                let desc = item.variant ? `<span class="bg-indigo-50 text-indigo-600 text-[10px] px-1.5 py-0.5 rounded mr-1 border border-indigo-200">${item.variant}</span>` : '';
                item.toppings.forEach(t => desc += `<span class="bg-gray-100 text-gray-600 text-[10px] px-1.5 py-0.5 rounded mr-1 border border-gray-200">+${t.name}</span>`);
                con.innerHTML += `
                <div class="flex justify-between items-start border-b border-gray-200 pb-4 mb-3 last:border-0 last:mb-0">
                    <div class="flex-1 pr-2">
                        <div class="font-bold text-sm text-gray-800">${item.name}</div>
                        <div class="mb-1 leading-tight mt-1">${desc}</div>
                        <div class="text-xs text-green-600 font-bold mt-1">Rp ${item.price.toLocaleString()} <span class="text-gray-500 font-normal">x ${item.qty}</span></div>
                        ${item.note ? `<div class="text-[10px] text-orange-500 italic mt-1 bg-orange-50 inline-block px-1.5 py-0.5 rounded border border-orange-100">Cat: ${item.note}</div>` : ''}
                    </div>
                    <div class="flex flex-col items-end gap-3">
                        <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1 border border-gray-200">
                            <button onclick="updateCartItemQty(${i}, -1)" class="w-7 h-7 flex items-center justify-center text-gray-600 hover:bg-gray-200 rounded"><i class="bi bi-dash"></i></button>
                            <span class="text-xs font-bold text-gray-800 w-5 text-center">${item.qty}</span>
                            <button onclick="updateCartItemQty(${i}, 1)" class="w-7 h-7 flex items-center justify-center text-green-600 hover:bg-green-100 rounded"><i class="bi bi-plus"></i></button>
                        </div>
                        <button onclick="removeItem(${i})" class="text-[10px] text-red-500 font-bold hover:text-red-600 flex items-center gap-1 bg-red-50 px-2 py-1 rounded border border-red-100"><i class="bi bi-trash"></i> Hapus</button>
                    </div>
                </div>`;
            });
            calculateTotal();
            const modal = document.getElementById('cartModal');
            const backdrop = document.getElementById('cartBackdrop');
            const content = document.getElementById('cartModalContent');
            modal.classList.remove('hidden'); void modal.offsetWidth;
            backdrop.classList.remove('opacity-0');
            content.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
        }

        function updateCartItemQty(index, change) {
            cart[index].qty += change;
            if(cart[index].qty <= 0) { if(confirm("Hapus item ini?")) cart.splice(index, 1); else cart[index].qty = 1; }
            localStorage.setItem('pos_cart', JSON.stringify(cart));
            if(cart.length === 0) closeModal('cartModal'); else openCartModal(); 
            renderCartFloating();
        }

        function removeItem(i) {
            cart.splice(i, 1); localStorage.setItem('pos_cart', JSON.stringify(cart));
            renderCartFloating(); openCartModal(); if(cart.length === 0) closeModal('cartModal');
        }

        function updateDeliveryFee() {
            const dist = document.getElementById('custDistrict').value;
            if(dist && DISTRICT_FEES[dist] !== undefined) {
                currentDeliveryFee = DISTRICT_FEES[dist];
            } else {
                currentDeliveryFee = 0;
            }
            calculateTotal();
        }

        function calculateTotal() {
            let subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            let total = subtotal + CONFIG.serviceFee;
            
            if(orderType === 'delivery') {
                total += currentDeliveryFee;
                document.getElementById('feeOngkir').innerText = "+Rp " + currentDeliveryFee.toLocaleString();
            }

            let discount = 0;
            if(CONFIG.voucherEligible && CONFIG.userId) discount = (total >= CONFIG.voucherAmount) ? CONFIG.voucherAmount : total;
            total = total - discount;

            document.getElementById('sumSubtotal').innerText = "Rp " + subtotal.toLocaleString();
            const rowVoucher = document.getElementById('rowVoucher');
            const elVoucher = document.getElementById('sumVoucher');
            if(discount > 0) { rowVoucher.classList.remove('hidden'); elVoucher.innerText = "-Rp " + discount.toLocaleString(); } 
            else { rowVoucher.classList.add('hidden'); }

            document.getElementById('sumTotal').innerText = "Rp " + total.toLocaleString();
            if(!document.getElementById('qrArea').classList.contains('hidden')) generateDynamicQRIS(total, "qris-container");
            return { subtotal, total, discount };
        }

        function setOrderType(type) {
            orderType = type;
            document.getElementById('tabPickup').className = type === 'pickup' ? 'py-2.5 text-sm font-bold cursor-pointer bg-green-50 text-green-600 rounded-lg border border-green-200 transition' : 'py-2.5 text-sm font-bold cursor-pointer text-gray-500 rounded-lg border border-transparent hover:bg-gray-50 transition';
            document.getElementById('tabDelivery').className = type === 'delivery' ? 'py-2.5 text-sm font-bold cursor-pointer bg-green-50 text-green-600 rounded-lg border border-green-200 transition' : 'py-2.5 text-sm font-bold cursor-pointer text-gray-500 rounded-lg border border-transparent hover:bg-gray-50 transition';
            if(type === 'pickup') { 
                document.getElementById('pickupInput').classList.remove('hidden'); 
                document.getElementById('deliveryInput').classList.add('hidden'); 
                document.getElementById('rowOngkir').classList.add('hidden'); 
            } else { 
                document.getElementById('pickupInput').classList.add('hidden'); 
                document.getElementById('deliveryInput').classList.remove('hidden'); 
                document.getElementById('rowOngkir').classList.remove('hidden'); 
                updateDeliveryFee();
            }
            calculateTotal();
        }

        function toggleQR(show) {
            document.getElementById('qrArea').classList.toggle('hidden', !show);
            if(show) { let t = calculateTotal(); generateDynamicQRIS(t.total, "qris-container"); setTimeout(() => { const s = document.getElementById('checkoutScrollArea'); s.scrollTop = s.scrollHeight; }, 100); }
        }

        function closeModal(id) {
            if(id === 'productModal') {
                const modal = document.getElementById('productModal');
                document.getElementById('modalBackdrop').classList.add('opacity-0');
                document.getElementById('productModalContent').classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0');
                setTimeout(() => modal.classList.add('hidden'), 300);
            } else if(id === 'cartModal') {
                const modal = document.getElementById('cartModal');
                document.getElementById('cartBackdrop').classList.add('opacity-0');
                document.getElementById('cartModalContent').classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0');
                setTimeout(() => modal.classList.add('hidden'), 300);
            } else {
                document.getElementById(id+'Content').classList.add('translate-y-full', 'md:translate-x-full');
                setTimeout(() => document.getElementById(id).classList.add('hidden'), 300);
            }
        }

        function processOrder() {
            const name = document.getElementById('custName').value;
            const wa = document.getElementById('custContact').value;
            if(!name) { Swal.fire('Info', 'Nama pemesan wajib diisi', 'warning'); return; }
            
            const totals = calculateTotal();
            const uniqueCode = generateUniqueCode(); 
            let locationInfo = "";
            
            if (orderType === 'pickup') {
                locationInfo = "Meja/Jam: " + (document.getElementById('custTable').value || '-');
            } else {
                const dist = document.getElementById('custDistrict').value;
                if(!dist) { Swal.fire('Info', 'Silakan pilih Kecamatan pengiriman', 'warning'); return; }
                const detailAddress = document.getElementById('custAddress').value || '-';
                locationInfo = "Alamat: " + detailAddress + " (Kec. " + dist + ")";
            }

            const orderData = {
                unique_code: uniqueCode, customer_name: name, customer_wa: wa, location: locationInfo,
                total_amount: totals.total, discount_amount: totals.discount, user_id: CONFIG.userId,
                payment_method: document.querySelector('input[name="payMethod"]:checked').value, items: cart
            };

            Swal.fire({ title: 'Memproses...', didOpen: () => Swal.showLoading() });

            fetch('save_order.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(orderData) })
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if(data.status === 'success') { generateReceiptAndWA(uniqueCode, name, locationInfo, totals); } 
                    else { Swal.fire({title:'Gagal', text:'Server: ' + data.message, icon:'error'}); }
                } catch (e) { Swal.fire({title:'Error', text:'Respon server tidak valid.', icon:'error'}); }
            }).catch(err => { Swal.fire({title:'Error', text:'Gagal koneksi server', icon:'error'}); });
        }

        function generateReceiptAndWA(uniqueCode, name, loc, totals) {
            let msg = `*PESANAN BARU - ${'<?php echo $shop['shop_name']; ?>'}*\n`;
            msg += `Kode: *${uniqueCode}*\nNama: ${name}\nTipe: ${orderType === 'pickup' ? 'AMBIL SENDIRI' : 'DIKIRIM'}\n${loc}\n--------------------------------\n`;
            let receiptHtml = "";
            cart.forEach(i => {
                msg += `${i.qty}x ${i.name} ${i.variant ? '('+i.variant+')' : ''}\n`;
                if(i.toppings.length > 0) msg += `   +${i.toppings.map(t=>t.name).join(',')}\n`;
                if(i.note) msg += `   Cat: ${i.note}\n`;
                receiptHtml += `<div style="margin-bottom:4px;"><div>${i.name} ${i.variant?'('+i.variant+')':''}</div><div style="display:flex; justify-content:space-between;"><span>${i.qty}x</span><span>${(i.price*i.qty).toLocaleString()}</span></div></div>`;
            });
            msg += `--------------------------------\nSubtotal: Rp ${totals.subtotal.toLocaleString()}\nLayanan: Rp ${CONFIG.serviceFee.toLocaleString()}\n`;
            if(orderType === 'delivery') msg += `Ongkir: Rp ${currentDeliveryFee.toLocaleString()}\n`;
            if(totals.discount > 0) msg += `Voucher: -Rp ${totals.discount.toLocaleString()}\n`;
            msg += `*TOTAL: Rp ${totals.total.toLocaleString()}*\nMetode: ${document.querySelector('input[name="payMethod"]:checked').value.toUpperCase()}`;

            document.getElementById('recUniqueCode').innerText = uniqueCode;
            document.getElementById('recDate').innerText = new Date().toLocaleString('id-ID');
            document.getElementById('recName').innerText = name;
            document.getElementById('recLoc').innerText = loc;
            document.getElementById('receiptItems').innerHTML = receiptHtml;
            document.getElementById('recSub').innerText = "Rp " + totals.subtotal.toLocaleString();
            document.getElementById('recFee').innerText = "Rp " + CONFIG.serviceFee.toLocaleString();
            if(orderType === 'delivery') { document.getElementById('recDelRow').style.display = 'flex'; document.getElementById('recDel').innerText = "Rp " + currentDeliveryFee.toLocaleString(); } else { document.getElementById('recDelRow').style.display = 'none'; }
            if(totals.discount > 0) { document.getElementById('recVoucherRow').style.display = 'flex'; document.getElementById('recVoucher').innerText = "-Rp " + totals.discount.toLocaleString(); } else { document.getElementById('recVoucherRow').style.display = 'none'; }
            document.getElementById('recTotal').innerText = "Rp " + totals.total.toLocaleString();
            const recQris = document.getElementById('receiptQR'); recQris.innerHTML = "";
            if(document.querySelector('input[name="payMethod"]:checked').value === 'qris' && CONFIG.qris) { generateDynamicQRIS(totals.total, "receiptQR", 100); }

            setTimeout(() => {
                html2canvas(document.querySelector("#receiptTemplate")).then(canvas => {
                    let link = document.createElement('a'); link.download = `Struk_${uniqueCode}.jpg`; link.href = canvas.toDataURL("image/jpeg", 0.9); link.click();
                    localStorage.removeItem('pos_cart');
                    window.open(`https://wa.me/${CONFIG.wa}?text=${encodeURIComponent(msg)}`, '_blank');
                    Swal.fire({ title: 'Terima Kasih!', text: 'Pesanan berhasil dibuat. Mohon lanjutkan kirim pesan di WhatsApp yang terbuka.', icon: 'success', confirmButtonText: 'Siap!', confirmButtonColor: '#10b981' }).then(() => { window.location.reload(); });
                });
            }, 1000);
        }

        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.category-btn').forEach(b => { b.classList.remove('bg-green-500', 'text-white', 'shadow-md'); b.classList.add('bg-white', 'text-gray-600'); });
                btn.classList.remove('bg-white', 'text-gray-600'); btn.classList.add('bg-green-500', 'text-white', 'shadow-md');
                const cat = btn.dataset.cat;
                document.querySelectorAll('.category-item').forEach(i => { i.style.display = (cat === 'all' || i.dataset.category === cat) ? 'flex' : 'none'; });
            });
        });
    </script>
</body>
</html>