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
$totalItems = 0;
foreach ($vouchers as $v) {
    $totalPoints += $v['points'] * $v['quantity'];
    $totalItems += $v['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Optima Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bank-primary: #2d0030;
            --bank-secondary: #4a1a4f;
            --bank-accent: #f59e0b;
            --success-color: #10b981;
            --error-color: #ef4444;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Bank Header */
        .bank-header {
            background: var(--bank-primary);
            box-shadow: 0 2px 15px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .header-top {
            background: rgba(255,255,255,0.08);
            padding: 6px 0;
            text-align: center;
            font-size: 11px;
            color: rgba(255,255,255,0.85);
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .header-main {
            padding: 12px 0;
        }
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1;
        }
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }
        .logo-container:hover { transform: translateY(-1px); }
        .logo { height: 35px; border-radius: 4px; }
        .bank-name { color: #ff9500; font-size: 22px; font-weight: 700; margin: 0; }
        .bank-tagline { color: rgba(255,255,255,0.8); font-size: 10px; }

        .nav-left, .nav-right { flex: 1; display: flex; align-items: center; }
        .nav-right { justify-content: flex-end; gap: 20px; }
        .page-title { color: #fff; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px; }

        .back-btn {
            background: rgba(255,255,255,0.15);
            color: #fff;
            padding: 8px 18px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }

        .page-wrapper { 
            max-width: 1200px; 
            width: 100%; 
            margin: 30px auto; 
            padding: 20px; 
            flex: 1; 
        }

        .container {
            display: flex;
            gap: 25px;
            align-items: flex-start;
        }

        .checkout-items {
            flex: 3;
        }

        /* Checkout Items */
        .checkout-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(45,0,48,0.08);
            border: 1px solid rgba(45,0,48,0.1);
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
            background:#eee; 
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
        .points-display { 
            font-size: 16px; 
            font-weight: bold; 
            color: var(--bank-primary); 
        }

        /* Order summary */
        .order-summary {
            flex: 1;
            background:#fff;
            border-radius: 12px;
            padding:25px;
            text-align: center;
            box-shadow: 0 3px 12px rgba(45,0,48,0.1);
            position: sticky;
            top: 30px;
            border: 1px solid rgba(45,0,48,0.1);
        }
        .order-summary h3 { 
            margin-bottom: 15px; 
            color: var(--bank-primary); 
            font-size: 20px; 
            font-weight: bold; 
        }
        .order-summary p { 
            font-size: 16px; 
            color:#333; 
            margin: 10px 0; 
        }
        .points-comparison {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--bank-primary);
        }
        .points-sufficient { 
            background: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
            padding: 8px 12px; 
            border-radius: 6px; 
            font-weight: bold;
        }
        .points-insufficient { 
            background: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
            padding: 8px 12px; 
            border-radius: 6px; 
            font-weight: bold;
        }

        .confirm-btn {
            display:inline-block;
            padding:12px 25px;
            margin-top:20px;
            background: linear-gradient(135deg, var(--success-color), #059669);
            color:#fff;
            border-radius:8px;
            text-decoration:none;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
            border: none;
            cursor: pointer;
        }
        .confirm-btn:hover { 
            background: linear-gradient(135deg, #059669, #047857); 
            transform: translateY(-1px);
        }
        .confirm-btn:disabled { 
            background: #9ca3af; 
            cursor: not-allowed; 
            opacity: 0.6; 
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .header-container {
                padding: 0 15px;
            }

            .logo-section {
                order: 2;
                flex: 2;
            }

            .nav-left {
                order: 1;
                flex: 1;
                justify-content: flex-start;
            }

            .nav-right {
                order: 3;
                flex: 1;
            }

            .bank-name {
                font-size: 18px;
            }

            .logo {
                height: 28px;
            }

            .page-title {
                font-size: 14px;
            }

            .back-btn {
                padding: 6px 14px;
                font-size: 12px;
            }

            .page-wrapper {
                margin: 15px auto;
                padding: 0;
                padding-bottom: 300px; /* Space for fixed summary */
            }

            .container {
                flex-direction: column;
                gap: 0;
                padding: 0 15px;
            }

            .checkout-items {
                flex: none;
                width: 100%;
                margin-bottom: 0;
            }

            /* Improved mobile product boxes */
            .checkout-item {
                padding: 12px 15px;
                margin-bottom: 12px;
                border-radius: 8px;
                background: #fff;
                border: 1px solid rgba(45,0,48,0.08);
                box-shadow: 0 2px 8px rgba(45,0,48,0.06);
            }

            .checkout-item-left {
                gap: 12px;
                flex: 1;
            }

            .checkout-item img {
                width: 55px;
                height: 55px;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
            }

            .checkout-item .info h4 {
                font-size: 14px;
                line-height: 1.3;
                margin-bottom: 4px;
            }

            .checkout-item .info p {
                font-size: 12px;
                line-height: 1.2;
                margin: 2px 0;
                color: #6b7280;
            }

            .points-display {
                font-size: 14px;
                font-weight: 700;
                color: var(--bank-primary);
                white-space: nowrap;
            }

            /* Fixed mobile summary */
            .order-summary {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                top: auto;
                background: #fff;
                border-radius: 16px 16px 0 0;
                border-top: 3px solid var(--bank-primary);
                padding: 18px 15px 15px;
                box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.15);
                z-index: 999;
                max-width: none;
                margin: 0;
                text-align: left;
            }

            .order-summary h3 {
                font-size: 16px;
                margin-bottom: 12px;
                text-align: center;
                color: var(--bank-primary);
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            .points-comparison {
                padding: 12px;
                margin: 10px 0;
                border-radius: 8px;
                background: #f8fafc;
                border-left: 3px solid var(--bank-primary);
            }

            .points-comparison p {
                margin: 4px 0;
                font-size: 13px;
            }

            .mobile-summary-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 6px 0;
                font-size: 13px;
            }

            .mobile-summary-row strong {
                color: var(--bank-primary);
                font-weight: 600;
            }

            .points-sufficient,
            .points-insufficient {
                padding: 8px 12px;
                font-size: 12px;
                text-align: center;
                margin: 10px 0;
                border-radius: 6px;
                font-weight: 600;
            }

            .confirm-btn {
                width: 100%;
                padding: 12px;
                margin-top: 12px;
                font-size: 14px;
                box-sizing: border-box;
                border-radius: 8px;
                font-weight: 600;
            }
        }

        @media (min-width: 769px) {
            .mobile-summary-row {
                display: block;
            }
        }

        /* Small phones optimization */
        @media (max-width: 480px) {
            .header-container {
                padding: 0 12px;
            }

            .bank-name {
                font-size: 16px;
            }

            .logo {
                height: 26px;
            }

            .page-title {
                font-size: 13px;
            }

            .back-btn {
                padding: 5px 12px;
                font-size: 11px;
            }

            .page-wrapper {
                margin: 10px auto;
                padding-bottom: 280px;
            }

            .container {
                padding: 0 12px;
            }

            /* Smaller product boxes for small screens */
            .checkout-item {
                padding: 10px 12px;
                margin-bottom: 10px;
                border-radius: 6px;
            }

            .checkout-item-left {
                gap: 10px;
            }

            .checkout-item img {
                width: 50px;
                height: 50px;
                border-radius: 6px;
            }

            .checkout-item .info h4 {
                font-size: 13px;
                line-height: 1.2;
            }

            .checkout-item .info p {
                font-size: 11px;
                margin: 1px 0;
            }

            .points-display {
                font-size: 13px;
            }

            .order-summary {
                padding: 15px 12px 12px;
                border-radius: 12px 12px 0 0;
            }

            .order-summary h3 {
                font-size: 15px;
                margin-bottom: 10px;
            }

            .points-comparison {
                padding: 10px;
                margin: 8px 0;
            }

            .mobile-summary-row {
                font-size: 12px;
                margin: 4px 0;
            }

            .points-sufficient,
            .points-insufficient {
                font-size: 11px;
                padding: 6px 10px;
                margin: 8px 0;
            }

            .confirm-btn {
                font-size: 13px;
                padding: 10px;
                margin-top: 10px;
            }
        }

        /* Extra small phones */
        @media (max-width: 360px) {
            .header-container {
                padding: 0 10px;
            }

            .bank-name {
                font-size: 15px;
            }

            .logo {
                height: 24px;
            }

            .page-wrapper {
                padding-bottom: 260px;
            }

            .container {
                padding: 0 10px;
            }

            .checkout-item {
                padding: 8px 10px;
                margin-bottom: 8px;
            }

            .checkout-item img {
                width: 45px;
                height: 45px;
            }

            .checkout-item .info h4 {
                font-size: 12px;
            }

            .checkout-item .info p {
                font-size: 10px;
            }

            .points-display {
                font-size: 12px;
            }

            .order-summary {
                padding: 12px 10px 10px;
                border-radius: 10px 10px 0 0;
            }

            .order-summary h3 {
                font-size: 14px;
                margin-bottom: 8px;
            }

            .mobile-summary-row {
                font-size: 11px;
                margin: 3px 0;
            }

            .points-comparison {
                padding: 8px;
                margin: 6px 0;
            }

            .confirm-btn {
                font-size: 12px;
                padding: 9px;
            }
        }
    </style>
</head>
<body>
    <!-- Bank Header -->
    <div class="bank-header">
        <div class="header-top">Secure Banking • 24/7 Support • Your Financial Partner</div>
        <div class="header-main">
            <div class="header-container">
                <div class="nav-left">
                    <div class="page-title"><i class="fas fa-receipt"></i> Confirmation</div>
                </div>
                <div class="logo-section">
                    <div class="logo-container">
                        <img src="images/optima.png" alt="Optima Bank" class="logo">
                        <div><div class="bank-name">OPTIMA BANK</div><div class="bank-tagline">Excellence in Banking</div></div>
                    </div>
                </div>
                <div class="nav-right"><a href="javascript:history.back()" class="back-btn">Back</a></div>
            </div>
        </div>
    </div>

    <!-- Page -->
    <div class="page-wrapper">
        <div class="container">
            <div class="checkout-items">
                <?php foreach ($vouchers as $v): ?>
                <div class="checkout-item">
                    <div class="checkout-item-left">
                        <img src="<?= htmlspecialchars($v['image']) ?>" alt="">
                        <div class="info">
                            <h4><?= htmlspecialchars($v['title']) ?></h4>
                            <p><?= number_format($v['points']) ?> Points each</p>
                            <p><strong>Quantity:</strong> <?= $v['quantity'] ?></p>
                        </div>
                    </div>
                    <div class="points-display"><?= number_format($v['points'] * $v['quantity']) ?> pts</div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="order-summary">
                <h3><i class="fas fa-clipboard-check"></i> Redeem Summary</h3>
                <div class="points-comparison">
                    <div class="mobile-summary-row">
                        <span><strong>Total Items:</strong></span>
                        <span><?= $totalItems ?></span>
                    </div>
                    <div class="mobile-summary-row">
                        <span><strong>Total Cost:</strong></span>
                        <span><?= number_format($totalPoints) ?> Points</span>
                    </div>
                    <div class="mobile-summary-row">
                        <span><strong>Your Balance:</strong></span>
                        <span><?= number_format($userPoints) ?> Points</span>
                    </div>

                    <?php if ($totalPoints <= $userPoints): ?>
                        <div class="points-sufficient"><i class="fas fa-check"></i> You have sufficient points!</div>
                        <div class="mobile-summary-row">
                            <span><strong>Balance After:</strong></span>
                            <span><?= number_format($userPoints - $totalPoints) ?> Points</span>
                        </div>
                    <?php else: ?>
                        <div class="points-insufficient"><i class="fas fa-times"></i> Insufficient points!</div>
                        <div class="mobile-summary-row">
                            <span><strong>Points Needed:</strong></span>
                            <span><?= number_format($totalPoints - $userPoints) ?> More</span>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($totalPoints <= $userPoints): ?>
                    <form method="post" action="confirm_checkout.php">
                        <input type="hidden" name="items" value="<?= implode(',', $checkoutItems) ?>">
                        <button type="submit" class="confirm-btn"><i class="fas fa-credit-card"></i> Confirm Redeem</button>
                    </form>
                <?php else: ?>
                    <a href="homepage.php" class="confirm-btn" style="background: var(--bank-primary);"><i class="fas fa-coins"></i> Earn More Points</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>