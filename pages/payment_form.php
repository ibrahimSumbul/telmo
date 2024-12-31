<?php
// pages/payment_form.php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Veritabanı bağlantısı
require_once '../config/database.php';

// Mesajlar
$error_message   = null;
$success_message = null;

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $odemepaket = htmlspecialchars($_POST['odemepaket'] ?? 'Borç Ödeme', ENT_QUOTES, 'UTF-8');
    $amount     = str_replace(',', '.', $_POST['amount'] ?? '0'); // "100,00" -> "100.00"
    $odemetipi  = htmlspecialchars($_POST['odemetipi'] ?? '', ENT_QUOTES, 'UTF-8');

    // Dekont / Kripto / Kart
    $dekont         = null;
    $kriptocuzdanno = null;
    $kartadsoyad    = null;
    $kartno         = null;
    $sonkullanim    = null;
    $cvv            = null;

    // Genel Kontroller
    if (!in_array($odemetipi, ['banka', 'kredi', 'kripto'])) {
        $error_message = "Geçerli bir ödeme yöntemi seçiniz.";
    }
    if (!is_numeric($amount) || floatval($amount) <= 0) {
        $error_message = "Geçerli bir tutar giriniz.";
    }

    // A) Banka Havalesi
    if ($odemetipi === 'banka' && !$error_message) {
        if (empty($_FILES['dekont']['name'])) {
            $error_message = "Lütfen dekont yükleyiniz.";
        } else {
            // Dekont Dosyası
            $dekont = $_FILES['dekont']['name'];
            $target_dir = "../uploads/dekont/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }
            move_uploaded_file($_FILES['dekont']['tmp_name'], $target_dir.$dekont);

            // DB kayıt
            $stmt = $conn->prepare("INSERT INTO payments (user_id, odemepaket, amount, odemetipi, dekont) 
                                    VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $_SESSION['user_id'], $odemepaket, $amount, $odemetipi, $dekont);
            if ($stmt->execute()) {
                $success_message = "Banka havalesi kaydı başarıyla alındı.";
            } else {
                $error_message = "Veritabanı hatası: " . $stmt->error;
            }
        }
    }
    // B) Kripto
    elseif ($odemetipi === 'kripto' && !$error_message) {
        $kriptocuzdanno = htmlspecialchars($_POST['kriptocuzdanno'] ?? '', ENT_QUOTES, 'UTF-8');
        // DB kayıt
        $stmt = $conn->prepare("INSERT INTO payments (user_id, odemepaket, amount, odemetipi, kriptocuzdanno) 
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $_SESSION['user_id'], $odemepaket, $amount, $odemetipi, $kriptocuzdanno);
        if ($stmt->execute()) {
            $success_message = "Kripto ödeme kaydı başarıyla alındı.";
        } else {
            $error_message = "Veritabanı hatası: " . $stmt->error;
        }
    }
    // C) Kredi Kartı (3D Secure)
    elseif ($odemetipi === 'kredi' && !$error_message) {
        // Kart Bilgileri
        $kartadsoyad = htmlspecialchars($_POST['kartadsoyad'] ?? '', ENT_QUOTES, 'UTF-8');
        $kartno      = htmlspecialchars($_POST['kartno']      ?? '', ENT_QUOTES, 'UTF-8');
        $sonkullanim = htmlspecialchars($_POST['sonkullanim'] ?? '', ENT_QUOTES, 'UTF-8');
        $cvv         = htmlspecialchars($_POST['cvv']         ?? '', ENT_QUOTES, 'UTF-8');

        // Bu verileri session'da saklayıp parampos_3d.php'ye yönlendirelim
        $_SESSION['kredi_form'] = [
            'odemepaket'   => $odemepaket,
            'amount'       => $amount,
            'kartadsoyad'  => $kartadsoyad,
            'kartno'       => $kartno,
            'sonkullanim'  => $sonkullanim,
            'cvv'          => $cvv,
        ];
        // parampos_3d.php sayfası 3D akışını başlatır
        header("Location: parampos_3d.php");
        exit;
    }
}

// Header / Navbar
include '../includes/header.php';
include '../includes/navbar.php';
?>
<div class="container-scroller">
    <?php include '../includes/sidebar.php'; ?>
    <div class="container-fluid page-body-wrapper">
        <div class="main-panel">
            <div class="content-wrapper">
                <div class="col-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">

                            <h4 class="card-title">ParamPos 3D Ödeme Testi</h4>
                            <?php if ($success_message): ?>
                                <p class="text-success"><?php echo $success_message; ?></p>
                            <?php endif; ?>
                            <?php if ($error_message): ?>
                                <p class="text-danger"><?php echo $error_message; ?></p>
                            <?php endif; ?>

                            <form method="POST" action="" enctype="multipart/form-data">
                                <!-- Ödeme Paketi -->
                                <div class="form-group">
                                    <label>Ödeme Paketi</label>
                                    <select class="form-control" name="odemepaket">
                                        <option>Borç Ödeme</option>
                                        <!-- Diğer paketler -->
                                    </select>
                                </div>

                                <!-- Ödeme Tutarı -->
                                <div class="form-group">
                                    <label>Ödeme Miktarı</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" placeholder="100,00" name="amount" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text bg-primary text-white">₺</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Ödeme Tipi -->
                                <div class="form-group">
                                    <label>Ödeme Yöntemi</label>
                                    <select class="form-control" name="odemetipi" onchange="showPaymentFields(this.value)">
                                        <option value="banka">Banka Havalesi</option>
                                        <option value="kredi">Kredi Kartı (3D)</option>
                                        <option value="kripto">Kripto (ETH)</option>
                                    </select>
                                </div>

                                <!-- Banka Alanları -->
                                <div id="banka-fields" class="payment-fields">
                                    <div class="form-group">
                                        <label>IBAN</label>
                                        <input type="text" class="form-control" value="TR99999999" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Dekont Yükleme</label>
                                        <input type="file" class="form-control" name="dekont">
                                    </div>
                                </div>

                                <!-- Kredi Kartı Alanları -->
                                <div id="kredi-fields" class="payment-fields" style="display:none;">
                                    <div class="form-group">
                                        <label>Kart Sahibinin Adı Soyadı</label>
                                        <input type="text" class="form-control" name="kartadsoyad">
                                    </div>
                                    <div class="form-group">
                                        <label>Kart Numarası</label>
                                        <input type="text" class="form-control" name="kartno" onblur="getCardInfo(this.value)">
                                        <div id="card-info" class="mt-2"></div>
                                    </div>
                                    <div class="form-group">
                                        <label>Son Kullanma (MM/YY)</label>
                                        <input type="text" class="form-control" name="sonkullanim" placeholder="12/26">
                                    </div>
                                    <div class="form-group">
                                        <label>CVV</label>
                                        <input type="text" class="form-control" name="cvv">
                                    </div>
                                    <div class="form-group">
                                        <label>Taksit</label>
                                        <select class="form-control" name="taksit" id="taksit">
                                            <option value="1">Peşin</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Kripto Alanları -->
                                <div id="kripto-fields" class="payment-fields" style="display:none;">
                                    <div class="form-group">
                                        <label>Kripto Cüzdan No</label>
                                        <input type="text" class="form-control" name="kriptocuzdanno" value="0xABC123..." readonly>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Öde</button>
                            </form>

                            <script>
                            function showPaymentFields(val) {
                                document.querySelectorAll('.payment-fields').forEach(el => {
                                    el.style.display = 'none';
                                });
                                if (val==='banka') {
                                    document.getElementById('banka-fields').style.display='block';
                                } else if (val==='kredi') {
                                    document.getElementById('kredi-fields').style.display='block';
                                } else if (val==='kripto') {
                                    document.getElementById('kripto-fields').style.display='block';
                                }
                            }

                            // BIN sorgusu + Taksit listesi
                            function getCardInfo(binNumber) {
                                if (binNumber.length < 6) {
                                    document.getElementById('card-info').innerText = 'Kart numarası eksik.';
                                    return;
                                }
                                // Kart Info
                                fetch(`../parampos_api.php?action=card_info&bin=${binNumber}`)
                                    .then(resp => resp.json())
                                    .then(data => {
                                        if (data.success) {
                                            document.getElementById('card-info').innerHTML =
                                                `Kart Türü: ${data.data.Kart_Tip}, Banka: ${data.data.Kart_Banka}`;
                                        } else {
                                            document.getElementById('card-info').innerText='Kart bilgisi alınamadı.';
                                        }
                                    });
                                // Taksit Bilgisi
                                let rawAmount = document.querySelector('[name="amount"]').value;
                                rawAmount = rawAmount.replace(',', '.'); // "100,00" => "100.00"
                                fetch(`../parampos_api.php?action=installment_info&bin=${binNumber}&amount=${rawAmount}`)
                                    .then(r=>r.json())
                                    .then(d => {
                                        const taksitSelect = document.getElementById('taksit');
                                        taksitSelect.innerHTML = "";
                                        if (d.success) {
                                            d.data.forEach(t => {
                                                taksitSelect.innerHTML += `<option value="${t.Taksit}">${t.Taksit} Taksit</option>`;
                                            });
                                        } else {
                                            taksitSelect.innerHTML = "<option value='1'>Peşin</option>";
                                        }
                                    });
                            }
                            </script>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>