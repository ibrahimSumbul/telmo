<?php
// pages/payment_history.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once '../config/database.php';

$payments = [];
try {
    $stmt = $conn->prepare("SELECT odemepaket, amount, odemetipi, dekont, kriptocuzdanno, created_at 
                            FROM payments WHERE user_id=? ORDER BY created_at DESC");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result   = $stmt->get_result();
    $payments = $result->fetch_all(MYSQLI_ASSOC);
} catch(Exception $e) {
    $error = $e->getMessage();
}

include '../includes/header.php';
include '../includes/navbar.php';
?>
<div class="container-scroller">
    <?php include '../includes/sidebar.php'; ?>
    <div class="container-fluid page-body-wrapper">
        <div class="main-panel">
            <div class="content-wrapper">
                <h4>Ödeme Geçmişi</h4>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Ödeme Paketi</th>
                            <th>Tutar</th>
                            <th>Yöntem</th>
                            <th>Dekont</th>
                            <th>Kripto Cüzdan</th>
                            <th>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(!empty($payments)): ?>
                        <?php foreach($payments as $i => $p): ?>
                            <tr>
                                <td><?php echo $i+1; ?></td>
                                <td><?php echo htmlspecialchars($p['odemepaket'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo number_format($p['amount'], 2, ',', '.'); ?> ₺</td>
                                <td><?php echo htmlspecialchars($p['odemetipi'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo $p['dekont'] ? htmlspecialchars($p['dekont'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                <td><?php echo $p['kriptocuzdanno'] ? htmlspecialchars($p['kriptocuzdanno'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                                <td><?php echo $p['created_at']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center">Henüz ödeme yok.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>