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

// Get items from POST or session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['items'])) {
    $confirmItems = explode(',', $_POST['items']);
    $_SESSION['confirm_items'] = $confirmItems;
} else {
    $confirmItems = $_SESSION['confirm_items'] ?? [];
}

if (empty($confirmItems)) {
    header("Location: cart.php");
    exit;
}

// Fetch voucher details with quantities
$placeholders = implode(',', array_fill(0, count($confirmItems), '?'));
$sql = "SELECT v.voucher_id, v.title, v.image, v.points, 
        COALESCE(c.quantity, 1) as quantity
        FROM voucher v
        LEFT JOIN cart_items c ON v.voucher_id = c.voucher_id AND c.user_id=?
        WHERE v.voucher_id IN ($placeholders)";
$stmt = $conn->prepare($sql);
$stmt->execute(array_merge([$userId], $confirmItems));
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalPoints = 0;
$totalItems = 0;
foreach ($vouchers as $v) {
    $totalPoints += $v['points'] * $v['quantity'];
    $totalItems += $v['quantity'];
}

// Handle form submission - redirect to confirm_checkout.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    // Redirect to confirm_checkout.php with items data
    $itemsString = implode(',', $confirmItems);
    // Use POST redirect by creating a form and submitting it
    echo "
    <!DOCTYPE html>
    <html>
    <body>
        <form id='redirectForm' method='post' action='confirm_checkout.php'>
            <input type='hidden' name='items' value='" . htmlspecialchars($itemsString) . "'>
        </form>
        <script>
            document.getElementById('redirectForm').submit();
        </script>
    </body>
    </html>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Optima Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
    :root {
        --primary-gradient: linear-gradient(135deg, #2d0030 0%, #4a1a4f 100%);
        --button-gradient: linear-gradient(90deg, #2d0030 0%, #4a1a4f 100%);
        --button-hover-gradient: linear-gradient(90deg, #3d1040 0%, #5b2d5f 100%);
        --text-color: #333;
        --text-secondary-color: #777;
        --border-color: #e0e0e0;
        --background-color: #f4f7fc;
        --white-color: #ffffff;
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

    /* Professional Bank Header */
    .bank-header {
        background: #2d0030;
        padding: 0;
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

    /* Centered Professional Logo */
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

    .logo-container:hover {
        transform: translateY(-1px);
    }
    
    .logo {
        height: 35px;
        transition: all 0.3s ease;
        border-radius: 4px;
    }

    .bank-name {
        color: #ff9500;
        font-size: 22px;
        font-weight: 700;
        letter-spacing: 0.5px;
        margin: 0;
        text-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }

    .bank-tagline {
        color: rgba(255,255,255,0.8);
        font-size: 10px;
        margin: 1px 0 0 0;
        letter-spacing: 0.3px;
        font-weight: 400;
    }

    /* Navigation Elements */
    .nav-left {
        flex: 1;
        display: flex;
        align-items: center;
    }

    .nav-right {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 20px;
    }

    .page-title {
        color: #fff;
        font-size: 16px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

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

    /* Success/Error Messages */
    .message-box {
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        font-size: 16px;
        font-weight: 600;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .success-box {
        background: #d1fae5;
        color: #065f46;
        border: 2px solid #10b981;
    }

    .error-box {
        background: #fee2e2;
        color: #991b1b;
        border: 2px solid #ef4444;
    }

    /* Container */
    .container {
        display: flex;
        gap: 25px;
        align-items: flex-start;
    }
    .confirm-items { 
        flex: 3; 
    }

    /* Confirm items */
    .confirm-item {
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
    .confirm-item-left {
        display: flex;
        align-items: center;
        gap: 15px;
        flex: 1;
    }
    .confirm-item img {
        width: 70px;
        height: 70px;
        border-radius: 10px;
        object-fit: cover;
        background: #eee;
        border: 2px solid #f3eaff;
    }
    .confirm-item .info h4 {
        margin: 0;
        font-size: 16px;
        font-weight: bold;
        color: #333;
    }
    .confirm-item .info p {
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
        background: #fff;
        border-radius: 12px;
        padding: 25px;
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
        color: #333;
        margin: 10px 0;
    }
    
    /* Points comparison */
    .points-comparison {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        padding: 15px;
        border-radius: 8px;
        margin: 15px 0;
        border-left: 4px solid var(--bank-primary);
    }
    
    .available-points {
        font-size: 14px;
        color: #666;
        margin-bottom: 8px;
    }
    
    .points-status {
        font-size: 16px;
        font-weight: bold;
        padding: 8px 12px;
        border-radius: 6px;
        margin: 10px 0;
    }
    
    .points-sufficient {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .points-insufficient {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .confirm-btn {
        display: inline-block;
        padding: 12px 25px;
        margin-top: 20px;
        background: linear-gradient(135deg, var(--success-color), #059669);
        color: #fff;
        text-align: center;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        font-size: 16px;
        transition: all 0.3s;
        box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        border: none;
        cursor: pointer;
    }
    .confirm-btn:hover { 
        transform: translateY(-1px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        background: linear-gradient(135deg, #059669, #047857);
    }
    
    .confirm-btn:disabled {
        background: #9ca3af;
        cursor: not-allowed;
        opacity: 0.6;
        transform: none;
    }

    .secondary-btn {
        background: linear-gradient(135deg, var(--bank-primary), var(--bank-secondary));
        margin-right: 15px;
    }
    
    .secondary-btn:hover {
        background: linear-gradient(135deg, #3d1040, #5b2d5f);
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
            margin: 20px auto;
            padding: 15px;
        }
        
        .container {
            flex-direction: column;
            gap: 15px;
        }
        
        .confirm-items {
            flex: none;
        }
        
        .confirm-item {
            padding: 12px 16px;
            margin-bottom: 12px;
            border-radius: 8px;
        }
        
        .confirm-item-left {
            gap: 12px;
        }
        
        .confirm-item img {
            width: 60px;
            height: 60px;
        }
        
        .confirm-item .info h4 {
            font-size: 14px;
        }
        
        .confirm-item .info p {
            font-size: 13px;
        }

        .order-summary {
            position: static;
            top: auto;
        }
    }
    
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

        .page-wrapper {
            padding: 10px;
        }
        
        .confirm-item {
            padding: 10px 12px;
        }
        
        .confirm-item img {
            width: 50px;
            height: 50px;
        }
        
        .confirm-item .info h4 {
            font-size: 13px;
        }
        
        .confirm-item .info p {
            font-size: 12px;
        }

        .order-summary {
            padding: 20px;
        }
    }
    </style>
</head>
<body>
    <!-- Professional Bank Header -->
    <div class="bank-header">
        <div class="header-top">
            Secure Banking • 24/7 Support • Your Financial Partner
        </div>
        <div class="header-main">
            <div class="header-container">
                <div class="nav-left">
                    <div class="page-title">
                        <i class="fas fa-clipboard-check"></i>
                        Confirm Order
                    </div>
                </div>
                
                <div class="logo-section">
                    <div class="logo-container">
                        <img src="images/optima.png" alt="Optima Bank" class="logo">
                        <div>
                            <div class="bank-name">OPTIMA BANK</div>
                            <div class="bank-tagline">Excellence in Banking</div>
                        </div>
                    </div>
                </div>
                
                <div class="nav-right">
                    <a href="cart.php?selected=<?= implode(',', $confirmItems) ?>" class="back-btn">
                        Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="page-wrapper">
        <!-- Order Confirmation -->
        <div class="message-box success-box">
            <i class="fas fa-info-circle"></i>
            Please review your order details before confirming your purchase.
        </div>

        <div class="container">
            <div class="confirm-items">
                <?php foreach ($vouchers as $v): ?>
                    <div class="confirm-item">
                        <div class="confirm-item-left">
                            <img src="<?= htmlspecialchars($v['image']) ?>" alt="">
                            <div class="info">
                                <h4><?= htmlspecialchars($v['title']) ?></h4>
                                <p><?= number_format($v['points']) ?> Points each</p>
                                <p><strong>Quantity:</strong> <?= $v['quantity'] ?></p>
                            </div>
                        </div>
                        <div class="points-display">
                            <?= number_format($v['points'] * $v['quantity']) ?> pts
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="order-summary">
                <h3><i class="fas fa-clipboard-check"></i> Final Confirmation</h3>
                
                <div class="points-comparison">
                    <div class="available-points">
                        <i class="fas fa-coins"></i> Current Balance: <?= number_format($userPoints) ?> Points
                    </div>
                    <p><strong>Total Items:</strong> <?= $totalItems ?></p>
                    <p><strong>Total Cost:</strong> <?= number_format($totalPoints) ?> Points</p>
                    
                    <?php if ($totalPoints <= $userPoints): ?>
                        <div class="points-status points-sufficient">
                            <i class="fas fa-check"></i> You have sufficient points!
                        </div>
                        <p><strong>Balance After:</strong> <?= number_format($userPoints - $totalPoints) ?> Points</p>
                    <?php else: ?>
                        <div class="points-status points-insufficient">
                            <i class="fas fa-times"></i> Insufficient points!
                        </div>
                        <p><strong>Points Needed:</strong> <?= number_format($totalPoints - $userPoints) ?> More</p>
                    <?php endif; ?>
                </div>

                <?php if ($totalPoints <= $userPoints): ?>
                    <form method="post">
                        <input type="hidden" name="items" value="<?= implode(',', $confirmItems) ?>">
                        <button type="submit" name="confirm_order" class="confirm-btn">
                            <i class="fas fa-credit-card"></i> Confirm Purchase
                        </button>
                    </form>
                <?php else: ?>
                    <p style="color: var(--error-color); font-weight: bold; margin-top: 20px;">
                        <i class="fas fa-exclamation-circle"></i> 
                        You don't have enough points to complete this purchase.
                    </p>
                    <a href="homepage.php" class="confirm-btn secondary-btn" style="margin-top: 15px;">
                        <i class="fas fa-coins"></i> Earn More Points
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>