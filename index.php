<?php
session_start();
include 'koneksi.php';

// FIX EMOJI
@mysqli_set_charset($conn, "utf8mb4");

// 1. AMBIL SETTINGS
$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));

// Ambil Teks & Link Komunitas
$c_text = !empty($shop['community_text']) ? $shop['community_text'] : 'Gabung Komunitas Kami';
$c_link = !empty($shop['community_link']) ? $shop['community_link'] : '';

// Ambil Banner & Cards
$q_banners = mysqli_query($conn, "SELECT * FROM shop_banners ORDER BY id DESC");
$banners = []; while($b = mysqli_fetch_assoc($q_banners)) $banners[] = $b;

$q_cards = mysqli_query($conn, "SELECT * FROM home_cards ORDER BY sort_order ASC, id ASC");

// 2. AMBIL DATA KECAMATAN & ONGKIR UNTUK JAVASCRIPT
$q_districts = mysqli_query($conn, "SELECT district_name, delivery_fee FROM districts ORDER BY district_name ASC");
$districts = [];
$district_fees_js = [];

if($q_districts){
    while($d = mysqli_fetch_assoc($q_districts)){
        $districts[] = $d;
        $district_fees_js[$d['district_name']] = (int)$d['delivery_fee'];
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

// 4. REFERRAL CHECK
$ref_active = $shop['referral_active'] ?? 0;

// 5. KATEGORI & FORMAT LAYANAN
$q_cat = mysqli_query($conn, "SELECT DISTINCT category FROM products WHERE product_status='ready' ORDER BY category ASC");
$service_text_formatted = str_replace([', ', ','], ' ○ ', $shop['service_text']);

// 6. AMBIL SEMUA BADGE UNTUK SECTION DINAMIS
$q_badges = mysqli_query($conn, "SELECT * FROM badges ORDER BY sort_order ASC, id ASC");
$badges_list = [];
if($q_badges) {
    while($b = mysqli_fetch_assoc($q_badges)) {
        $badges_list[] = $b;
    }
}

// 7. AMBIL ARTIKEL / BERITA YANG PUBLISHED
$q_berita = mysqli_query($conn, "SELECT * FROM articles WHERE status='published' ORDER BY created_at DESC LIMIT 10");
$berita_list = [];
if($q_berita) {
    while($art = mysqli_fetch_assoc($q_berita)) {
        $berita_list[] = $art;
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($shop['shop_name']); ?></title>
    
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
        body { background-color: #f8fafc; color: #1e293b; overflow-x: hidden; -webkit-tap-highlight-color: transparent; padding-bottom: 120px; }
        .hide-scroll::-webkit-scrollbar { display: none; }
        
        /* HEADER STYLE */
        .top-header { background: white; padding: 12px 16px 8px 16px; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 50; }
        .btn-outline { background: white; border: 1.5px solid #16a34a; color: #16a34a; border-radius: 9999px; display: flex; align-items: center; justify-content: center; font-weight: bold; transition: 0.2s; }
        .btn-outline:hover { background: #f0fdf4; }
        
        /* SLIDER & CARDS */
        .slider-container { width: 100%; height: 250px; position: relative; overflow: hidden; }
        @media (min-width: 768px) { .slider-container { height: 380px; } }
        
        .slide { position: absolute; inset: 0; opacity: 0; transition: opacity 0.5s ease-in-out; }
        .slide.active { opacity: 1; }
        .slide img { width: 100%; height: 100%; object-fit: cover; object-position: center; }
        
        /* KARTU SHORTCUT */
        .menu-card { background: white; border-radius: 16px; padding: 16px 8px; text-align: center; box-shadow: 0 10px 25px -3px rgba(0,0,0,0.15); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; border: 1px solid #f8fafc; transition: all 0.3s ease; }
        .menu-card:hover { transform: translateY(-4px); box-shadow: 0 15px 30px -5px rgba(0,0,0,0.2); }
        .menu-card .icon-box { background: #86efac; color: white; width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 20px; box-shadow: 0 4px 10px rgba(134,239,172,0.4); }
        
        /* PRODUK */
        .product-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .product-card:active { transform: scale(0.98); }
        .sold-out-layer { position: absolute; inset: 0; background: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center; z-index: 10; backdrop-filter: blur(2px); }
        
        /* BOTTOM NAV */
        .nav-item { color: #94a3b8; font-size: 10px; font-weight: 700; display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; padding: 10px 0; transition: 0.2s; }
        .nav-item.active { color: #10b981; }
        
        /* MODAL & STRUK */
        #receiptTemplate { position: fixed; left: -9999px; top: 0; width: 350px; background: white; color: black; z-index: -1; padding: 20px; font-family: monospace; }
        input:checked + div { border-color: #10b981; background-color: rgba(16, 185, 129, 0.1); }
        .pb-safe { padding-bottom: env(safe-area-inset-bottom, 20px); }
        select option { background-color: white; color: #1e293b; }
        
        /* ARTIKEL CARD */
        .article-card { transition: transform 0.2s; }
        .article-card:active { transform: scale(0.98); }

        /* PROGRESS BAR ANIMATION */
        @keyframes shrinkProgress {
            from { width: 100%; }
            to { width: 0%; }
        }
        .animate-progress { animation: shrinkProgress 3s linear forwards; }
    </style>
</head>
<body> 

    <div id="infoPopup" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm hidden transition-opacity duration-500 opacity-0 px-4">
        <div class="bg-white p-6 md:p-8 rounded-3xl w-full max-w-sm text-center shadow-2xl transform scale-90 transition-transform duration-300 relative overflow-hidden" id="infoPopupContent">
            <button onclick="closeInfoPopup()" class="absolute top-4 right-4 w-8 h-8 bg-gray-100 text-gray-500 rounded-full flex items-center justify-center hover:bg-gray-200 transition"><i class="bi bi-x-lg text-sm"></i></button>
            <div class="w-16 h-16 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-4 text-green-500">
                <i class="bi bi-megaphone-fill text-2xl animate-bounce"></i>
            </div>
            <h3 class="text-lg md:text-xl font-extrabold text-gray-800 mb-2 leading-tight">Info <?php echo htmlspecialchars($shop['shop_name']); ?></h3>
            <p class="text-gray-600 text-sm leading-relaxed mb-6 font-medium"><?php echo htmlspecialchars($shop['running_text'] ? $shop['running_text'] : 'Selamat datang, silakan pesan menu favoritmu!'); ?></p>
            
            <div class="w-full bg-gray-100 h-1.5 rounded-full overflow-hidden mb-2 relative">
                <div id="popupProgressBar" class="bg-green-500 h-full w-full rounded-full"></div>
            </div>
            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest text-center" id="popupCountdownText">Tutup otomatis dalam 3 detik</div>
        </div>
    </div>

    <div id="searchModal" class="fixed inset-0 z-[70] hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity opacity-0" id="searchBackdrop" onclick="closeSearchModal()"></div>
        <div class="absolute top-4 w-full max-w-2xl mx-auto left-0 right-0 px-4 pointer-events-none z-10">
            <div class="bg-white rounded-2xl shadow-2xl flex items-center p-2 pointer-events-auto transform -translate-y-24 transition-transform duration-300" id="searchModalContent">
                <i class="bi bi-search text-gray-400 ml-3 text-lg"></i>
                <input type="text" id="searchInput" onkeyup="checkSearchFilter(this)" placeholder="Ketik nama menu yang dicari..." class="w-full px-4 py-3 bg-transparent text-sm focus:outline-none text-gray-800 font-medium">
                <button onclick="closeSearchModal()" class="w-10 h-10 shrink-0 rounded-xl bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-200 transition"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
    </div>

    <header class="top-header">
        <div class="max-w-5xl mx-auto">
            <div class="flex justify-between items-center mb-3 pb-1 gap-2">
                <?php if(!empty($shop['maps_link'])): ?>
                    <a href="<?php echo htmlspecialchars($shop['maps_link']); ?>" target="_blank" class="flex items-center gap-1 text-gray-500 text-[11px] hover:text-green-600 flex-1 min-w-0">
                        <i class="bi bi-geo-alt text-gray-400 shrink-0"></i>
                        <span class="truncate"><?php echo htmlspecialchars($shop['shop_name']); ?></span>
                    </a>
                <?php else: ?>
                    <div class="flex-1"></div>
                <?php endif; ?>

                <?php if($shop['show_halal']): ?>
                <a href="<?php echo !empty($shop['halal_link']) ? htmlspecialchars($shop['halal_link']) : '#'; ?>" target="_blank" class="flex items-center gap-1 shrink-0 hover:opacity-80 transition bg-purple-50/80 border border-purple-100 px-1.5 py-0.5 rounded ml-auto">
                    <img src="https://pasteimg.com/images/2026/02/28/halal.png" class="h-3.5 object-contain" alt="Halal">
                    <span class="text-[9px] font-bold" style="color: #6a2a88;"><?php echo !empty($shop['halal_id']) ? htmlspecialchars($shop['halal_id']) : 'ID32410028681530925'; ?></span>
                </a>
                <?php endif; ?>
            </div>

            <div class="flex justify-between items-center mb-2">
                <div class="flex items-center gap-2">
                    <img src="<?php echo htmlspecialchars($shop['logo']); ?>?v=<?php echo time(); ?>" class="h-10 w-auto object-contain" alt="Logo">
                    <?php if(isset($shop['show_shop_name']) && $shop['show_shop_name'] == 1): ?>
                        <h1 class="font-extrabold text-green-900 text-[15px] md:text-lg leading-none tracking-tight"><?php echo htmlspecialchars($shop['shop_name']); ?></h1>
                    <?php endif; ?>
                </div>
                
                <div class="flex items-center gap-2">
                    <button onclick="openSearchModal()" class="hidden md:flex text-gray-700 hover:text-green-600 transition p-1 rounded-full hover:bg-gray-100">
                        <i class="bi bi-search text-xl font-bold"></i>
                    </button>

                    <button onclick="openTrackModal()" class="text-gray-700 hover:text-green-600 transition p-1.5 rounded-full hover:bg-gray-100 md:mr-1" title="Lacak Pesanan">
                        <i class="bi bi-box-seam text-xl font-bold"></i>
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

            <div class="flex justify-between items-center pt-2 border-t border-gray-100 gap-2">
                <div class="text-[10px] text-gray-500 font-medium overflow-x-auto hide-scroll whitespace-nowrap flex-1">
                    <?php echo $service_text_formatted; ?>
                </div>
                <button onclick="openSearchModal()" class="md:hidden flex items-center justify-center text-gray-700 hover:text-green-600 transition p-1 shrink-0">
                    <i class="bi bi-search text-lg font-bold"></i>
                </button>
            </div>
        </div>
    </header>

    <div class="max-w-5xl mx-auto md:px-4 md:pt-4 relative z-0">
        <div class="slider-container md:rounded-3xl shadow-sm" id="heroSlider">
            <?php if(count($banners) > 0): foreach($banners as $index => $b): ?>
                <div class="slide <?php echo $index == 0 ? 'active' : ''; ?>">
                    <img src="<?php echo htmlspecialchars($b['image']); ?>" alt="Banner">
                </div>
            <?php endforeach; else: ?>
                <div class="slide active">
                    <img src="https://placehold.co/800x400/10b981/ffffff?text=Promo+Spesial" alt="Default Banner">
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="cards-wrapper max-w-5xl mx-auto relative z-20 -mt-3 md:-mt-4 px-4">
        <div class="grid grid-cols-4 gap-3 md:gap-6">
            <?php if(mysqli_num_rows($q_cards) > 0): while($c = mysqli_fetch_assoc($q_cards)): ?>
                <a href="<?php echo htmlspecialchars($c['link']); ?>" class="menu-card cursor-pointer active:scale-95">
                    <div class="icon-box"><i class="bi <?php echo htmlspecialchars($c['icon']); ?>"></i></div>
                    <span class="text-[9px] md:text-[11px] font-bold text-gray-700 leading-tight"><?php echo htmlspecialchars($c['title']); ?></span>
                </a>
            <?php endwhile; else: ?>
                <a href="javascript:scrollToMenu()" class="menu-card cursor-pointer active:scale-95">
                    <div class="icon-box"><i class="bi bi-chat-dots-fill"></i></div><span class="text-[9px] md:text-[11px] font-bold text-gray-700 leading-tight">Pesan</span>
                </a>
                <a href="javascript:openTrackModal()" class="menu-card cursor-pointer active:scale-95">
                    <div class="icon-box"><i class="bi bi-geo-alt"></i></div><span class="text-[9px] md:text-[11px] font-bold text-gray-700 leading-tight">Lacak</span>
                </a>
                <a href="https://wa.me/<?php echo htmlspecialchars($shop['wa_number']); ?>" class="menu-card cursor-pointer active:scale-95">
                    <div class="icon-box"><i class="bi bi-cart-check"></i></div><span class="text-[9px] md:text-[11px] font-bold text-gray-700 leading-tight">Reservasi</span>
                </a>
                <a href="https://wa.me/<?php echo htmlspecialchars($shop['wa_number']); ?>" class="menu-card cursor-pointer active:scale-95">
                    <div class="icon-box"><i class="bi bi-calendar-event"></i></div><span class="text-[9px] md:text-[11px] font-bold text-gray-700 leading-tight">Catering</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="px-4 mt-8 space-y-4 max-w-5xl mx-auto">

        <?php if($voucher_eligible): ?>
        <div class="bg-gradient-to-r from-orange-50 to-yellow-50 border border-orange-200 rounded-xl p-3 flex items-center justify-between shadow-sm w-full mb-4 cursor-pointer active:scale-95 transition-transform" onclick="scrollToMenu()">
            <div>
                <span class="text-orange-600 text-xs font-bold"><i class="bi bi-ticket-perforated-fill"></i> Voucher Member Baru</span>
                <p class="text-gray-600 text-[10px] mt-0.5">Diskon Rp <?php echo number_format($voucher_amount); ?> otomatis di keranjang.</p>
            </div>
            <button class="bg-orange-500 text-white text-xs font-bold px-3 py-1.5 rounded-lg shadow-sm">Pakai</button>
        </div>
        <?php endif; ?>

        <div id="dynamicSections">
            <?php 
            foreach($badges_list as $badge):
                $b_name = mysqli_real_escape_string($conn, $badge['name']);
                $q_bp = mysqli_query($conn, "SELECT * FROM products WHERE FIND_IN_SET('$b_name', selected_badges) > 0 ORDER BY product_status ASC, name ASC");
                
                if(mysqli_num_rows($q_bp) > 0):
            ?>
            <div class="badge-section mb-10 pt-2">
                <div class="flex items-center justify-between mb-3 px-1">
                    <h3 class="font-extrabold text-gray-800 text-lg md:text-xl"><?php echo htmlspecialchars($badge['name']); ?></h3>
                    <i class="bi bi-chevron-right text-gray-400"></i>
                </div>
                
                <div class="flex gap-3 md:gap-4 overflow-x-auto hide-scroll pb-4 snap-x snap-mandatory">
                    <?php 
                    while($p = mysqli_fetch_assoc($q_bp)): 
                        $img = ($p['image']=='default.png') ? "https://placehold.co/400x400/e2e8f0/94a3b8?text=".urlencode($p['name']) : (filter_var($p['image'], FILTER_VALIDATE_URL) ? $p['image'] : $p['image']);
                        $isRetail = ($p['product_type'] == 'retail');
                        $isSoldOut = ($p['product_status'] == 'habis');
                        $variantsData = "[]"; $toppingsData = "[]"; $priceDisplay = $p['base_price'];
                        
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
                    <div class="product-card shrink-0 w-[42%] sm:w-[30%] md:w-[220px] snap-start relative group flex flex-col cursor-pointer overflow-hidden" onclick='openProductModal(<?php echo htmlspecialchars(json_encode($p)); ?>, <?php echo htmlspecialchars($variantsData); ?>, <?php echo htmlspecialchars($toppingsData); ?>)'>
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
            <?php 
                endif; 
            endforeach; 
            ?>
        </div>

        <div id="productSection" class="pt-2 border-t border-gray-100">
            <div class="flex items-center justify-between mb-4 px-1">
                <h3 class="font-extrabold text-gray-800 text-lg md:text-xl">Semua Menu</h3>
            </div>

            <div class="flex gap-2 overflow-x-auto justify-start md:justify-center hide-scroll pb-2 mb-6">
                <button class="px-5 py-2 rounded-full bg-green-500 text-white text-xs font-bold category-btn whitespace-nowrap shadow-md" data-cat="all">Semua</button>
                <?php 
                mysqli_data_seek($q_cat, 0); 
                while($c = mysqli_fetch_assoc($q_cat)): 
                ?>
                    <button class="px-5 py-2 rounded-full bg-white border border-gray-200 text-gray-600 text-xs font-medium category-btn whitespace-nowrap hover:bg-gray-50 transition" data-cat="<?php echo htmlspecialchars($c['category']); ?>"><?php echo htmlspecialchars($c['category']); ?></button>
                <?php endwhile; ?>
            </div>
            
            <div class="flex flex-wrap justify-center gap-3 md:gap-4" id="productGrid">
                <?php
                $q_prod = mysqli_query($conn, "SELECT * FROM products ORDER BY product_status ASC, name ASC");
                while($p = mysqli_fetch_assoc($q_prod)):
                    $img = ($p['image']=='default.png') ? "https://placehold.co/400x400/e2e8f0/94a3b8?text=".urlencode($p['name']) : (filter_var($p['image'], FILTER_VALIDATE_URL) ? $p['image'] : $p['image']);
                    $isRetail = ($p['product_type'] == 'retail');
                    $isSoldOut = ($p['product_status'] == 'habis');
                    
                    $variantsData = "[]"; $toppingsData = "[]"; $priceDisplay = $p['base_price'];

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

        <?php if($ref_active): ?>
        <div class="bg-gradient-to-r from-emerald-600 to-green-600 rounded-2xl p-5 md:p-8 flex items-center justify-between shadow-xl relative overflow-hidden mt-10 mb-2 w-full cursor-pointer active:scale-95 transition-transform" onclick="window.location.href='<?php echo $is_member ? 'member/index.php' : 'login.php'; ?>'">
            <div class="relative z-10 text-white">
                <h3 class="font-extrabold text-lg md:text-2xl uppercase tracking-wide">Berbagi Keberkahan</h3>
                <p class="text-xs md:text-sm text-green-50 mt-1 max-w-[200px] md:max-w-md">Dapatkan Poin <?php echo number_format($shop['referral_bonus'] ?? 0); ?> Pts dari setiap teman yang mendaftar!</p>
                <div class="inline-block mt-4 bg-gray-900 text-white text-xs md:text-sm font-bold px-5 py-2.5 rounded-lg shadow-md hover:bg-gray-800 transition">Gabung Afiliasi</div>
            </div>
            <div class="w-32 h-32 md:w-48 md:h-48 bg-white/10 rounded-full flex items-center justify-center absolute -right-6 -bottom-6 md:-right-10 md:-bottom-10">
                <i class="bi bi-people-fill text-6xl md:text-8xl text-white/30"></i>
            </div>
        </div>
        <?php endif; ?>

        <?php if(!empty($shop['review_link'])): ?>
        <div class="bg-white border border-gray-200 rounded-2xl p-6 md:p-8 shadow-sm flex flex-col items-center text-center w-full mt-6">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/c/c1/Google_%22G%22_logo.svg/768px-Google_%22G%22_logo.svg.png" class="h-8 md:h-10 mb-3" alt="Google">
            <h4 class="font-extrabold text-gray-800 text-lg md:text-xl">Rating Google Maps</h4>
            
            <div class="flex items-center justify-center gap-2 mt-2 mb-2">
                <span class="text-4xl md:text-5xl font-black text-gray-800"><?php echo htmlspecialchars($shop['google_rating'] ?? '5.0'); ?></span>
                <div class="flex flex-col items-start">
                    <div class="flex text-yellow-400 text-base md:text-lg">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-half"></i>
                    </div>
                    <span class="text-[10px] md:text-xs text-gray-500 font-bold uppercase mt-0.5">Dari <?php echo htmlspecialchars($shop['google_reviews'] ?? '0'); ?> Ulasan</span>
                </div>
            </div>
            
            <p class="text-xs md:text-sm text-gray-500 mb-5 px-4 leading-relaxed">Terima kasih atas ulasan dan kepercayaan Anda kepada <?php echo htmlspecialchars($shop['shop_name']); ?>.</p>
            <a href="<?php echo htmlspecialchars($shop['review_link']); ?>" target="_blank" class="block w-full max-w-sm py-3.5 text-center font-bold text-sm text-blue-600 bg-blue-50 border border-blue-200 rounded-xl hover:bg-blue-100 transition shadow-sm mx-auto active:scale-95">
                Lihat Semua Ulasan
            </a>
        </div>
        <?php endif; ?>

        <?php if(!empty($c_link)): ?>
        <div onclick="window.open('<?php echo htmlspecialchars($c_link); ?>', '_blank')" class="bg-white rounded-2xl p-4 md:p-6 shadow-sm border border-gray-200 mt-6 flex items-center justify-between cursor-pointer hover:bg-gray-50 active:scale-95 transition w-full">
            <div class="flex items-center gap-4 md:gap-6">
                <div class="w-12 h-12 md:w-16 md:h-16 rounded-full bg-[#25D366]/10 text-[#25D366] flex items-center justify-center shrink-0">
                    <i class="bi bi-whatsapp text-2xl md:text-3xl"></i>
                </div>
                <div>
                    <h4 class="font-extrabold text-gray-800 text-sm md:text-base"><?php echo htmlspecialchars($c_text); ?></h4>
                    <p class="text-[10px] md:text-xs text-gray-500 mt-0.5 leading-tight md:max-w-lg">Dapatkan info diskon, promo khusus, dan update terbaru langsung dari grup WhatsApp kami.</p>
                </div>
            </div>
            <i class="bi bi-chevron-right text-gray-400 md:text-xl"></i>
        </div>
        <?php endif; ?>

        <?php if(count($berita_list) > 0): ?>
        <div id="beritaSection" class="mt-10 pt-8 border-t border-gray-200 w-full scroll-mt-24 pb-8">
            <div class="flex items-center justify-between mb-5 px-1">
                <div>
                    <h3 class="font-extrabold text-gray-800 text-lg md:text-xl flex items-center gap-2"><i class="bi bi-stars text-yellow-500"></i> Kabar & Promo</h3>
                    <p class="text-[10px] md:text-xs text-gray-500 mt-0.5">Geser untuk melihat penawaran spesial!</p>
                </div>
            </div>
            
            <div class="flex gap-4 overflow-x-auto hide-scroll pb-6 snap-x snap-mandatory px-1">
                <?php foreach($berita_list as $art): 
                    $imgArt = !empty($art['image']) ? "uploads/berita/".$art['image'] : "https://placehold.co/600x300/10b981/ffffff?text=".urlencode($art['title']);
                ?>
                <div class="article-card shrink-0 w-[85%] md:w-[320px] snap-start bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm flex flex-col cursor-pointer hover:shadow-md" onclick='openArticleModal(<?php echo htmlspecialchars(json_encode($art)); ?>)'>
                    <div class="h-40 w-full bg-gray-100 relative shrink-0">
                        <img src="<?php echo htmlspecialchars($imgArt); ?>" class="w-full h-full object-cover" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                        <span class="absolute bottom-3 right-3 text-[9px] font-bold bg-white/90 text-gray-800 px-2 py-1 rounded shadow-sm backdrop-blur-sm"><i class="bi bi-calendar-event"></i> <?php echo date('d M Y', strtotime($art['created_at'])); ?></span>
                    </div>
                    <div class="p-4 flex flex-col flex-1">
                        <h4 class="font-bold text-gray-800 text-sm leading-snug line-clamp-2"><?php echo htmlspecialchars($art['title']); ?></h4>
                        <p class="text-xs text-gray-500 mt-2 line-clamp-2 leading-relaxed flex-1"><?php echo htmlspecialchars(strip_tags($art['content'])); ?></p>
                        <div class="mt-4 text-[10px] font-bold text-green-600 flex items-center gap-1 group">Baca selengkapnya <i class="bi bi-arrow-right group-hover:translate-x-1 transition-transform"></i></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div id="floatingCart" class="fixed bottom-[85px] md:bottom-24 left-0 right-0 z-30 transform translate-y-48 transition-transform duration-300 flex justify-center pointer-events-none hidden mb-2">
        <div class="bg-green-600 text-white rounded-2xl p-3 md:p-3.5 shadow-2xl flex justify-between items-center w-[calc(100%-2rem)] max-w-md cursor-pointer pointer-events-auto border border-green-500 hover:bg-green-700 active:scale-95 transition-all" onclick="openCartModal()">
            <div class="flex items-center gap-3"><div class="bg-white/20 w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm"><span id="floatCount">0</span></div><span class="font-bold text-base tracking-wide" id="floatTotal">Rp 0</span></div>
            <div class="text-xs font-bold bg-black/20 px-3 py-1.5 rounded-lg flex items-center gap-1"><i class="bi bi-cart-check"></i> Checkout</div>
        </div>
    </div>

    <nav class="bottom-nav fixed bottom-0 w-full z-40 bg-transparent flex justify-center pointer-events-none">
        <div class="w-full max-w-5xl bg-white border-t border-gray-200 flex justify-around items-center px-1 pb-safe shadow-[0_-5px_15px_rgba(0,0,0,0.05)] md:rounded-t-2xl pointer-events-auto">
            <a href="index.php" class="nav-item active">
                <i class="bi bi-house-door-fill text-[20px] md:text-[22px]"></i>
                <span class="mt-1">Beranda</span>
            </a>
            <a href="javascript:scrollToMenu()" class="nav-item">
                <i class="bi bi-grid-fill text-[20px] md:text-[22px]"></i>
                <span class="mt-1">Menu</span>
            </a>
            
            <a href="promo.php" class="nav-item relative">
                <div class="relative inline-block">
                    <i class="bi bi-newspaper text-[20px] md:text-[22px]"></i>
                    <?php if(count($berita_list) > 0): ?>
                        <span class="absolute -top-1 -right-1.5 w-2.5 h-2.5 bg-red-500 border border-white rounded-full animate-pulse"></span>
                    <?php endif; ?>
                </div>
                <span class="mt-1">Promo</span>
            </a>
            
            <a href="javascript:void(0)" onclick="openCartModal()" class="nav-item">
                <div class="relative inline-block">
                    <i class="bi bi-bag-fill text-[20px] md:text-[22px]"></i>
                    <div id="navCartBadge" class="absolute -top-1.5 -right-2 w-4 h-4 bg-red-500 text-white text-[9px] rounded-full flex items-center justify-center font-bold hidden border border-white shadow-sm">0</div>
                </div>
                <span class="mt-1">Keranjang</span>
            </a>

            <a href="<?php echo $is_member ? 'member/index.php' : 'login.php'; ?>" class="nav-item">
                <i class="bi bi-person-fill text-[20px] md:text-[22px]"></i>
                <span class="mt-1">Saya</span>
            </a>
        </div>
    </nav>

    <div id="trackModal" class="fixed inset-0 z-[70] hidden">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm transition-opacity opacity-0" id="trackBackdrop" onclick="closeModal('trackModal')"></div>
        <div class="absolute inset-0 flex items-end md:items-center justify-center p-0 md:p-4 pointer-events-none">
            <div class="bg-white w-full md:max-w-md rounded-t-3xl md:rounded-2xl shadow-2xl flex flex-col transition-all duration-300 transform translate-y-full md:translate-y-0 md:scale-95 md:opacity-0 pointer-events-auto border border-gray-200 p-6 pb-safe" id="trackModalContent">
                <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-3">
                    <h3 class="font-extrabold text-xl text-gray-800 flex items-center gap-2"><i class="bi bi-geo-alt-fill text-green-500"></i> Lacak Pesanan</h3>
                    <button onclick="closeModal('trackModal')" class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 hover:bg-gray-200 transition"><i class="bi bi-x-lg text-sm"></i></button>
                </div>
                <p class="text-xs text-gray-500 mb-4 leading-relaxed">Masukkan <span class="font-bold text-gray-700">Nomor Invoice</span> atau <span class="font-bold text-gray-700">Nomor WhatsApp</span> yang digunakan saat memesan.</p>
                <div class="relative mb-4">
                    <i class="bi bi-search absolute left-4 top-3.5 text-gray-400"></i>
                    <input type="text" id="trackInput" placeholder="Contoh: ORD-ABC12 atau 0812..." class="w-full bg-gray-50 border border-gray-200 rounded-xl pl-11 pr-4 py-3 text-sm focus:border-green-500 focus:outline-none focus:bg-white transition">
                </div>
                <button onclick="searchTrackOrder()" class="w-full py-3.5 rounded-xl bg-green-600 hover:bg-green-500 text-white font-bold transition active:scale-95 flex items-center justify-center gap-2 shadow-md">
                    <i class="bi bi-crosshair"></i> Lacak Sekarang
                </button>
                
                <div id="trackResult" class="mt-6 hidden"></div>
            </div>
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
                        <div class="grid grid-cols-2 text-center border-b border-gray-100 sticky top-0 bg-white z-10 p-3 gap-3 shadow-sm">
                            <div onclick="setOrderType('pickup')" id="tabPickup" class="py-2 text-sm font-bold cursor-pointer bg-green-50 text-green-600 rounded-lg border border-green-200 transition active:scale-95"><i class="bi bi-shop mr-1"></i> Ambil / Makan</div>
                            <div onclick="setOrderType('delivery')" id="tabDelivery" class="py-2 text-sm font-bold cursor-pointer text-gray-500 rounded-lg border border-transparent hover:bg-gray-50 transition active:scale-95"><i class="bi bi-truck mr-1"></i> Delivery</div>
                        </div>
                        <div class="p-5 space-y-5">
                            <div class="space-y-4">
                                <div><label class="text-xs font-bold text-gray-500 ml-1 uppercase">Nama Pemesan</label><input type="text" id="custName" value="<?php echo htmlspecialchars($member_name); ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none" placeholder="Nama Kamu (Wajib)"></div>
                                <div><label class="text-xs font-bold text-gray-500 ml-1 uppercase">No. WhatsApp</label><input type="text" id="custContact" value="<?php echo htmlspecialchars($member_phone); ?>" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none" placeholder="08xxxxxxxx"></div>
                                
                                <div id="deliveryInput" class="hidden space-y-4">
                                    <div>
                                        <label class="text-xs font-bold text-gray-500 ml-1 uppercase">Kecamatan Tujuan</label>
                                        <div class="relative">
                                            <select id="custDistrict" onchange="updateDeliveryFee()" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none appearance-none font-medium">
                                                <option value="" disabled selected>-- Pilih Kecamatan --</option>
                                                <?php foreach($districts as $d): ?>
                                                    <option value="<?php echo htmlspecialchars($d['district_name']); ?>"><?php echo htmlspecialchars($d['district_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="bi bi-chevron-down absolute right-4 top-3.5 text-gray-400 pointer-events-none"></i>
                                        </div>
                                    </div>
                                    <div><label class="text-xs font-bold text-gray-500 ml-1 uppercase">Alamat Lengkap</label><textarea id="custAddress" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 h-20 focus:border-green-500 focus:outline-none" placeholder="Patokan, RT/RW, Warna Rumah..."></textarea></div>
                                </div>

                                <div id="pickupInput"><label class="text-xs font-bold text-gray-500 ml-1 uppercase">No. Meja / Waktu Ambil</label><input type="text" id="custTable" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-800 focus:border-green-500 focus:outline-none" placeholder="Kosongkan jika dibawa pulang"></div>
                            </div>
                            <div class="pt-2">
                                <label class="text-xs font-bold text-gray-500 ml-1 uppercase mb-2 block">Metode Pembayaran</label>
                                <div class="flex gap-2">
                                    <label class="flex-1 cursor-pointer active:scale-95 transition-transform"><input type="radio" name="payMethod" value="cash" class="peer sr-only" checked onchange="toggleQR(false)"><div class="text-center py-3 rounded-xl bg-gray-50 border border-gray-200 peer-checked:border-green-500 peer-checked:bg-green-50 text-xs text-gray-500 peer-checked:text-green-600 font-bold shadow-sm"><i class="bi bi-cash-stack"></i> Tunai</div></label>
                                    <label class="flex-1 cursor-pointer active:scale-95 transition-transform"><input type="radio" name="payMethod" value="qris" class="peer sr-only" onchange="toggleQR(true)"><div class="text-center py-3 rounded-xl bg-gray-50 border border-gray-200 peer-checked:border-green-500 peer-checked:bg-green-50 text-xs text-gray-500 peer-checked:text-green-600 font-bold shadow-sm"><i class="bi bi-qr-code-scan"></i> QRIS / Transfer</div></label>
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
                        <div class="flex justify-between text-sm text-gray-500"><span>Subtotal Item</span><span id="sumSubtotal" class="font-medium text-gray-800">Rp 0</span></div>
                        <?php if($shop['service_fee'] > 0): ?><div class="flex justify-between text-sm text-gray-500"><span><?php echo htmlspecialchars($shop['fee_label']); ?></span><span class="text-gray-800">+Rp <?php echo number_format($shop['service_fee']); ?></span></div><?php endif; ?>
                        <div id="rowOngkir" class="flex justify-between text-sm text-gray-500 hidden"><span>Ongkir</span><span id="feeOngkir" class="text-gray-800">+Rp 0</span></div>
                        <div id="rowVoucher" class="flex justify-between text-sm text-green-600 font-bold hidden"><span>Diskon / Poin</span><span id="sumVoucher">-Rp 0</span></div>
                        <div class="flex justify-between text-xl font-bold text-gray-800 pt-3 border-t border-gray-200 mt-2"><span>Total Bayar</span><span id="sumTotal" class="text-green-600">Rp 0</span></div>
                    </div>
                    <button type="button" onclick="processOrder()" class="w-full py-4 rounded-xl bg-green-600 hover:bg-green-500 text-white font-extrabold shadow-lg shadow-green-600/30 flex justify-center gap-2 transition active:scale-95 text-base tracking-wide"><i class="bi bi-whatsapp text-lg leading-none"></i> Buat Pesanan</button>
                </div>
            </div>
        </div>
    </div>

    <div id="articleModal" class="fixed inset-0 z-[80] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity opacity-0" id="articleBackdrop" onclick="closeModal('articleModal')"></div>
        <div class="absolute inset-0 flex items-end md:items-center justify-center p-0 md:p-4 pointer-events-none">
            <div class="bg-white w-full md:max-w-xl h-[85vh] md:h-auto md:max-h-[85vh] rounded-t-3xl md:rounded-2xl shadow-2xl flex flex-col pointer-events-auto transform translate-y-full md:translate-y-0 md:scale-95 md:opacity-0 transition-all duration-300 overflow-hidden border border-gray-200" id="articleModalContent">
                
                <button onclick="closeModal('articleModal')" class="absolute top-4 right-4 z-10 w-8 h-8 bg-black/50 text-white rounded-full flex items-center justify-center hover:bg-black/70 transition backdrop-blur-md"><i class="bi bi-x-lg text-sm"></i></button>
                
                <div class="w-full h-56 md:h-64 bg-gray-100 shrink-0 relative">
                    <img id="artImg" src="" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-white via-transparent to-transparent"></div>
                </div>
                
                <div class="flex-1 bg-white overflow-y-auto custom-scrollbar p-6 -mt-8 relative z-10 rounded-t-3xl">
                    <div class="text-[10px] font-bold text-green-600 mb-2" id="artDate"><i class="bi bi-calendar-event"></i> </div>
                    <h2 class="text-2xl font-black text-gray-800 leading-tight mb-4" id="artTitle"></h2>
                    <div class="text-sm text-gray-600 leading-relaxed whitespace-pre-wrap" id="artContent"></div>
                </div>
                
                <div class="p-4 border-t border-gray-100 bg-gray-50 shrink-0 pb-safe">
                    <button onclick="closeModal('articleModal'); scrollToMenu();" class="w-full py-3.5 rounded-xl bg-green-600 text-white font-bold shadow-md hover:bg-green-500 transition active:scale-95">Pesan Sekarang</button>
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
        let countdownTimer;

        window.onload = function() { 
            if(cart.length > 0) renderCartFloating();
            
            const popup = document.getElementById('infoPopup');
            const content = document.getElementById('infoPopupContent');
            if("<?php echo trim($shop['running_text'] ?? ''); ?>" !== "") {
                popup.classList.remove('hidden');
                setTimeout(() => { popup.classList.remove('opacity-0'); content.classList.remove('scale-90'); }, 100);
                
                document.getElementById('popupProgressBar').classList.add('animate-progress');
                let timeLeft = 3;
                const countText = document.getElementById('popupCountdownText');
                
                countdownTimer = setInterval(() => {
                    timeLeft--;
                    if(timeLeft > 0) {
                        countText.innerText = `Tutup otomatis dalam ${timeLeft} detik`;
                    } else {
                        clearInterval(countdownTimer);
                        closeInfoPopup();
                    }
                }, 1000);
            }

            let slides = document.querySelectorAll('.slide');
            if(slides.length > 1) {
                let currentSlide = 0;
                setInterval(() => {
                    slides[currentSlide].classList.remove('active');
                    currentSlide = (currentSlide + 1) % slides.length;
                    slides[currentSlide].classList.add('active');
                }, 3000);
            }
        };

        function closeInfoPopup() {
            if(countdownTimer) clearInterval(countdownTimer);
            const popup = document.getElementById('infoPopup');
            const content = document.getElementById('infoPopupContent');
            popup.classList.add('opacity-0'); 
            content.classList.add('scale-90'); 
            setTimeout(() => popup.classList.add('hidden'), 500);
        }

        // FUNGSI LACAK PESANAN DENGAN TIMELINE STEPPER
        function openTrackModal() {
            const modal = document.getElementById('trackModal');
            const backdrop = document.getElementById('trackBackdrop');
            const content = document.getElementById('trackModalContent');
            
            document.getElementById('trackInput').value = '';
            document.getElementById('trackResult').classList.add('hidden');
            
            modal.classList.remove('hidden'); void modal.offsetWidth;
            backdrop.classList.remove('opacity-0');
            content.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
            setTimeout(() => document.getElementById('trackInput').focus(), 300);
        }

        function searchTrackOrder() {
            const keyword = document.getElementById('trackInput').value.trim();
            if(!keyword) { Swal.fire('Oops', 'Masukkan No Invoice atau No WA dulu ya!', 'warning'); return; }

            const btn = document.querySelector('#trackModalContent button[onclick="searchTrackOrder()"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = `<i class="bi bi-arrow-repeat animate-spin"></i> Mencari...`;
            btn.disabled = true;

            fetch('track_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ keyword: keyword })
            })
            .then(res => res.json())
            .then(res => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                const resultBox = document.getElementById('trackResult');
                resultBox.classList.remove('hidden');

                if(res.status === 'error') {
                    resultBox.innerHTML = `<div class="text-center text-red-500 py-4"><i class="bi bi-exclamation-circle text-3xl mb-2 block"></i><span class="text-sm font-bold">${res.message}</span></div>`;
                } else {
                    const d = res.data;
                    const st = d.status;
                    const isDelivery = (d.location && d.location.includes('Alamat:'));
                    
                    let lvl = 0;
                    if (st === 'pending' || st === 'success') lvl = 1;
                    else if (st === 'preparing') lvl = 2;
                    else if (st === 'ready' || st === 'delivering') lvl = 3;
                    else if (st === 'completed') lvl = 4;

                    if (st === 'cancelled') {
                        resultBox.innerHTML = `
                            <div class="flex justify-between items-center mb-4 border-b border-gray-200 pb-3">
                                <div><span class="font-mono font-bold text-gray-800 text-lg">${d.no_invoice}</span><br><span class="text-[10px] text-gray-500">Atas nama: ${d.customer_name}</span></div>
                                <span class="font-bold text-gray-800">Rp ${parseInt(d.total_amount).toLocaleString('id-ID')}</span>
                            </div>
                            <div class="text-center text-red-500 py-4 bg-red-50 rounded-xl border border-red-100">
                                <i class="bi bi-x-circle-fill text-3xl mb-2 block"></i>
                                <span class="text-sm font-bold">PESANAN DIBATALKAN</span>
                            </div>
                        `;
                        return;
                    }

                    let step3Text = isDelivery ? 'Sedang Diantar Kurir' : 'Siap Diambil (Pick Up)';
                    let step3Icon = isDelivery ? 'bi-scooter' : 'bi-bag-check';

                    resultBox.innerHTML = `
                        <div class="flex justify-between items-start mb-5 border-b border-gray-200 pb-4">
                            <div>
                                <span class="font-mono font-bold text-gray-800 text-base">${d.no_invoice}</span>
                                <div class="text-[10px] text-gray-500 mt-1">Pemesan: <strong class="text-gray-700">${d.customer_name}</strong></div>
                            </div>
                            <div class="text-right">
                                <span class="text-[9px] text-gray-400 block mb-0.5">Total Tagihan</span>
                                <span class="font-black text-green-600">Rp ${parseInt(d.total_amount).toLocaleString('id-ID')}</span>
                            </div>
                        </div>

                        <div class="relative pl-6 space-y-6 mt-2 mb-2">
                            <div class="absolute left-[11px] top-2 bottom-2 w-0.5 bg-gray-200 z-0"></div>

                            <div class="relative z-10 flex items-start gap-4">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center -ml-[28px] ${lvl >= 1 ? 'bg-green-500 text-white shadow-md shadow-green-500/30' : 'bg-gray-200 text-gray-400'} ${lvl == 1 ? 'ring-4 ring-green-100' : ''}">
                                    <i class="bi ${lvl > 1 ? 'bi-check' : 'bi-receipt'} text-xs font-bold"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm ${lvl >= 1 ? 'text-gray-800' : 'text-gray-400'}">Pesanan Diterima</h4>
                                    <p class="text-[10px] text-gray-500 mt-0.5">${lvl >= 1 ? 'Masuk pada: ' + d.created_at : '-'}</p>
                                </div>
                            </div>

                            <div class="relative z-10 flex items-start gap-4">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center -ml-[28px] ${lvl >= 2 ? 'bg-orange-500 text-white shadow-md shadow-orange-500/30' : 'bg-gray-200 text-gray-400'} ${lvl == 2 ? 'ring-4 ring-orange-100 animate-pulse' : ''}">
                                    <i class="bi ${lvl > 2 ? 'bi-check' : 'bi-fire'} text-xs font-bold"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm ${lvl >= 2 ? 'text-gray-800' : 'text-gray-400'}">Sedang Disiapkan</h4>
                                    <p class="text-[10px] text-gray-500 mt-0.5">${lvl == 2 ? 'Dapur sedang memasak pesananmu...' : (lvl > 2 ? 'Selesai dimasak' : '-')}</p>
                                </div>
                            </div>

                            <div class="relative z-10 flex items-start gap-4">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center -ml-[28px] ${lvl >= 3 ? 'bg-blue-500 text-white shadow-md shadow-blue-500/30' : 'bg-gray-200 text-gray-400'} ${lvl == 3 ? 'ring-4 ring-blue-100 animate-pulse' : ''}">
                                    <i class="bi ${lvl > 3 ? 'bi-check' : step3Icon} text-xs font-bold"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm ${lvl >= 3 ? 'text-gray-800' : 'text-gray-400'}">${step3Text}</h4>
                                    <p class="text-[10px] text-gray-500 mt-0.5">${lvl == 3 ? (isDelivery ? 'Driver menuju lokasimu...' : 'Silakan ambil di kasir') : (lvl > 3 ? 'Sudah sampai' : '-')}</p>
                                </div>
                            </div>

                            <div class="relative z-10 flex items-start gap-4">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center -ml-[28px] ${lvl >= 4 ? 'bg-green-600 text-white shadow-md shadow-green-600/30' : 'bg-gray-200 text-gray-400'} ${lvl == 4 ? 'ring-4 ring-green-100' : ''}">
                                    <i class="bi bi-check-all text-sm font-bold"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-sm ${lvl >= 4 ? 'text-green-600' : 'text-gray-400'}">Pesanan Selesai</h4>
                                    <p class="text-[10px] text-gray-500 mt-0.5">${lvl >= 4 ? 'Terima kasih, selamat menikmati!' : '-'}</p>
                                </div>
                            </div>
                        </div>
                    `;
                }
            })
            .catch(err => {
                btn.innerHTML = originalText;
                btn.disabled = false;
                Swal.fire('Error', 'Gagal menghubungi server.', 'error');
            });
        }

        // NAVIGATION SCROLL
        function scrollToMenu() {
            document.getElementById('productSection').scrollIntoView({behavior: 'smooth', block: 'start'});
        }

        // FUNGSI MODAL ARTIKEL BACA
        function openArticleModal(data) {
            document.getElementById('artTitle').innerText = data.title;
            document.getElementById('artContent').innerHTML = data.content.replace(/\n/g, "<br>");
            
            const dateObj = new Date(data.created_at);
            const dateStr = dateObj.toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});
            document.getElementById('artDate').innerHTML = `<i class="bi bi-calendar-event"></i> Dipublikasi pada: ${dateStr}`;
            
            let imgUrl = data.image ? `uploads/berita/${data.image}` : `https://placehold.co/600x300/10b981/ffffff?text=${encodeURIComponent(data.title)}`;
            document.getElementById('artImg').src = imgUrl;

            const modal = document.getElementById('articleModal');
            const backdrop = document.getElementById('articleBackdrop');
            const content = document.getElementById('articleModalContent');
            
            modal.classList.remove('hidden'); void modal.offsetWidth;
            backdrop.classList.remove('opacity-0');
            content.classList.remove('translate-y-full', 'md:scale-95', 'md:opacity-0');
        }

        // FUNGSI UNTUK MODAL PENCARIAN (SEARCH)
        function openSearchModal() {
            const modal = document.getElementById('searchModal');
            const backdrop = document.getElementById('searchBackdrop');
            const content = document.getElementById('searchModalContent');
            modal.classList.remove('hidden'); void modal.offsetWidth;
            backdrop.classList.remove('opacity-0');
            content.classList.remove('-translate-y-24');
            document.getElementById('searchInput').focus();
        }

        function closeSearchModal() {
            const modal = document.getElementById('searchModal');
            const backdrop = document.getElementById('searchBackdrop');
            const content = document.getElementById('searchModalContent');
            backdrop.classList.add('opacity-0');
            content.classList.add('-translate-y-24');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }

        // FUNGSI PENCARIAN & FILTER KATEGORI
        function checkSearchFilter(input) {
            const term = input.value.toLowerCase();
            const badgeSections = document.querySelectorAll('.badge-section');
            
            if (term !== '') { badgeSections.forEach(el => el.style.display = 'none'); } 
            else {
                const activeCat = document.querySelector('.category-btn.bg-green-500').dataset.cat;
                if(activeCat === 'all') badgeSections.forEach(el => el.style.display = 'block');
            }
            
            document.querySelectorAll('.category-item').forEach(item => { 
                item.style.display = item.textContent.toLowerCase().includes(term) ? 'flex' : 'none'; 
            });
            if (term === 'login admin') { window.location.href = 'login.php'; }
        }

        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.category-btn').forEach(b => { b.classList.remove('bg-green-500', 'text-white', 'shadow-md'); b.classList.add('bg-white', 'text-gray-600'); });
                btn.classList.remove('bg-white', 'text-gray-600'); btn.classList.add('bg-green-500', 'text-white', 'shadow-md');
                
                const cat = btn.dataset.cat;
                const term = document.getElementById("searchInput").value;
                const badgeSections = document.querySelectorAll('.badge-section');
                
                if (cat === 'all' && term === '') { badgeSections.forEach(el => el.style.display = 'block'); } 
                else { badgeSections.forEach(el => el.style.display = 'none'); }

                document.querySelectorAll('.category-item').forEach(i => { 
                    const matchCat = (cat === 'all' || i.dataset.category === cat);
                    const matchSearch = i.textContent.toLowerCase().includes(term.toLowerCase());
                    i.style.display = (matchCat && matchSearch) ? 'flex' : 'none'; 
                });
            });
        });

        // FUNGSI KERANJANG DAN PEMBAYARAN
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
                } else { el.classList.add('hidden', 'translate-y-48'); setTimeout(() => el.classList.add('hidden'), 300); } 
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
            document.getElementById('tabPickup').className = type === 'pickup' ? 'py-2 text-sm font-bold cursor-pointer bg-green-50 text-green-600 rounded-lg border border-green-200 transition active:scale-95' : 'py-2 text-sm font-bold cursor-pointer text-gray-500 rounded-lg border border-transparent hover:bg-gray-50 transition active:scale-95';
            document.getElementById('tabDelivery').className = type === 'delivery' ? 'py-2 text-sm font-bold cursor-pointer bg-green-50 text-green-600 rounded-lg border border-green-200 transition active:scale-95' : 'py-2 text-sm font-bold cursor-pointer text-gray-500 rounded-lg border border-transparent hover:bg-gray-50 transition active:scale-95';
            
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

        // FUNGSI GLOBAL TUTUP MODAL
        function closeModal(id) {
            const modal = document.getElementById(id);
            const content = document.getElementById(id + 'Content');
            const backdrop = document.getElementById(id === 'productModal' ? 'modalBackdrop' : (id === 'cartModal' ? 'cartBackdrop' : (id === 'trackModal' ? 'trackBackdrop' : 'articleBackdrop')));
            
            backdrop.classList.add('opacity-0');
            content.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0');
            setTimeout(() => modal.classList.add('hidden'), 300);
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
    </script>
</body>
</html>