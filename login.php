<?php
session_start();
include 'koneksi.php'; // PASTIKAN KONEKSI BERADA DI SINI

// TANGKAP KODE REFERRAL JIKA ADA DI URL
if (isset($_GET['ref'])) {
    $ref_id = (int)$_GET['ref'];
    setcookie('ref_code', $ref_id, time() + 86400, "/");
}

// 1. CEK SESI: Pintu Otomatis jika sudah login
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/index.php");
    } elseif ($_SESSION['role'] == 'kasir') {
        header("Location: kasir/index.php");
    } elseif ($_SESSION['role'] == 'dapur') {
        header("Location: dapur/index.php"); // Redirect ke Dapur
    } elseif ($_SESSION['role'] == 'driver') {
        header("Location: driver/index.php"); // Redirect ke Driver
    } elseif ($_SESSION['role'] == 'member') {
        header("Location: member/index.php");
    }
    exit;
}

// 2. AMBIL PENGATURAN TOKO
$shop_setting = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));
$google_active = !empty($shop_setting['google_client_id']) && !empty($shop_setting['google_client_secret']);
$allow_manual_reg = (!isset($shop_setting['allow_manual_reg']) || $shop_setting['allow_manual_reg'] == 1);

// 3. PROSES LOGIN
$error = "";
if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = md5(mysqli_real_escape_string($conn, $_POST['password']));
    
    $q = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' AND password='$password'");
    
    if (mysqli_num_rows($q) > 0) {
        $data = mysqli_fetch_assoc($q);
        
        // Simpan Data ke Session
        $_SESSION['user_id']  = $data['id']; 
        $_SESSION['username'] = $data['username'];
        $_SESSION['role']     = $data['role'];
        $_SESSION['fullname'] = $data['fullname'];

        // Pintu Otomatis setelah submit login
        if ($data['role'] == 'admin') {
            header("Location: admin/index.php");
        } elseif ($data['role'] == 'kasir') {
            header("Location: kasir/index.php");
        } elseif ($data['role'] == 'dapur') {
            header("Location: dapur/index.php"); // Ke Dapur
        } elseif ($data['role'] == 'driver') {
            header("Location: driver/index.php"); // Ke Driver
        } elseif ($data['role'] == 'member') {
            header("Location: member/index.php");
        }
        exit; // PENTING: Tambahkan exit setelah header location
    } else {
        $error = "Username atau Password tidak ditemukan.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($shop_setting['shop_name']); ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    animation: {
                        'float': 'float 6s ease-in-out infinite',
                        'fade-in-up': 'fadeInUp 0.5s ease-out'
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-15px)' },
                        },
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            color: #1e293b;
        }
        .glass-card { background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.05); }
        .input-field { background: #f8fafc; border: 1px solid #e2e8f0; color: #1e293b; transition: all 0.3s; }
        .input-field:focus { background: #ffffff; border-color: #10b981; outline: none; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        .input-field::placeholder { color: #94a3b8; }
        .blob { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.15; z-index: -1; }
        .blob-1 { top: -10%; left: -10%; width: 400px; height: 400px; background: #10b981; animation: float 8s infinite alternate; }
        .blob-2 { bottom: -10%; right: -10%; width: 350px; height: 350px; background: #34d399; animation: float 10s infinite alternate-reverse; }
    </style>
</head>
<body class="p-4 relative">

    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="glass-card w-full max-w-sm md:max-w-md p-8 rounded-3xl relative z-10 animate-fade-in-up">
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-green-500 to-emerald-600 mb-4 shadow-lg shadow-green-500/30 animate-float">
                <i class="bi bi-person-workspace text-3xl text-white"></i>
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight text-gray-800">Selamat Datang</h2>
            <p class="text-gray-500 text-sm mt-1">Silakan login ke akun Anda</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-500 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-2 font-medium">
                <i class="bi bi-exclamation-circle-fill"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1.5 ml-1 uppercase tracking-wider">Username</label>
                <div class="relative">
                    <i class="bi bi-person absolute left-4 top-3.5 text-gray-400"></i>
                    <input type="text" name="username" class="input-field w-full pl-11 pr-4 py-3 rounded-xl font-medium" placeholder="Masukkan username" required autocomplete="off">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1.5 ml-1 uppercase tracking-wider">Password</label>
                <div class="relative">
                    <i class="bi bi-key absolute left-4 top-3.5 text-gray-400"></i>
                    <input type="password" name="password" class="input-field w-full pl-11 pr-4 py-3 rounded-xl font-medium" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" name="login" class="w-full py-3.5 rounded-xl bg-green-600 hover:bg-green-500 text-white font-extrabold shadow-lg shadow-green-500/30 transition transform active:scale-[0.98] duration-200">
                MASUK SISTEM
            </button>
        </form>

        <?php if($google_active): ?>
        <div class="relative flex py-6 items-center">
            <div class="flex-grow border-t border-gray-200"></div>
            <span class="flex-shrink-0 mx-4 text-gray-400 text-[10px] uppercase font-bold tracking-widest">Metode Lain</span>
            <div class="flex-grow border-t border-gray-200"></div>
        </div>

        <a href="google_auth.php" class="flex items-center justify-center gap-3 w-full py-3 rounded-xl border border-gray-200 bg-white text-gray-700 font-bold hover:bg-gray-50 transition shadow-sm mb-4 transform active:scale-95">
            <svg class="w-5 h-5" viewBox="0 0 24 24">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            <span>Masuk dengan Google</span>
        </a>
        <?php else: ?>
        <div class="relative flex py-8 items-center">
            <div class="flex-grow border-t border-gray-200"></div>
            <span class="flex-shrink-0 mx-4 text-gray-400 text-xs uppercase font-bold">Atau</span>
            <div class="flex-grow border-t border-gray-200"></div>
        </div>
        <?php endif; ?>

        <div class="mb-4 text-center">
            <p class="text-sm text-gray-500 font-medium">Belum punya akun? <a href="register.php" class="text-green-600 font-bold hover:text-green-500 hover:underline transition">Daftar di sini</a></p>
        </div>

        <a href="index.php" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl border border-gray-200 bg-gray-50 hover:bg-gray-100 text-gray-600 hover:text-gray-800 transition group mt-2 font-bold">
            <span class="text-sm">Ke Menu Pembeli (Tanpa Login)</span>
            <i class="bi bi-arrow-right group-hover:translate-x-1 transition"></i>
        </a>

        <div class="mt-6 text-center">
            <p class="text-[10px] text-gray-400 font-medium">© 2026 <?php echo htmlspecialchars($shop_setting['shop_name']); ?>. All rights reserved.</p>
        </div>

    </div>

</body>
</html>