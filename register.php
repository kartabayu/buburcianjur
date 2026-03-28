<?php
session_start();
include 'koneksi.php';

// TANGKAP KODE REFERRAL JIKA ADA DI URL
if (isset($_GET['ref'])) {
    $ref_id = (int)$_GET['ref'];
    setcookie('ref_code', $ref_id, time() + 86400, "/");
}

// Jika sudah login, lempar ke dashboard
if (isset($_SESSION['role'])) {
    header("Location: member/index.php");
    exit;
}

$shop_setting = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));
$google_active = !empty($shop_setting['google_client_id']) && !empty($shop_setting['google_client_secret']);

// CEK STATUS IZIN DAFTAR MANUAL
$allow_manual_reg = (!isset($shop_setting['allow_manual_reg']) || $shop_setting['allow_manual_reg'] == 1);

$error = "";
$success = "";

if (isset($_POST['register']) && $allow_manual_reg) {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $username = mysqli_real_escape_string($conn, $_POST['username']); 
    $password = md5(mysqli_real_escape_string($conn, $_POST['password']));
    
    $cek_user = mysqli_query($conn, "SELECT id FROM users WHERE username='$username'");
    if (mysqli_num_rows($cek_user) > 0) {
        $error = "Username / No. HP sudah terdaftar. Silakan gunakan yang lain atau Login.";
    } else {
        // LOGIC REFERRAL
        $initial_balance = 0; // Wajib 0, bonus pendaftaran bentuknya voucher diskon
        $referrer_id = 'NULL';

        if($shop_setting['referral_active'] == 1 && isset($_COOKIE['ref_code'])) {
            $upline_id = (int)$_COOKIE['ref_code'];
            $cek_upline = mysqli_query($conn, "SELECT id FROM users WHERE id='$upline_id'");
            if(mysqli_num_rows($cek_upline) > 0){
                $referrer_id = $upline_id;
                $bonus_ref = $shop_setting['referral_bonus'];
                if($bonus_ref > 0){
                    mysqli_query($conn, "UPDATE users SET balance = balance + $bonus_ref WHERE id='$upline_id'");
                }
            }
        }

        $insert = "INSERT INTO users (username, password, fullname, role, balance, referred_by, created_at) 
                   VALUES ('$username', '$password', '$fullname', 'member', '$initial_balance', $referrer_id, NOW())";
        
        if (mysqli_query($conn, $insert)) {
            setcookie('ref_code', '', time() - 3600, "/"); 
            
            $new_id = mysqli_insert_id($conn);
            $_SESSION['user_id']  = $new_id; 
            $_SESSION['username'] = $username;
            $_SESSION['role']     = 'member';
            $_SESSION['fullname'] = $fullname;

            echo "<script>alert('Pendaftaran Berhasil!'); window.location='member/index.php';</script>";
            exit;
        } else {
            $error = "Terjadi kesalahan sistem saat mendaftar.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - <?php echo htmlspecialchars($shop_setting['shop_name']); ?></title>
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

    <div class="glass-card w-full max-w-sm md:max-w-md p-8 rounded-3xl relative z-10 my-8 animate-fade-in-up">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-green-500 to-emerald-600 mb-3 shadow-lg shadow-green-500/30 animate-float">
                <i class="bi bi-person-plus text-2xl text-white"></i>
            </div>
            <h2 class="text-2xl font-extrabold tracking-tight text-gray-800">Daftar Akun Baru</h2>
            <p class="text-gray-500 text-xs mt-1">Gabung sekarang dan nikmati promonya!</p>
        </div>

        <?php if($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-500 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-2 font-medium">
                <i class="bi bi-exclamation-circle-fill"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if($allow_manual_reg): ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1 ml-1 uppercase tracking-wider">Nama Lengkap</label>
                    <div class="relative">
                        <i class="bi bi-person-badge absolute left-4 top-3 text-gray-400"></i>
                        <input type="text" name="fullname" class="input-field w-full pl-11 pr-4 py-2.5 rounded-xl text-sm font-medium" placeholder="Contoh: Budi Santoso" required>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1 ml-1 uppercase tracking-wider">Username / No. WhatsApp</label>
                    <div class="relative">
                        <i class="bi bi-phone absolute left-4 top-3 text-gray-400"></i>
                        <input type="text" name="username" class="input-field w-full pl-11 pr-4 py-2.5 rounded-xl text-sm font-medium" placeholder="08xxxxxxxx" required autocomplete="off">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-gray-500 mb-1 ml-1 uppercase tracking-wider">Buat Password</label>
                    <div class="relative">
                        <i class="bi bi-key absolute left-4 top-3 text-gray-400"></i>
                        <input type="password" name="password" class="input-field w-full pl-11 pr-4 py-2.5 rounded-xl text-sm font-medium" placeholder="Minimal 6 karakter" required>
                    </div>
                </div>

                <button type="submit" name="register" class="w-full py-3.5 rounded-xl bg-green-600 hover:bg-green-500 text-white font-extrabold shadow-lg shadow-green-500/30 transition transform active:scale-[0.98] duration-200 mt-2">
                    DAFTAR SEKARANG
                </button>
            </form>

            <?php if($google_active): ?>
            <div class="relative flex py-6 items-center">
                <div class="flex-grow border-t border-gray-200"></div>
                <span class="flex-shrink-0 mx-4 text-gray-400 text-[10px] uppercase font-bold tracking-widest">Atau Daftar Cepat</span>
                <div class="flex-grow border-t border-gray-200"></div>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="bg-blue-50 border border-blue-200 px-4 py-5 rounded-2xl mb-6 text-center shadow-inner">
                <i class="bi bi-shield-lock-fill text-3xl block mb-2 text-blue-500"></i>
                <p class="text-sm text-blue-800 font-bold">Pendaftaran Manual Ditutup</p>
                <p class="text-[11px] text-blue-600 mt-1 leading-relaxed">Untuk mencegah spam, pendaftaran secara manual dinonaktifkan sementara. Silakan gunakan pendaftaran instan via Google di bawah ini.</p>
            </div>
        <?php endif; ?>

        <?php if($google_active): ?>
        <a href="google_auth.php" class="flex items-center justify-center gap-3 w-full py-3.5 rounded-xl border border-gray-200 bg-white text-gray-700 font-bold hover:bg-gray-50 transition shadow-sm mb-4 transform active:scale-95">
            <svg class="w-5 h-5" viewBox="0 0 24 24">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            <span>Daftar dengan Google</span>
        </a>
        <?php elseif(!$allow_manual_reg): ?>
            <div class="bg-red-50 border border-red-200 text-red-500 px-4 py-3 rounded-xl text-center text-xs font-bold mt-4">
                Sistem pendaftaran sedang tidak tersedia sepenuhnya.
            </div>
        <?php endif; ?>

        <div class="mt-4 text-center">
            <p class="text-xs text-gray-500 font-medium">Sudah punya akun? <a href="login.php" class="text-green-600 font-bold hover:text-green-500 hover:underline transition">Login di sini</a></p>
        </div>
    </div>
</body>
</html>