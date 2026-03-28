<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
include '../koneksi.php';

// Cek Login
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'kasir'])) { header("Location: ../login.php"); exit; }

// --- LOGIC CRUD ---
$alert_script = "";

// 1. TAMBAH / EDIT
if (isset($_POST['simpan_pengeluaran'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['expense_name']);
    $nominal = intval(str_replace('.', '', $_POST['amount'])); // Hapus titik jika format ribuan
    $tgl = $_POST['expense_date'];
    $ket = mysqli_real_escape_string($conn, $_POST['description']);
    $uid = $_SESSION['user_id'] ?? 1;

    if (!empty($_POST['id_edit'])) {
        // Update
        $id = intval($_POST['id_edit']);
        $q = "UPDATE expenses SET expense_name='$nama', amount='$nominal', expense_date='$tgl', description='$ket' WHERE id='$id'";
        $msg = "Data pengeluaran diperbarui.";
    } else {
        // Insert
        $q = "INSERT INTO expenses (user_id, expense_name, amount, expense_date, description) VALUES ('$uid', '$nama', '$nominal', '$tgl', '$ket')";
        $msg = "Pengeluaran berhasil ditambahkan.";
    }

    if (mysqli_query($conn, $q)) {
        $alert_script = "Swal.fire({icon:'success', title:'Berhasil', text:'$msg', background:'#ffffff', color:'#1e293b', confirmButtonColor:'#10b981', timer:1500, showConfirmButton:false}).then(()=>{ window.location='pengeluaran.php'; });";
    }
}

// 2. HAPUS
if (isset($_GET['hapus'])) {
    $id = intval($_GET['hapus']);
    mysqli_query($conn, "DELETE FROM expenses WHERE id='$id'");
    header("Location: pengeluaran.php"); exit;
}

// --- FILTER & QUERY DATA ---
$start_date = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
$end_date   = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');

$query = "SELECT * FROM expenses WHERE expense_date BETWEEN '$start_date' AND '$end_date' ORDER BY expense_date DESC, created_at DESC";
$result = mysqli_query($conn, $query);

// Hitung Total Pengeluaran Periode Ini
$q_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM expenses WHERE expense_date BETWEEN '$start_date' AND '$end_date'"));
$total_expense = $q_total['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengeluaran - POS PRO</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } } }</script>
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
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-50 border border-rose-200 text-rose-600 text-[10px] font-bold uppercase tracking-widest mb-3">
                    <i class="bi bi-cart-dash-fill"></i> Arus Kas Keluar
                </div>
                <h2 class="text-2xl md:text-3xl font-extrabold text-gray-800 tracking-tight">Data Pengeluaran</h2>
                <p class="text-gray-500 text-xs mt-1 font-medium">Catat biaya operasional seperti Listrik, Gaji, dan Belanja Bahan.</p>
            </div>
            
            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                <form class="bg-white p-1.5 rounded-xl flex items-center gap-2 shadow-sm border border-gray-200 w-full md:w-auto">
                    <div class="relative flex-1 md:flex-none">
                        <i class="bi bi-calendar-event absolute left-3 top-2 text-gray-400 text-xs"></i>
                        <input type="date" name="start" value="<?php echo $start_date; ?>" class="bg-transparent border-none text-gray-700 font-medium text-xs focus:ring-0 pl-8 pr-2 py-1.5 outline-none cursor-pointer w-full">
                    </div>
                    <span class="text-gray-400 font-bold">-</span>
                    <div class="relative flex-1 md:flex-none">
                        <i class="bi bi-calendar-event absolute left-3 top-2 text-gray-400 text-xs"></i>
                        <input type="date" name="end" value="<?php echo $end_date; ?>" class="bg-transparent border-none text-gray-700 font-medium text-xs focus:ring-0 pl-8 pr-2 py-1.5 outline-none cursor-pointer w-full">
                    </div>
                    <button type="submit" class="bg-rose-600 hover:bg-rose-500 text-white px-3 py-1.5 rounded-lg flex items-center justify-center transition shadow-sm font-bold text-xs shrink-0" title="Filter Tanggal">
                        <i class="bi bi-filter"></i>
                    </button>
                </form>

                <button onclick="openModal()" class="px-5 py-2.5 font-bold text-white rounded-xl bg-green-600 hover:bg-green-500 shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                    <i class="bi bi-plus-lg"></i> Input Biaya
                </button>
            </div>
        </div>

        <div class="bg-white p-6 md:p-8 rounded-3xl border border-gray-200 shadow-sm mb-6 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute right-0 top-0 p-8 opacity-[0.03] group-hover:scale-110 group-hover:rotate-12 transition-transform duration-500"><i class="bi bi-graph-down-arrow text-8xl text-gray-900"></i></div>
            <div class="relative z-10 flex justify-between items-center">
                <div>
                    <p class="text-gray-500 text-xs mb-1 font-bold uppercase tracking-wider">Total Pengeluaran (Periode Terpilih)</p>
                    <h3 class="text-3xl md:text-4xl font-black text-rose-500 tracking-tight">Rp <?php echo number_format($total_expense, 0, ',', '.'); ?></h3>
                </div>
                <div class="w-14 h-14 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-500 text-2xl shadow-inner shrink-0">
                    <i class="bi bi-wallet2"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-gray-500 border-b border-gray-200 text-xs uppercase tracking-wider bg-gray-50/50">
                            <th class="p-4 font-bold">Tanggal</th>
                            <th class="p-4 font-bold">Nama Pengeluaran</th>
                            <th class="p-4 font-bold">Keterangan</th>
                            <th class="p-4 font-bold text-right">Nominal</th>
                            <th class="p-4 font-bold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700 divide-y divide-gray-100 text-sm">
                        <?php if(mysqli_num_rows($result) > 0): while($row = mysqli_fetch_assoc($result)): ?>
                        <tr class="hover:bg-gray-50/80 transition group">
                            <td class="p-4 font-mono text-gray-500 text-xs font-semibold whitespace-nowrap">
                                <i class="bi bi-calendar-minus mr-1"></i> <?php echo date('d/m/Y', strtotime($row['expense_date'])); ?>
                            </td>
                            <td class="p-4 font-bold text-gray-800"><?php echo htmlspecialchars($row['expense_name']); ?></td>
                            <td class="p-4 text-xs text-gray-500 font-medium max-w-[200px] truncate" title="<?php echo htmlspecialchars($row['description']); ?>"><?php echo htmlspecialchars($row['description']) ?: '-'; ?></td>
                            <td class="p-4 font-black text-rose-500 text-right whitespace-nowrap">Rp <?php echo number_format($row['amount'], 0, ',', '.'); ?></td>
                            <td class="p-4 text-right">
                                <div class="flex justify-end gap-1.5">
                                    <button onclick='editData(<?php echo json_encode($row); ?>)' class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white border border-blue-100 hover:border-blue-600 transition shadow-sm" title="Edit"><i class="bi bi-pencil-fill text-xs"></i></button>
                                    <a href="?hapus=<?php echo $row['id']; ?>" onclick="return confirm('Hapus data pengeluaran ini?')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white border border-red-100 hover:border-red-500 transition shadow-sm" title="Hapus"><i class="bi bi-trash-fill text-xs"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="5" class="p-10 text-center text-gray-400 font-medium border border-dashed border-gray-200 rounded-xl m-4">Belum ada pencatatan pengeluaran pada periode ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <div id="modalExpense" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl flex flex-col transform scale-95 opacity-0 transition-all border border-gray-200" id="modalContent">
                
                <div class="flex justify-between items-center p-6 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="text-xl font-extrabold text-gray-800 flex items-center gap-2">
                        <div class="w-8 h-8 bg-rose-100 text-rose-600 rounded-lg flex items-center justify-center border border-rose-200"><i class="bi bi-cart-dash-fill"></i></div>
                        <span id="modalTitle">Catat Pengeluaran</span>
                    </h3>
                    <button onclick="closeModal()" class="w-8 h-8 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100 transition shadow-sm"><i class="bi bi-x-lg text-sm"></i></button>
                </div>
                
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="id_edit" id="inp_id">
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Tanggal</label>
                        <input type="date" name="expense_date" id="inp_date" required class="glass-input w-full px-4 py-2.5 rounded-xl font-semibold text-gray-700 cursor-pointer" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Nama Pengeluaran</label>
                        <input type="text" name="expense_name" id="inp_name" required class="glass-input w-full px-4 py-2.5 rounded-xl font-bold text-gray-800" placeholder="Contoh: Beli Gas, Bayar Listrik...">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Nominal (Rp)</label>
                        <div class="relative">
                            <span class="absolute left-4 top-2.5 font-bold text-gray-500">Rp</span>
                            <input type="number" name="amount" id="inp_amount" required class="glass-input w-full pl-11 pr-4 py-2.5 rounded-xl font-black text-rose-500 text-lg" placeholder="0">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5 ml-1">Keterangan Tambahan <span class="normal-case font-medium text-gray-400">(Opsional)</span></label>
                        <textarea name="description" id="inp_desc" rows="2" class="glass-input w-full px-4 py-3 rounded-xl text-sm font-medium text-gray-600 leading-relaxed" placeholder="Tulis rincian/catatan di sini..."></textarea>
                    </div>

                    <div class="pt-5 border-t border-gray-100 flex justify-end gap-3 mt-2">
                        <button type="button" onclick="closeModal()" class="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-500 font-bold hover:bg-gray-50 transition shadow-sm">Batal</button>
                        <button type="submit" name="simpan_pengeluaran" class="px-8 py-2.5 rounded-xl bg-green-600 hover:bg-green-500 text-white font-extrabold shadow-lg shadow-green-500/30 active:scale-95 transition">Simpan Data</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        <?php if($alert_script) echo $alert_script; ?>

        function openModal() {
            document.getElementById('modalExpense').classList.remove('hidden');
            setTimeout(() => { 
                document.getElementById('modalContent').classList.remove('scale-95', 'opacity-0'); 
            }, 10);
            
            // Reset Form
            document.getElementById('modalTitle').innerText = "Catat Pengeluaran";
            document.getElementById('inp_id').value = "";
            document.getElementById('inp_name').value = "";
            document.getElementById('inp_amount').value = "";
            document.getElementById('inp_desc').value = "";
            document.getElementById('inp_date').value = "<?php echo date('Y-m-d'); ?>";
        }

        function editData(data) {
            openModal();
            document.getElementById('modalTitle').innerText = "Edit Pengeluaran";
            document.getElementById('inp_id').value = data.id;
            document.getElementById('inp_name').value = data.expense_name;
            document.getElementById('inp_amount').value = data.amount;
            document.getElementById('inp_desc').value = data.description;
            document.getElementById('inp_date').value = data.expense_date;
        }

        function closeModal() {
            document.getElementById('modalContent').classList.add('scale-95', 'opacity-0');
            setTimeout(() => { 
                document.getElementById('modalExpense').classList.add('hidden'); 
            }, 200);
        }
    </script>
</body>
</html>