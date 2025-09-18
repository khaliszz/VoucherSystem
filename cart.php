<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

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

    $_SESSION['success_message'] = "Voucher added to cart ✅";
    header("Location: homepage.php");
    exit;
}

// Handle quantity update
if (isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['id']) && isset($_GET['qty'])) {
    $voucherId = intval($_GET['id']);
    $qty = max(1, intval($_GET['qty']));
    $conn->prepare("UPDATE cart_items SET quantity=? WHERE user_id=? AND voucher_id=?")
         ->execute([$qty, $userId, $voucherId]);
    header("Location: cart.php");
    exit;
}

// Handle remove item
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['id'])) {
    $voucherId = intval($_GET['id']);
    $conn->prepare("DELETE FROM cart_items WHERE user_id=? AND voucher_id=?")
         ->execute([$userId, $voucherId]);
    header("Location: cart.php");
    exit;
}

// Handle clear cart
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    $conn->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$userId]);
    header("Location: cart.php");
    exit;
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
    <title>My Cart</title>
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
    /* Header */
    .cart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        background: linear-gradient(135deg, #8e2de2, #4a00e0);
        padding: 15px 20px;
        border-radius: 12px;
        color: #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        position: sticky;    /* stick header */
        top: 0;
        z-index: 1000;
    }
    .cart-title {
        font-size: 1.2rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Cart Icon */
    .cart-container {
        position: relative;
        display: inline-block;
    }
    .cart-container i {
        font-size: 26px;
        color: #fff;
    }
    .cart-count {
        position: absolute;
        top: -8px;
        right: -12px;
        background: linear-gradient(135deg, #ff416c, #ff4b2b);
        color: #fff;
        font-size: 12px;
        font-weight: bold;
        padding: 3px 7px;
        border-radius: 50%;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        min-width: 20px;
        text-align: center;
        line-height: 1.2;
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
        box-shadow: 0 3px 10px rgba(106,17,203,0.08);
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
        background:#f9f5ff;
        padding: 5px 10px;
        border-radius: 8px;
    }
    .quantity a {
        border: none;
        padding: 6px 12px;
        text-decoration: none;
        font-size: 16px;
        font-weight: bold;
        color: #6a11cb;
        border-radius: 6px;
        transition: all 0.2s;
        background:#fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .quantity a:hover {
        background:#ff6f3c;
        color:#fff;
    }

    /* Sticky Select All / Clear Cart */
    .cart-header-box {
        position: sticky;
        top: 70px;   /* below main header */
        z-index: 999;
    }
    .cart-header-box label {
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }
    .cart-header-box .remove {
        font-size: 16px;
        font-weight: 600;
        color: #6a11cb;
        text-decoration: none;
        cursor: pointer;
        transition: color 0.3s;
    }
    .cart-header-box .remove:hover {
        color: #4a00e0;
    }

    /* Trash remove (per item) */
    .remove {
        border: none;
        background: none;
        color: #6a11cb;
        font-size: 22px;
        font-weight: bold;
        cursor: pointer;
        text-decoration: none;
        transition: transform 0.2s, color 0.3s;
    }
    .remove:hover {
        color: #4a00e0;
        transform: scale(1.15);
    }

    /* Order summary */
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
    .checkout-btn {
        display:inline-block;
        padding:12px 25px;
        margin-top:20px;
        background: linear-gradient(135deg, #8e2de2, #4a00e0);
        color:#fff;
        text-align:center;
        border-radius:8px;
        text-decoration:none;
        font-weight: bold;
        font-size: 16px;
        transition: opacity 0.3s;
        box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    }
    .checkout-btn:hover { opacity:0.85; }

    /* Empty cart */
    .empty-cart {
        text-align: center;
        padding: 60px;
        color: #777;
    }
    .empty-cart img {
        width: 130px;
        opacity: 0.6;
        margin-bottom: 20px;
    }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        body {
            display: block;
        }
        
        .page-wrapper {
            margin: 0;
            padding: 10px;
            padding-bottom: 120px; /* Space for mobile checkout bar */
        }
        
        .cart-header {
            margin-bottom: 15px;
            padding: 12px 16px;
            border-radius: 8px;
        }
        
        .cart-title {
            font-size: 1.1rem;
        }
        
        .back-btn {
            padding: 6px 12px;
            font-size: 14px;
        }
        
        /* Stack container vertically on mobile */
        .container {
            flex-direction: column;
            gap: 15px;
        }
        
        .cart-items {
            flex: none;
        }
        
        /* Mobile cart items */
        .cart-item, .cart-header-box {
            display: flex;
            flex-direction: row;
            align-items: center;
            padding: 12px 16px;
            margin-bottom: 12px;
            border-radius: 8px;
        }
        
        .cart-item-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        
        .cart-item img {
            width: 60px;
            height: 60px;
            border-radius: 8px;
        }
        
        .cart-item .info h4 {
            font-size: 14px;
        }
        
        .cart-item .info p {
            font-size: 13px;
        }
        
        .cart-item-right {
            display: flex;
            align-items: center;
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
            color: #6a11cb;
            margin-bottom: 2px;
        }
        
        .mobile-checkout-items {
            font-size: 12px;
            color: #777;
        }
        
        .mobile-checkout-btn {
            background: linear-gradient(135deg, #8e2de2, #4a00e0);
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
        }
        
        .mobile-checkout-btn:hover {
            opacity: 0.85;
        }
        
        .mobile-checkout-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        /* Hide desktop order summary on mobile */
        .order-summary {
            display: none;
        }
        
        /* Mobile select all adjustments */
        .cart-header-box {
            position: static;
            top: auto;
            justify-content: space-between;
        }
        
        .cart-header-box .cart-item-left {
            margin-bottom: 0;
        }
        
        .cart-header-box label {
            font-size: 14px;
        }
        
        .cart-header-box .remove {
            font-size: 14px;
        }
        
        /* Touch-friendly buttons */
        .quantity a, .back-btn, .mobile-checkout-btn {
            min-height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .remove {
            font-size: 18px;
            padding: 8px;
            min-height: 32px;
            min-width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    }
    
    /* Hide mobile checkout bar on desktop */
    @media (min-width: 769px) {
        .mobile-checkout-bar {
            display: none;
        }
    }
    
    /* Small mobile adjustments */
    @media (max-width: 480px) {
        .page-wrapper {
            padding: 8px;
        }
        
        .cart-header {
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        
        .cart-title {
            font-size: 1rem;
        }
        
        .cart-item, .cart-header-box {
            padding: 10px 12px;
        }
        
        .cart-item img {
            width: 50px;
            height: 50px;
        }
        
        .cart-item .info {
            flex: 1;
        }
        
        .cart-item .info h4 {
            font-size: 13px;
            margin-bottom: 4px;
        }
        
        .cart-item .info p {
            font-size: 12px;
            margin: 0;
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
<div class="page-wrapper">

    <div class="cart-header">
        <div class="cart-title">
            My Cart 
            <div class="cart-container">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($totalCount > 0): ?>
                    <span class="cart-count"><?= $totalCount ?></span>
                <?php endif; ?>
            </div>
        </div>
        <a href="homepage.php" class="back-btn">Back</a>
    </div>

    <div class="container">
        <div class="cart-items">

            <?php if (!empty($cartItems)): ?>
            <div class="cart-item cart-header-box">
                <div class="cart-item-left">
                    <input type="checkbox" id="selectAll">
                    <label for="selectAll">Select All</label>
                </div>
                <div class="cart-item-right">
                    <a href="cart.php?action=clear" class="remove">🗑 Clear Cart</a>
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
                            <a href="cart.php?action=update&id=<?= $item['voucher_id'] ?>&qty=<?= $item['quantity']-1 ?>" class="qty-btn">-</a>
                            <span><?= $item['quantity'] ?></span>
                            <a href="cart.php?action=update&id=<?= $item['voucher_id'] ?>&qty=<?= $item['quantity']+1 ?>" class="qty-btn">+</a>
                        </div>
                        <a href="cart.php?action=remove&id=<?= $item['voucher_id'] ?>" class="remove">🗑</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-cart">
                    <img src="https://cdn-icons-png.flaticon.com/512/102/102661.png" alt="Empty Cart">
                    <p>Your cart is empty</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="order-summary">
            <h3>Order Summary</h3>
            <p>Total Points: <strong id="totalPoints">0</strong></p>
            <a href="checkout.php" class="checkout-btn">Checkout</a>
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
            <a href="checkout.php" class="mobile-checkout-btn" id="mobileCheckoutBtn">Checkout</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    const totalPointsEl = document.getElementById('totalPoints');
    const mobileTotalPointsEl = document.getElementById('mobileTotalPoints');
    const mobileSelectedItemsEl = document.getElementById('mobileSelectedItems');
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.select-item');
    const mobileCheckoutBtn = document.getElementById('mobileCheckoutBtn');

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
            totalPointsEl.textContent = total;
        }

        // Update mobile
        if (mobileTotalPointsEl) {
            mobileTotalPointsEl.textContent = total + ' Points';
        }
        if (mobileSelectedItemsEl) {
            mobileSelectedItemsEl.textContent = selectedCount + ' item' + (selectedCount !== 1 ? 's' : '') + ' selected';
        }

        // Update mobile checkout button state
        if (mobileCheckoutBtn) {
            mobileCheckoutBtn.disabled = selectedCount === 0;
            mobileCheckoutBtn.style.opacity = selectedCount > 0 ? '1' : '0.6';
        }
    }

    function saveChecked() {
        let checkedIds = [];
        document.querySelectorAll('.select-item:checked').forEach(cb => {
            checkedIds.push(cb.closest('.cart-item').dataset.id);
        });
        localStorage.setItem('checkedItems', JSON.stringify(checkedIds));
    }

    function restoreChecked() {
        let checkedIds = JSON.parse(localStorage.getItem('checkedItems') || '[]');
        document.querySelectorAll('.cart-item').forEach(item => {
            if (checkedIds.includes(item.dataset.id)) {
                item.querySelector('.select-item').checked = true;
            }
        });
        updateTotal();
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            saveChecked();
            updateTotal();
        });
    }

    checkboxes.forEach(cb => cb.addEventListener('change', () => {
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

    // Mobile checkout validation
    if (mobileCheckoutBtn) {
        mobileCheckoutBtn.addEventListener('click', function(e) {
            const selectedCount = document.querySelectorAll('.select-item:checked').length;
            if (selectedCount === 0) {
                e.preventDefault();
                alert('Please select at least one item to checkout');
                return;
            }
        });
    }

    restoreChecked();
</script>
</body>
</html>
