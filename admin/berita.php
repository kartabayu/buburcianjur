<?php
session_start();
include '../koneksi.php';

// FIX EMOJI
@mysqli_set_charset($conn, "utf8mb4");

// Cek Login
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'kasir'])) { 
    header("Location: ../login.php"); 
    exit; 
}

// Pastikan folder uploads/berita tersedia untuk menyimpan gambar
$target_dir = "../uploads/berita/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

// --- PROSES SIMPAN / UPDATE ARTIKEL ---
if (isset($_POST['simpan'])) {
    $id = isset($_POST['id_artikel']) ? intval($_POST['id_artikel']) : 0;
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $status = $_POST['status'];
    
    $image = $_POST['old_image'] ?? '';

    // Jika ada upload gambar baru
    if (isset($_FILES['image']['name']) && $_FILES['image']['name'] != '') {
        $file_name = $_FILES['image']['name'];
        $tmp_name = $_FILES['image']['tmp_name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $valid_ext = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($ext, $valid_ext)) {
            $new_image = time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($tmp_name, $target_dir . $new_image)) {
                // Hapus gambar lama jika ada
                if (!empty($image) && file_exists($target_dir . $image)) {
                    unlink($target_dir . $image);
                }
                $image = $new_image;
            }
        } else {
            echo "<script>alert('Format gambar tidak valid! Gunakan JPG, PNG, atau WEBP.'); window.location='berita.php';</script>";
            exit;
        }
    }

    if ($id > 0) {
        // UPDATE
        mysqli_query($conn, "UPDATE articles SET title='$title', content='$content', image='$image', status='$status' WHERE id='$id'");
    } else {
        // INSERT BARU
        mysqli_query($conn, "INSERT INTO articles (title, content, image, status) VALUES ('$title', '$content', '$image', '$status')");
    }
    
    header("Location: berita.php");
    exit;
}

// --- PROSES HAPUS ARTIKEL ---
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM articles WHERE id='$id'"));
    
    // Hapus file gambar dari server
    if (!empty($cek['image']) && file_exists($target_dir . $cek['image'])) {
        unlink($target_dir . $cek['image']);
    }
    
    mysqli_query($conn, "DELETE FROM articles WHERE id='$id'");
    header("Location: berita.php");
    exit;
}

// Ambil data artikel
$query = mysqli_query($conn, "SELECT * FROM articles ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Berita & Promo - POS PRO</title>
    
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
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-6 gap-4 border-b border-gray-200 pb-5">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-50 border border-blue-200 text-blue-600 text-[10px] font-bold uppercase tracking-widest mb-3">
                    <i class="bi bi-megaphone-fill"></i> Pusat Pengumuman
                </div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-gray-800 tracking-tight">Berita & Promo</h2>
                <p class="text-gray-500 text-xs mt-1 font-medium">Buat pengumuman atau artikel promo yang akan tampil di halaman depan pembeli.</p>
            </div>
            <button onclick="openModal()" class="px-5 py-2.5 font-bold text-white rounded-xl bg-green-600 hover:bg-green-500 shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                <i class="bi bi-pencil-square"></i> Tulis Artikel Baru
            </button>
        </div>

        <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-gray-500 border-b border-gray-200 text-xs uppercase font-bold tracking-wider bg-gray-50/50">
                            <th class="p-4 w-24">Banner</th>
                            <th class="p-4">Judul Artikel</th>
                            <th class="p-4">Tanggal Rilis</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm divide-y divide-gray-100 text-gray-700">
                        <?php if(mysqli_num_rows($query) > 0): while($row = mysqli_fetch_assoc($query)): ?>
                        <tr class="hover:bg-gray-50/80 transition-colors group">
                            <td class="p-4">
                                <?php if(!empty($row['image'])): ?>
                                    <div class="w-20 h-14 rounded-lg bg-gray-100 bg-cover bg-center border border-gray-200 shadow-sm" style="background-image: url('../uploads/berita/<?php echo htmlspecialchars($row['image']); ?>');"></div>
                                <?php else: ?>
                                    <div class="w-20 h-14 rounded-lg bg-gray-50 flex items-center justify-center text-gray-400 border border-gray-200 border-dashed"><i class="bi bi-image text-xl"></i></div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <div class="font-extrabold text-gray-800 text-base leading-tight mb-1"><?php echo htmlspecialchars($row['title']); ?></div>
                                <div class="text-xs text-gray-500 truncate max-w-xs md:max-w-md font-medium"><?php echo htmlspecialchars(strip_tags($row['content'])); ?></div>
                            </td>
                            <td class="p-4 text-xs font-bold text-gray-500">
                                <?php echo date('d M Y', strtotime($row['created_at'])); ?><br>
                                <span class="text-[10px] text-gray-400 font-medium"><i class="bi bi-clock"></i> <?php echo date('H:i', strtotime($row['created_at'])); ?></span>
                            </td>
                            <td class="p-4">
                                <?php if($row['status'] == 'published'): ?>
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-green-50 text-green-600 border border-green-200 tracking-wide uppercase"><i class="bi bi-globe mr-1"></i> Published</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-gray-100 text-gray-500 border border-gray-300 tracking-wide uppercase"><i class="bi bi-archive mr-1"></i> Draft</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button onclick='editArticle(<?php echo json_encode($row); ?>)' class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white border border-blue-100 hover:border-blue-600 transition shadow-sm" title="Edit"><i class="bi bi-pencil-fill text-xs"></i></button>
                                    <a href="?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus artikel ini permanen?')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white border border-red-100 hover:border-red-500 transition shadow-sm" title="Hapus"><i class="bi bi-trash-fill text-xs"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" class="p-10 text-center text-gray-400 font-medium border border-dashed border-gray-200 rounded-xl m-4">Belum ada artikel atau promo yang dibuat.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="modalArtikel" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl transform transition-all scale-95 opacity-0 border border-gray-200 flex flex-col max-h-[90vh]" id="modalContent">
                
                <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/80 sticky top-0 z-20 rounded-t-2xl backdrop-blur-md">
                    <h3 class="text-xl font-extrabold text-gray-800 flex items-center gap-2">
                        <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center border border-blue-200"><i class="bi bi-newspaper"></i></div>
                        <span id="modalTitle">Tulis Artikel Baru</span>
                    </h3>
                    <button onclick="closeModal()" class="w-8 h-8 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100 transition shadow-sm"><i class="bi bi-x-lg text-sm"></i></button>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="flex-1 overflow-y-auto custom-scrollbar p-6 space-y-5">
                    <input type="hidden" name="id_artikel" id="form-id">
                    <input type="hidden" name="old_image" id="form-old-image">

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Judul Artikel / Promo</label>
                        <input type="text" name="title" id="form-title" required class="glass-input w-full px-4 py-3 rounded-xl font-bold text-gray-800 text-lg" placeholder="Contoh: Promo Spesial Weekend! Diskon 50%">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="bg-gray-50 border border-gray-200 p-4 rounded-xl">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Upload Banner (Opsional)</label>
                            <input type="file" name="image" accept="image/*" class="w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer border border-gray-200 bg-white rounded-lg p-1">
                            <p class="text-[10px] text-gray-400 mt-2 font-medium"><i class="bi bi-info-circle"></i> Format: JPG, PNG. Rekomendasi rasio 16:9</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-200 p-4 rounded-xl">
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Status Publikasi</label>
                            <div class="relative">
                                <select name="status" id="form-status" class="glass-input w-full px-4 py-3 rounded-xl text-sm font-bold text-gray-700 appearance-none cursor-pointer">
                                    <option value="published">Tayangkan Langsung (Published)</option>
                                    <option value="draft">Simpan Dulu Saja (Draft)</option>
                                </select>
                                <i class="bi bi-chevron-down absolute right-4 top-3.5 text-gray-400 pointer-events-none text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Isi Artikel</label>
                        <textarea name="content" id="form-content" required rows="10" class="glass-input w-full px-5 py-4 rounded-xl text-sm leading-relaxed text-gray-700 font-medium" placeholder="Tulis rincian promo, syarat ketentuan, atau berita terbaru di sini... (Tekan Enter untuk membuat baris baru)"></textarea>
                    </div>

                    <div class="pt-5 border-t border-gray-100 flex justify-end gap-3 sticky bottom-0 bg-white pb-2">
                        <button type="button" onclick="closeModal()" class="px-6 py-2.5 rounded-xl border border-gray-200 text-gray-500 font-bold hover:bg-gray-50 transition shadow-sm">Batal</button>
                        <button type="submit" name="simpan" class="px-8 py-2.5 rounded-xl bg-green-600 hover:bg-green-500 text-white font-extrabold shadow-lg shadow-green-500/30 active:scale-95 transition flex items-center gap-2">
                            <i class="bi bi-send-fill"></i> Simpan Artikel
                        </button>
                    </div>
                </form>
                
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('modalTitle').innerText = 'Tulis Artikel Baru';
            document.getElementById('form-id').value = '';
            document.getElementById('form-title').value = '';
            document.getElementById('form-content').value = '';
            document.getElementById('form-status').value = 'published';
            document.getElementById('form-old-image').value = '';

            document.getElementById('modalArtikel').classList.remove('hidden');
            setTimeout(() => { document.getElementById('modalContent').classList.remove('scale-95', 'opacity-0'); }, 10);
        }

        function editArticle(data) {
            document.getElementById('modalTitle').innerText = 'Edit Artikel';
            document.getElementById('form-id').value = data.id;
            document.getElementById('form-title').value = data.title;
            // Gunakan regex untuk mengembalikan <br> menjadi newline agar rapi saat diedit di textarea
            document.getElementById('form-content').value = data.content; 
            document.getElementById('form-status').value = data.status;
            document.getElementById('form-old-image').value = data.image;

            document.getElementById('modalArtikel').classList.remove('hidden');
            setTimeout(() => { document.getElementById('modalContent').classList.remove('scale-95', 'opacity-0'); }, 10);
        }

        function closeModal() {
            document.getElementById('modalContent').classList.add('scale-95', 'opacity-0');
            setTimeout(() => { document.getElementById('modalArtikel').classList.add('hidden'); }, 200);
        }
    </script>
</body>
</html>