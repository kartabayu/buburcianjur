<?php
session_start();
include 'koneksi.php';

// FIX EMOJI
@mysqli_set_charset($conn, "utf8mb4");

// AMBIL SETTINGS
$shop = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));

// CEK LOGIN MEMBER (Untuk Bottom Nav)
$is_member = false;
if(isset($_SESSION['role']) && $_SESSION['role'] == 'member') {
    $is_member = true;
}

// AMBIL SEMUA ARTIKEL PUBLISHED
$q_berita = mysqli_query($conn, "SELECT * FROM articles WHERE status='published' ORDER BY created_at DESC");
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
    <title>Promo & Kabar - <?php echo htmlspecialchars($shop['shop_name']); ?></title>
    
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#ffffff">
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($shop['logo']); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } } }
    </script>

    <style>
        body { background-color: #f8fafc; color: #1e293b; overflow-x: hidden; -webkit-tap-highlight-color: transparent; padding-bottom: 90px; }
        .hide-scroll::-webkit-scrollbar { display: none; }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        /* BOTTOM NAV */
        .nav-item { color: #94a3b8; font-size: 10px; font-weight: 700; display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; padding: 10px 0; transition: 0.2s; }
        .nav-item.active { color: #10b981; }
        .pb-safe { padding-bottom: env(safe-area-inset-bottom, 20px); }
    </style>
</head>
<body> 

    <header class="bg-white px-4 md:px-6 py-4 sticky top-0 z-40 shadow-sm border-b border-gray-100 flex items-center justify-between">
        <div>
            <h1 class="text-lg md:text-xl font-extrabold text-gray-800 flex items-center gap-2">
                <i class="bi bi-stars text-yellow-500"></i> Kabar & Promo Terkini
            </h1>
            <p class="text-[10px] md:text-xs text-gray-500 mt-0.5">Berita terbaru dan diskon spesial untukmu!</p>
        </div>
        <a href="index.php" class="w-8 h-8 md:w-10 md:h-10 bg-gray-50 hover:bg-gray-100 rounded-full flex items-center justify-center text-gray-600 transition">
            <i class="bi bi-x-lg text-sm md:text-base"></i>
        </a>
    </header>

    <main class="max-w-5xl mx-auto p-4 md:p-6 mt-2">
        <?php if(count($berita_list) > 0): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach($berita_list as $art): 
                    $imgArt = !empty($art['image']) ? "uploads/berita/".$art['image'] : "https://placehold.co/600x300/10b981/ffffff?text=".urlencode($art['title']);
                ?>
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm flex flex-col cursor-pointer hover:shadow-md transition-transform active:scale-[0.98]" onclick='openArticleModal(<?php echo htmlspecialchars(json_encode($art)); ?>)'>
                    <div class="h-48 w-full bg-gray-100 relative shrink-0">
                        <img src="<?php echo htmlspecialchars($imgArt); ?>" class="w-full h-full object-cover" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent"></div>
                        <span class="absolute bottom-3 right-3 text-[10px] font-bold bg-white/90 text-gray-800 px-2.5 py-1 rounded shadow-sm backdrop-blur-sm"><i class="bi bi-calendar-event"></i> <?php echo date('d M Y', strtotime($art['created_at'])); ?></span>
                    </div>
                    <div class="p-5 flex flex-col flex-1">
                        <h4 class="font-bold text-gray-800 text-base leading-snug line-clamp-2"><?php echo htmlspecialchars($art['title']); ?></h4>
                        <p class="text-xs text-gray-500 mt-2.5 line-clamp-3 leading-relaxed flex-1"><?php echo htmlspecialchars(strip_tags($art['content'])); ?></p>
                        <div class="mt-4 text-[11px] font-bold text-green-600 flex items-center gap-1 group bg-green-50 w-fit px-3 py-1.5 rounded-lg border border-green-100">
                            Baca detail <i class="bi bi-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="flex flex-col items-center justify-center py-20 opacity-50">
                <div class="w-20 h-20 bg-gray-200 rounded-full flex items-center justify-center mb-4 text-gray-400">
                    <i class="bi bi-newspaper text-4xl"></i>
                </div>
                <p class="text-gray-500 font-medium">Belum ada promo atau berita saat ini.</p>
                <a href="index.php" class="mt-4 text-green-600 font-bold text-sm underline">Kembali ke Beranda</a>
            </div>
        <?php endif; ?>
    </main>

    <nav class="bottom-nav fixed bottom-0 w-full z-30 bg-transparent flex justify-center pointer-events-none">
        <div class="w-full max-w-5xl bg-white border-t border-gray-200 flex justify-around items-center px-1 pb-safe shadow-[0_-5px_15px_rgba(0,0,0,0.05)] md:rounded-t-2xl pointer-events-auto">
            <a href="index.php" class="nav-item">
                <i class="bi bi-house-door-fill text-[20px] md:text-[22px]"></i>
                <span class="mt-1">Beranda</span>
            </a>
            <a href="index.php#productSection" class="nav-item">
                <i class="bi bi-grid-fill text-[20px] md:text-[22px]"></i>
                <span class="mt-1">Menu</span>
            </a>
            
            <a href="#" class="nav-item active relative">
                <div class="relative inline-block">
                    <i class="bi bi-newspaper text-[20px] md:text-[22px]"></i>
                </div>
                <span class="mt-1">Promo</span>
            </a>
            
            <a href="index.php" onclick="alert('Silakan kembali ke beranda untuk melihat keranjang.')" class="nav-item">
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

    <div id="articleModal" class="fixed inset-0 z-[80] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity opacity-0" id="articleBackdrop" onclick="closeModal('articleModal')"></div>
        <div class="absolute inset-0 flex items-end md:items-center justify-center p-0 md:p-4 pointer-events-none">
            <div class="bg-white w-full md:max-w-xl h-[85vh] md:h-auto md:max-h-[85vh] rounded-t-3xl md:rounded-2xl shadow-2xl flex flex-col pointer-events-auto transform translate-y-full md:translate-y-0 md:scale-95 md:opacity-0 transition-all duration-300 overflow-hidden border border-gray-200" id="articleModalContent">
                
                <button onclick="closeModal('articleModal')" class="absolute top-4 right-4 z-10 w-8 h-8 bg-black/50 text-white rounded-full flex items-center justify-center hover:bg-black/70 transition backdrop-blur-md"><i class="bi bi-x-lg text-sm"></i></button>
                
                <div class="w-full h-56 md:h-64 bg-gray-100 shrink-0 relative">
                    <img id="artImg" src="" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-white via-transparent to-transparent"></div>
                </div>
                
                <div class="flex-1 bg-white overflow-y-auto custom-scrollbar p-6 md:p-8 -mt-8 relative z-10 rounded-t-3xl">
                    <div class="text-[10px] md:text-xs font-bold text-green-600 mb-2.5" id="artDate"><i class="bi bi-calendar-event"></i> </div>
                    <h2 class="text-2xl md:text-3xl font-black text-gray-800 leading-tight mb-5" id="artTitle"></h2>
                    <div class="text-sm md:text-base text-gray-600 leading-relaxed whitespace-pre-wrap font-medium" id="artContent"></div>
                </div>
                
                <div class="p-4 md:p-5 border-t border-gray-100 bg-gray-50 shrink-0 pb-safe">
                    <a href="index.php" class="w-full block text-center py-3.5 md:py-4 rounded-xl bg-green-600 text-white font-extrabold text-sm md:text-base shadow-lg shadow-green-500/30 hover:bg-green-500 transition active:scale-95">Mulai Pesan Sekarang</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tampilkan badge keranjang jika ada isinya (mengambil dari localStorage index.php)
        window.onload = function() {
            let cart = JSON.parse(localStorage.getItem('pos_cart')) || [];
            let count = cart.reduce((n, i) => n + i.qty, 0);
            const badge = document.getElementById('navCartBadge');
            if(count > 0) { 
                badge.innerText = count; 
                badge.classList.remove('hidden'); 
            }
        };

        // FUNGSI MODAL ARTIKEL BACA
        function openArticleModal(data) {
            document.getElementById('artTitle').innerText = data.title;
            // Format ganti baris
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

        // FUNGSI GLOBAL TUTUP MODAL
        function closeModal(id) {
            const modal = document.getElementById(id);
            const content = document.getElementById(id + 'Content');
            const backdrop = document.getElementById(id === 'articleModal' ? 'articleBackdrop' : 'modalBackdrop');
            
            backdrop.classList.add('opacity-0');
            content.classList.add('translate-y-full', 'md:scale-95', 'md:opacity-0');
            setTimeout(() => modal.classList.add('hidden'), 300);
        }
    </script>
</body>
</html>