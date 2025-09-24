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

        .page-wrapper { max-width: 1200px; width: 100%; margin: 30px auto; padding: 20px; flex: 1; }

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
        .checkout-item-left { display: flex; align-items: center; gap: 15px; flex: 1; }
        .checkout-item img { width: 70px; height: 70px; border-radius: 10px; object-fit: cover; background:#eee; border: 2px solid #f3eaff; }
        .checkout-item .info h4 { margin: 0; font-size: 16px; font-weight: bold; color: #333; }
        .checkout-item .info p { margin: 5px 0 0; font-size: 14px; color: #777; }
        .points-display { font-size: 16px; font-weight: bold; color: var(--bank-primary); }

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
        .order-summary h3 { margin-bottom: 15px; color: var(--bank-primary); font-size: 20px; font-weight: bold; }
        .order-summary p { font-size: 16px; color:#333; margin: 10px 0; }
        .points-comparison {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--bank-primary);
        }
        .points-sufficient { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 8px 12px; border-radius: 6px; }
        .points-insufficient { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 8px 12px; border-radius: 6px; }

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
        .confirm-btn:hover { background: linear-gradient(135deg, #059669, #047857); }
        .confirm-btn:disabled { background: #9ca3af; cursor: not-allowed; opacity: 0.6; }
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
        <div class="container" style="display:flex; gap:25px; align-items:flex-start;">
            <div class="checkout-items" style="flex:3;">
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
                    <p><strong>Total Items:</strong> <?= $totalItems ?></p>
                    <p><strong>Total Cost:</strong> <?= number_format($totalPoints) ?> Points</p>
                    <p><strong>Your Balance:</strong> <?= number_format($userPoints) ?> Points</p>

                    <?php if ($totalPoints <= $userPoints): ?>
                        <div class="points-sufficient"><i class="fas fa-check"></i> You have sufficient points!</div>
                        <p><strong>Balance After:</strong> <?= number_format($userPoints - $totalPoints) ?> Points</p>
                    <?php else: ?>
                        <div class="points-insufficient"><i class="fas fa-times"></i> Insufficient points!</div>
                        <p><strong>Points Needed:</strong> <?= number_format($totalPoints - $userPoints) ?> More</p>
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
