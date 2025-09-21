<?php
session_start();
require_once 'connection.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// --- Pagination setup ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total vouchers for pagination
$countSql = "SELECT COUNT(*) FROM cart_item_history WHERE user_id = ?";
$countStmt = $conn->prepare($countSql);
$countStmt->execute([$userId]);
$totalVouchers = $countStmt->fetchColumn();
$totalPages = ceil($totalVouchers / $limit);

// Fetch paginated voucher history
$sql = "
    SELECT h.history_id, h.quantity, v.title, h.completed_date, h.expiry_date
    FROM cart_item_history h
    JOIN voucher v ON h.voucher_id = v.voucher_id
    WHERE h.user_id = ?
    ORDER BY h.completed_date DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
$stmt->bindValue(1, $userId, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$voucherHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getVoucherStatus($expiryDate) {
    if (empty($expiryDate)) return 'Expired';
    $now = new DateTime();
    $expiry = new DateTime($expiryDate);
    return ($now <= $expiry) ? 'Active' : 'Expired';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher History</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: #f4f7fc;
            margin: 0;
            padding-top: 100px;
        }
        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.08);
        }
        h2 {
            margin-bottom: 20px;
        }
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #eee;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
        }
        .history-info h4 {
            margin: 0 0 5px;
        }
        .history-badge {
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #fff;
        }
        .badge-active { background: #28a745; }
        .badge-expired { background: #dc3545; }
        .history-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .download-link {
            color: #333;
            font-size: 1.2rem;
            text-decoration: none;
        }
        .download-link:hover {
            color: #6a11cb;
        }

        /* ✅ Your original back button style restored */
        .back-btn {
            display: inline-block;
            margin-bottom: 15px;
            padding: 10px 18px;
            background: #6a11cb;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
        .back-btn:hover {
            background: #4a00e0;
        }

        /* Pagination */
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            margin: 0 5px;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            background: #eee;
            color: #333;
            font-weight: 500;
        }
        .pagination a.active {
            background: #6a11cb;
            color: #fff;
        }
        .pagination a:hover {
            background: #4a00e0;
            color: #fff;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container">
        <a href="profile.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Profile</a>
        <h2>Your Voucher History</h2>

        <div class="history-list">
            <?php if (!empty($voucherHistory)): ?>
                <?php foreach ($voucherHistory as $voucher): ?>
                    <?php 
                        $status = getVoucherStatus($voucher['expiry_date']); 
                        $badgeClass = ($status === 'Active') ? 'badge-active' : 'badge-expired';
                    ?>
                    <div class="history-item">
                        <div class="history-info">
                            <h4><?= htmlspecialchars($voucher['title']) ?></h4>
                            <p>Redeemed on <?= date("M d, Y", strtotime($voucher['completed_date'])) ?></p>
                            <p>Valid until <?= date("M d, Y", strtotime($voucher['expiry_date'])) ?></p>
                            <p>Quantity: <?= $voucher['quantity'] ?></p>
                        </div>
                        <div class="history-actions">
                            <span class="history-badge <?= $badgeClass ?>"><?= $status ?></span>
                            <?php if ($status === 'Active'): ?>
                                <a href="download_voucher.php?id=<?= $voucher['history_id'] ?>" class="download-link">
                                    <i class="fas fa-download"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>You haven’t redeemed any vouchers yet.</p>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>" class="<?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>
