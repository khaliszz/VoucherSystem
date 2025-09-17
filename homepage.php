<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'connection.php';

// ✅ Fetch user points
$userId = $_SESSION['user_id'];
$userSql = "SELECT points FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userPoints = $user['points'] ?? 0; // Default to 0 if no points

// ✅ Fetch top 5 vouchers based on total quantity redeemed
$sql = "
    SELECT v.voucher_id, v.title, v.image, SUM(c.quantity) as total_quantity
    FROM cart_item_history c
    JOIN voucher v ON c.voucher_id = v.voucher_id
    GROUP BY v.voucher_id, v.title, v.image
    ORDER BY total_quantity DESC
    LIMIT 5
";
$stmt = $conn->prepare($sql);
$stmt->execute();
$topVouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Fetch categories for dropdown
$catSql = "SELECT category_id, name FROM category";
$catStmt = $conn->prepare($catSql);
$catStmt->execute();
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Handle search
$searchResults = [];
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $searchSql = "SELECT voucher_id, title, image FROM voucher WHERE title LIKE :search";
    $searchStmt = $conn->prepare($searchSql);
    $searchStmt->bindParam(':search', $search, PDO::PARAM_STR);
    $searchStmt->execute();
    $searchResults = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ✅ Fetch cart count
$cartCount = 0;
$cartSql = "SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?";
$cartStmt = $conn->prepare($cartSql);
$cartStmt->execute([$userId]);
$cartRow = $cartStmt->fetch(PDO::FETCH_ASSOC);
$cartCount = $cartRow['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Page</title>
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
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--white-color);
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(137, 99, 232, 0.3);
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
            transition: opacity 0.3s ease;
        }

        nav a:hover {
            color: #6a5af9;
        }

        /* ✅ Cart badge */
        .cart-btn {
            position: relative;
            display: inline-block;
        }
        .cart-badge {
            position: absolute;
            top: -6px;
            right: -10px;
            background: red;
            color: #fff;
            font-size: 12px;
            padding: 2px 6px;
            border-radius: 50%;
            font-weight: bold;
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

        main {
            padding: 40px 30px;
        }

        main h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 1rem;
        }

        main h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--text-color);
            margin: 2rem 0 1rem;
        }

        /* Voucher Grid Style Update */
        .voucher-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 25px;
            margin-top: 20px;
        }

        .voucher-card {
            background: var(--white-color);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: calc(20% - 20px);
            margin-bottom:15px;
        }

        .voucher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }

        .voucher-card img {
            width: 100%;
            height: 150px;
            background: #eee;
            margin-bottom: 15px;
            object-fit: cover;
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .voucher-card img:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        .voucher-card p {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .voucher-card small {
            font-size: 0.9rem;
            color: var(--text-secondary-color);
            display: block;
            margin-bottom: 20px;
        }

        .voucher-card a.btn {
            display:inline-block;
            background: var(--button-gradient);
            border:none;
            padding: 12px 20px;
            margin: 5px;
            border-radius: 8px;
            color: var(--white-color);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            text-decoration:none;
            transition: all 0.3s ease;
            min-width: 110px;
        }

        .voucher-card a.btn:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        /* Make image clickable to voucher details */
        .image-link {
            display: block;
            text-decoration: none;
        }

        /* Mobile */
        @media (max-width: 768px) {
            nav { gap: 20px; }
            nav a { font-size: 0.9rem; }
            .voucher-grid { justify-content: center; }
            .voucher-card { width: calc(50% - 20px); }
        }

        @media (max-width: 500px) {
           .voucher-grid { justify-content: center; }
           .voucher-card { width: 100%; }
        }

        /* Style for User Points Display */
        .user-points {
            background: var(--white-color);
            padding: 10px 20px;
            border-radius: 8px;
            margin: 15px 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-color);
            text-align: right;
        }

       main {
            padding: 40px 30px;
            margin-top: -30px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- ✅ Cart badge inside navbar.php -->
    <script>
        // Optional: JS could go here if you want AJAX update later
    </script>

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

    <!-- User Points Display -->
    <div class="user-points">
        Your Points: <?php echo htmlspecialchars($userPoints); ?>
    </div>

    <main>
        <h1>Home Page</h1>

        <!-- 🔍 Search Bar -->
        <form method="get" action="" style="margin: 20px 0; text-align:left;">
            <input type="text" name="search" placeholder="Search voucher..."
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                style="padding:10px; width:280px; border-radius:8px; border:1px solid #ccc;">
            <button type="submit"
                style="padding:10px 18px; border:none; border-radius:8px;
                    background: var(--button-gradient); color:#fff; font-weight:600; cursor:pointer;">
                Search
            </button>
        </form>

        <!-- Search Results Section -->
        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
            <h2>Search Results</h2>
            <div class="voucher-grid">
                <?php if (!empty($searchResults)): ?>
                    <?php foreach ($searchResults as $voucher): ?>
                        <div class="voucher-card">
                            <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                                <img src="<?php echo htmlspecialchars($voucher['image']); ?>"
                                    alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                            </a>
                            <p><?php echo htmlspecialchars($voucher['title']); ?></p>
                            <a href="redeem.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn">REDEEM NOW</a>
                            <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No vouchers match your search.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Top Pick Voucher Section -->
        <h2>Top Pick Voucher</h2>
        <div class="voucher-grid">
            <?php if (!empty($topVouchers)): ?>
                <?php foreach ($topVouchers as $voucher): ?>
                    <div class="voucher-card">
                        <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                            <img src="<?php echo htmlspecialchars($voucher['image']); ?>"
                                alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                        </a>
                        <p><?php echo htmlspecialchars($voucher['title']); ?></p>
                        <small>Total Redeemed: <?php echo $voucher['total_quantity']; ?></small>
                        <a href="redeem.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn">REDEEM NOW</a>
                        <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No vouchers found.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
