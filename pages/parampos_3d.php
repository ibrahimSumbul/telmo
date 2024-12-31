<?php
// pages/parampos_3d.php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once '../config/database.php';
require_once '../parampos_api.php'; // 3D fonksiyonları

if (!isset($_SESSION['kredi_form'])) {
    die("Kredi form verisi bulunamadı.");
}

$form_data = $_SESSION['kredi_form'];
unset($_SESSION['kredi_form']); // Tek seferlik kullan

$odemepaket   = $form_data['odemepaket'];
$amount       = $form_data['amount'];        // "100.00"
$kartadsoyad  = $form_data['kartadsoyad'];
$kartno       = $form_data['kartno'];
$sonkullanim  = $form_data['sonkullanim'];   // "MM/YY"
$cvv          = $form_data['cvv'];

// Son Kullanma
list($ay, $yil_short) = explode('/', $sonkullanim);
$ay  = trim($ay);
$yil = '20'.trim($yil_short); // "26" => "2026"

// Basarili/Hata URL => parampos_3d_return.php
$base_url    = 'http://localhost/telmo/pages'; // XAMPP'de localhost 
$Hata_URL    = $base_url.'/parampos_3d_return.php';
$Basarili_URL= $base_url.'/parampos_3d_return.php';

// Sipariş ID
$siparis_id = "SP_".time();

// 3D Başlat
$result = parampos3DPayment(
    $amount,      // "nokta" format
    $siparis_id,
    $kartadsoyad,
    $kartno,
    $ay,
    $yil,
    $cvv,
    $Hata_URL,
    $Basarili_URL,
    $taksit="1" // Tek çekim
);

if (!$result['success']) {
    echo "<p>3D Başlatma Hatası: ".htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8')."</p>";
    echo "<a href='payment_form.php'>Geri Dön</a>";
} else {
    // Redirect
    $ucd_url = $result['UCD_URL'];
    header("Location: $ucd_url");
    exit;
}