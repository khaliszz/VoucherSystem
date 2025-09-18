<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'connection.php';

// âœ… Fetch user points
$userId = $_SESSION['user_id'];
$userSql = "SELECT points FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
$userPoints = $user['points'] ?? 0; // Default to 0 if no points

// âœ… Fetch top 5 vouchers based on total quantity redeemed
$sql = "
    SELECT v.voucher_id, v.title, v.image, v.points, SUM(c.quantity) as total_quantity
    FROM cart_item_history c
    JOIN voucher v ON c.voucher_id = v.voucher_id
    GROUP BY v.voucher_id, v.title, v.image, v.points
    ORDER BY total_quantity DESC
    LIMIT 5
";
$stmt = $conn->prepare($sql);
$stmt->execute();
$topVouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// âœ… Fetch categories
$catSql = "SELECT category_id, name FROM category";
$catStmt = $conn->prepare($catSql);
$catStmt->execute();
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Create mapping of lowercase name â†’ ID
$categoryMap = [];
foreach ($categories as $cat) {
    $categoryMap[strtolower($cat['name'])] = $cat['category_id'];
}

// âœ… Handle category filter
$categoryResults = null; // <-- set to null by default (no category selected)

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $catKey = strtolower($_GET['category']);
    if (isset($categoryMap[$catKey])) {
        $selectedCategoryId = $categoryMap[$catKey];

        $voucherSql = "
            SELECT voucher_id, title, image, points, description
            FROM voucher
            WHERE category_id = ?
            ORDER BY voucher_id DESC
        ";
        $voucherStmt = $conn->prepare($voucherSql);
        $voucherStmt->execute([$selectedCategoryId]);
        $categoryResults = $voucherStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $categoryResults = []; // no valid category found
    }
}

// âœ… Handle search with points filter (FIXED)
$searchResults = [];
$pointsResults = [];

// Check if we have search term or points filter
$hasSearch = isset($_GET['search']) && !empty(trim($_GET['search']));

// CORRECTED LOGIC HERE
$minPoints = $_GET['min_points'] ?? '';
$maxPoints = $_GET['max_points'] ?? '';

$hasPointsFilter = ($minPoints !== '' || $maxPoints !== '');

if ($hasSearch || $hasPointsFilter) {
    $sql = "SELECT voucher_id, title, image, points FROM voucher WHERE 1=1";
    $params = [];

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
}

// âœ… Fetch promotions
$promoSql = "SELECT promote_id, title, image, descriptions FROM promotion";
$promoStmt = $conn->prepare($promoSql);
$promoStmt->execute();
$promotions = $promoStmt->fetchAll(PDO::FETCH_ASSOC);

// âœ… Fetch cart count
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
    padding-top: 70px; /* Adjust this value based on your header's height */
}

      header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--white-color);
    padding: 15px 30px;
    box-shadow: 0 2px 10px rgba(137, 99, 232, 0.3);
    position: fixed; /* Add this */
    top: 0;         /* Add this */
    left: 0;        /* Add this */
    width: 100%;      /* Add this */
    z-index: 1000;  /* Add this */
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

        /* âœ… Cart badge */
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
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
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

        /* Promotion Slider */
        .promo-slider {
            width: 1000px;
            height: 250px;
            margin: 30px auto;
            position: relative;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            background: #fff;
        }

        .slides {
            display: flex;
            transition: transform 0.6s ease-in-out;
            width: 100%;
            height: 100%;
        }

        .slide {
            min-width: 100%;
            height: 100%;
            box-sizing: border-box;
            position: relative;
        }

        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            border-radius: 12px;
        }

        /* Arrows */
        .promo-slider .prev,
        .promo-slider .next {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 0, 0, 0.5);
            color: #fff;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 50%;
            font-size: 18px;
            transition: background 0.3s;
            z-index: 10;
        }

        .promo-slider .prev:hover,
        .promo-slider .next:hover {
            background: rgba(0, 0, 0, 0.8);
        }

        .promo-slider .prev {
            left: 15px;
        }

        .promo-slider .next {
            right: 15px;
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
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            width: calc(20% - 20px);
            margin-bottom: 15px;
        }

        .voucher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
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
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
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

        /* Points display styling */
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

        /* Button container for better spacing */
        .button-container {
            margin-top: 10px;
        }

        /* Make image clickable to voucher details */
        .image-link {
            display: block;
            text-decoration: none;
        }

        /* Search and Filter Section Styling */
        .search-filter-container {
            background: var(--white-color);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin: 20px 0;
        }

        .search-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-bottom: 15px;
        }

        .search-row input,
        .search-row select {
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }

        .search-row input:focus,
        .search-row select:focus {
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

        /* Quick filter buttons */
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

        /* Mobile */
        @media (max-width: 768px) {
            nav {
                gap: 20px;
            }

            nav a {
                font-size: 0.9rem;
            }

            .voucher-grid {
                justify-content: center;
            }

            .voucher-card {
                width: calc(50% - 20px);
            }

            .search-row {
                flex-direction: column;
                align-items: stretch;
            }

            .points-filter-group {
                justify-content: center;
            }

            .quick-filters {
                justify-content: center;
            }
        }

        @media (max-width: 500px) {
            .voucher-grid {
                justify-content: center;
            }

            .voucher-card {
                width: 100%;
            }
        }

        /* Style for User Points Display */
        .user-points {
            background: var(--white-color);
            padding: 10px 20px;
            border-radius: 8px;
            margin: 15px 30px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
            <span onclick="this.parentElement.style.display='none'" style="position:absolute;top:8px;right:12px;cursor:pointer;font-weight:bold;">&times;</span>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- User Points Display -->
    <div class="user-points">
        Your Points:
        <?php echo htmlspecialchars($userPoints); ?>
    </div>

    <!-- Promotion Slider -->
    <?php if (!empty($promotions)): ?>
        <div class="promo-slider">
            <div class="slides">
                <?php foreach ($promotions as $promo): ?>
                    <div class="slide">
                        <img src="<?php echo htmlspecialchars($promo['image']); ?>" alt="<?php echo htmlspecialchars($promo['title']); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="prev">&#10094;</button>
            <button class="next">&#10095;</button>
        </div>
    <?php endif; ?>

    <main>
        <h1>Home Page</h1>

        <!-- Category Buttons -->
        <form method="get" action="" style="margin: 20px 0; text-align:left;">
            <button type="submit" name="category" value="fashion" style="padding:10px 15px; margin:5px; border:none; border-radius:20px;
                    background: var(--button-gradient); color:#fff; font-weight:600; cursor:pointer;">
                Fashion
            </button>
            <button type="submit" name="category" value="food and beverage" style="padding:10px 15px; margin:5px; border:none; border-radius:20px;
                    background: var(--button-gradient); color:#fff; font-weight:600; cursor:pointer;">
                Food & Beverage
            </button>
            <button type="submit" name="category" value="travel" style="padding:10px 15px; margin:5px; border:none; border-radius:20px;
                    background: var(--button-gradient); color:#fff; font-weight:600; cursor:pointer;">
                Travel
            </button>
            <button type="submit" name="category" value="sports" style="padding:10px 15px; margin:5px; border:none; border-radius:20px;
                    background: var(--button-gradient); color:#fff; font-weight:600; cursor:pointer;">
                Sports
            </button>
        </form>

        <!-- Enhanced Search and Filter Section -->
        <div class="search-filter-container">
            <form method="get" action="">
                <div class="search-row">
                    <input type="text" name="search" placeholder="Search vouchers..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

                    <div class="points-filter-group">
                        <label>Points:</label>
                        <input type="number" name="min_points" placeholder="Min" value="<?php echo isset($_GET['min_points']) ? htmlspecialchars($_GET['min_points']) : ''; ?>">
                        <span>-</span>
                        <input type="number" name="max_points" placeholder="Max" value="<?php echo isset($_GET['max_points']) ? htmlspecialchars($_GET['max_points']) : ''; ?>">
                    </div>

                    <button type="submit" class="filter-btn">Search & Filter</button>
                    <a href="?" class="clear-btn">Clear All</a>
                </div>
            </form>

            <!-- Quick Filter Buttons -->
            <div class="quick-filters">
                <a href="?min_points=500&max_points=1000" class="quick-filter-btn">500-1000 Points</a>
                <a href="?min_points=1001&max_points=2000" class="quick-filter-btn">1001-2000 Points</a>
                <a href="?min_points=2001&max_points=3000" class="quick-filter-btn">2001-3000 Points</a>
                <a href="?min_points=3001&max_points=4000" class="quick-filter-btn">3001-4000 Points</a>
                <a href="?min_points=4001" class="quick-filter-btn">4000+ Points</a>
            </div>
        </div>

        <!-- Search Results Section (UPDATED) -->
        <?php if ($hasSearch): ?>
            <h2>Search Results
                <?php if ($hasPointsFilter): ?>
                    <small style="color: #666; font-weight: normal;">
                        (
                        <?php
                        echo isset($_GET['min_points']) && $_GET['min_points'] !== '' ? $_GET['min_points'] : '0';
                        echo '-';
                        echo isset($_GET['max_points']) && $_GET['max_points'] !== '' ? $_GET['max_points'] : 'âˆž';
                        ?> Points)
                    </small>
                <?php endif; ?>
            </h2>
            <div class="voucher-grid">
                <?php if (!empty($searchResults)): ?>
                    <?php foreach ($searchResults as $voucher): ?>
                        <div class="voucher-card">
                            <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                                <img src="<?php echo htmlspecialchars($voucher['image']); ?>" alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                            </a>
                            <p>
                                <?php echo htmlspecialchars($voucher['title']); ?>
                            </p>
                            <div class="points-display">
                                <?php echo htmlspecialchars($voucher['points']); ?> Points
                            </div>
                            <div class="button-container">
                                <a href="redeem.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn">REDEEM NOW</a>
                                <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No vouchers match your search criteria.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Points Filter Results Section -->
        <?php if (!$hasSearch && $hasPointsFilter): ?>
            <h2>Filtered by Points</h2>
            <div class="voucher-grid">
                <?php foreach ($pointsResults as $voucher): ?>
                    <div class="voucher-card">
                        <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                            <img src="<?php echo htmlspecialchars($voucher['image']); ?>" alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                        </a>
                        <p>
                            <?php echo htmlspecialchars($voucher['title']); ?>
                        </p>
                        <div class="points-display">
                            <?php echo htmlspecialchars($voucher['points']); ?> Points
                        </div>
                        <div class="button-container">
                            <a href="redeem.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn">REDEEM NOW</a>
                            <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Category Results Section -->
        <?php if ($categoryResults !== null): ?>
            <h2>
                <?php echo ucfirst($_GET['category']); ?> Vouchers
            </h2>
            <div class="voucher-grid">
                <?php if (!empty($categoryResults)): ?>
                    <?php foreach ($categoryResults as $voucher): ?>
                        <div class="voucher-card">
                            <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                                <img src="<?php echo htmlspecialchars($voucher['image']); ?>" alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                            </a>
                            <p>
                                <?php echo htmlspecialchars($voucher['title']); ?>
                            </p>
                            <div class="points-display">
                                <?php echo htmlspecialchars($voucher['points']); ?> Points
                            </div>
                            <div class="button-container">
                                <a href="redeem.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn">REDEEM NOW</a>
                                <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No vouchers found in this category.</p>
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
                            <img src="<?php echo htmlspecialchars($voucher['image']); ?>" alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                        </a>
                        <p>
                            <?php echo htmlspecialchars($voucher['title']); ?>
                        </p>
                        <div class="points-display">
                            <?php echo htmlspecialchars($voucher['points']); ?> Points
                        </div>
                        <small>Total Redeemed:
                            <?php echo $voucher['total_quantity']; ?>
                        </small>
                        <a href="redeem.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn">REDEEM NOW</a>
                        <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No vouchers found.</p>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const slides = document.querySelector(".slides");
            const slideItems = document.querySelectorAll(".slide");
            const prevBtn = document.querySelector(".prev");
            const nextBtn = document.querySelector(".next");

            let currentIndex = 0;
            const totalSlides = slideItems.length;

            function showSlide(index) {
                slides.style.transform = `translateX(-${index * 100}%)`;
            }

            if (prevBtn && nextBtn && totalSlides > 0) {
                prevBtn.addEventListener("click", function () {
                    currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
                    showSlide(currentIndex);
                });

                nextBtn.addEventListener("click", function () {
                    currentIndex = (currentIndex + 1) % totalSlides;
                    showSlide(currentIndex);
                });

                // Optional: auto-slide every 5s
                setInterval(function () {
                    currentIndex = (currentIndex + 1) % totalSlides;
                    showSlide(currentIndex);
                }, 5000);
            }
        });
    </script>
    <?php include 'footer.php'; ?>

</body>
</html>