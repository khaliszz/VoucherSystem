<?php
session_start();
require_once 'connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

// ✅ Handle add to cart
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

    // ✅ Success message for homepage
    $_SESSION['success_message'] = "Voucher added to cart ✅";
    header("Location: homepage.php");
    exit;

}

// ✅ Handle quantity update
if (isset($_GET['action']) && $_GET['action'] == 'update' && isset($_GET['id']) && isset($_GET['qty'])) {
    $voucherId = intval($_GET['id']);
    $qty = max(1, intval($_GET['qty']));
    $conn->prepare("UPDATE cart_items SET quantity=? WHERE user_id=? AND voucher_id=?")
         ->execute([$qty, $userId, $voucherId]);
    header("Location: cart.php");
    exit;
}

// ✅ Handle remove item
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['id'])) {
    $voucherId = intval($_GET['id']);
    $conn->prepare("DELETE FROM cart_items WHERE user_id=? AND voucher_id=?")
         ->execute([$userId, $voucherId]);
    header("Location: cart.php");
    exit;
}

// ✅ Handle clear cart
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    $conn->prepare("DELETE FROM cart_items WHERE user_id=?")->execute([$userId]);
    header("Location: cart.php");
    exit;
}

// ✅ Fetch cart items
$sql = "
    SELECT c.voucher_id, c.quantity, v.title, v.image, v.points
    FROM cart_items c
    JOIN voucher v ON c.voucher_id = v.voucher_id
    WHERE c.user_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Cart</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background:#f9f9f9;
            margin:0;
            padding:0;
            display:flex;
            justify-content:center;
        }
        .page-wrapper {
            max-width: 1200px;
            width: 100%;
            margin: 50px auto;
            padding: 20px;
        }
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .cart-title {
            font-size: 2rem;  
            font-weight: 700;
            color: #333;
        }
        .back-btn {
            background:#444;
            color:#fff;
            padding:8px 15px;
            border-radius:5px;
            text-decoration:none;
        }
        .divider {
            border-bottom: 2px solid #ccc;
            margin: 0 0 20px;
        }
        .cart-header-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .container {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }
        .cart-items {
            flex: 3;
        }
        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
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
            border-radius: 5px;
            object-fit: cover;
            background:#eee;
        }
        .cart-item .info h4 {
            margin: 0;
            font-size: 16px;
        }
        .cart-item .info p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #666;
        }
        .cart-item-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .quantity {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quantity a {
            border: 1px solid #ccc;
            padding: 5px 10px;
            text-decoration: none;
            color: #333;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .remove {
            border: none;
            background: none;
            color: #000;
            font-size: 18px;
            cursor: pointer;
            text-decoration: none;
        }
        .order-summary {
            flex: 1;
            background:#fff;
            border: 1px solid #ddd;
            padding:20px;
            border-radius: 6px;
            height: fit-content;
            text-align: center;
        }
        .checkout-btn {
            display:inline-block;
            width:auto;
            padding:10px 20px;
            margin-top:15px;
            background:#444;
            color:#fff;
            text-align:center;
            border-radius:4px;
            text-decoration:none;
        }
        .empty-cart {
            text-align: center;
            padding: 50px;
            color: #666;
        }
        .empty-cart img {
            width: 120px;
            opacity: 0.6;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="page-wrapper">

    <div class="cart-header">
        <div class="cart-title">My Cart</div>
        <a href="homepage.php" class="back-btn">Back</a>
    </div>

    <div class="divider"></div>

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
</div>

<script>
    const totalPointsEl = document.getElementById('totalPoints');
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.select-item');

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.select-item:checked').forEach(cb => {
            const item = cb.closest('.cart-item');
            const points = parseInt(item.dataset.points);
            const qty = parseInt(item.dataset.qty);
            total += points * qty;
        });
        totalPointsEl.textContent = total;
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

    restoreChecked();
</script>

</body>
</html>
