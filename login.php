<?php
// login.php
session_start();
echo session_save_path();

// Eğer kullanıcı zaten giriş yapmışsa
if (isset($_SESSION['user_id'])) {
    header("Location: pages/payment_form.php");
    exit;
}

// Veritabanı bağlantısı
require_once 'config/database.php';

$message = "";

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Formdan gelen veriler
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Trim ve basit kontrol
    $username = trim($username);
    $password = trim($password);

    if (!empty($username) && !empty($password)) {
        // Veritabanında kullanıcı arayalım
        $stmt = $conn->prepare("SELECT id, username, `password` FROM users WHERE username=? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Veritabanındaki hashed parola
            $hashedPassword = $row['password'];

            // Parola doğrulama
            if (password_verify($password, $hashedPassword)) {
                // Başarılı giriş
                $_SESSION['user_id']   = $row['id'];
                $_SESSION['username']  = $row['username']; // opsiyonel

                header("Location: pages/payment_form.php");
                exit;
            } else {
                $message = "Şifre hatalı!";
            }
        } else {
            $message = "Kullanıcı bulunamadı!";
        }
    } else {
        $message = "Lütfen kullanıcı adı ve şifre giriniz.";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Telmo - Giriş Yap</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 40px;
        }
        .login-container {
            max-width: 350px; margin: 0 auto;
        }
        .error {
            color: red; margin-bottom: 15px;
        }
        label {
            display: block; margin-top: 10px;
        }
        input[type="text"], input[type="password"] {
            width: 100%; padding: 6px; box-sizing: border-box;
        }
        button {
            margin-top: 15px; padding: 8px 12px; cursor: pointer;
        }
    </style>
</head>
<body>
<div class="login-container">
    <h2>Telmo - Gerçek Giriş</h2>

    <?php if ($message): ?>
        <div class="error"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label>Kullanıcı Adı:</label>
        <input type="text" name="username" required>

        <label>Parola:</label>
        <input type="password" name="password" required>

        <button type="submit">Giriş</button>
    </form>
</div>
</body>
</html>