<?php
session_start();
include 'koneksi.php';
require_once 'vendor/autoload.php';

// 1. AMBIL SETTING TOKO (Client ID & Logic Bonus)
$settings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM shop_settings WHERE id=1"));

if(empty($settings['google_client_id']) || empty($settings['google_client_secret'])) {
    die("Fitur Google Login belum dikonfigurasi.");
}

$clientID = $settings['google_client_id'];
$clientSecret = $settings['google_client_secret'];
$redirectUri = $settings['google_redirect_uri'];

// Setup Client Google
$client = new Google_Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if(!isset($token['error'])){
        $client->setAccessToken($token['access_token']);
        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        
        $email =  $google_account_info->email;
        $name =  $google_account_info->name;
        $google_id = $google_account_info->id;

        // CEK USER SUDAH ADA BELUM?
        $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email'");
        
        if (mysqli_num_rows($check) > 0) {
            // --- A. USER LAMA (LOGIN) ---
            $data = mysqli_fetch_assoc($check);
            
            // Update Google ID jika belum ada
            if(empty($data['google_id'])){
                mysqli_query($conn, "UPDATE users SET google_id='$google_id' WHERE email='$email'");
            }

            $_SESSION['user_id'] = $data['id']; 
            $_SESSION['username'] = $data['username'];
            $_SESSION['role'] = $data['role'];
            $_SESSION['fullname'] = $data['fullname'];

        } else {
            // --- B. USER BARU (REGISTER) ---
            
            $username_gen = explode('@', $email)[0];
            $pass_random = md5(rand(1000,9999)); 
            
            // 1. SIAPKAN LOGIC SALDO AWAL (WAJIB 0 KARENA BONUS ADALAH VOUCHER DISKON)
            $initial_balance = 0; 
            $referrer_id = 'NULL';

            // Cek Referral (Jika Aktif & Ada Cookie peninggalan dari login.php)
            if($settings['referral_active'] == 1 && isset($_COOKIE['ref_code'])) {
                $upline_id = (int)$_COOKIE['ref_code'];
                
                // Pastikan Upline Valid (Ada di DB)
                $cek_upline = mysqli_query($conn, "SELECT id FROM users WHERE id='$upline_id'");
                if(mysqli_num_rows($cek_upline) > 0){
                    $referrer_id = $upline_id;
                    
                    // KASIH BONUS SALDO TUNAI KE UPLINE (ORANG YANG NGAJAK)
                    $bonus_ref = $settings['referral_bonus'];
                    if($bonus_ref > 0){
                        mysqli_query($conn, "UPDATE users SET balance = balance + $bonus_ref WHERE id='$upline_id'");
                    }
                }
            }

            // 2. INPUT KE DATABASE
            $insert = "INSERT INTO users (username, password, fullname, role, email, google_id, balance, referred_by, created_at) 
                       VALUES ('$username_gen', '$pass_random', '$name', 'member', '$email', '$google_id', '$initial_balance', $referrer_id, NOW())";
            
            if(mysqli_query($conn, $insert)){
                $new_id = mysqli_insert_id($conn);
                $_SESSION['user_id'] = $new_id;
                $_SESSION['username'] = $username_gen;
                $_SESSION['role'] = 'member';
                $_SESSION['fullname'] = $name;
                
                // Hapus Cookie Referral biar bersih dan tidak dipakai ulang
                setcookie('ref_code', '', time() - 3600, "/"); 
            } else {
                die("Gagal registrasi user baru: " . mysqli_error($conn));
            }
        }

        // Redirect
        if ($_SESSION['role'] == 'admin') { header("Location: admin/index.php"); }
        elseif ($_SESSION['role'] == 'kasir') { header("Location: kasir/index.php"); }
        else { header("Location: member/index.php"); }
        exit;

    } else {
        header("Location: login.php"); exit;
    }
} else {
    header("Location: " . $client->createAuthUrl()); exit;
}
?>