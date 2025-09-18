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

// ✅ Fetch categories
$catSql = "SELECT category_id, name FROM category";
$catStmt = $conn->prepare($catSql);
$catStmt->execute();
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Create mapping of lowercase name → ID
$categoryMap = [];
foreach ($categories as $cat) {
    $categoryMap[strtolower($cat['name'])] = $cat['category_id'];
}

// ✅ Handle category filter with search and points filter support
$categoryResults = null;
$searchResults = [];
$pointsResults = [];
$selectedCategoryId = null;

// Check if we have a category selected
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $catKey = strtolower($_GET['category']);
    if (isset($categoryMap[$catKey])) {
        $selectedCategoryId = $categoryMap[$catKey];
    }
}

// Check if we have search term or points filter
$hasSearch = isset($_GET['search']) && !empty(trim($_GET['search']));
$minPoints = $_GET['min_points'] ?? '';
$maxPoints = $_GET['max_points'] ?? '';
$hasPointsFilter = ($minPoints !== '' || $maxPoints !== '');

if ($hasSearch || $hasPointsFilter || $selectedCategoryId) {
    $sql = "SELECT voucher_id, title, image, points, description FROM voucher WHERE 1=1";
    $params = [];

    // Add category condition FIRST (most important)
    if ($selectedCategoryId) {
        $sql .= " AND category_id = :category_id";
        $params[':category_id'] = $selectedCategoryId;
    }

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

    $sql .= " ORDER BY voucher_id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Determine how to display results
    if ($selectedCategoryId) {
        $categoryResults = $results;
    } elseif ($hasSearch) {
        $searchResults = $results;
    } else {
        $pointsResults = $results;
    }
}

// ✅ Fetch promotions
$promoSql = "SELECT promote_id, title, image, descriptions FROM promotion";
$promoStmt = $conn->prepare($promoSql);
$promoStmt->execute();
$promotions = $promoStmt->fetchAll(PDO::FETCH_ASSOC);

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
            padding-top: 140px; /* Increased to accommodate navbar with search */
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

        /* Voucher Grid Style */
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

        /* Results section styling */
        .results-section {
            margin: 20px 0;
        }

        .results-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .results-count {
            background: #e6e6ff;
            color: #6a5af9;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .filter-info {
            margin-left: 10px;
            font-size: 0.9rem;
            color: var(--text-secondary-color);
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            background: var(--white-color);
            border-radius: 12px;
            color: var(--text-secondary-color);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .no-results h3 {
            margin-bottom: 10px;
            color: var(--text-color);
        }

        /* Warning message styling */
        .warning-message {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 12px 20px;
            margin: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            position: fixed;
            top: 150px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-width: 300px;
            max-width: 80%;
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

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            body {
                padding-top: 200px; /* More space on mobile */
            }

            .voucher-grid {
                justify-content: center;
            }

            .voucher-card {
                width: calc(50% - 20px);
            }

            .promo-slider {
                width: 90%;
                height: 200px;
                margin: 20px auto;
            }

            main {
                padding: 20px 15px;
            }

            main h1 {
                font-size: 2rem;
            }

            main h2 {
                font-size: 1.5rem;
            }

            .results-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-info {
                margin-left: 0;
            }

            .warning-message {
                top: 180px;
                width: 90%;
                min-width: unset;
            }
        }

        @media (max-width: 500px) {
            .voucher-grid {
                justify-content: center;
            }

            .voucher-card {
                width: 100%;
            }

            body {
                padding-top: 220px;
            }
        }

        /* Welcome section */
        .welcome-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .welcome-message {
            background: var(--white-color);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--white-color);
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .empty-state h3 {
            color: var(--text-color);
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .empty-state p {
            color: var(--text-secondary-color);
            margin-bottom: 20px;
        }

        .empty-state .btn {
            display: inline-block;
            background: var(--button-gradient);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .empty-state .btn:hover {
            background: var(--button-hover-gradient);
            transform: translateY(-2px);
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

    <!-- Warning message container (initially hidden) -->
    <div id="warningMessage" class="warning-message" style="display: none;">
        <span id="warningText"></span>
        <button class="close-btn" onclick="document.getElementById('warningMessage').style.display='none'">&times;</button>
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
        <div class="welcome-section">
            <h1>Welcome to Voucher Store</h1>
        </div>

        <!-- Search Results Section -->
        <?php if ($hasSearch && !$selectedCategoryId): ?>
            <div class="results-section">
                <div class="results-header">
                    <h2>Search Results for "<?php echo htmlspecialchars($_GET['search']); ?>"</h2>
                    <span class="results-count"><?php echo count($searchResults); ?> found</span>
                    <?php if ($hasPointsFilter): ?>
                        <div class="filter-info">
                            • Points: 
                            <?php echo isset($_GET['min_points']) && $_GET['min_points'] !== '' ? $_GET['min_points'] : '0'; ?>
                            -
                            <?php echo isset($_GET['max_points']) && $_GET['max_points'] !== '' ? $_GET['max_points'] : '∞'; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="voucher-grid">
                    <?php if (!empty($searchResults)): ?>
                        <?php foreach ($searchResults as $voucher): ?>
                            <div class="voucher-card">
                                <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                                    <img src="<?php echo htmlspecialchars($voucher['image']); ?>" alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                                </a>
                                <p><?php echo htmlspecialchars($voucher['title']); ?></p>
                                <div class="points-display">
                                    <?php echo htmlspecialchars($voucher['points']); ?> Points
                                </div>
                                <div class="button-container">
                                    <a href="redeem.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn redeem-btn" 
                                       data-points="<?php echo $voucher['points']; ?>" 
                                       data-title="<?php echo htmlspecialchars($voucher['title']); ?>">
                                        REDEEM NOW
                                    </a>
                                    <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <h3>No results found</h3>
                            <p>Try adjusting your search terms or filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Points Filter Results Section -->
        <?php if (!$hasSearch && $hasPointsFilter && !$selectedCategoryId): ?>
            <div class="results-section">
                <div class="results-header">
                    <h2>Filtered by Points</h2>
                    <span class="results-count"><?php echo count($pointsResults); ?> found</span>
                    <div class="filter-info">
                        Points: 
                        <?php echo isset($_GET['min_points']) && $_GET['min_points'] !== '' ? $_GET['min_points'] : '0'; ?>
                        -
                        <?php echo isset($_GET['max_points']) && $_GET['max_points'] !== '' ? $_GET['max_points'] : '∞'; ?>
                    </div>
                </div>
                <div class="voucher-grid">
                    <?php if (!empty($pointsResults)): ?>
                        <?php foreach ($pointsResults as $voucher): ?>
                            <div class="voucher-card">
                                <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                                    <img src="<?php echo htmlspecialchars($voucher['image']); ?>" alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                                </a>
                                <p><?php echo htmlspecialchars($voucher['title']); ?></p>
                                <div class="points-display">
                                    <?php echo htmlspecialchars($voucher['points']); ?> Points
                                </div>
                                <div class="button-container">
                                    <a href="redeem.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn redeem-btn" 
                                       data-points="<?php echo $voucher['points']; ?>" 
                                       data-title="<?php echo htmlspecialchars($voucher['title']); ?>">
                                        REDEEM NOW
                                    </a>
                                    <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <h3>No vouchers found</h3>
                            <p>Try different point ranges.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Category Results Section -->
        <?php if ($categoryResults !== null): ?>
            <div class="results-section">
                <div class="results-header">
                    <h2><?php echo ucfirst($_GET['category']); ?> Vouchers</h2>
                    <span class="results-count"><?php echo count($categoryResults); ?> found</span>
                    <?php if ($hasSearch || $hasPointsFilter): ?>
                        <div class="filter-info">
                            <?php if ($hasSearch): ?>
                                • Search: "<?php echo htmlspecialchars($_GET['search']); ?>"
                            <?php endif; ?>
                            <?php if ($hasPointsFilter): ?>
                                • Points: 
                                <?php echo isset($_GET['min_points']) && $_GET['min_points'] !== '' ? $_GET['min_points'] : '0'; ?>
                                -
                                <?php echo isset($_GET['max_points']) && $_GET['max_points'] !== '' ? $_GET['max_points'] : '∞'; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="voucher-grid">
                    <?php if (!empty($categoryResults)): ?>
                        <?php foreach ($categoryResults as $voucher): ?>
                            <div class="voucher-card">
                                <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                                    <img src="<?php echo htmlspecialchars($voucher['image']); ?>" alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                                </a>
                                <p><?php echo htmlspecialchars($voucher['title']); ?></p>
                                <div class="points-display">
                                    <?php echo htmlspecialchars($voucher['points']); ?> Points
                                </div>
                                <div class="button-container">
                                    <a href="redeem.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn redeem-btn" 
                                       data-points="<?php echo $voucher['points']; ?>" 
                                       data-title="<?php echo htmlspecialchars($voucher['title']); ?>">
                                        REDEEM NOW
                                    </a>
                                    <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <h3>No vouchers found</h3>
                            <p>No vouchers match your criteria in this category.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Top Pick Voucher Section (shown when no filters are active) -->
        <?php if (!$hasSearch && !$hasPointsFilter && !$selectedCategoryId): ?>
            <div class="results-section">
                <div class="results-header">
                    <h2>Top Pick Vouchers</h2>
                    <span class="results-count"><?php echo count($topVouchers); ?> vouchers</span>
                </div>
                <div class="voucher-grid">
                    <?php if (!empty($topVouchers)): ?>
                        <?php foreach ($topVouchers as $voucher): ?>
                            <div class="voucher-card">
                                <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                                    <img src="<?php echo htmlspecialchars($voucher['image']); ?>" alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                                </a>
                                <p><?php echo htmlspecialchars($voucher['title']); ?></p>
                                <div class="points-display">
                                    <?php echo htmlspecialchars($voucher['points']); ?> Points
                                </div>
                                <small>Total Redeemed: <?php echo $voucher['total_quantity']; ?></small>
                                <div class="button-container">
                                    <a href="redeem.php?id=<?php echo $voucher['voucher_id']; ?>" class="btn redeem-btn" 
                                       data-points="<?php echo $voucher['points']; ?>" 
                                       data-title="<?php echo htmlspecialchars($voucher['title']); ?>">
                                        REDEEM NOW
                                    </a>
                                    <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>Welcome to Our Voucher Store!</h3>
                            <p>No vouchers available at the moment. Check back later for exciting offers!</p>
                            <a href="homepage.php" class="btn">Refresh Page</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
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
                if (slides) {
                    slides.style.transform = `translateX(-${index * 100}%)`;
                }
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

                // Optional: auto-slide every 5 seconds
                setInterval(function () {
                    currentIndex = (currentIndex + 1) % totalSlides;
                    showSlide(currentIndex);
                }, 5000);
            }

            // Add event listeners to all redeem buttons
            const redeemButtons = document.querySelectorAll('.redeem-btn');
            const userPoints = <?php echo $userPoints; ?>;
            
            redeemButtons.forEach(button => {
                button.addEventListener('click', function(e) {
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
            });
        });

        // Auto-hide success message after 5 seconds
        document.addEventListener("DOMContentLoaded", function() {
            const successMessage = document.querySelector('[style*="background:#d4edda"]');
            if (successMessage) {
                setTimeout(function() {
                    successMessage.style.display = 'none';
                }, 5000);
            }
        });
    </script>
    <?php include 'footer.php'; ?>

</body>
</html>