<?php
// config/database.php

$host = 'localhost';
$db   = 'my_database';
$user = 'my_db_username';
$pass = 'my_db_password';

// MySQLi bağlantısı
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Veritabanına bağlanırken hata oluştu: " . $conn->connect_error);
}

// UTF-8 ayarı
if (!$conn->set_charset("utf8")) {
    die("UTF-8 karakter seti ayarlanamadı: " . $conn->error);
}