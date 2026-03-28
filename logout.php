<?php
session_start();

// 1. Kosongkan semua variabel session
$_SESSION = [];

// 2. Hapus cookie session jika ada (Biar bersih total)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Hancurkan session
session_destroy();

// 4. Redirect kembali ke halaman Login Staff
header("Location: login.php");
exit;
?>