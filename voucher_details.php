<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'connection.php';

// Check if voucher_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid voucher.");
}

$voucher_id = intval($_GET['id']);

// Fetch user points
$userId = $_SESSION['user_id'];
$userSql = "SELECT points FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userPoints = $user['points'] ?? 0;

// Fetch voucher details
$sql = "SELECT * FROM voucher WHERE voucher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$voucher_id]);
$voucher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voucher) {
    die("Voucher not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Details</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --button-gradient: linear-gradient(90deg, #8963e8 0%, #6352e7 100%);
            --button-hover-gradient: linear-gradient(90deg, #9a7af0 0%, #7665f1 100%);
            --text-color: #333;
            --text-secondary-color: #777;
            --border-color: #e0e0e0;
            --background-color: #f4f7fc;
            --white-color: #ffffff;
            --success-gradient: linear-gradient(90deg, #28a745 0%, #218838 100%);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background: var(--background-color);
            color: var(--text-color);
            min-height: 100vh;
            padding-top: 100px;
        }

        /* Header - Full Width */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--white-color);
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(137, 99, 232, 0.3);
            width: 100%;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        nav {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        nav a {
            text-decoration: none;
            color: var(--text-color);
            font-weight: 600;
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        nav a:hover {
            color: #6a5af9;
        }

        /* Dropdown */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background-color: var(--white-color);
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 8px;
            z-index: 1;
        }

        .dropdown-content a {
            color: var(--text-color);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background 0.2s ease;
        }

        .dropdown-content a:hover {
            background: #f1f1f1;
            color: #6a5af9;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .profile-btn {
            display: inline-block;
            border-radius: 50%;
            overflow: hidden;
            width: 45px;
            height: 45px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .profile-btn:hover {
            transform: scale(1.05);
        }

        .profile-btn .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--white-color);
            transition: border-color 0.3s ease;
        }

        .profile-btn:hover .profile-img {
            border-color: #6a5af9;
        }

        /* Main Content */
        main {
            padding: 40px 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-color);
            margin: 0;
        }

        .back-btn {
            background: var(--text-secondary-color);
            color: var(--white-color);
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: var(--text-color);
            transform: translateY(-2px);
        }

        /* Voucher Container */
        .voucher-container {
            background: var(--white-color);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            display: flex;
            gap: 40px;
            align-items: flex-start;
        }

        .voucher-left {
            flex-shrink: 0;
        }

        .voucher-left img {
            width: 350px;
            height: 250px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .voucher-left img:hover {
            transform: scale(1.02);
        }

        .voucher-right {
            flex: 1;
        }

        .voucher-right h2 {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-color);
            margin: 0 0 20px 0;
        }

        .points-display {
            font-size: 1.5rem;
            font-weight: 700;
            color: #6a5af9;
            margin-bottom: 25px;
        }

        /* Action Controls */
        .voucher-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .quantity-button {
            background: #f8f9fa;
            border: none;
            padding: 12px 16px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: background 0.2s ease;
            min-width: 45px;
        }

        .quantity-button:hover {
            background: #e9ecef;
        }

        .quantity-input {
            width: 60px;
            text-align: center;
            border: none;
            font-size: 1.1rem;
            font-weight: 600;
            padding: 12px 8px;
            background: var(--white-color);
        }

        .action-button {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            min-width: 140px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        /* Make both buttons rectangular and purple */
        .redeem-btn,
        .cart-btn {
            background: var(--button-gradient);
            color: var(--white-color);
        }

        .redeem-btn:hover,
        .cart-btn:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        /* Terms and Conditions */
        .terms-section {
            margin-top: 30px;
        }

        .terms-section h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 10px;
        }

        .terms-textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            resize: vertical;
            min-height: 120px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            line-height: 1.6;
            background: #f8f9fa;
            color: var(--text-color);
            transition: border-color 0.3s ease;
        }

        .terms-textarea:focus {
            outline: none;
            border-color: #6a5af9;
        }

        /* Warning message styling */
        .warning-message {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 12px 20px;
            margin: 15px 0;
            border-radius: 8px;
            font-weight: 600;
            position: fixed;
            top: 120px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-width: 300px;
            max-width: 80%;
            display: none;
        }

        .warning-message .close-btn {
            background: none;
            border: none;
            color: #856404;
            font-size: 1.2rem;
            cursor: pointer;
            font-weight: bold;
            margin-left: 15px;
        }

        /* Popup Modal Styles */
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }

        .popup-modal {
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            animation: modalOpen 0.3s ease;
        }

        @keyframes modalOpen {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .popup-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #777;
            background: none;
            border: none;
        }

        .popup-close:hover {
            color: #333;
        }

        .popup-icon {
            width: 70px;
            height: 70px;
            background: var(--success-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .popup-icon::after {
            content: "✓";
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
        }

        .popup-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }

        .popup-description {
            font-size: 1.1rem;
            color: #777;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .popup-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .popup-btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            min-width: 120px;
            text-align: center;
        }

        .add-more-btn {
            background: #6c757d;
            color: white;
        }

        .add-more-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .view-cart-btn {
            background: var(--button-gradient);
            color: white;
        }

        .view-cart-btn:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            header {
                padding: 12px 20px;
            }

            nav {
                gap: 20px;
            }

            nav a {
                font-size: 0.9rem;
            }

            main {
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .voucher-container {
                flex-direction: column;
                padding: 25px;
                gap: 25px;
            }

            .voucher-left img {
                width: 100%;
                max-width: 350px;
            }

            .voucher-actions {
                justify-content: center;
            }

            .action-button {
                min-width: 120px;
            }

            .warning-message {
                top: 120px;
                width: 90%;
                min-width: unset;
            }
        }

        @media (max-width: 480px) {
            .voucher-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .quantity-selector {
                align-self: center;
            }

            .action-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Popup Modal -->
    <div id="cartPopup" class="popup-overlay">
        <div class="popup-modal">
            <button class="popup-close" id="closePopup">&times;</button>
            <div class="popup-icon"></div>
            <h2 class="popup-title">Successfully added to cart</h2>
            <p class="popup-description">Your selected voucher has been successfully added to your shopping cart.</p>
            <div class="popup-buttons">
                <button class="popup-btn add-more-btn" id="addMoreBtn">Add more</button>
                <button class="popup-btn view-cart-btn" id="viewCartBtn">View Cart</button>
            </div>
        </div>
    </div>

    <!-- Warning message container -->
    <div id="warningMessage" class="warning-message">
        <span id="warningText"></span>
        <button class="close-btn" onclick="document.getElementById('warningMessage').style.display='none'">&times;</button>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div style="
            background:#d4edda;
            color:#155724;
            border:1px solid #c3e6cb;
            padding:12px 20px;
            margin:15px 30px;
            border-radius:8px;
            font-weight:600;
            position:relative;
        ">
            <?= htmlspecialchars($_SESSION['success_message']); ?>
            <span onclick="this.parentElement.style.display='none'"
                style="position:absolute;top:8px;right:12px;cursor:pointer;font-weight:bold;">&times;</span>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <main>
        <div class="page-header">
            <h1>Voucher Details</h1>
            <a href="javascript:history.back()" class="back-btn">
                ← Back
            </a>
        </div>

        <div class="voucher-container">
            <div class="voucher-left">
                <img src="<?php echo htmlspecialchars($voucher['image'] ?? ''); ?>"
                     alt="<?php echo htmlspecialchars($voucher['title'] ?? 'Voucher Image'); ?>">
            </div>

            <div class="voucher-right">
                <h2><?php echo htmlspecialchars($voucher['title'] ?? 'Voucher Title'); ?></h2>

                <div class="points-display">
                    <?php echo htmlspecialchars($voucher['points'] ?? '0'); ?> pts
                </div>

                <div class="voucher-actions">
                    <div class="quantity-selector">
                        <button class="quantity-button" onclick="decreaseQuantity()">-</button>
                        <input type="number" class="quantity-input" value="1" min="1" max="10" id="quantity">
                        <button class="quantity-button" onclick="increaseQuantity()">+</button>
                    </div>

                    <a href="process_redeem.php?id=<?php echo $voucher['voucher_id']; ?>" class="action-button redeem-btn"
                       data-points="<?php echo $voucher['points']; ?>"
                       data-title="<?php echo htmlspecialchars($voucher['title']); ?>">
                        REDEEM NOW
                    </a>

                    <a href="#" class="action-button cart-btn" id="addToCartBtn">
                        ADD TO CART
                    </a>
                </div>

                <div class="terms-section">
                    <h3>Terms and Conditions</h3>
                    <textarea class="terms-textarea" readonly><?php echo htmlspecialchars($voucher['terms_and_condition'] ?? 'No terms and conditions specified.'); ?></textarea>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const userPoints = <?php echo $userPoints; ?>;

            // Handle redeem button with points validation
            const redeemButton = document.querySelector('.redeem-btn');
            if (redeemButton) {
                redeemButton.addEventListener('click', function(e) {
                    const voucherPoints = parseInt(this.getAttribute('data-points'));
                    const voucherTitle = this.getAttribute('data-title');

                    if (userPoints < voucherPoints) {
                        e.preventDefault(); // Prevent navigation to redeem page

                        // Show warning message
                        const warningMessage = document.getElementById('warningMessage');
                        const warningText = document.getElementById('warningText');

                        warningText.textContent = `You don't have enough points to redeem "${voucherTitle}". You need ${voucherPoints} points but only have ${userPoints}.`;
                        warningMessage.style.display = 'flex';

                        // Auto-hide after 5 seconds
                        setTimeout(() => {
                            warningMessage.style.display = 'none';
                        }, 5000);
                    }
                });
            }

            // Handle Add to Cart button with popup
            const addToCartButton = document.querySelector('.cart-btn');
            const cartPopup = document.getElementById('cartPopup');
            const closePopup = document.getElementById('closePopup');
            const addMoreBtn = document.getElementById('addMoreBtn');
            const viewCartBtn = document.getElementById('viewCartBtn');

            if (addToCartButton) {
                addToCartButton.addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent default link behavior

                    // Get the voucher ID and quantity from the form
                    const voucherId = <?php echo $voucher['voucher_id']; ?>;
                    const quantity = document.getElementById('quantity').value;

                    // Make AJAX request to add item to cart with quantity
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', `cart.php?action=add&id=${voucherId}&quantity=${quantity}`, true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    // Show the popup
                                    cartPopup.style.display = 'flex';

                                    // Update cart count in navbar
                                    const cartBadge = document.querySelector('.cart-badge');
                                    if (cartBadge) {
                                        if (response.cartCount > 0) {
                                            cartBadge.textContent = response.cartCount > 99 ? '99+' : response.cartCount;
                                            cartBadge.style.display = 'flex';
                                        } else {
                                            cartBadge.style.display = 'none';
                                        }
                                    }
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                            }
                        }
                    };
                    xhr.send();
                });
            }

            // Popup event handlers
            if (closePopup) {
                closePopup.addEventListener('click', function() {
                    cartPopup.style.display = 'none';
                });
            }

            if (addMoreBtn) {
                addMoreBtn.addEventListener('click', function() {
                    cartPopup.style.display = 'none';
                });
            }

            if (viewCartBtn) {
                viewCartBtn.addEventListener('click', function() {
                    window.location.href = 'cart.php';
                });
            }

            // Close popup when clicking outside the modal
            window.addEventListener('click', function(event) {
                if (event.target === cartPopup) {
                    cartPopup.style.display = 'none';
                }
            });
        });

        function increaseQuantity() {
            const input = document.getElementById('quantity');
            const currentValue = parseInt(input.value);
            const maxValue = parseInt(input.max);
            if (currentValue < maxValue) {
                input.value = currentValue + 1;
            }
        }

        function decreaseQuantity() {
            const input = document.getElementById('quantity');
            const currentValue = parseInt(input.value);
            const minValue = parseInt(input.min);
            if (currentValue > minValue) {
                input.value = currentValue - 1;
            }
        }
    </script>
</body>
</html>