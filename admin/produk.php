<?php
include '../koneksi.php';

// --- INITIALIZE VARIABLES UNTUK EDIT ---
$is_edit = false;
$edit_data = [];
$edit_variants = [];
$edit_modifiers = [];

// JIKA MODE EDIT
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $q = mysqli_query($conn, "SELECT * FROM products WHERE id='$id'");
    if (mysqli_num_rows($q) > 0) {
        $is_edit = true;
        $edit_data = mysqli_fetch_assoc($q);
        
        $qv = mysqli_query($conn, "SELECT * FROM product_variants WHERE product_id='$id'");
        while($v = mysqli_fetch_assoc($qv)) $edit_variants[] = $v;

        $qm = mysqli_query($conn, "SELECT * FROM product_modifiers WHERE product_id='$id'");
        while($m = mysqli_fetch_assoc($qm)) $edit_modifiers[] = $m;
    }
}

// --- HANDLE CRUD KATEGORI & BADGE ---
if (isset($_GET['hapus_kategori'])) {
    $id_cat = intval($_GET['hapus_kategori']);
    mysqli_query($conn, "DELETE FROM categories WHERE id='$id_cat'");
    header("Location: produk.php?modal_cat=true"); exit;
}
if (isset($_GET['hapus_badge'])) {
    $id_bg = intval($_GET['hapus_badge']);
    mysqli_query($conn, "DELETE FROM badges WHERE id='$id_bg'");
    header("Location: produk.php?modal_badge=true"); exit;
}
if (isset($_POST['add_new_category'])) {
    $new_cat = mysqli_real_escape_string($conn, $_POST['new_category_name']);
    mysqli_query($conn, "INSERT INTO categories (name) VALUES ('$new_cat')");
    header("Location: produk.php?modal_cat=true"); exit;
}
if (isset($_POST['add_new_badge'])) {
    $new_badge = mysqli_real_escape_string($conn, $_POST['new_badge_name']);
    $sort_order = intval($_POST['new_badge_order']); 
    $colors = ['bg-red-500', 'bg-blue-500', 'bg-green-500', 'bg-yellow-500', 'bg-purple-500'];
    $rand_color = $colors[array_rand($colors)];
    mysqli_query($conn, "INSERT INTO badges (name, color, sort_order) VALUES ('$new_badge', '$rand_color', '$sort_order')");
    header("Location: produk.php?modal_badge=true"); exit;
}
// TAMBAHAN: FITUR UPDATE BADGE (INLINE EDIT)
if (isset($_POST['update_badge'])) {
    $b_id = intval($_POST['badge_id']);
    $b_name = mysqli_real_escape_string($conn, $_POST['edit_name']);
    $b_order = intval($_POST['edit_order']);
    mysqli_query($conn, "UPDATE badges SET name='$b_name', sort_order='$b_order' WHERE id='$b_id'");
    header("Location: produk.php?modal_badge=true"); exit;
}

// --- PROSES SIMPAN PRODUK ---
if (isset($_POST['simpan'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $category = $_POST['category'];
    $status = $_POST['status'];
    $badges_str = isset($_POST['badges']) ? implode(",", $_POST['badges']) : "";

    $image = $_POST['old_image'] ?? 'default.png';
    if (!empty($_POST['image_url'])) {
        $image = mysqli_real_escape_string($conn, $_POST['image_url']);
    } elseif (!empty($_FILES['image_upload']['name'])) {
        $target_dir = "../assets/images/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
        $filename = time() . "_" . basename($_FILES["image_upload"]["name"]);
        if (move_uploaded_file($_FILES["image_upload"]["tmp_name"], $target_dir . $filename)) {
            $image = "assets/images/" . $filename;
        }
    }

    $type = isset($_POST['mode_retail']) ? 'retail' : 'non_retail';
    
    // Siapkan Data Harga/Stok Utama
    $price = ($type == 'non_retail') ? $_POST['main_price'] : 0;
    $coret = ($type == 'non_retail') ? $_POST['main_coret'] : 0;
    $stock = ($type == 'non_retail' && !empty($_POST['main_stock'])) ? $_POST['main_stock'] : 0;

    if (isset($_POST['id_produk']) && !empty($_POST['id_produk'])) {
        // UPDATE
        $id = intval($_POST['id_produk']);
        $query = "UPDATE products SET 
                  name='$name', description='$desc', category='$category', product_type='$type', 
                  base_price='$price', original_price='$coret', stock='$stock', product_status='$status', 
                  selected_badges='$badges_str', image='$image' 
                  WHERE id='$id'";
        mysqli_query($conn, $query);
        
        // Hapus detail lama biar bersih
        mysqli_query($conn, "DELETE FROM product_variants WHERE product_id='$id'");
        mysqli_query($conn, "DELETE FROM product_modifiers WHERE product_id='$id'");
        $product_id = $id;
    } else {
        // INSERT
        $query = "INSERT INTO products (name, description, category, product_type, base_price, original_price, stock, product_status, selected_badges, image) 
                  VALUES ('$name', '$desc', '$category', '$type', '$price', '$coret', '$stock', '$status', '$badges_str', '$image')";
        mysqli_query($conn, $query);
        $product_id = mysqli_insert_id($conn);
    }

    // SIMPAN DETAIL
    if ($type == 'non_retail') {
        if (isset($_POST['t_name'])) {
            foreach($_POST['t_name'] as $i => $t_name) {
                if(!empty($t_name)) {
                    $tp = intval($_POST['t_price'][$i]);
                    $t_name_safe = mysqli_real_escape_string($conn, $t_name);
                    mysqli_query($conn, "INSERT INTO product_modifiers (product_id, modifier_name, extra_price) VALUES ('$product_id', '$t_name_safe', '$tp')");
                }
            }
        }
    } else {
        if (isset($_POST['v_name'])) {
            foreach($_POST['v_name'] as $i => $v_name) {
                if(!empty($v_name)) {
                    $p = intval($_POST['v_price'][$i]);
                    $c = intval($_POST['v_capital'][$i]);
                    $s = intval($_POST['v_stock'][$i]);
                    $v_name_safe = mysqli_real_escape_string($conn, $v_name);
                    mysqli_query($conn, "INSERT INTO product_variants (product_id, variant_name, price, capital_price, stock) VALUES ('$product_id', '$v_name_safe', '$p', '$c', '$s')");
                }
            }
        }
    }
    header("Location: produk.php"); exit;
}

// HAPUS PRODUK
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    mysqli_query($conn, "DELETE FROM product_variants WHERE product_id='$id'");
    mysqli_query($conn, "DELETE FROM product_modifiers WHERE product_id='$id'");
    mysqli_query($conn, "DELETE FROM products WHERE id='$id'");
    header("Location: produk.php"); exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - POS PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        body { background-color: #f8fafc; color: #1e293b; min-height: 100vh; }
        
        .glass-input { background: #ffffff; border: 1px solid #e2e8f0; color: #1e293b; transition: all 0.3s; }
        .glass-input:focus { border-color: #10b981; outline: none; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
        .glass-input::placeholder { color: #94a3b8; }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar:hover::-webkit-scrollbar-thumb { background: #94a3b8; }
    </style>
</head>
<body class="flex overflow-hidden text-sm">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 ml-0 md:ml-64 h-screen overflow-y-auto p-4 md:p-8 relative pb-24 custom-scrollbar">
        
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end mb-6 gap-4 border-b border-gray-200 pb-5">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-600 text-[10px] font-bold uppercase tracking-widest mb-3">
                    <i class="bi bi-box-seam-fill"></i> Manajemen Menu
                </div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-gray-800 tracking-tight">Daftar Produk</h2>
                <p class="text-gray-500 text-xs mt-1 font-medium">Kelola harga, stok, dan varian produk.</p>
            </div>
            
            <div class="flex flex-col md:flex-row gap-3 w-full lg:w-auto">
                <div class="relative">
                    <i class="bi bi-funnel absolute left-3 top-2.5 text-gray-400"></i>
                    <select id="filterCategory" onchange="filterTable()" class="glass-input pl-9 pr-4 py-2.5 rounded-xl appearance-none cursor-pointer min-w-[160px] font-medium text-sm">
                        <option value="all">Semua Kategori</option>
                        <?php 
                        $fcats = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
                        while($fc = mysqli_fetch_assoc($fcats)) echo "<option value='".htmlspecialchars($fc['name'])."'>".htmlspecialchars($fc['name'])."</option>";
                        ?>
                    </select>
                    <i class="bi bi-chevron-down absolute right-3 top-3 text-gray-400 pointer-events-none text-xs"></i>
                </div>
                <div class="relative flex-1 lg:w-72">
                    <i class="bi bi-search absolute left-3 top-2.5 text-gray-400"></i>
                    <input type="text" id="searchTable" onkeyup="filterTable()" placeholder="Cari nama produk..." class="glass-input w-full pl-9 pr-4 py-2.5 rounded-xl font-medium text-sm">
                </div>
                <a href="#" onclick="openAddModal(); return false;" class="px-5 py-2.5 font-bold text-white rounded-xl bg-green-600 hover:bg-green-500 shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                    <i class="bi bi-plus-lg"></i> Tambah
                </a>
            </div>
        </div>

        <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="productTable">
                    <thead>
                        <tr class="text-gray-500 border-b border-gray-200 text-xs uppercase tracking-wider bg-gray-50/50">
                            <th class="p-4 font-bold">Menu / Produk</th>
                            <th class="p-4 font-bold">Kategori</th>
                            <th class="p-4 font-bold">Tipe</th>
                            <th class="p-4 font-bold">Harga Jual</th>
                            <th class="p-4 font-bold">Stok</th>
                            <th class="p-4 font-bold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 divide-y divide-gray-100">
                        <?php
                        $q = mysqli_query($conn, "SELECT * FROM products ORDER BY id DESC");
                        while($row = mysqli_fetch_assoc($q)) {
                            $isRetail = ($row['product_type'] == 'retail');
                            $badgeType = $isRetail ? '<span class="px-2 py-1 rounded-md text-[10px] font-bold bg-blue-50 text-blue-600 border border-blue-100">Retail</span>' : '<span class="px-2 py-1 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100">F&B</span>';
                            
                            $harga = "-";
                            $stokDisplay = "0";

                            if(!$isRetail) {
                                $harga = "Rp " . number_format($row['base_price']);
                                if($row['original_price'] > 0) $harga .= " <span class='text-xs text-red-400 line-through ml-1'>Rp ".number_format($row['original_price'])."</span>";
                                $stokDisplay = ($row['stock'] > 0) ? $row['stock'] : "<span class='text-green-500 text-lg leading-none'>∞</span>";
                            } else {
                                $pid = $row['id'];
                                $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT min(price) as min_p, max(price) as max_p, sum(stock) as total_s FROM product_variants WHERE product_id='$pid'"));
                                $harga = ($cek['min_p']) ? "Rp ".number_format($cek['min_p']) . " - " . number_format($cek['max_p']) : "-";
                                $stokDisplay = ($cek['total_s']) ? $cek['total_s'] : "0";
                            }
                            
                            $imgSrc = (filter_var($row['image'], FILTER_VALIDATE_URL)) ? $row['image'] : "../".$row['image'];
                            if($row['image'] == 'default.png') $imgSrc = "https://placehold.co/100x100?text=IMG";
                        ?>
                        <tr class="hover:bg-gray-50/80 transition group product-row" data-category="<?php echo htmlspecialchars($row['category']); ?>">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl overflow-hidden bg-gray-100 shrink-0 border border-gray-200">
                                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" class="w-full h-full object-cover">
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-800 text-sm product-name leading-tight"><?php echo htmlspecialchars($row['name']); ?></div>
                                        <div class="flex gap-1 mt-1.5 flex-wrap">
                                            <?php 
                                            if(!empty($row['selected_badges'])){
                                                foreach(explode(',', $row['selected_badges']) as $b) echo "<span class='text-[9px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 font-semibold border border-gray-200'>".htmlspecialchars($b)."</span>";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="p-4"><span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-lg text-xs font-semibold border border-gray-200"><?php echo htmlspecialchars($row['category']); ?></span></td>
                            <td class="p-4"><?php echo $badgeType; ?></td>
                            <td class="p-4 font-mono text-sm font-semibold text-gray-700"><?php echo $harga; ?></td>
                            <td class="p-4 font-mono text-sm font-bold text-gray-800"><?php echo $stokDisplay; ?></td>
                            <td class="p-4 text-right">
                                <div class="flex justify-end gap-1.5">
                                    <a href="?edit=<?php echo $row['id']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white border border-blue-100 hover:border-blue-600 transition shadow-sm" title="Edit"><i class="bi bi-pencil-fill text-xs"></i></a>
                                    <a href="?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus menu ini?')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white border border-red-100 hover:border-red-500 transition shadow-sm" title="Hapus"><i class="bi bi-trash-fill text-xs"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="modalTambah" class="fixed inset-0 z-50 <?php echo ($is_edit || isset($_GET['modal_open'])) ? '' : 'hidden'; ?>">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="window.location.href='produk.php'"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-5xl max-h-[95vh] overflow-y-auto rounded-2xl shadow-2xl flex flex-col border border-gray-200">
                <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50/50 sticky top-0 z-20 backdrop-blur-md">
                    <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                        <div class="w-8 h-8 bg-green-100 text-green-600 rounded-lg flex items-center justify-center border border-green-200"><i class="bi bi-box-seam"></i></div>
                        <span id="modalTitle"><?php echo $is_edit ? 'Edit Detail Produk' : 'Tambah Menu Baru'; ?></span>
                    </h3>
                    <a href="produk.php" class="w-8 h-8 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100 transition shadow-sm"><i class="bi bi-x-lg text-sm"></i></a>
                </div>

                <form id="productForm" method="POST" enctype="multipart/form-data" class="p-6 grid grid-cols-1 md:grid-cols-12 gap-6">
                    <?php if($is_edit): ?>
                        <input type="hidden" name="id_produk" value="<?php echo $edit_data['id']; ?>">
                        <input type="hidden" name="old_image" value="<?php echo $edit_data['image']; ?>">
                    <?php endif; ?>

                    <div class="md:col-span-7 space-y-4">
                        <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm">
                            <h4 class="text-gray-800 font-extrabold mb-4 flex items-center gap-2"><i class="bi bi-info-circle text-green-500"></i> Informasi Utama</h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Nama Produk</label>
                                    <input type="text" name="name" value="<?php echo $is_edit ? htmlspecialchars($edit_data['name']) : ''; ?>" required class="glass-input w-full px-4 py-3 rounded-xl font-semibold text-gray-800" placeholder="Contoh: Bubur Ayam Spesial">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Deskripsi Singkat</label>
                                    <textarea name="description" rows="2" class="glass-input w-full px-4 py-3 rounded-xl text-gray-600 text-sm leading-relaxed" placeholder="Jelaskan isi/komposisi menu ini..."><?php echo $is_edit ? htmlspecialchars($edit_data['description']) : ''; ?></textarea>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1 flex justify-between items-center">
                                            <span>Kategori</span>
                                            <a href="?modal_cat=true" class="text-blue-500 hover:text-blue-700 bg-blue-50 px-2 py-0.5 rounded text-[9px] border border-blue-100">Kelola</a>
                                        </label>
                                        <div class="relative">
                                            <select name="category" class="glass-input w-full px-4 py-3 rounded-xl appearance-none font-semibold text-gray-700 cursor-pointer">
                                                <?php 
                                                $cats = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
                                                while($c = mysqli_fetch_assoc($cats)) {
                                                    $sel = ($is_edit && $edit_data['category'] == $c['name']) ? 'selected' : '';
                                                    echo "<option value='".htmlspecialchars($c['name'])."' $sel>".htmlspecialchars($c['name'])."</option>";
                                                }
                                                ?>
                                            </select>
                                            <i class="bi bi-chevron-down absolute right-4 top-3.5 text-gray-400 pointer-events-none"></i>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1 flex justify-between items-center">
                                            <span>Label Promo (Badge)</span>
                                            <a href="?modal_badge=true" class="text-blue-500 hover:text-blue-700 bg-blue-50 px-2 py-0.5 rounded text-[9px] border border-blue-100">Kelola</a>
                                        </label>
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <?php 
                                            $badges = mysqli_query($conn, "SELECT * FROM badges ORDER BY sort_order ASC, id ASC");
                                            $current_badges = $is_edit ? explode(',', $edit_data['selected_badges']) : [];
                                            while($b = mysqli_fetch_assoc($badges)) {
                                                $checked = in_array($b['name'], $current_badges) ? 'checked' : '';
                                            ?>
                                            <label class="cursor-pointer select-none group">
                                                <input type="checkbox" name="badges[]" value="<?php echo htmlspecialchars($b['name']); ?>" <?php echo $checked; ?> class="peer sr-only">
                                                <div class="px-3 py-1 rounded-lg border border-gray-200 bg-gray-50 text-xs font-bold text-gray-500 peer-checked:bg-green-500 peer-checked:text-white peer-checked:border-green-600 transition shadow-sm"><?php echo htmlspecialchars($b['name']); ?></div>
                                            </label>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="sectionVarian" class="bg-blue-50/50 border border-blue-100 p-5 rounded-2xl shadow-sm <?php echo ($is_edit && $edit_data['product_type'] == 'retail') || !$is_edit ? '' : 'hidden'; ?>">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h4 class="text-blue-800 font-extrabold flex items-center gap-2"><i class="bi bi-tags-fill"></i> Varian & Stok FIsik</h4>
                                    <p class="text-[10px] text-blue-600/70 mt-0.5">Atur ukuran atau rasa yang berbeda harga.</p>
                                </div>
                                <button type="button" onclick="addVariant()" class="text-xs font-bold bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 shadow-sm transition active:scale-95"><i class="bi bi-plus-lg"></i> Tambah</button>
                            </div>
                            <div class="grid grid-cols-12 gap-2 text-[10px] text-blue-800/60 uppercase font-extrabold mb-2 px-1 tracking-wider">
                                <div class="col-span-4">Nama Varian</div>
                                <div class="col-span-3">Harga Jual</div>
                                <div class="col-span-3">Modal</div>
                                <div class="col-span-2">Stok</div>
                            </div>
                            <div id="variantContainer" class="space-y-2 max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                                <?php if($is_edit && $edit_data['product_type'] == 'retail') foreach($edit_variants as $ev) echo "<script>window.addEventListener('load', function() { addVariant('".htmlspecialchars($ev['variant_name'])."', '{$ev['price']}', '{$ev['capital_price']}', '{$ev['stock']}'); });</script>"; ?>
                            </div>
                        </div>

                        <div id="sectionTopping" class="bg-amber-50/50 border border-amber-100 p-5 rounded-2xl shadow-sm <?php echo ($is_edit && $edit_data['product_type'] == 'non_retail') ? '' : 'hidden'; ?>">
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h4 class="text-amber-800 font-extrabold flex items-center gap-2"><i class="bi bi-plus-circle-fill"></i> Tambahan (Topping)</h4>
                                    <p class="text-[10px] text-amber-700/70 mt-0.5">Opsi tambahan seperti telur, sate, dll.</p>
                                </div>
                                <button type="button" onclick="addTopping()" class="text-xs font-bold bg-amber-500 text-white px-3 py-1.5 rounded-lg hover:bg-amber-600 shadow-sm transition active:scale-95"><i class="bi bi-plus-lg"></i> Tambah</button>
                            </div>
                            <div class="grid grid-cols-12 gap-2 text-[10px] text-amber-800/60 uppercase font-extrabold mb-2 px-1 tracking-wider">
                                <div class="col-span-8">Nama Tambahan</div>
                                <div class="col-span-3">+ Harga</div>
                                <div class="col-span-1"></div>
                            </div>
                            <div id="toppingContainer" class="space-y-2 max-h-40 overflow-y-auto pr-2 custom-scrollbar">
                                <?php if($is_edit && $edit_data['product_type'] == 'non_retail') foreach($edit_modifiers as $em) echo "<script>window.addEventListener('load', function() { addTopping('".htmlspecialchars($em['modifier_name'])."', '{$em['extra_price']}'); });</script>"; ?>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-5 space-y-4">
                        
                        <div class="bg-gray-800 p-5 rounded-2xl shadow-lg relative overflow-hidden">
                            <div class="absolute -right-4 -bottom-4 opacity-10"><i class="bi bi-shop text-9xl text-white"></i></div>
                            <div class="relative z-10 flex items-center justify-between">
                                <div>
                                    <h4 class="text-white font-bold text-sm">Mode Retail / Fisik</h4>
                                    <p class="text-[10px] text-gray-400 mt-0.5 w-4/5">Aktifkan ini jika produk berupa barang fisik dengan stok riil.</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                    <input type="checkbox" id="modeRetail" name="mode_retail" class="sr-only peer" onchange="toggleMode()" <?php echo ($is_edit && $edit_data['product_type'] == 'retail') ? 'checked' : ''; ?>>
                                    <div class="w-12 h-6 bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-green-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                                </label>
                            </div>
                        </div>

                        <div id="mainPriceSection" class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm <?php echo ($is_edit && $edit_data['product_type'] == 'retail') ? 'hidden' : ''; ?>">
                            <h4 class="text-gray-800 font-extrabold mb-4 flex items-center gap-2"><i class="bi bi-tag-fill text-green-500"></i> Penetapan Harga</h4>
                            <div class="mb-4 relative">
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Harga Jual Pokok</label>
                                <span class="absolute left-4 top-[34px] font-bold text-gray-500">Rp</span>
                                <input type="number" name="main_price" value="<?php echo $is_edit ? $edit_data['base_price'] : ''; ?>" class="glass-input w-full pl-10 pr-4 py-3 rounded-xl font-black text-xl text-green-600" placeholder="0">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="relative">
                                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wide mb-1.5 ml-1">Harga Coret (Promo)</label>
                                    <span class="absolute left-3 top-[33px] text-xs font-bold text-gray-400">Rp</span>
                                    <input type="number" name="main_coret" value="<?php echo $is_edit ? $edit_data['original_price'] : ''; ?>" class="glass-input w-full pl-8 pr-3 py-2.5 rounded-xl text-sm text-red-500 font-semibold" placeholder="0">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-1.5 ml-1 leading-tight">Batas Stok Hari ini<br>(Kosongkan jika tak terbatas)</label>
                                    <input type="number" name="main_stock" value="<?php echo $is_edit ? $edit_data['stock'] : ''; ?>" class="glass-input w-full px-3 py-2.5 rounded-xl text-sm font-semibold text-gray-700" placeholder="∞">
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 p-5 rounded-2xl shadow-sm">
                            <h4 class="text-gray-800 font-extrabold mb-3 flex items-center gap-2"><i class="bi bi-image text-green-500"></i> Visual Produk</h4>
                            
                            <?php if($is_edit && $edit_data['image'] != 'default.png'): ?>
                                <div class="w-full h-32 rounded-xl overflow-hidden bg-gray-100 mb-3 border border-gray-200 relative group">
                                    <img src="<?php echo (filter_var($edit_data['image'], FILTER_VALIDATE_URL)) ? htmlspecialchars($edit_data['image']) : "../".htmlspecialchars($edit_data['image']); ?>" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white text-xs font-bold">Gambar Saat Ini</div>
                                </div>
                            <?php endif; ?>
                            
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Gunakan URL Gambar</label>
                            <input type="text" name="image_url" class="glass-input w-full px-4 py-2.5 rounded-xl text-sm mb-3 font-medium" placeholder="https://...">
                            
                            <div class="relative w-full border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:bg-gray-50 transition cursor-pointer">
                                <i class="bi bi-cloud-arrow-up text-2xl text-gray-400 mb-1 block"></i>
                                <span class="text-xs font-bold text-gray-500">Atau upload file dari perangkat</span>
                                <input type="file" name="image_upload" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            </div>
                        </div>
                        
                        <div class="bg-white border border-gray-200 p-2 rounded-2xl shadow-sm flex gap-2">
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="status" value="ready" class="peer sr-only" <?php echo (!$is_edit || $edit_data['product_status']=='ready') ? 'checked' : ''; ?>>
                                <div class="text-center py-3 rounded-xl border border-transparent bg-gray-50 text-gray-500 font-bold peer-checked:bg-green-50 peer-checked:border-green-500 peer-checked:text-green-600 transition shadow-sm"><i class="bi bi-check-circle"></i> Ready</div>
                            </label>
                            <label class="flex-1 cursor-pointer">
                                <input type="radio" name="status" value="habis" class="peer sr-only" <?php echo ($is_edit && $edit_data['product_status']=='habis') ? 'checked' : ''; ?>>
                                <div class="text-center py-3 rounded-xl border border-transparent bg-gray-50 text-gray-500 font-bold peer-checked:bg-red-50 peer-checked:border-red-500 peer-checked:text-red-600 transition shadow-sm"><i class="bi bi-x-circle"></i> Habis</div>
                            </label>
                        </div>
                    </div>

                    <div class="md:col-span-12 pt-5 border-t border-gray-200 mt-2 flex justify-end gap-3 pb-4 md:pb-0">
                        <button type="button" onclick="window.location.href='produk.php'" class="px-6 py-3 rounded-xl text-gray-500 font-bold hover:bg-gray-100 transition">Batal</button>
                        <button type="submit" name="simpan" class="px-8 py-3 rounded-xl bg-green-600 hover:bg-green-500 text-white font-black text-base shadow-lg shadow-green-500/30 active:scale-95 transition flex items-center gap-2">
                            <i class="bi bi-floppy2-fill"></i> Simpan Menu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="modalCategory" class="fixed inset-0 z-[60] <?php echo isset($_GET['modal_cat']) ? '' : 'hidden'; ?>">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="window.location.href='produk.php'"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl flex flex-col border border-gray-200 overflow-hidden">
                <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2"><i class="bi bi-tags text-blue-500"></i> Kelola Kategori</h3>
                    <a href="produk.php" class="w-8 h-8 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100 transition shadow-sm"><i class="bi bi-x-lg"></i></a>
                </div>
                <div class="p-6">
                    <form method="POST" class="flex gap-2 mb-5">
                        <input type="text" name="new_category_name" required class="glass-input flex-1 px-4 py-2.5 rounded-xl font-medium" placeholder="Nama Kategori Baru...">
                        <button type="submit" name="add_new_category" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl font-bold shadow-md transition active:scale-95">Tambah</button>
                    </form>
                    
                    <div class="space-y-2.5 max-h-60 overflow-y-auto custom-scrollbar pr-2">
                        <?php 
                        $cats = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
                        if(mysqli_num_rows($cats) > 0):
                            while($c = mysqli_fetch_assoc($cats)): ?>
                            <div class="flex justify-between items-center bg-gray-50 p-3 rounded-xl border border-gray-200 group hover:border-blue-300 transition shadow-sm">
                                <span class="text-gray-700 font-bold text-sm"><?php echo htmlspecialchars($c['name']); ?></span>
                                <a href="?hapus_kategori=<?php echo $c['id']; ?>" onclick="return confirm('Hapus kategori ini?')" class="text-red-400 hover:text-red-600 w-8 h-8 flex items-center justify-center rounded-lg bg-white border border-gray-200 hover:border-red-200 hover:bg-red-50 transition shadow-sm"><i class="bi bi-trash"></i></a>
                            </div>
                        <?php endwhile; else: ?>
                            <div class="text-center text-gray-400 text-xs py-6 font-medium border border-dashed border-gray-300 rounded-xl">Belum ada kategori yang dibuat.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="modalBadge" class="fixed inset-0 z-[60] <?php echo isset($_GET['modal_badge']) ? '' : 'hidden'; ?>">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="window.location.href='produk.php'"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl flex flex-col border border-gray-200 overflow-hidden">
                <div class="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2"><i class="bi bi-bookmark-star text-purple-500"></i> Kelola Label Promo</h3>
                    <a href="produk.php" class="w-8 h-8 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100 transition shadow-sm"><i class="bi bi-x-lg"></i></a>
                </div>
                <div class="p-6">
                    <form method="POST" class="flex gap-2 mb-5 bg-purple-50 p-3 rounded-xl border border-purple-100">
                        <input type="text" name="new_badge_name" required class="glass-input flex-1 px-3 py-2 rounded-lg font-medium text-sm" placeholder="Nama Label...">
                        <input type="number" name="new_badge_order" required class="glass-input w-16 px-2 py-2 rounded-lg text-sm font-bold text-center" placeholder="No" title="Urutan Tampil">
                        <button type="submit" name="add_new_badge" class="bg-purple-600 hover:bg-purple-500 text-white px-4 py-2 rounded-lg font-bold shadow-md transition active:scale-95"><i class="bi bi-plus-lg"></i></button>
                    </form>
                    
                    <div class="space-y-3 max-h-60 overflow-y-auto custom-scrollbar pr-2">
                        <?php 
                        $badges = mysqli_query($conn, "SELECT * FROM badges ORDER BY sort_order ASC, name ASC");
                        if(mysqli_num_rows($badges) > 0):
                            while($b = mysqli_fetch_assoc($badges)): ?>
                            
                            <form method="POST" class="flex justify-between items-center bg-gray-50 p-2.5 rounded-xl border border-gray-200 group hover:border-purple-300 transition shadow-sm gap-2">
                                <input type="hidden" name="badge_id" value="<?php echo $b['id']; ?>">
                                
                                <input type="number" name="edit_order" value="<?php echo isset($b['sort_order']) ? $b['sort_order'] : '0'; ?>" class="glass-input w-12 h-9 px-1 text-center font-bold text-gray-700 rounded-lg" required title="Urutan">
                                
                                <input type="text" name="edit_name" value="<?php echo htmlspecialchars($b['name']); ?>" class="glass-input flex-1 h-9 px-3 font-bold text-gray-800 rounded-lg" required title="Nama Badge">
                                
                                <div class="flex gap-1.5 shrink-0">
                                    <button type="submit" name="update_badge" class="w-9 h-9 flex items-center justify-center rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white border border-blue-100 hover:border-blue-600 transition shadow-sm" title="Simpan Perubahan"><i class="bi bi-check-lg text-lg font-black"></i></button>
                                    <a href="?hapus_badge=<?php echo $b['id']; ?>" onclick="return confirm('Hapus label ini?')" class="w-9 h-9 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white border border-red-100 hover:border-red-500 transition shadow-sm" title="Hapus"><i class="bi bi-trash text-sm"></i></a>
                                </div>
                            </form>

                        <?php endwhile; else: ?>
                            <div class="text-center text-gray-400 text-xs py-6 font-medium border border-dashed border-gray-300 rounded-xl">Belum ada label promo.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            resetForm(); 
            document.getElementById('modalTitle').innerText = 'Tambah Menu Baru';
            document.getElementById('modalTambah').classList.remove('hidden');
            if(document.getElementById('variantContainer').children.length === 0) addVariant();
            if(document.getElementById('toppingContainer').children.length === 0) addTopping();
        }

        function resetForm() {
            document.getElementById('productForm').reset();
            document.getElementById('variantContainer').innerHTML = '';
            document.getElementById('toppingContainer').innerHTML = '';
            const inputs = document.getElementById('productForm').querySelectorAll('input[type="hidden"]');
            inputs.forEach(input => input.remove());
        }

        function toggleMode() {
            const isRetail = document.getElementById('modeRetail').checked;
            if (isRetail) {
                document.getElementById('mainPriceSection').classList.add('hidden');
                document.getElementById('sectionTopping').classList.add('hidden');
                document.getElementById('sectionVarian').classList.remove('hidden');
            } else {
                document.getElementById('mainPriceSection').classList.remove('hidden');
                document.getElementById('sectionTopping').classList.remove('hidden');
                document.getElementById('sectionVarian').classList.add('hidden');
            }
        }

        function addVariant(name='', price='', capital='', stock='') {
            const div = document.createElement('div');
            div.className = "grid grid-cols-12 gap-2 bg-white border border-blue-100 shadow-sm p-2.5 rounded-xl relative group mt-2";
            div.innerHTML = `
                <div class="col-span-4"><input type="text" name="v_name[]" value="${name}" placeholder="Cth: Jumbo" class="glass-input w-full px-3 py-2 text-xs font-bold text-gray-800 rounded-lg"></div>
                <div class="col-span-3"><input type="number" name="v_price[]" value="${price}" placeholder="0" class="glass-input w-full px-3 py-2 text-xs font-bold text-green-600 rounded-lg"></div>
                <div class="col-span-3"><input type="number" name="v_capital[]" value="${capital}" placeholder="0" class="glass-input w-full px-3 py-2 text-xs font-medium text-gray-500 rounded-lg"></div>
                <div class="col-span-2 relative">
                    <input type="number" name="v_stock[]" value="${stock}" placeholder="0" class="glass-input w-full px-2 py-2 text-xs font-bold text-blue-600 rounded-lg">
                    <button type="button" onclick="this.parentElement.parentElement.remove()" class="absolute -right-2 -top-2 text-white bg-red-500 hover:bg-red-600 rounded-full w-5 h-5 flex items-center justify-center text-[10px] shadow-md border-2 border-white transition cursor-pointer"><i class="bi bi-x-lg"></i></button>
                </div>`;
            document.getElementById('variantContainer').appendChild(div);
        }

        function addTopping(name='', price='') {
            const div = document.createElement('div');
            div.className = "grid grid-cols-12 gap-2 bg-white border border-amber-100 shadow-sm p-2.5 rounded-xl relative group mt-2";
            div.innerHTML = `
                <div class="col-span-7"><input type="text" name="t_name[]" value="${name}" placeholder="Cth: Ekstra Keju" class="glass-input w-full px-3 py-2 text-xs font-bold text-gray-800 rounded-lg"></div>
                <div class="col-span-4 relative"><span class="absolute left-2 top-2 text-[10px] font-bold text-gray-400">Rp</span><input type="number" name="t_price[]" value="${price}" placeholder="0" class="glass-input w-full pl-6 pr-2 py-2 text-xs font-bold text-green-600 rounded-lg"></div>
                <div class="col-span-1 flex items-center justify-center"><button type="button" onclick="this.parentElement.parentElement.remove()" class="text-red-400 hover:text-white hover:bg-red-500 w-8 h-8 rounded-lg border border-red-100 transition shadow-sm flex items-center justify-center"><i class="bi bi-trash-fill text-xs"></i></button></div>`;
            document.getElementById('toppingContainer').appendChild(div);
        }

        function filterTable() {
            let input = document.getElementById("searchTable").value.toLowerCase();
            let cat = document.getElementById("filterCategory").value;
            let rows = document.querySelectorAll("#productTable tbody tr");
            rows.forEach(row => {
                let name = row.querySelector(".product-name").innerText.toLowerCase();
                let rowCat = row.getAttribute("data-category");
                if (name.includes(input) && (cat === "all" || rowCat === cat)) row.style.display = ""; else row.style.display = "none";
            });
        }
    </script>
</body>
</html>