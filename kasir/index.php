<?php
session_start();
include '../koneksi.php';

// 1. CEK LOGIN & AMBIL ID USER
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kasir') {
    if($_SESSION['role'] != 'admin') { header("Location: ../login.php"); exit; }
}
$user_id = $_SESSION['user_id']; // ID Kasir yang sedang login

// 2. AMBIL SETTINGS
$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));
$fee_nominal = $shop['service_fee'];
$fee_label   = $shop['fee_label'];

// 3. AMBIL KATEGORI
$cats = mysqli_query($conn, "SELECT DISTINCT category FROM products WHERE product_status='ready'");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir - <?php echo $shop['shop_name']; ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: { glass: "rgba(255, 255, 255, 0.05)" }
                }
            }
        }
    </script>
    <style>
        body { background: radial-gradient(circle at top left, #0f172a, #020617, #000000); color: #fff; height: 100vh; overflow: hidden; }
        .glass-panel { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .hide-scroll::-webkit-scrollbar { display: none; }
        .item-card:active { transform: scale(0.95); transition: 0.1s; }
        
        /* SweetAlert Dark Theme */
        div:where(.swal2-container) div:where(.swal2-popup) {
            background: #1e293b !important;
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff !important;
        }
    </style>
</head>
<body class="flex">

    <div class="flex-1 flex flex-col h-screen border-r border-white/10 relative">
        
        <div class="h-16 flex justify-between items-center px-6 border-b border-white/10 glass-panel z-20">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold"><i class="bi bi-cart4"></i></div>
                <div>
                    <h1 class="font-bold text-lg leading-none">Kasir Pro</h1>
                    <p class="text-[10px] text-gray-400"><?php echo date('d M Y'); ?> • <?php echo $_SESSION['fullname']; ?></p>
                </div>
            </div>
            
            <div class="flex gap-3">
                <input type="text" id="searchInput" placeholder="Cari menu (Ctrl+F)" class="bg-black/30 border border-white/10 rounded-full px-4 py-2 text-sm w-64 focus:outline-none focus:border-indigo-500 transition">
                <a href="../logout.php" onclick="return confirm('Logout kasir?')" class="w-10 h-10 rounded-full bg-red-500/10 text-red-400 flex items-center justify-center hover:bg-red-500 hover:text-white transition"><i class="bi bi-power"></i></a>
            </div>
        </div>

        <div class="h-12 flex items-center gap-2 px-4 overflow-x-auto hide-scroll border-b border-white/5 bg-black/20">
            <button onclick="filterKategori('all')" class="cat-btn active px-4 py-1 rounded-full text-xs font-bold bg-indigo-600 text-white transition">Semua</button>
            <?php while($c = mysqli_fetch_assoc($cats)): ?>
                <button onclick="filterKategori('<?php echo $c['category']; ?>')" class="cat-btn px-4 py-1 rounded-full text-xs font-medium text-gray-400 hover:text-white hover:bg-white/10 transition"><?php echo $c['category']; ?></button>
            <?php endwhile; ?>
        </div>

        <div class="flex-1 overflow-y-auto p-4 custom-scrollbar">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4" id="productGrid">
                <?php
                $q = mysqli_query($conn, "SELECT * FROM products WHERE product_status='ready' ORDER BY name ASC");
                while($p = mysqli_fetch_assoc($q)):
                    $img = ($p['image']=='default.png' || empty($p['image'])) ? "https://placehold.co/150x150/1e293b/FFF?text=".urlencode($p['name']) : (filter_var($p['image'], FILTER_VALIDATE_URL) ? $p['image'] : "../".$p['image']);
                    
                    $isRetail = ($p['product_type'] == 'retail');
                    $pid = $p['id'];
                    
                    // 1. DATA VARIAN (Retail)
                    $variantsData = "[]";
                    $priceDisplay = $p['base_price'];
                    if($isRetail) {
                        $qv = mysqli_query($conn, "SELECT * FROM product_variants WHERE product_id='$pid' AND stock > 0");
                        $vars = [];
                        while($v = mysqli_fetch_assoc($qv)) $vars[] = $v;
                        $variantsData = json_encode($vars);
                        $priceDisplay = (count($vars) > 0) ? $vars[0]['price'] : 0;
                        if(empty($vars)) continue; // Skip jika stok varian habis
                    }

                    // 2. DATA TOPPING (F&B)
                    $toppingsData = "[]";
                    if(!$isRetail) {
                        $qm = mysqli_query($conn, "SELECT * FROM product_modifiers WHERE product_id='$pid'");
                        $mods = [];
                        while($m = mysqli_fetch_assoc($qm)) $mods[] = $m;
                        $toppingsData = json_encode($mods);
                    }
                ?>
                <div class="item-card glass-panel p-3 rounded-xl cursor-pointer group hover:border-indigo-500/50 transition relative overflow-hidden" 
                     data-cat="<?php echo $p['category']; ?>" 
                     data-name="<?php echo strtolower($p['name']); ?>"
                     onclick='handleClick(<?php echo htmlspecialchars(json_encode($p)); ?>, <?php echo $variantsData; ?>, <?php echo $toppingsData; ?>)'>
                    
                    <div class="aspect-square rounded-lg bg-black/20 mb-3 overflow-hidden">
                        <img src="<?php echo $img; ?>" class="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-110 transition duration-500">
                    </div>
                    
                    <h4 class="font-bold text-sm leading-tight line-clamp-2 h-10"><?php echo $p['name']; ?></h4>
                    <div class="flex justify-between items-end mt-2">
                        <span class="text-indigo-400 font-bold text-sm">
                            <?php echo $isRetail ? "Varian" : "Rp ".number_format($priceDisplay,0,',','.'); ?>
                        </span>
                        <?php if($isRetail): ?>
                            <span class="text-[9px] bg-blue-500/20 text-blue-300 px-1.5 py-0.5 rounded border border-blue-500/20">Retail</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <div class="w-96 bg-[#0f172a] border-l border-white/10 flex flex-col h-screen shadow-2xl z-30">
        
        <div class="h-16 flex items-center justify-between px-6 border-b border-white/10 bg-black/20">
            <h3 class="font-bold text-lg">Keranjang</h3>
            <button onclick="clearCart()" class="text-xs text-red-400 hover:text-red-300 flex items-center gap-1"><i class="bi bi-trash"></i> Reset</button>
        </div>

        <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar relative" id="cartContainer">
            <div class="text-center mt-20 text-gray-500">
                <i class="bi bi-cart-x text-4xl mb-2 block"></i>
                <p class="text-xs">Belum ada item dipilih</p>
            </div>
        </div>

        <div class="p-6 bg-[#020617] border-t border-white/10 space-y-2">
            <div class="flex justify-between text-xs text-gray-400">
                <span>Subtotal</span>
                <span id="txtSubtotal">Rp 0</span>
            </div>
            
            <?php if($fee_nominal > 0): ?>
            <div class="flex justify-between text-xs text-gray-400">
                <span><?php echo $fee_label; ?></span>
                <span class="text-orange-400">+ Rp <?php echo number_format($fee_nominal,0,',','.'); ?></span>
            </div>
            <?php endif; ?>

            <div class="flex justify-between text-xl font-bold text-white pt-2 border-t border-white/10 mt-2">
                <span>Total</span>
                <span id="txtTotal" class="text-indigo-400">Rp 0</span>
            </div>

            <button onclick="openCheckout()" id="btnCheckout" disabled class="w-full py-3.5 mt-4 rounded-xl bg-gray-700 text-gray-400 font-bold cursor-not-allowed transition-all flex justify-between px-6">
                <span>Bayar</span>
                <span><i class="bi bi-arrow-right"></i></span>
            </button>
        </div>
    </div>

    <script>
        const SERVICE_FEE = <?php echo $fee_nominal; ?>;
        
        // --- KEYWORD UNIK UNTUK LOCALSTORAGE ---
        // Ini kuncinya! Kita tambahkan ID USER ke nama storage.
        // Jadi cart User 1 tersimpan di 'pos_cart_1', cart User 2 di 'pos_cart_2'.
        const USER_CART_KEY = 'pos_cart_<?php echo $user_id; ?>';

        let cart = JSON.parse(localStorage.getItem(USER_CART_KEY)) || []; 

        // Load saat pertama kali
        window.onload = function() {
            if(cart.length > 0) {
                renderCart();
            }
        };

        // Fungsi Simpan (Dipanggil setiap ada perubahan)
        function saveCart() {
            localStorage.setItem(USER_CART_KEY, JSON.stringify(cart));
        }

        // --- 1. HANDLE CLICK ---
        function handleClick(product, variants, toppings) {
            if (product.product_type === 'retail') {
                if(variants.length === 0) {
                    Swal.fire('Stok Habis', 'Semua varian produk ini habis.', 'warning');
                    return;
                }
                showVariantModal(product, variants);
            } else {
                if (toppings.length > 0) {
                    showToppingModal(product, toppings);
                } else {
                    addToCart(product.id, product.name, null, product.base_price, []);
                }
            }
        }

        // --- 2. MODAL TOPPING (CHECKBOX STYLE) ---
        function showToppingModal(product, toppings) {
            let html = `<div class="text-left space-y-2 mt-2 max-h-60 overflow-y-auto custom-scrollbar">`;
            toppings.forEach(t => {
                html += `
                <label class="flex items-center justify-between p-3 bg-slate-700/50 rounded-lg cursor-pointer hover:bg-slate-700 transition border border-white/5">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" class="w-5 h-5 rounded border-gray-500 text-indigo-600 focus:ring-indigo-500 topping-check" 
                            value="${t.id}" data-name="${t.modifier_name}" data-price="${t.extra_price}">
                        <span class="text-sm text-gray-200">${t.modifier_name}</span>
                    </div>
                    <span class="text-xs font-bold text-indigo-300">+Rp ${parseInt(t.extra_price).toLocaleString()}</span>
                </label>`;
            });
            html += `</div>`;

            Swal.fire({
                title: `Pilih Topping - ${product.name}`,
                html: html,
                showCancelButton: true,
                confirmButtonText: 'Tambah ke Pesanan',
                confirmButtonColor: '#4f46e5',
                background: '#1e293b',
                color: '#fff',
                preConfirm: () => {
                    const selected = [];
                    document.querySelectorAll('.topping-check:checked').forEach(cb => {
                        selected.push({
                            id: cb.value,
                            name: cb.dataset.name,
                            price: parseInt(cb.dataset.price)
                        });
                    });
                    return selected;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    addToCart(product.id, product.name, null, product.base_price, result.value);
                }
            });
        }

        // --- 3. MODAL VARIAN ---
        function showVariantModal(product, variants) {
            let html = '<div class="grid grid-cols-2 gap-2 mt-2">';
            variants.forEach(v => {
                html += `<button onclick="selectVariant(${product.id}, '${product.name}', '${v.variant_name}', ${v.price}, ${v.stock})" 
                         class="p-3 bg-slate-700 rounded-lg text-left hover:bg-indigo-600 transition">
                            <div class="font-bold text-sm text-white">${v.variant_name}</div>
                            <div class="text-xs text-gray-300">Rp ${parseInt(v.price).toLocaleString()}</div>
                            <div class="text-[10px] text-gray-400">Stok: ${v.stock}</div>
                         </button>`;
            });
            html += '</div>';

            Swal.fire({
                title: product.name,
                html: html,
                showConfirmButton: false,
                showCloseButton: true,
                background: '#1e293b',
                color: '#fff'
            });
        }

        window.selectVariant = function(pid, pname, vname, price, stock) {
            addToCart(pid, pname, vname, price, []);
            Swal.close();
        };

        // --- 4. LOGIC KERANJANG UTAMA ---
        function addToCart(id, name, variant, price, toppings = []) {
            let toppingTotal = 0;
            let toppingNames = "";
            if(toppings.length > 0) {
                toppings.sort((a, b) => a.id - b.id);
                toppingTotal = toppings.reduce((sum, t) => sum + t.price, 0);
                toppingNames = toppings.map(t => t.name).join('+');
            }

            let finalPrice = parseInt(price) + toppingTotal;
            let uniqueId = id + '-' + (variant ? variant : 'null') + '-' + toppingNames;
            
            let existing = cart.find(item => item.uniqueId === uniqueId);
            
            if (existing) {
                existing.qty++;
            } else {
                cart.push({
                    uniqueId: uniqueId,
                    id: id,
                    name: name,
                    variant: variant,
                    toppings: toppings,
                    price: finalPrice,
                    basePrice: parseInt(price),
                    qty: 1
                });
            }
            saveCart(); // Simpan otomatis
            renderCart();
        }

        function renderCart() {
            const container = document.getElementById('cartContainer');
            container.innerHTML = '';
            let subtotal = 0;

            if (cart.length === 0) {
                container.innerHTML = `<div class="text-center mt-20 text-gray-500 opacity-50"><i class="bi bi-cart-x text-4xl mb-2 block"></i><p class="text-xs">Keranjang Kosong</p></div>`;
                updateTotals(0);
                return;
            }

            cart.forEach(item => {
                let itemTotal = item.price * item.qty;
                subtotal += itemTotal;
                let variantLabel = item.variant ? `<span class="text-[10px] bg-indigo-500/20 text-indigo-300 px-1 rounded ml-1">${item.variant}</span>` : '';
                
                let toppingHtml = '';
                if(item.toppings && item.toppings.length > 0) {
                    toppingHtml = `<div class="text-[10px] text-gray-400 mt-0.5 flex flex-wrap gap-1">`;
                    item.toppings.forEach(t => {
                        toppingHtml += `<span class="bg-white/10 px-1 rounded">+${t.name}</span>`;
                    });
                    toppingHtml += `</div>`;
                }

                container.innerHTML += `
                <div class="bg-white/5 p-3 rounded-lg flex justify-between items-start animate-fade-in mb-2 border border-white/5">
                    <div class="flex-1">
                        <div class="font-medium text-sm text-white leading-tight mb-1">${item.name} ${variantLabel}</div>
                        ${toppingHtml}
                        <div class="text-xs text-indigo-400 mt-1">Rp ${item.price.toLocaleString()} x ${item.qty}</div>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <div class="font-bold text-sm">Rp ${itemTotal.toLocaleString()}</div>
                        <div class="flex items-center gap-2 bg-black/30 rounded-lg p-0.5">
                            <button onclick="updateQty('${item.uniqueId}', -1)" class="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-white hover:bg-white/10 rounded"><i class="bi bi-dash"></i></button>
                            <span class="text-xs font-mono w-4 text-center">${item.qty}</span>
                            <button onclick="updateQty('${item.uniqueId}', 1)" class="w-6 h-6 flex items-center justify-center text-green-400 hover:text-green-300 hover:bg-white/10 rounded"><i class="bi bi-plus"></i></button>
                        </div>
                    </div>
                </div>`;
            });
            updateTotals(subtotal);
        }

        function updateQty(uniqueId, change) {
            let item = cart.find(i => i.uniqueId === uniqueId);
            if(item) {
                item.qty += change;
                if(item.qty <= 0) cart = cart.filter(i => i.uniqueId !== uniqueId);
            }
            saveCart(); // Simpan otomatis
            renderCart();
        }

        function updateTotals(subtotal) {
            let total = subtotal + SERVICE_FEE;
            document.getElementById('txtSubtotal').innerText = 'Rp ' + subtotal.toLocaleString('id-ID');
            document.getElementById('txtTotal').innerText = 'Rp ' + total.toLocaleString('id-ID');
            
            const btn = document.getElementById('btnCheckout');
            if(subtotal > 0) {
                btn.disabled = false;
                btn.classList.remove('bg-gray-700', 'text-gray-400', 'cursor-not-allowed');
                btn.classList.add('bg-gradient-to-r', 'from-indigo-600', 'to-purple-600', 'text-white', 'hover:opacity-90', 'shadow-lg');
            } else {
                btn.disabled = true;
                btn.classList.add('bg-gray-700', 'text-gray-400', 'cursor-not-allowed');
                btn.classList.remove('bg-gradient-to-r', 'from-indigo-600', 'to-purple-600', 'text-white', 'hover:opacity-90', 'shadow-lg');
            }
        }

        function clearCart() {
            if(cart.length === 0) return;
            Swal.fire({
                title: 'Reset Keranjang?', icon: 'question', showCancelButton: true,
                confirmButtonColor: '#ef4444', background: '#1e293b', color: '#fff', confirmButtonText: 'Ya'
            }).then((r) => { 
                if (r.isConfirmed) { 
                    cart = []; 
                    saveCart(); 
                    renderCart(); 
                } 
            });
        }

        function filterKategori(cat) {
            const cards = document.querySelectorAll('.item-card');
            const btns = document.querySelectorAll('.cat-btn');
            btns.forEach(b => {
                if(b.innerText === cat || (cat === 'all' && b.innerText === 'Semua')) {
                    b.classList.remove('bg-transparent', 'text-gray-400'); b.classList.add('bg-indigo-600', 'text-white');
                } else {
                    b.classList.add('bg-transparent', 'text-gray-400'); b.classList.remove('bg-indigo-600', 'text-white');
                }
            });
            cards.forEach(card => {
                if(cat === 'all' || card.dataset.cat === cat) card.classList.remove('hidden'); else card.classList.add('hidden');
            });
        }

        document.getElementById('searchInput').addEventListener('keyup', function(e) {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.item-card').forEach(card => {
                if(card.dataset.name.includes(term)) card.classList.remove('hidden'); else card.classList.add('hidden');
            });
        });

        function openCheckout() {
            alert("Fitur Pembayaran (Checkout) akan kita buat di tahap selanjutnya!");
        }
    </script>
</body>
</html>