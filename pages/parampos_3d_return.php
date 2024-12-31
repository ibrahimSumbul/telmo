<?php
// pages/parampos_3d_return.php
session_start();
require_once '../config/database.php';
require_once '../parampos_api.php';

// Dökümanda "POST Parametreleri" => TURKPOS_RETVAL_...
$post = $_POST;

$sonuc           = $post['TURKPOS_RETVAL_Sonuc']         ?? '';
$sonuc_str       = $post['TURKPOS_RETVAL_Sonuc_Str']     ?? '';
$dekont_id       = $post['TURKPOS_RETVAL_Dekont_ID']     ?? '0';
$tahsilat_tutari = $post['TURKPOS_RETVAL_Tahsilat_Tutari'] ?? '0'; // "virgüllü" format gelebilir (örn: "100,50")
$siparis_id      = $post['TURKPOS_RETVAL_Siparis_ID']    ?? '';
$islem_id        = $post['TURKPOS_RETVAL_Islem_ID']      ?? '';
$ret_hash        = $post['TURKPOS_RETVAL_Hash']          ?? '';

// Hash doğrulama
$computed_hash = paramposVerifyHash($dekont_id, $tahsilat_tutari, $siparis_id, $islem_id);
if ($computed_hash !== $ret_hash) {
    die("<p style='color:red;'>Hash doğrulama hatası! Güvenlik riski.</p>");
}

// Dekont_ID > 0 => karttan çekim yapıldı
if ($dekont_id > 0 && $sonuc >= 0) {
    echo "<h2>Ödeme Başarılı!</h2>";
    echo "Dekont ID: $dekont_id<br>";
    echo "Tahsilat Tutarı: $tahsilat_tutari<br>";
    echo "Siparis ID: $siparis_id<br>";
    
    // İsterseniz DB kayıt
    if (isset($_SESSION['user_id'])) {
        // Tutarı noktaya çevirebilirsiniz
        $tutar_clean = str_replace(',', '.', $tahsilat_tutari);
        $stmt = $conn->prepare("INSERT INTO payments (user_id, odemepaket, amount, odemetipi) 
                                VALUES (?, ?, ?, 'kredi')");
        $paket = "3D Ödeme"; 
        $stmt->bind_param("iss", $_SESSION['user_id'], $paket, $tutar_clean);
        $stmt->execute();
    }
} else {
    echo "<h2>Ödeme Başarısız</h2>";
    echo "Sonuc: $sonuc<br>";
    echo "Açıklama: $sonuc_str<br>";
}

echo "<br><a href='payment_form.php'>Ödeme Formuna Dön</a>";