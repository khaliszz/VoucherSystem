<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch user points
$stmt = $conn->prepare("SELECT points FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$userPoints = $user['points'] ?? 0;

// ✅ Get voucher IDs from POST (checkout form)
if (!isset($_POST['items']) || empty($_POST['items'])) {
    $_SESSION['error_message'] = "No items selected for checkout.";
    header("Location: cart.php");
    exit;
}

$itemIds = explode(',', $_POST['items']);
$placeholders = implode(',', array_fill(0, count($itemIds), '?'));

// ✅ Fetch vouchers (works for both Cart + Redeem Now)
$sql = "
    SELECT v.voucher_id, v.points, COALESCE(c.quantity, 1) AS quantity
    FROM voucher v
    LEFT JOIN cart_items c 
        ON v.voucher_id = c.voucher_id AND c.user_id = ?
    WHERE v.voucher_id IN ($placeholders)
";
$stmt = $conn->prepare($sql);
$stmt->execute(array_merge([$userId], $itemIds));
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Check empty
if (empty($cartItems)) {
    $_SESSION['error_message'] = "No valid items found for checkout.";
    header("Location: cart.php");
    exit;
}

// ✅ Calculate total required points
$totalPoints = 0;
foreach ($cartItems as $item) {
    $totalPoints += $item['points'] * $item['quantity'];
}

// ✅ Check if user has enough points
if ($userPoints < $totalPoints) {
    $_SESSION['error_message'] = "Not enough points to redeem.";
    header("Location: checkout.php");
    exit;
}

// ✅ Deduct points
$newPoints = $userPoints - $totalPoints;
$updatePoints = $conn->prepare("UPDATE users SET points=? WHERE user_id=?");
$updatePoints->execute([$newPoints, $userId]);

// ✅ Move items to history
$historyIds = [];
$insertHistory = $conn->prepare("
    INSERT INTO cart_item_history (voucher_id, user_id, quantity, completed_date, expiry_date) 
    VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))
");
foreach ($cartItems as $item) {
    $insertHistory->execute([$item['voucher_id'], $userId, $item['quantity']]);
    $historyIds[] = $conn->lastInsertId(); // capture history_id
}
$_SESSION['recent_history_ids'] = $historyIds;


// Clear only the purchased items from cart
foreach ($cartItems as $item) {
    $clearCartItem = $conn->prepare("DELETE FROM cart_items WHERE user_id=? AND voucher_id=?");
    $clearCartItem->execute([$userId, $item['voucher_id']]);
}

// ✅ Get latest expiry date
$expiryStmt = $conn->prepare("
    SELECT MAX(expiry_date) as expiry_date 
    FROM cart_item_history 
    WHERE user_id=? 
    ORDER BY completed_date DESC LIMIT 1
");
$expiryStmt->execute([$userId]);
$expiry = $expiryStmt->fetch(PDO::FETCH_ASSOC);
$expiryDate = $expiry['expiry_date'] ?? null;

// ✅ Show success popup
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Success Redeem</title>
    <style>
        body {
            margin:0;
            font-family: Arial, sans-serif;
            background: rgba(0,0,0,0.8);
            display:flex;
            align-items:center;
            justify-content:center;
            min-height:100vh;
            padding:16px;
        }
        .modal {
            background:#fff;
            padding:20px;
            border-radius:12px;
            text-align:center;
            max-width:420px;
            width:100%;
            box-shadow:0 6px 25px rgba(0,0,0,0.25);
            position:relative;
            max-height:90vh;
            overflow:auto;
            -webkit-overflow-scrolling: touch;
        }
        .close-btn {
            position:absolute;
            top:8px;
            right:10px;
            font-size:22px;
            font-weight:bold;
            cursor:pointer;
            color:#666;
            transition:0.2s;
            padding:6px;
            line-height:1;
        }
        .close-btn:hover {
            color:#e60000;
            transform: scale(1.1);
        }
        .success-icon {
            width:64px;
            height:64px;
            background:#28a745;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 auto 14px auto;
        }
        .success-icon i {
            color:#fff;
            font-size:28px;
        }
        .modal h2 {
            margin:10px 0 12px 0;
            color:#28a745;
            font-size:1.25rem;
        }
        .modal p {
            margin:6px 0;
            color:#333;
            word-break: break-word;
        }
        .download-btn {
            display:block;
            width:100%;
            margin-top:16px;
            padding:12px 18px;
            background:#6a11cb;
            color:#fff;
            text-decoration:none;
            border-radius:8px;
            font-weight:bold;
            transition:0.3s;
        }
        .download-btn:hover {
            background:#4a00e0;
        }

        @media (max-width: 480px) {
            .modal { padding:16px; border-radius:12px; }
            .success-icon { width:56px; height:56px; }
            .success-icon i { font-size:24px; }
            .modal h2 { font-size:1.1rem; }
            .modal p { font-size:0.95rem; }
            .download-btn { padding:12px; font-size:1rem; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="modal">
        <span class="close-btn" onclick="window.location.href='homepage.php'">&times;</span>
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h2>Voucher Redeemed Successfully</h2>
        <p>Remember to use this voucher by <strong><?= htmlspecialchars(date("d F Y", strtotime($expiryDate))) ?></strong>.</p>
        <p>Your new balance: <strong><?= htmlspecialchars($newPoints) ?> points</strong></p>
        <button type="button" class="download-btn" id="downloadVoucherBtn">Download Voucher</button>
    </div>
    <script>
        (function() {
            var btn = document.getElementById('downloadVoucherBtn');
            if (btn) {
                btn.addEventListener('click', function() {
                    // Use same-tab navigation to avoid pop-up/download blocking on mobile simulators
                    window.location.href = 'download_voucher.php?mode=recent';
                });
            }
        })();
    </script>
</body>
</html>
