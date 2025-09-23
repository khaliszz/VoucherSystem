<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Get user points
$stmt = $conn->prepare("SELECT points FROM users WHERE user_id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$userPoints = $user['points'] ?? 0;

// Get items (from POST or session)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['items'])) {
    $checkoutItems = explode(',', $_POST['items']);
    $_SESSION['checkout_items'] = $checkoutItems;
} else {
    $checkoutItems = $_SESSION['checkout_items'] ?? [];
}

if (empty($checkoutItems)) {
    header("Location: cart.php");
    exit;
}

// Fetch voucher details
$placeholders = implode(',', array_fill(0, count($checkoutItems), '?'));
$sql = "SELECT v.voucher_id, v.title, v.image, v.points, 
        COALESCE(c.quantity, 1) as quantity
        FROM voucher v
        LEFT JOIN cart_items c ON v.voucher_id = c.voucher_id AND c.user_id=?
        WHERE v.voucher_id IN ($placeholders)";
$stmt = $conn->prepare($sql);
$stmt->execute(array_merge([$userId], $checkoutItems));
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Calculate totals
$totalPoints = 0;
foreach ($vouchers as $v) {
    $totalPoints += $v['points'] * $v['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f9f5ff, #fff4ef);
            margin:0;
            padding:0;
            display:flex;
            justify-content:center;
            min-height:100vh;
        }
        .page-wrapper {
            max-width: 1200px;
            width: 100%;
            margin: 40px auto;
            padding: 20px;
        }
        .checkout-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: linear-gradient(135deg, #8e2de2, #4a00e0);
            padding: 15px 20px;
            border-radius: 12px;
            color: #fff;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .checkout-title {
            font-size: 1.3rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .back-btn {
            background: #fff;
            color: #6a11cb;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration:none;
            font-weight: bold;
            transition: all 0.3s;
        }
        .back-btn:hover {
            background:#ff6f3c;
            color:#fff;
        }
        .container {
            display: flex;
            gap: 25px;
            align-items: flex-start;
        }
        .checkout-items { flex: 3; }
        .checkout-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(106,17,203,0.08);
        }
        .checkout-item-left {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }
        .checkout-item img {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #f3eaff;
        }
        .checkout-item .info h4 {
            margin: 0;
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        .checkout-item .info p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #777;
        }
        .order-summary {
            flex: 1;
            background:#fff;
            border-radius: 12px;
            padding:25px;
            text-align: center;
            box-shadow: 0 3px 12px rgba(106,17,203,0.1);
            position: sticky;
            top: 30px;
        }
        .order-summary h3 {
            margin-bottom: 15px;
            color:#6a11cb;
            font-size: 20px;
            font-weight: bold;
        }
        .order-summary p {
            font-size: 16px;
            color:#333;
            margin: 10px 0;
        }
        .confirm-btn {
            display:inline-block;
            padding:12px 25px;
            margin-top:20px;
            background: linear-gradient(135deg, #8e2de2, #4a00e0);
            color:#fff;
            border-radius:8px;
            text-decoration:none;
            font-weight: bold;
            font-size: 16px;
            transition: opacity 0.3s;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
            border: none;
            cursor: pointer;
        }
        .confirm-btn:hover { opacity:0.85; }
        .confirm-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="checkout-header">
        <div class="checkout-title">
            <i class="fas fa-receipt"></i> Checkout
        </div>
        <a href="cart.php" class="back-btn">Back</a>
    </div>

    <div class="container">
        <div class="checkout-items">
            <?php foreach ($vouchers as $v): ?>
                <div class="checkout-item">
                    <div class="checkout-item-left">
                        <img src="<?= htmlspecialchars($v['image']) ?>" alt="">
                        <div class="info">
                            <h4><?= htmlspecialchars($v['title']) ?></h4>
                            <p><?= $v['points'] ?> Points</p>
                            <p>Quantity: <?= $v['quantity'] ?></p>
                        </div>
                    </div>
                    <strong><?= $v['points'] * $v['quantity'] ?> pts</strong>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="order-summary">
            <h3>Order Summary</h3>
            <p><strong>Total Required:</strong> <?= $totalPoints ?> Points</p>
            <p><strong>Your Balance:</strong> <?= $userPoints ?> Points</p>

            <?php if ($totalPoints <= $userPoints): ?>
                <form method="post" action="confirm_checkout.php">
                    <input type="hidden" name="items" value="<?= implode(',', $checkoutItems) ?>">
                    <button type="submit" class="confirm-btn">Confirm Checkout</button>
                </form>
            <?php else: ?>
                <p style="color:red;">You don’t have enough points.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
