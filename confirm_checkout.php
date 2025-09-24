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
    <title>Success Redeem</title>
    <style>
        body {
            margin:0;
            font-family: Arial, sans-serif;
            background: rgba(0,0,0,0.8);
            display:flex;
            align-items:center;
            justify-content:center;
            height:100vh;
        }
        .modal {
            background:#fff;
            padding:30px 25px;
            border-radius:12px;
            text-align:center;
            max-width:420px;
            width:90%;
            box-shadow:0 6px 25px rgba(0,0,0,0.25);
            position:relative;
        }
        .close-btn {
            position:absolute;
            top:12px;
            right:15px;
            font-size:22px;
            font-weight:bold;
            cursor:pointer;
            color:#666;
            transition:0.2s;
        }
        .close-btn:hover {
            color:#e60000;
            transform: scale(1.2);
        }
        .success-icon {
            width:70px;
            height:70px;
            background:#28a745;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 auto 15px auto;
        }
        .success-icon i {
            color:#fff;
            font-size:32px;
        }
        .modal h2 {
            margin:10px 0 15px 0;
            color:#28a745;
            font-size:1.4rem;
        }
        .modal p {
            margin:6px 0;
            color:#333;
        }
        .download-btn {
            display:inline-block;
            margin-top:15px;
            padding:12px 22px;
            background:#6a11cb;
            color:#fff;
            text-decoration:none;
            border-radius:6px;
            font-weight:bold;
            transition:0.3s;
        }
        .download-btn:hover {
            background:#4a00e0;
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
        <a href="download_voucher.php?mode=recent" class="download-btn">Download Voucher</a>
    </div>
</body>
</html>
