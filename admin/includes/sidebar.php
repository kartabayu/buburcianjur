<?php
$page = basename($_SERVER['PHP_SELF']);

// Ambil nama toko dari database untuk sidebar
$q_shop_sidebar = mysqli_query($conn, "SELECT shop_name FROM shop_settings WHERE id=1");
$shop_sidebar = mysqli_fetch_assoc($q_shop_sidebar);
$nama_toko = $shop_sidebar ? htmlspecialchars($shop_sidebar['shop_name']) : 'Admin Panel';
?>

<aside class="hidden md:flex w-64 h-screen bg-white flex-col fixed z-20 transition-all duration-300 border-r border-gray-200 shadow-sm">
    <div class="p-6 text-center border-b border-gray-50 flex flex-col items-center justify-center min-h-[100px]">
        <h1 class="text-lg font-black tracking-tight text-green-700 leading-snug line-clamp-2 px-2">
            <?php echo $nama_toko; ?>
        </h1>
        <span class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-2 block bg-gray-100 px-2 py-0.5 rounded-md">Admin Panel</span>
    </div>

    <nav class="flex-1 px-4 space-y-2 mt-6 overflow-y-auto custom-scrollbar">
        <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group <?php echo ($page == 'index.php') ? 'bg-green-50 text-green-700 border border-green-200 font-bold shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-green-600 font-medium'; ?>">
            <i class="bi bi-grid-fill text-lg <?php echo ($page == 'index.php') ? 'text-green-600' : 'text-gray-400 group-hover:text-green-500'; ?>"></i> 
            <span>Dashboard</span>
        </a>
        <a href="produk.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group <?php echo ($page == 'produk.php') ? 'bg-green-50 text-green-700 border border-green-200 font-bold shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-green-600 font-medium'; ?>">
            <i class="bi bi-box-seam-fill text-lg <?php echo ($page == 'produk.php') ? 'text-green-600' : 'text-gray-400 group-hover:text-green-500'; ?>"></i> 
            <span>Produk</span>
        </a>
        <a href="pegawai.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group <?php echo ($page == 'pegawai.php') ? 'bg-green-50 text-green-700 border border-green-200 font-bold shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-green-600 font-medium'; ?>">
            <i class="bi bi-people-fill text-lg <?php echo ($page == 'pegawai.php') ? 'text-green-600' : 'text-gray-400 group-hover:text-green-500'; ?>"></i> 
            <span>Pegawai</span>
        </a>
        <a href="pengeluaran.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group <?php echo ($page == 'pengeluaran.php') ? 'bg-green-50 text-green-700 border border-green-200 font-bold shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-green-600 font-medium'; ?>">
            <i class="bi bi-wallet-fill text-lg <?php echo ($page == 'pengeluaran.php') ? 'text-green-600' : 'text-gray-400 group-hover:text-green-500'; ?>"></i> 
            <span>Pengeluaran</span>
        </a>
        <a href="laporan.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group <?php echo ($page == 'laporan.php') ? 'bg-green-50 text-green-700 border border-green-200 font-bold shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-green-600 font-medium'; ?>">
            <i class="bi bi-file-earmark-text-fill text-lg <?php echo ($page == 'laporan.php') ? 'text-green-600' : 'text-gray-400 group-hover:text-green-500'; ?>"></i> 
            <span>Laporan</span>
        </a>
        <a href="berita.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group <?php echo ($page == 'berita.php') ? 'bg-green-50 text-green-700 border border-green-200 font-bold shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-green-600 font-medium'; ?>">
            <i class="bi bi-newspaper text-lg <?php echo ($page == 'berita.php') ? 'text-green-600' : 'text-gray-400 group-hover:text-green-500'; ?>"></i> 
            <span>Berita & Promo</span>
        </a>
        <a href="settings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 group <?php echo ($page == 'settings.php') ? 'bg-green-50 text-green-700 border border-green-200 font-bold shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-green-600 font-medium'; ?>">
            <i class="bi bi-gear-fill text-lg <?php echo ($page == 'settings.php') ? 'text-green-600' : 'text-gray-400 group-hover:text-green-500'; ?>"></i> 
            <span>Pengaturan</span>
        </a>
    </nav>

    <div class="p-5 border-t border-gray-100 bg-gray-50/50">
        <a href="../logout.php" onclick="return confirm('Yakin ingin keluar?')" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl bg-white text-red-500 hover:bg-red-50 hover:text-red-600 transition-all border border-red-200 hover:border-red-300 font-bold shadow-sm">
            <i class="bi bi-box-arrow-left"></i> <span>Logout</span>
        </a>
    </div>
</aside>

<div id="menuOverlay" class="hidden md:hidden fixed inset-0 z-[55] bg-black/60 backdrop-blur-sm transition-opacity" onclick="toggleMobileMenu()"></div>

<div id="mobileMenuPopup" class="hidden md:hidden fixed bottom-24 left-4 right-4 z-[60] animate-fade-up">
    <div class="bg-white p-5 rounded-2xl grid grid-cols-4 gap-3 shadow-2xl border border-gray-200 relative overflow-hidden">
        
        <a href="pegawai.php" class="flex flex-col items-center justify-center gap-2 group w-full">
            <div class="w-12 h-12 rounded-full flex items-center justify-center transition-all <?php echo ($page == 'pegawai.php') ? 'bg-green-500 text-white shadow-md shadow-green-500/30' : 'bg-gray-50 text-gray-500 border border-gray-200 group-hover:bg-green-50 group-hover:text-green-600 group-hover:border-green-200'; ?>">
                <i class="bi bi-people-fill text-xl"></i>
            </div>
            <span class="text-[10px] font-bold <?php echo ($page == 'pegawai.php') ? 'text-green-600' : 'text-gray-500'; ?>">Pegawai</span>
        </a>
        
        <a href="berita.php" class="flex flex-col items-center justify-center gap-2 group w-full">
            <div class="w-12 h-12 rounded-full flex items-center justify-center transition-all <?php echo ($page == 'berita.php') ? 'bg-green-500 text-white shadow-md shadow-green-500/30' : 'bg-gray-50 text-gray-500 border border-gray-200 group-hover:bg-green-50 group-hover:text-green-600 group-hover:border-green-200'; ?>">
                <i class="bi bi-newspaper text-xl"></i>
            </div>
            <span class="text-[10px] font-bold <?php echo ($page == 'berita.php') ? 'text-green-600' : 'text-gray-500'; ?>">Berita</span>
        </a>

        <a href="settings.php" class="flex flex-col items-center justify-center gap-2 group w-full">
            <div class="w-12 h-12 rounded-full flex items-center justify-center transition-all <?php echo ($page == 'settings.php') ? 'bg-green-500 text-white shadow-md shadow-green-500/30' : 'bg-gray-50 text-gray-500 border border-gray-200 group-hover:bg-green-50 group-hover:text-green-600 group-hover:border-green-200'; ?>">
                <i class="bi bi-gear-fill text-xl"></i>
            </div>
            <span class="text-[10px] font-bold <?php echo ($page == 'settings.php') ? 'text-green-600' : 'text-gray-500'; ?>">Settings</span>
        </a>

        <a href="../logout.php" onclick="return confirm('Keluar aplikasi?')" class="flex flex-col items-center justify-center gap-2 group w-full">
            <div class="w-12 h-12 rounded-full bg-red-50 border border-red-100 flex items-center justify-center text-red-500 group-hover:bg-red-500 group-hover:text-white transition-all shadow-sm">
                <i class="bi bi-box-arrow-left text-xl"></i>
            </div>
            <span class="text-[10px] text-red-500 font-bold">Logout</span>
        </a>

    </div>
</div>

<nav class="md:hidden fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-200 shadow-[0_-5px_15px_rgba(0,0,0,0.03)] pb-safe">
    <div class="grid grid-cols-5 items-center h-16">
        
        <a href="index.php" class="flex flex-col items-center justify-center w-full h-full group <?php echo ($page == 'index.php') ? 'text-green-600' : 'text-gray-400 hover:text-green-500'; ?>">
            <div class="relative p-1">
                <i class="bi bi-grid-fill text-xl transition-transform group-active:scale-90 <?php echo ($page == 'index.php') ? '-translate-y-1' : ''; ?>"></i>
                <?php if($page == 'index.php'): ?><span class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-1.5 h-1.5 bg-green-500 rounded-full"></span><?php endif; ?>
            </div>
            <span class="text-[10px] font-bold mt-0.5">Home</span>
        </a>

        <a href="pengeluaran.php" class="flex flex-col items-center justify-center w-full h-full group <?php echo ($page == 'pengeluaran.php') ? 'text-green-600' : 'text-gray-400 hover:text-green-500'; ?>">
            <div class="relative p-1">
                <i class="bi bi-wallet-fill text-xl transition-transform group-active:scale-90 <?php echo ($page == 'pengeluaran.php') ? '-translate-y-1' : ''; ?>"></i>
                <?php if($page == 'pengeluaran.php'): ?><span class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-1.5 h-1.5 bg-green-500 rounded-full"></span><?php endif; ?>
            </div>
            <span class="text-[10px] font-bold mt-0.5">Biaya</span>
        </a>

        <div class="relative flex items-center justify-center -top-5">
            <?php if($page == 'produk.php'): ?>
                <button onclick="toggleModal('modalTambah')" class="w-14 h-14 rounded-full bg-green-600 hover:bg-green-500 text-white shadow-lg shadow-green-500/30 flex items-center justify-center transform transition-transform active:scale-95 border-4 border-white">
                    <i class="bi bi-plus-lg text-2xl font-bold"></i>
                </button>
            <?php else: ?>
                <a href="produk.php" class="w-14 h-14 rounded-full bg-white text-green-600 flex items-center justify-center border border-gray-200 shadow-md transform transition-transform hover:bg-green-50 active:scale-95">
                    <i class="bi bi-box-seam-fill text-2xl"></i>
                </a>
            <?php endif; ?>
        </div>

        <a href="laporan.php" class="flex flex-col items-center justify-center w-full h-full group <?php echo ($page == 'laporan.php') ? 'text-green-600' : 'text-gray-400 hover:text-green-500'; ?>">
            <div class="relative p-1">
                <i class="bi bi-file-earmark-text-fill text-xl transition-transform group-active:scale-90 <?php echo ($page == 'laporan.php') ? '-translate-y-1' : ''; ?>"></i>
                <?php if($page == 'laporan.php'): ?><span class="absolute -bottom-2 left-1/2 transform -translate-x-1/2 w-1.5 h-1.5 bg-green-500 rounded-full"></span><?php endif; ?>
            </div>
            <span class="text-[10px] font-bold mt-0.5">Laporan</span>
        </a>

        <button onclick="toggleMobileMenu()" class="flex flex-col items-center justify-center w-full h-full group text-gray-400 hover:text-green-500 focus:outline-none">
            <div class="relative p-1">
                <i id="menuIcon" class="bi bi-list text-2xl transition-transform group-active:scale-90"></i>
            </div>
            <span class="text-[10px] font-bold mt-0.5 text-gray-500">Menu</span>
        </button>

    </div>
</nav>

<script>
    function toggleMobileMenu() {
        const popup = document.getElementById('mobileMenuPopup');
        const overlay = document.getElementById('menuOverlay');
        const icon = document.getElementById('menuIcon');
        
        if (popup.classList.contains('hidden')) {
            // BUKA MENU
            popup.classList.remove('hidden');
            popup.classList.add('block');
            
            overlay.classList.remove('hidden');
            
            icon.classList.remove('bi-list');
            icon.classList.add('bi-x-lg');
            icon.classList.add('text-green-600');
        } else {
            // TUTUP MENU
            popup.classList.add('hidden');
            popup.classList.remove('block');
            
            overlay.classList.add('hidden');
            
            icon.classList.add('bi-list');
            icon.classList.remove('bi-x-lg');
            icon.classList.remove('text-green-600');
        }
    }
</script>