<?php
include '../koneksi.php';

// --- INITIALIZE VARIABLES UNTUK EDIT ---
$is_edit = false;
$edit_data = [];
$current_perms = [];

// MODE EDIT
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $q = mysqli_query($conn, "SELECT * FROM users WHERE id='$id'");
    if (mysqli_num_rows($q) > 0) {
        $is_edit = true;
        $edit_data = mysqli_fetch_assoc($q);
        $current_perms = explode(',', $edit_data['permissions'] ?? ''); 
    }
}

// --- PROSES SIMPAN (TAMBAH / UPDATE) ---
if (isset($_POST['simpan'])) {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $username = mysqli_real_escape_string($conn, $_POST['username']); 
    $phone = mysqli_real_escape_string($conn, $_POST['phone']); 
    $email = mysqli_real_escape_string($conn, $_POST['email']); 
    $address = mysqli_real_escape_string($conn, $_POST['address']); 
    $role = $_POST['role'];
    
    // Ambil Checklist Permissions (Hanya jika role adalah admin atau kasir)
    $perms = "";
    if ($role == 'admin' || $role == 'kasir') {
        $perms = isset($_POST['access']) ? implode(',', $_POST['access']) : '';
    }
    
    // Password Logic
    $password_sql = "";
    if (!empty($_POST['password'])) {
        $pass_hash = md5($_POST['password']);
        $password_sql = ", password='$pass_hash'";
    }

    if (isset($_POST['id_user']) && !empty($_POST['id_user'])) {
        // UPDATE 
        $id = intval($_POST['id_user']);
        $query = "UPDATE users SET fullname='$fullname', username='$username', phone='$phone', email='$email', address='$address', role='$role', permissions='$perms' $password_sql WHERE id='$id'";
        mysqli_query($conn, $query);
    } else {
        // INSERT BARU 
        $pass_hash = md5($_POST['password']); 
        $query = "INSERT INTO users (fullname, username, phone, email, address, password, role, permissions) VALUES ('$fullname', '$username', '$phone', '$email', '$address', '$pass_hash', '$role', '$perms')";
        mysqli_query($conn, $query);
    }
    
    // Redirect balik ke tab yang sesuai
    $tab = ($role == 'member') ? 'member' : 'staff';
    header("Location: pegawai.php?tab=$tab"); exit;
}

// --- HAPUS ---
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    // Cegah hapus diri sendiri
    if(isset($_SESSION['user_id']) && $_SESSION['user_id'] == $id) {
        echo "<script>alert('Tidak bisa menghapus akun Anda sendiri!'); window.location='pegawai.php';</script>";
    } else {
        $cek = mysqli_fetch_assoc(mysqli_query($conn, "SELECT role FROM users WHERE id='$id'"));
        $tab = ($cek['role'] == 'member') ? 'member' : 'staff';
        
        mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
        header("Location: pegawai.php?tab=$tab"); exit;
    }
}

// TENTUKAN TAB AKTIF
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'staff';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen User - POS PRO</title>
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
        
        .tab-btn { opacity: 0.5; border-bottom: 3px solid transparent; transition: all 0.3s; }
        .tab-btn:hover { opacity: 0.8; }
        .tab-btn.active { opacity: 1; border-color: #10b981; color: #059669; }

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
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-600 text-[10px] font-bold uppercase tracking-widest mb-3">
                    <i class="bi bi-people-fill"></i> Data Pengguna
                </div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-gray-800 tracking-tight">Manajemen User</h2>
                <p class="text-gray-500 text-xs mt-1 font-medium">Kelola akun akses pegawai dan data member/pelanggan.</p>
            </div>
            <button onclick="openModal()" class="px-5 py-2.5 font-bold text-white rounded-xl bg-green-600 hover:bg-green-500 shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                <i class="bi bi-person-plus-fill"></i> Tambah User
            </button>
        </div>

        <div class="flex gap-6 border-b border-gray-200 mb-6 px-2">
            <button onclick="switchTab('staff')" id="btn-staff" class="tab-btn pb-3 px-2 font-extrabold text-sm <?php echo $active_tab == 'staff' ? 'active' : ''; ?>">
                <i class="bi bi-person-badge mr-1"></i> Data Staff
            </button>
            <button onclick="switchTab('member')" id="btn-member" class="tab-btn pb-3 px-2 font-extrabold text-sm <?php echo $active_tab == 'member' ? 'active' : ''; ?>">
                <i class="bi bi-people mr-1"></i> Data Member
            </button>
        </div>

        <div id="content-staff" class="<?php echo $active_tab == 'staff' ? '' : 'hidden'; ?>">
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-200">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-gray-500 border-b border-gray-200 text-xs uppercase tracking-wider bg-gray-50/50">
                                <th class="p-4 font-bold">Nama Staff</th>
                                <th class="p-4 font-bold">Role / Jabatan</th>
                                <th class="p-4 font-bold">Hak Akses Menu</th>
                                <th class="p-4 font-bold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-100">
                            <?php
                            $q = mysqli_query($conn, "SELECT * FROM users WHERE role IN ('admin', 'kasir', 'dapur', 'driver') ORDER BY role ASC, fullname ASC");
                            while($row = mysqli_fetch_assoc($q)) {
                                if($row['role'] == 'admin') $roleBadge = '<span class="px-2 py-1 rounded-md font-bold text-[10px] bg-purple-50 text-purple-600 border border-purple-200 uppercase tracking-wide">Super Admin</span>';
                                elseif($row['role'] == 'kasir') $roleBadge = '<span class="px-2 py-1 rounded-md font-bold text-[10px] bg-blue-50 text-blue-600 border border-blue-200 uppercase tracking-wide">Kasir/Staff</span>';
                                elseif($row['role'] == 'dapur') $roleBadge = '<span class="px-2 py-1 rounded-md font-bold text-[10px] bg-amber-50 text-amber-600 border border-amber-200 uppercase tracking-wide">Dapur</span>';
                                elseif($row['role'] == 'driver') $roleBadge = '<span class="px-2 py-1 rounded-md font-bold text-[10px] bg-emerald-50 text-emerald-600 border border-emerald-200 uppercase tracking-wide">Driver</span>';
                                else $roleBadge = '<span class="px-2 py-1 rounded-md font-bold text-[10px] bg-gray-100 text-gray-600 border border-gray-300 uppercase tracking-wide">'.$row['role'].'</span>';
                                
                                $perms_display = "";
                                if($row['permissions']) {
                                    $p_list = explode(',', $row['permissions']);
                                    foreach($p_list as $p) {
                                        $perms_display .= "<span class='inline-block px-2 py-0.5 mr-1 mb-1 rounded bg-gray-100 font-semibold text-[10px] text-gray-600 border border-gray-200'>".ucfirst($p)."</span>";
                                    }
                                } else { 
                                    $perms_display = "<span class='text-gray-400 italic text-[10px] font-medium'>Akses Bawaan Role</span>"; 
                                }
                            ?>
                            <tr class="hover:bg-gray-50/80 transition">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-gray-200 to-gray-100 border border-gray-300 flex items-center justify-center font-bold text-gray-600 text-sm shadow-inner shrink-0">
                                            <?php echo substr($row['fullname'], 0, 1); ?>
                                        </div>
                                        <div>
                                            <div class="font-extrabold text-gray-800 text-sm"><?php echo htmlspecialchars($row['fullname']); ?></div>
                                            <div class="text-xs text-gray-500 font-medium">@<?php echo htmlspecialchars($row['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4"><?php echo $roleBadge; ?></td>
                                <td class="p-4"><?php echo $perms_display; ?></td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-1.5">
                                        <a href="?edit=<?php echo $row['id']; ?>&tab=staff" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white border border-blue-100 hover:border-blue-600 transition shadow-sm" title="Edit"><i class="bi bi-pencil-fill text-xs"></i></a>
                                        <a href="?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Yakin ingin menghapus staff ini?')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white border border-red-100 hover:border-red-500 transition shadow-sm" title="Hapus"><i class="bi bi-trash-fill text-xs"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="content-member" class="<?php echo $active_tab == 'member' ? '' : 'hidden'; ?>">
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-200">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-gray-500 border-b border-gray-200 text-xs uppercase tracking-wider bg-gray-50/50">
                                <th class="p-4 font-bold">Nama Member</th>
                                <th class="p-4 font-bold">Kontak</th>
                                <th class="p-4 font-bold">Tgl Daftar</th>
                                <th class="p-4 font-bold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700 divide-y divide-gray-100">
                            <?php
                            $q_mem = mysqli_query($conn, "SELECT * FROM users WHERE role = 'member' ORDER BY id DESC");
                            if(mysqli_num_rows($q_mem) > 0) {
                                while($mem = mysqli_fetch_assoc($q_mem)) {
                                    $is_google = !empty($mem['google_id']);
                                    $tgl_daftar = isset($mem['created_at']) ? date('d M Y', strtotime($mem['created_at'])) : '-';
                            ?>
                            <tr class="hover:bg-gray-50/80 transition">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center font-bold text-emerald-600 text-sm relative shrink-0">
                                            <i class="bi bi-person-fill"></i>
                                            <?php if($is_google): ?>
                                            <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-white rounded-full flex items-center justify-center shadow border border-gray-100" title="Login Google">
                                                <svg class="w-2.5 h-2.5" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-extrabold text-gray-800 text-sm"><?php echo htmlspecialchars($mem['fullname']); ?></div>
                                            <div class="text-[10px] text-gray-500 font-medium truncate w-40" title="<?php echo htmlspecialchars($mem['address'] ?? ''); ?>"><?php echo !empty($mem['address']) ? htmlspecialchars($mem['address']) : 'Alamat tidak diisi'; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <div class="text-xs text-gray-700 font-bold mb-0.5"><i class="bi bi-person-badge text-gray-400 mr-1"></i> <?php echo htmlspecialchars($mem['username']); ?></div>
                                    <div class="text-xs text-gray-500 font-medium"><i class="bi bi-telephone text-gray-400 mr-1"></i> <?php echo !empty($mem['phone']) ? htmlspecialchars($mem['phone']) : '-'; ?></div>
                                </td>
                                <td class="p-4 text-xs font-bold text-gray-500">
                                    <?php echo $tgl_daftar; ?>
                                </td>
                                <td class="p-4 text-right">
                                    <div class="flex justify-end gap-1.5">
                                        <a href="?edit=<?php echo $mem['id']; ?>&tab=member" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white border border-blue-100 hover:border-blue-600 transition shadow-sm" title="Edit"><i class="bi bi-pencil-fill text-xs"></i></a>
                                        <a href="?hapus=<?php echo $mem['id']; ?>" onclick="return confirm('Yakin ingin menghapus member ini?')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white border border-red-100 hover:border-red-500 transition shadow-sm" title="Hapus"><i class="bi bi-trash-fill text-xs"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else {
                                echo "<tr><td colspan='4' class='p-10 text-center text-gray-400 font-medium border border-dashed border-gray-200 rounded-xl'>Belum ada data member yang mendaftar.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <div id="modalPegawai" class="fixed inset-0 z-50 <?php echo ($is_edit) ? '' : 'hidden'; ?>">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="window.location.href='pegawai.php'"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl flex flex-col border border-gray-200 relative max-h-[90vh] overflow-y-auto custom-scrollbar">
                
                <div class="flex justify-between items-center p-6 border-b border-gray-100 sticky top-0 bg-white/95 backdrop-blur-sm z-20">
                    <h3 class="text-xl font-extrabold text-gray-800 flex items-center gap-2">
                        <div class="w-8 h-8 bg-green-100 text-green-600 rounded-lg flex items-center justify-center border border-green-200"><i class="bi bi-person-fill"></i></div>
                        <?php echo $is_edit ? 'Edit Data User' : 'Tambah User Baru'; ?>
                    </h3>
                    <a href="pegawai.php" class="w-8 h-8 bg-gray-50 border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100 transition shadow-sm"><i class="bi bi-x-lg text-sm"></i></a>
                </div>

                <form method="POST" class="p-6 space-y-5">
                    <?php if($is_edit): ?>
                        <input type="hidden" name="id_user" value="<?php echo $edit_data['id']; ?>">
                    <?php endif; ?>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Nama Lengkap</label>
                            <input type="text" name="fullname" value="<?php echo $is_edit ? htmlspecialchars($edit_data['fullname']) : ''; ?>" required class="glass-input w-full px-4 py-2.5 rounded-xl font-semibold text-gray-800" placeholder="Nama User">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Role / Jabatan</label>
                            <div class="relative">
                                <select name="role" id="roleSelect" onchange="togglePermissions()" class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-gray-700 appearance-none cursor-pointer">
                                    <option value="member" <?php echo ($is_edit && $edit_data['role']=='member') ? 'selected' : ''; ?>>👤 Member / Pelanggan</option>
                                    <option value="kasir" <?php echo ($is_edit && $edit_data['role']=='kasir') ? 'selected' : ''; ?>>💼 Kasir / Staff</option>
                                    <option value="dapur" <?php echo ($is_edit && $edit_data['role']=='dapur') ? 'selected' : ''; ?>>🍳 Dapur / Kitchen</option>
                                    <option value="driver" <?php echo ($is_edit && $edit_data['role']=='driver') ? 'selected' : ''; ?>>🛵 Driver / Kurir</option>
                                    <option value="admin" <?php echo ($is_edit && $edit_data['role']=='admin') ? 'selected' : ''; ?>>👑 Admin (Full Akses)</option>
                                </select>
                                <i class="bi bi-chevron-down absolute right-4 top-3 text-gray-400 pointer-events-none text-xs font-bold"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Username Login</label>
                            <input type="text" name="username" value="<?php echo $is_edit ? htmlspecialchars($edit_data['username']) : ''; ?>" required class="glass-input w-full px-4 py-2.5 rounded-xl font-semibold text-gray-800" placeholder="Cth: budi123">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">No. WhatsApp</label>
                            <input type="text" name="phone" value="<?php echo $is_edit ? htmlspecialchars($edit_data['phone'] ?? '') : ''; ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-semibold text-gray-800" placeholder="08xxxxxxxx">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Email (Opsional)</label>
                            <input type="email" name="email" value="<?php echo $is_edit ? htmlspecialchars($edit_data['email'] ?? '') : ''; ?>" class="glass-input w-full px-4 py-2.5 rounded-xl font-medium text-gray-700" placeholder="user@mail.com">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Password <?php echo $is_edit ? '<span class="text-[9px] text-red-400 lowercase normal-case">(Isi jika ingin diubah)</span>' : ''; ?></label>
                            <input type="password" name="password" <?php echo $is_edit ? '' : 'required'; ?> class="glass-input w-full px-4 py-2.5 rounded-xl font-medium" placeholder="••••••">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Alamat (Opsional)</label>
                        <textarea name="address" rows="2" class="glass-input w-full px-4 py-3 rounded-xl text-sm font-medium text-gray-700 leading-relaxed" placeholder="Alamat lengkap pengiriman/tinggal..."><?php echo $is_edit ? htmlspecialchars($edit_data['address'] ?? '') : ''; ?></textarea>
                    </div>

                    <div id="permissionBox" class="bg-gray-50 p-5 rounded-2xl border border-gray-200 mt-2 shadow-inner">
                        <h4 class="text-xs font-extrabold text-blue-600 mb-3 uppercase tracking-wider flex items-center gap-1"><i class="bi bi-shield-lock-fill"></i> Izin Akses Menu (Admin/Kasir)</h4>
                        <div class="grid grid-cols-2 gap-3">
                            <?php 
                            $menus = [
                                'dashboard' => 'Dashboard', 'produk' => 'Produk & Stok',
                                'kasir' => 'Mesin Kasir', 'pegawai' => 'Manajemen User',
                                'laporan' => 'Laporan', 'settings' => 'Pengaturan'
                            ];
                            foreach($menus as $key => $label): 
                                $checked = in_array($key, $current_perms) ? 'checked' : '';
                            ?>
                            <label class="flex items-center gap-3 p-2.5 rounded-xl border border-transparent hover:bg-white hover:border-gray-200 hover:shadow-sm cursor-pointer transition select-none group">
                                <div class="relative flex items-center">
                                    <input type="checkbox" name="access[]" value="<?php echo $key; ?>" <?php echo $checked; ?> class="peer sr-only">
                                    <div class="w-5 h-5 border-2 border-gray-300 rounded-md peer-checked:bg-green-500 peer-checked:border-green-500 transition"></div>
                                    <i class="bi bi-check text-white absolute left-[2px] top-px opacity-0 peer-checked:opacity-100 text-sm font-bold pointer-events-none"></i>
                                </div>
                                <span class="text-sm font-bold text-gray-600 group-hover:text-gray-900"><?php echo $label; ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="pt-5 border-t border-gray-200 flex justify-end gap-3 sticky bottom-0 bg-white pb-2">
                        <a href="pegawai.php" class="px-6 py-2.5 rounded-xl border border-gray-200 text-gray-500 font-bold hover:bg-gray-50 transition shadow-sm">Batal</a>
                        <button type="submit" name="simpan" class="px-8 py-2.5 rounded-xl bg-green-600 hover:bg-green-500 text-white font-extrabold shadow-lg shadow-green-500/30 active:scale-95 transition">Simpan Data</button>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('modalPegawai').classList.remove('hidden');
        }

        // Logic Tab Switching
        function switchTab(tab) {
            document.getElementById('btn-staff').classList.remove('active');
            document.getElementById('btn-member').classList.remove('active');
            document.getElementById('btn-' + tab).classList.add('active');

            document.getElementById('content-staff').classList.add('hidden');
            document.getElementById('content-member').classList.add('hidden');
            document.getElementById('content-' + tab).classList.remove('hidden');
        }

        // Logic Hide Permissions kalau Role = Member/Dapur/Driver
        function togglePermissions() {
            const role = document.getElementById('roleSelect').value;
            const box = document.getElementById('permissionBox');
            
            if (role === 'member' || role === 'dapur' || role === 'driver') {
                box.style.display = 'none';
            } else {
                box.style.display = 'block';
            }
        }

        window.onload = function() {
            togglePermissions();
        }
    </script>
</body>
</html>