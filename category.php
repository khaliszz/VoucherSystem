<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'connection.php';

// Fetch user points
$userId = $_SESSION['user_id'];
$userSql = "SELECT points FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userPoints = $user['points'] ?? 0;

// Handle Add to Cart
$successMsg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['voucher_id'])) {
    $voucherId = intval($_POST['voucher_id']);

    // Check if already in cart
    $checkSql = "SELECT * FROM cart_items WHERE user_id = ? AND voucher_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute([$userId, $voucherId]);

    if ($checkStmt->rowCount() > 0) {
        // Update quantity if already exists
        $conn->prepare("UPDATE cart_items SET quantity = quantity + 1 WHERE user_id = ? AND voucher_id = ?")
             ->execute([$userId, $voucherId]);
    } else {
        // Insert new item
        $conn->prepare("INSERT INTO cart_items (user_id, voucher_id, quantity) VALUES (?, ?, 1)")
             ->execute([$userId, $voucherId]);
    }

    // Success message
    $successMsg = "Voucher added to cart ✅";
}

// All categories for dropdown
$catSql = "SELECT category_id, name FROM category";
$catStmt = $conn->prepare($catSql);
$catStmt->execute();
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Check category ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid category.");
}
$category_id = intval($_GET['id']);

// Fetch category name
$catNameSql = "SELECT name FROM category WHERE category_id = ?";
$catNameStmt = $conn->prepare($catNameSql);
$catNameStmt->execute([$category_id]);
$category = $catNameStmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    die("Category not found.");
}

// Search and Filter Logic (ENHANCED TO MATCH HOMEPAGE)
$searchResults = [];
$pointsResults = [];

$hasSearch = isset($_GET['search']) && !empty(trim($_GET['search']));
$minPoints = $_GET['min_points'] ?? '';
$maxPoints = $_GET['max_points'] ?? '';
$hasPointsFilter = ($minPoints !== '' || $maxPoints !== '');

if ($hasSearch || $hasPointsFilter) {
    $sql = "SELECT * FROM voucher WHERE category_id = :category_id";
    $params = [':category_id' => $category_id];

    // Add search condition
    if ($hasSearch) {
        $sql .= " AND title LIKE :search";
        $params[':search'] = "%" . trim($_GET['search']) . "%";
    }

    // Add points conditions
    if ($hasPointsFilter) {
        if ($minPoints !== '' && is_numeric($minPoints)) {
            $sql .= " AND points >= :min_points";
            $params[':min_points'] = (int)$minPoints;
        }

        if ($maxPoints !== '' && is_numeric($maxPoints)) {
            $sql .= " AND points <= :max_points";
            $params[':max_points'] = (int)$maxPoints;
        }
    }

    $sql .= " ORDER BY points ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Assign results based on whether we have a search term
    if ($hasSearch) {
        $searchResults = $results;
    } else {
        $pointsResults = $results;
    }
} else {
    // Default: Fetch all vouchers for the category
    $sql = "SELECT * FROM voucher WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$category_id]);
    $vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch cart count
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
    <title><?php echo htmlspecialchars($category['name']); ?> Vouchers</title>
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

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            background: var(--background-color);
            color: var(--text-color);
            padding-top: 80px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--white-color);
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(137, 99, 232, 0.3);
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
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
            margin-top: -30px; 
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
            margin-bottom: 15px;
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

        /* Points display styling - MATCHING HOMEPAGE */
        .points-display {
            font-size: 1.1rem;
            font-weight: 700;
            color: #6a5af9;
            background: linear-gradient(135deg, #f0f0ff 0%, #e6e6ff 100%);
            padding: 10px 20px;
            border-radius: 25px;
            margin: 15px 0 20px 0;
            border: 2px solid #6a5af9;
            display: inline-block;
            min-width: 120px;
        }

        .voucher-card a.btn {
            display: inline-block;
            background: var(--button-gradient);
            border: none;
            padding: 12px 20px;
            margin: 3px;
            border-radius: 8px;
            color: var(--white-color);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            text-decoration: none;
            transition: all 0.3s ease;
            min-width: 110px;
        }

        .voucher-card a.btn:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        .voucher-card button {
            display: inline-block;
            background: var(--button-gradient);
            border: none;
            padding: 12px 20px;
            margin: 3px;
            border-radius: 8px;
            color: var(--white-color);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            min-width: 110px;
        }

        .voucher-card button:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        /* Button container for better spacing */
        .button-container {
            margin-top: 10px;
        }

        .image-link {
            display: block;
            text-decoration: none;
        }

        /* Search and Filter Section Styling - MATCHING HOMEPAGE */
        .search-filter-container {
            background: var(--white-color);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin: 20px 0;
        }

        .search-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-bottom: 15px;
        }

        .search-row input, .search-row select {
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }

        .search-row input:focus, .search-row select:focus {
            outline: none;
            border-color: #6a5af9;
        }

        .search-row input[name="search"] {
            flex: 1;
            min-width: 200px;
        }

        .points-filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .points-filter-group label {
            font-weight: 600;
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .points-filter-group input {
            width: 80px;
        }

        .filter-btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            background: var(--button-gradient);
            color: var(--white-color);
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .filter-btn:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        .clear-btn {
            padding: 10px 18px;
            border: 2px solid #6a5af9;
            border-radius: 8px;
            background: transparent;
            color: #6a5af9;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            text-decoration: none;
        }

        .clear-btn:hover {
            background: #6a5af9;
            color: white;
        }

        /* Quick filter buttons - MATCHING HOMEPAGE */
        .quick-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .quick-filter-btn {
            padding: 8px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 20px;
            background: white;
            color: var(--text-secondary-color);
            font-weight: 500;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            font-size: 0.85rem;
            text-decoration: none;
        }

        .quick-filter-btn:hover {
            border-color: #6a5af9;
            color: #6a5af9;
            background: #f8f8ff;
        }

        /* Success message */
        .success-msg {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 12px 15px;
            border-radius: 6px;
            margin: 15px 30px;
            font-size: 0.95rem;
        }

        /* Style for User Points Display - MATCHING HOMEPAGE */
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

        /* Mobile Responsive - MATCHING HOMEPAGE */
        @media (max-width: 768px) {
            nav { gap: 20px; }
            nav a { font-size: 0.9rem; }
            .voucher-grid { justify-content: center; }
            .voucher-card { width: calc(50% - 20px); }
            .search-row { flex-direction: column; align-items: stretch; }
            .points-filter-group { justify-content: center; }
            .quick-filters { justify-content: center; }
        }

        @media (max-width: 500px) {
            .voucher-grid { justify-content: center; }
            .voucher-card { width: 100%; }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- User Points Display -->
    <div class="user-points">
        Your Points: <?php echo htmlspecialchars($userPoints); ?>
    </div>

    <!-- Success Message -->
    <?php if (!empty($successMsg)): ?>
        <div class="success-msg"><?php echo $successMsg; ?></div>
    <?php endif; ?>

    <main>
        <h1><?php echo htmlspecialchars($category['name']); ?> Vouchers</h1>

        <!-- Enhanced Search and Filter Section - MATCHING HOMEPAGE -->
        <div class="search-filter-container">
            <form method="get" action="">
                <input type="hidden" name="id" value="<?php echo $category_id; ?>">
                <div class="search-row">
                    <input type="text" name="search" placeholder="Search vouchers..."
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    
                    <div class="points-filter-group">
                        <label>Points:</label>
                        <input type="number" name="min_points" placeholder="Min"
                            value="<?php echo isset($_GET['min_points']) ? htmlspecialchars($_GET['min_points']) : ''; ?>">
                        <span>-</span>
                        <input type="number" name="max_points" placeholder="Max"
                            value="<?php echo isset($_GET['max_points']) ? htmlspecialchars($_GET['max_points']) : ''; ?>">
                    </div>
                    
                    <button type="submit" class="filter-btn">Search & Filter</button>
                    <a href="category.php?id=<?php echo $category_id; ?>" class="clear-btn">Clear All</a>
                </div>
            </form>
            
            <!-- Quick Filter Buttons - MATCHING HOMEPAGE -->
            <div class="quick-filters">
                <a href="category.php?id=<?php echo $category_id; ?>&min_points=500&max_points=1000" class="quick-filter-btn">500-1000 Points</a>
                <a href="category.php?id=<?php echo $category_id; ?>&min_points=1001&max_points=2000" class="quick-filter-btn">1001-2000 Points</a>
                <a href="category.php?id=<?php echo $category_id; ?>&min_points=20011&max_points=3000" class="quick-filter-btn">2001-3000 Points</a>
                <a href="category.php?id=<?php echo $category_id; ?>&min_points=3001&max_points=4000" class="quick-filter-btn">3001-4000 Points</a>
                <a href="category.php?id=<?php echo $category_id; ?>&min_points=4001" class="quick-filter-btn">4000+ Points</a>
            </div>
        </div>

        <!-- Search Results Section - MATCHING HOMEPAGE -->
        <?php if ($hasSearch): ?>
            <h2>Search Results 
                <?php if ($hasPointsFilter): ?>
                    <small style="color: #666; font-weight: normal;">
                        (<?php 
                        echo isset($_GET['min_points']) && $_GET['min_points'] !== '' ? $_GET['min_points'] : '0';
                        echo '-';
                        echo isset($_GET['max_points']) && $_GET['max_points'] !== '' ? $_GET['max_points'] : '∞';
                        ?> Points)
                    </small>
                <?php endif; ?>
            </h2>
            <div class="voucher-grid">
                <?php if (!empty($searchResults)): ?>
                    <?php foreach ($searchResults as $voucher): ?>
                        <div class="voucher-card">
                            <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                                <img src="<?php echo htmlspecialchars($voucher['image']); ?>"
                                    alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                            </a>
                            <p><?php echo htmlspecialchars($voucher['title']); ?></p>
                            <div class="points-display"><?php echo htmlspecialchars($voucher['points']); ?> Points</div>
                            <div class="button-container">
                                <form action="redeem.php" method="post" style="display:inline;">
                                    <input type="hidden" name="voucher_id" value="<?php echo $voucher['voucher_id']; ?>">
                                    <button type="submit">REDEEM NOW</button>
                                </form>
                                <form method="post" action="category.php?id=<?php echo $category_id; ?>" style="display:inline;">
                                    <input type="hidden" name="voucher_id" value="<?php echo $voucher['voucher_id']; ?>">
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                                    <input type="hidden" name="min_points" value="<?php echo htmlspecialchars($_GET['min_points'] ?? ''); ?>">
                                    <input type="hidden" name="max_points" value="<?php echo htmlspecialchars($_GET['max_points'] ?? ''); ?>">
                                    <button type="submit">ADD TO CART</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No vouchers match your search criteria.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Points Filter Results Section - MATCHING HOMEPAGE -->
        <?php if (!$hasSearch && !empty($pointsResults)): ?>
            <h2>Filtered by Points 
                <small style="color: #666; font-weight: normal;">
                    (<?php 
                    echo isset($_GET['min_points']) && $_GET['min_points'] !== '' ? $_GET['min_points'] : '0';
                    echo '-';
                    echo isset($_GET['max_points']) && $_GET['max_points'] !== '' ? $_GET['max_points'] : '∞';
                    ?> Points)
                </small>
            </h2>
            <div class="voucher-grid">
                <?php foreach ($pointsResults as $voucher): ?>
                    <div class="voucher-card">
                        <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                            <img src="<?php echo htmlspecialchars($voucher['image']); ?>"
                                alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                        </a>
                        <p><?php echo htmlspecialchars($voucher['title']); ?></p>
                        <div class="points-display"><?php echo htmlspecialchars($voucher['points']); ?> Points</div>
                        <div class="button-container">
                            <form action="redeem.php" method="post" style="display:inline;">
                                <input type="hidden" name="voucher_id" value="<?php echo $voucher['voucher_id']; ?>">
                                <button type="submit">REDEEM NOW</button>
                            </form>
                            <form method="post" action="category.php?id=<?php echo $category_id; ?>" style="display:inline;">
                                <input type="hidden" name="voucher_id" value="<?php echo $voucher['voucher_id']; ?>">
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                                <input type="hidden" name="min_points" value="<?php echo htmlspecialchars($_GET['min_points'] ?? ''); ?>">
                                <input type="hidden" name="max_points" value="<?php echo htmlspecialchars($_GET['max_points'] ?? ''); ?>">
                                <button type="submit">ADD TO CART</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Default Category Vouchers Section -->
        <?php if (!$hasSearch && !$hasPointsFilter): ?>
            <div class="voucher-grid">
                <?php if (!empty($vouchers)): ?>
                    <?php foreach ($vouchers as $voucher): ?>
                        <div class="voucher-card">
                            <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                                <img src="<?php echo htmlspecialchars($voucher['image']); ?>"
                                    alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                            </a>
                            <p><?php echo htmlspecialchars($voucher['title']); ?></p>
                            <div class="points-display"><?php echo htmlspecialchars($voucher['points']); ?> Points</div>
                            <div class="button-container">
                                <form action="redeem.php" method="post" style="display:inline;">
                                    <input type="hidden" name="voucher_id" value="<?php echo $voucher['voucher_id']; ?>">
                                    <button type="submit">REDEEM NOW</button>
                                </form>
                                <form method="post" action="category.php?id=<?php echo $category_id; ?>" style="display:inline;">
                                    <input type="hidden" name="voucher_id" value="<?php echo $voucher['voucher_id']; ?>">
                                    <button type="submit">ADD TO CART</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No vouchers found in this category.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>