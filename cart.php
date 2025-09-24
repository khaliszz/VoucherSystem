<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch user points
$userSql = "SELECT points FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userPoints = $user['points'] ?? 0; // Default to 0 if no points

// Handle add to cart
if (isset($_GET['action']) && $_GET['action'] == 'add' && isset($_GET['id'])) {
    $voucherId = intval($_GET['id']);
    $sql = "SELECT * FROM cart_items WHERE user_id=? AND voucher_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId, $voucherId]);

    if ($stmt->rowCount() > 0) {
        $conn->prepare("UPDATE cart_items SET quantity = quantity + 1 WHERE user_id=? AND voucher_id=?")
             ->execute([$userId, $voucherId]);
    } else {
        $conn->prepare("INSERT INTO cart_items (user_id, voucher_id, quantity) VALUES (?, ?, 1)")
             ->execute([$userId, $voucherId]);
    }

    // Check if this is an AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // Calculate updated cart count
        $cartCount = 0;
        $cartSql = "SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?";
        $cartStmt = $conn->prepare($cartSql);
        $cartStmt->execute([$userId]);
        $cartRow = $cartStmt->fetch(PDO::FETCH_ASSOC);
        $cartCount = $cartRow['total'] ?? 0;
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Voucher added to cart successfully',
            'cartCount' => $cartCount
        ]);
        exit;
    } else {
        // Regular page request - redirect to homepage
        $_SESSION['success_message'] = "Voucher added to cart ✅";
        header("Location: homepage.php");
        exit;
    }
}

// Handle quantity update
if (isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['id']) && isset($_GET['qty'])) {
    $voucherId = intval($_GET['id']);
    $qty = max(1, intval($_GET['qty']));
    $conn->prepare("UPDATE cart_items SET quantity=? WHERE user_id=? AND voucher_id=?")
         ->execute([$qty, $userId, $voucherId]);
    
    // Preserve selected items when updating quantity
    $selectedParam = isset($_GET['selected']) ? '&selected=' . urlencode($_GET['selected']) : '';
    header("Location: cart.php?updated=1" . $selectedParam);
    exit;
}

// Handle remove item
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['id'])) {
    $voucherId = intval($_GET['id']);
    $conn->prepare("DELETE FROM cart_items WHERE user_id=? AND voucher_id=?")
         ->execute([$userId, $voucherId]);
    
    // Preserve selected items when removing item (but remove the deleted item from selection)
    if (isset($_GET['selected'])) {
        $selectedItems = explode(',', $_GET['selected']);
        $selectedItems = array_filter($selectedItems, function($id) use ($voucherId) {
            return intval($id) !== $voucherId;
        });
        $selectedParam = !empty($selectedItems) ? '&selected=' . implode(',', $selectedItems) : '';
        header("Location: cart.php?removed=1" . $selectedParam);
    } else {
        header("Location: cart.php?removed=1");
    }
    exit;
}

// Handle clear cart
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    $conn->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$userId]);
    header("Location: cart.php?cleared=1");
    exit;
}

// Get selected items from URL parameter (when coming back from checkout/confirm)
$preSelectedItems = [];
if (isset($_GET['selected']) && !empty($_GET['selected'])) {
    $preSelectedItems = explode(',', $_GET['selected']);
    $preSelectedItems = array_filter(array_map('intval', $preSelectedItems));
}

// Fetch cart items
$sql = "
    SELECT c.voucher_id, c.quantity, v.title, v.image, v.points
    FROM cart_items c
    JOIN voucher v ON c.voucher_id = v.voucher_id
    WHERE c.user_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count total cart quantity
$totalCount = 0;
foreach ($cartItems as $item) {
    $totalCount += $item['quantity'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - Optima Bank</title>
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

    /* Cart Icon */
    .cart-container {
        position: relative;
        display: inline-block;
    }
    .cart-container i {
        font-size: 22px;
        color: #fff;
    }
    .cart-count {
        position: absolute;
        top: -8px;
        right: -12px;
        background: linear-gradient(135deg, #ff416c, #ff4b2b);
        color: #fff;
        font-size: 11px;
        font-weight: bold;
        padding: 3px 7px;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        min-width: 18px;
        text-align: center;
        line-height: 1.2;
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

    /* Container */
    .container {
        display: flex;
        gap: 25px;
        align-items: flex-start;
    }
    .cart-items { flex: 3; }

    /* Cart items */
    .cart-item, .cart-header-box {
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
    .cart-item-left {
        display: flex;
        align-items: center;
        gap: 15px;
        flex: 1;
    }
    .cart-item img {
        width: 70px;
        height: 70px;
        border-radius: 10px;
        object-fit: cover;
        background:#eee;
        border: 2px solid #f3eaff;
    }
    .cart-item .info h4 {
        margin: 0;
        font-size: 16px;
        font-weight: bold;
        color: #333;
    }
    .cart-item .info p {
        margin: 5px 0 0;
        font-size: 14px;
        color: #777;
    }

    /* Right section */
    .cart-item-right {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .quantity {
        display: flex;
        align-items: center;
        gap: 8px;
        background:#f8fafc;
        padding: 5px 10px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    .quantity a {
        border: none;
        padding: 6px 12px;
        text-decoration: none;
        font-size: 16px;
        font-weight: bold;
        color: var(--bank-primary);
        border-radius: 6px;
        transition: all 0.2s;
        background:#fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .quantity a:hover {
        background: var(--bank-secondary);
        color:#fff;
    }

    /* Sticky Select All / Clear Cart */
    .cart-header-box {
        position: sticky;
        top: 90px;
        z-index: 999;
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        border: 2px solid var(--bank-primary);
    }
    .cart-header-box label {
        font-size: 16px;
        font-weight: 600;
        color: var(--bank-primary);
    }
    .cart-header-box .remove {
        font-size: 16px;
        font-weight: 600;
        color: var(--bank-secondary);
        text-decoration: none;
        cursor: pointer;
        transition: color 0.3s;
    }
    .cart-header-box .remove:hover {
        color: var(--bank-primary);
    }

    /* Trash remove (per item) */
    .remove {
        border: none;
        background: none;
        color: var(--bank-secondary);
        font-size: 22px;
        font-weight: bold;
        cursor: pointer;
        text-decoration: none;
        transition: transform 0.2s, color 0.3s;
    }
    .remove:hover {
        color: var(--bank-primary);
        transform: scale(1.15);
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
    
    /* Points comparison in order summary */
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
    
    /* FIXED: Correct colors for sufficient/insufficient points */
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
    
    .checkout-btn {
        display:inline-block;
        padding:12px 25px;
        margin-top:20px;
        background: linear-gradient(135deg, var(--bank-primary), var(--bank-secondary));
        color:#fff;
        text-align:center;
        border-radius:8px;
        text-decoration:none;
        font-weight: bold;
        font-size: 16px;
        transition: all 0.3s;
        box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        border: none;
        cursor: pointer;
    }
    .checkout-btn:hover { 
        transform: translateY(-1px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }
    
    .checkout-btn.disabled {
        background: #9ca3af;
        cursor: not-allowed;
        opacity: 0.6;
        transform: none;
    }

    /* Empty cart */
    .empty-cart {
        text-align: center;
        padding: 60px;
        color: #777;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 3px 10px rgba(45,0,48,0.08);
    }
    .empty-cart img {
        width: 130px;
        opacity: 0.6;
        margin-bottom: 20px;
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
            padding-bottom: 120px;
        }
        
        .container {
            flex-direction: column;
            gap: 15px;
        }
        
        .cart-items {
            flex: none;
        }
        
        .cart-item, .cart-header-box {
            padding: 12px 16px;
            margin-bottom: 12px;
            border-radius: 8px;
        }
        
        .cart-item-left {
            gap: 12px;
        }
        
        .cart-item img {
            width: 60px;
            height: 60px;
        }
        
        .cart-item .info h4 {
            font-size: 14px;
        }
        
        .cart-item .info p {
            font-size: 13px;
        }
        
        .cart-item-right {
            gap: 12px;
        }
        
        .quantity {
            padding: 4px 8px;
        }
        
        .quantity a {
            padding: 4px 8px;
            font-size: 14px;
        }
        
        /* Mobile sticky checkout bar */
        .mobile-checkout-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            border-top: 1px solid #e0e0e0;
            padding: 12px 16px;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            display: block;
        }
        
        .mobile-checkout-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .mobile-checkout-info {
            flex: 1;
        }
        
        .mobile-checkout-total {
            font-size: 16px;
            font-weight: bold;
            color: var(--bank-primary);
            margin-bottom: 2px;
        }
        
        .mobile-checkout-items {
            font-size: 12px;
            color: #777;
        }
        
        .mobile-checkout-btn {
            background: linear-gradient(135deg, var(--bank-primary), var(--bank-secondary));
            color: #fff;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            transition: opacity 0.3s;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            min-width: 80px;
            text-align: center;
            border: none;
            cursor: pointer;
        }
        
        .mobile-checkout-btn:hover {
            opacity: 0.85;
        }
        
        .mobile-checkout-btn:disabled,
        .mobile-checkout-btn.disabled {
            background: #9ca3af;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .order-summary {
            display: none;
        }
        
        .cart-header-box {
            position: static;
            top: auto;
        }
    }
    
    @media (min-width: 769px) {
        .mobile-checkout-bar {
            display: none;
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
        
        .cart-item, .cart-header-box {
            padding: 10px 12px;
        }
        
        .cart-item img {
            width: 50px;
            height: 50px;
        }
        
        .cart-item .info h4 {
            font-size: 13px;
        }
        
        .cart-item .info p {
            font-size: 12px;
        }
        
        .mobile-checkout-bar {
            padding: 10px 12px;
        }
        
        .mobile-checkout-total {
            font-size: 15px;
        }
        
        .mobile-checkout-btn {
            padding: 8px 16px;
            font-size: 13px;
            min-width: 70px;
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
                        <i class="fas fa-shopping-cart"></i>
                        My Cart
                        <?php if ($totalCount > 0): ?>
                            <div class="cart-container">
                                <span class="cart-count"><?= $totalCount ?></span>
                            </div>
                        <?php endif; ?>
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
                    <a href="homepage.php" class="back-btn">
                        Back
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="page-wrapper">
        <div class="container">
            <div class="cart-items">

                <?php if (!empty($cartItems)): ?>
                <div class="cart-item cart-header-box">
                    <div class="cart-item-left">
                        <input type="checkbox" id="selectAll">
                        <label for="selectAll">Select All Items</label>
                    </div>
                    <div class="cart-item-right">
                        <a href="cart.php?action=clear" class="remove">
                            <i class="fas fa-trash"></i> Clear Cart
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($cartItems)): ?>
                    <?php foreach ($cartItems as $item): ?>
                    <div class="cart-item" data-id="<?= $item['voucher_id'] ?>" data-points="<?= $item['points'] ?>" data-qty="<?= $item['quantity'] ?>">
                        <div class="cart-item-left">
                            <input type="checkbox" class="select-item">
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="">
                            <div class="info">
                                <h4><?= htmlspecialchars($item['title']) ?></h4>
                                <p><?= $item['points'] ?> Points</p>
                            </div>
                        </div>
                        <div class="cart-item-right">
                            <div class="quantity">
                                <a href="cart.php?action=update&id=<?= $item['voucher_id'] ?>&qty=<?= $item['quantity']-1 ?><?= isset($_GET['selected']) ? '&selected=' . urlencode($_GET['selected']) : '' ?>" class="qty-btn">-</a>
                                <span><?= $item['quantity'] ?></span>
                                <a href="cart.php?action=update&id=<?= $item['voucher_id'] ?>&qty=<?= $item['quantity']+1 ?><?= isset($_GET['selected']) ? '&selected=' . urlencode($_GET['selected']) : '' ?>" class="qty-btn">+</a>
                            </div>
                            <a href="cart.php?action=remove&id=<?= $item['voucher_id'] ?><?= isset($_GET['selected']) ? '&selected=' . urlencode($_GET['selected']) : '' ?>" class="remove">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-cart">
                        <img src="https://cdn-icons-png.flaticon.com/512/102/102661.png" alt="Empty Cart">
                        <h3>Your cart is empty</h3>
                        <p>Start shopping to add items to your cart</p>
                        <a href="homepage.php" class="checkout-btn" style="margin-top: 20px;">
                            <i class="fas fa-shopping-bag"></i> Start Shopping
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="order-summary">
                <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                <div class="points-comparison">
                    <div class="available-points">
                        <i class="fas fa-coins"></i> Available: <?php echo number_format($userPoints); ?> Points
                    </div>
                    <p>Selected Total: <strong id="totalPoints">0</strong> Points</p>
                    <div id="pointsStatus" class="points-status points-sufficient" style="display: none;">
                        You have sufficient points!
                    </div>
                </div>
                <form id="checkoutForm" action="checkout.php" method="post">
                    <input type="hidden" name="items" id="checkoutItems">
                    <button type="submit" class="checkout-btn" id="checkoutBtn">
                        <i class="fas fa-credit-card"></i> Proceed to Checkout
                    </button>
                </form>
            </div>
        </div>

        <!-- Mobile Sticky Checkout Bar -->
        <?php if (!empty($cartItems)): ?>
        <div class="mobile-checkout-bar">
            <div class="mobile-checkout-content">
                <div class="mobile-checkout-info">
                    <div class="mobile-checkout-total" id="mobileTotalPoints">0 Points</div>
                    <div class="mobile-checkout-items" id="mobileSelectedItems">0 items selected</div>
                </div>
                <form id="mobileCheckoutForm" action="checkout.php" method="post">
                    <input type="hidden" name="items" id="mobileCheckoutItems">
                    <button type="submit" class="mobile-checkout-btn" id="mobileCheckoutBtn">
                        <i class="fas fa-credit-card"></i> Checkout
                    </button>
                </form>
            </div>
<script>
    const totalPointsEl = document.getElementById('totalPoints');
    const mobileTotalPointsEl = document.getElementById('mobileTotalPoints');
    const mobileSelectedItemsEl = document.getElementById('mobileSelectedItems');
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.select-item');
    const mobileCheckoutBtn = document.getElementById('mobileCheckoutBtn');

    const checkoutBtn = document.getElementById('checkoutBtn');
    const pointsStatus = document.getElementById('pointsStatus');
    const userPoints = <?php echo $userPoints; ?>;

    // Pre-selected items from URL parameter (when coming back from checkout/confirm)
    const preSelectedItems = <?php echo json_encode($preSelectedItems); ?>;

    function updateTotal() {
        let total = 0;
        let selectedCount = 0;
        
        document.querySelectorAll('.select-item:checked').forEach(cb => {
            const item = cb.closest('.cart-item');
            const points = parseInt(item.dataset.points);
            const qty = parseInt(item.dataset.qty);
            total += points * qty;
            selectedCount++;
        });

        // Update desktop
        if (totalPointsEl) {
            totalPointsEl.textContent = total.toLocaleString();
        }

        // Update mobile
        if (mobileTotalPointsEl) {
            mobileTotalPointsEl.textContent = total.toLocaleString() + ' Points';
        }
        if (mobileSelectedItemsEl) {
            mobileSelectedItemsEl.textContent = selectedCount + ' item' + (selectedCount !== 1 ? 's' : '') + ' selected';
        }

        // Update points status with correct logic and colors
        if (pointsStatus) {
            if (selectedCount > 0) {
                pointsStatus.style.display = 'block';
                if (total <= userPoints) {
                    // Sufficient points - GREEN
                    pointsStatus.textContent = 'You have sufficient points!';
                    pointsStatus.className = 'points-status points-sufficient';
                } else {
                    // Insufficient points - RED
                    pointsStatus.textContent = `Not enough points! You need ${(total - userPoints).toLocaleString()} more points.`;
                    pointsStatus.className = 'points-status points-insufficient';
                }
            } else {
                pointsStatus.style.display = 'none';
            }
        }

        // Update checkout button states
        const canCheckout = selectedCount > 0 && total <= userPoints;
        
        if (checkoutBtn) {
            if (canCheckout) {
                checkoutBtn.classList.remove('disabled');
                checkoutBtn.style.pointerEvents = 'auto';
            } else {
                checkoutBtn.classList.add('disabled');
                checkoutBtn.style.pointerEvents = 'none';
            }
        }

        if (mobileCheckoutBtn) {
            if (canCheckout) {
                mobileCheckoutBtn.classList.remove('disabled');
                mobileCheckoutBtn.style.pointerEvents = 'auto';
            } else {
                mobileCheckoutBtn.classList.add('disabled');
                mobileCheckoutBtn.style.pointerEvents = 'none';
            }
        }
    }

    function saveChecked() {
        let checkedIds = [];
        document.querySelectorAll('.select-item:checked').forEach(cb => {
            checkedIds.push(cb.closest('.cart-item').dataset.id);
        });
        // Store selected items in memory for session persistence
        window.checkedItems = checkedIds;
    }

    function restoreChecked() {
        // First, restore from URL parameter (priority for back navigation)
        if (preSelectedItems && preSelectedItems.length > 0) {
            document.querySelectorAll('.cart-item').forEach(item => {
                const itemId = parseInt(item.dataset.id);
                if (preSelectedItems.includes(itemId)) {
                    item.querySelector('.select-item').checked = true;
                }
            });
            // Save the restored selection
            saveChecked();
        } else {
            // Fallback to in-memory storage
            let checkedIds = window.checkedItems || [];
            document.querySelectorAll('.cart-item').forEach(item => {
                if (checkedIds.includes(item.dataset.id)) {
                    item.querySelector('.select-item').checked = true;
                }
            });
        }
        
        // Update select all checkbox state
        updateSelectAllState();
        updateTotal();
    }

    function updateSelectAllState() {
        if (selectAll) {
            const totalCheckboxes = checkboxes.length;
            const checkedCheckboxes = document.querySelectorAll('.select-item:checked').length;
            
            if (checkedCheckboxes === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else if (checkedCheckboxes === totalCheckboxes) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } else {
                selectAll.checked = false;
                selectAll.indeterminate = true;
            }
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            saveChecked();
            updateTotal();
        });
    }

    checkboxes.forEach(cb => cb.addEventListener('change', () => {
        updateSelectAllState();
        saveChecked();
        updateTotal();
    }));

    document.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const item = this.closest('.cart-item');
            item.querySelector('.select-item').checked = true;
            saveChecked();
        });
    });

    // Checkout validation
    function validateCheckout(e, formId, inputId) {
    e.preventDefault();

    const selected = [];
    let total = 0;
    document.querySelectorAll('.select-item:checked').forEach(cb => {
        const item = cb.closest('.cart-item');
        const points = parseInt(item.dataset.points);
        const qty = parseInt(item.dataset.qty);
        selected.push(item.dataset.id);
        total += points * qty;
    });

    if (selected.length === 0) {
        alert('Please select at least one item to checkout');
        return false;
    }

    if (total > userPoints) {
        alert(`Not enough points. You need ${total - userPoints} more points.`);
        return false;
    }

    // ✅ Store selected IDs in hidden input & submit form
    document.getElementById(inputId).value = selected.join(',');
    document.getElementById(formId).submit();
    return true;
    }
    
    document.querySelector('#checkoutForm button').addEventListener('click', function(e) {
    validateCheckout(e, 'checkoutForm', 'checkoutItems');
    });

    document.querySelector('#mobileCheckoutForm button').addEventListener('click', function(e) {
    validateCheckout(e, 'mobileCheckoutForm', 'mobileCheckoutItems');
    });

    // Initialize on page load
    restoreChecked();
</script>
</body>
</html>