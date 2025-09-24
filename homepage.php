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
$showAll = false; // all vouchers

// Check if we have a category selected
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $catKey = strtolower($_GET['category']);
    if (isset($categoryMap[$catKey])) {
        $selectedCategoryId = $categoryMap[$catKey];
    }
}

// Check if show_all is requested
if (isset($_GET['show_all']) && $_GET['show_all'] == 1) {
    $showAll = true;
    $sql = "SELECT voucher_id, title, image, points, description FROM voucher ORDER BY voucher_id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $allResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            $params[':min_points'] = (int) $minPoints;
        }

        if ($maxPoints !== '' && is_numeric($maxPoints)) {
            $sql .= " AND points <= :max_points";
            $params[':max_points'] = (int) $maxPoints;
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
            padding-top: 100px;
            /* Increased to accommodate navbar with search */
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
            gap: 20px;
            justify-content: flex-start; /* Align items to the left */
        }

       .voucher-card {
            background: #fff;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: 0.3s ease;
            flex: 0 0 calc(20% - 16px); /* Fixed width, no growing or shrinking */
            width: calc(20% - 16px); /* Explicit width for consistency */
            max-width: 280px; /* Maximum width to prevent cards from getting too large */
            display: flex;
            flex-direction: column;
            min-height: 400px; /* Set minimum height for consistency */
        }

        /* Make all titles consistent height */
       .voucher-title {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
            height: 52px; /* Fixed height for exactly 2 lines */
            line-height: 1.3;
            margin: 10px 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color);
            flex-shrink: 0; /* Prevent title from shrinking */
        }

        /* Push buttons to the bottom */
        .button-container {
            margin-top: auto;
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
            flex-shrink: 0; /* Prevent image from shrinking */
        }

        .voucher-card img:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        /* Content area that grows to fill available space */
        .voucher-content {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            justify-content: space-between;
        }

        .voucher-card small {
            font-size: 0.9rem;
            color: var(--text-secondary-color);
            display: block;
            margin-bottom: 15px;
            flex-shrink: 0; /* Prevent from shrinking */
        }

        /* Points display styling */
        .points-display {
            font-size: 1.1rem;
            font-weight: 700;
            color: #6a5af9;
            background: linear-gradient(135deg, #f0f0ff 0%, #e6e6ff 100%);
            padding: 10px 20px;
            border-radius: 25px;
            margin: 15px 0;
            border: 2px solid #6a5af9;
            display: inline-block;
            min-width: 120px;
            flex-shrink: 0; /* Prevent from shrinking */
        }

        .voucher-card a.btn {
            display: inline-block;
            background: var(--button-gradient);
            border: none;
            padding: 12px 20px;
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
            margin-top: auto;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-top: 15px;
        }

        /* Make image clickable to voucher details */
        .image-link {
            display: block;
            text-decoration: none;
            flex-shrink: 0;
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
        @media (max-width: 1400px) {
            .voucher-card {
                flex: 0 0 calc(25% - 15px); /* 4 per row on large screens */
                width: calc(25% - 15px);
            }
        }

        @media (max-width: 1200px) {
            .voucher-card {
                flex: 0 0 calc(33.33% - 14px); /* 3 per row on medium screens */
                width: calc(33.33% - 14px);
            }
        }

        @media (max-width: 768px) {
            .voucher-card {
                flex: 0 0 calc(50% - 10px); /* 2 per row on small screens */
                width: calc(50% - 10px);
                min-height: 350px; /* Adjust minimum height for mobile */
                max-width: none; /* Remove max-width constraint on mobile */
            }
            
            .voucher-title {
                height: 48px; /* Slightly smaller on mobile */
                font-size: 1rem;
            }
            
            .voucher-grid {
                gap: 15px;
            }
        }

        @media (max-width: 500px) {
            .voucher-card {
                flex: 0 0 100%; /* 1 per row on very small screens */
                width: 100%;
                min-height: 320px;
            }
            
            .voucher-grid {
                gap: 10px;
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

        /* Points Range Filter Dropdown */
        .points-range-filter {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
            margin-right: 10px;
        }

        .filter-dropbtn {
            background-color: var(--white-color);
            color: var(--text-color);
            padding: 10px 16px;
            font-size: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .filter-dropbtn:hover {
            border-color: #6a5af9;
            color: #6a5af9;
        }

        .filter-dropdown-content {
            display: none;
            position: absolute;
            background-color: var(--white-color);
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            z-index: 1;
            margin-top: 5px;
            overflow: hidden;
        }

        .filter-dropdown-content a {
            color: var(--text-color);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background 0.2s ease;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.95rem;
        }

        .filter-dropdown-content a:last-child {
            border-bottom: none;
        }

        .filter-dropdown-content a:hover {
            background-color: #f8f9fa;
            color: #6a5af9;
        }

        .points-range-filter.active .filter-dropdown-content {
            display: block;
        }

        /* Category Dropdown Styles (for inline use) */
        .inline-category-dropdown {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
            margin-right: 10px;
        }

        .inline-category-dropbtn {
            background-color: var(--white-color);
            color: var(--text-color);
            padding: 10px 16px;
            font-size: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .inline-category-dropbtn:hover {
            border-color: #6a5af9;
            color: #6a5af9;
        }

        .inline-category-dropdown-content {
            display: none;
            position: absolute;
            background-color: var(--white-color);
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-radius: 8px;
            z-index: 1;
            margin-top: 5px;
            overflow: hidden;
        }

        .inline-category-dropdown-content a {
            color: var(--text-color);
            padding: 10px 14px;
            text-decoration: none;
            display: block;
            transition: background 0.2s ease;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.95rem;
        }

        .inline-category-dropdown-content a:last-child {
            border-bottom: none;
        }

        .inline-category-dropdown-content a:hover {
            background-color: #f8f9fa;
            color: #6a5af9;
        }

        .inline-category-dropdown.active .inline-category-dropdown-content {
            display: block;
        }

        /* Search bar styles (for inline use) */
        .inline-search-container {
            display: inline-block;
            margin-bottom: 20px;
            position: relative;
        }

        .inline-search-form {
            display: flex;
            align-items: center;
        }

        .inline-search-input {
            padding: 10px 10px;
            border: 2px solid var(--border-color);
            border-radius: 25px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
            padding-right: 45px;
            width: 200px;
        }

        .inline-search-input:focus {
            outline: none;
            border-color: #6a5af9;
        }

        .inline-search-btn {
            background: var(--button-gradient);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }

        .inline-search-btn:hover {
            background: var(--button-hover-gradient);
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
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

        /* Add success gradient variable */
        :root {
            --success-gradient: linear-gradient(90deg, #28a745 0%, #218838 100%);
        }

        .floating-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #8e2de2, #4a00e0);
            color: #fff;
            font-size: 16px;
            font-weight: bold;
            padding: 14px 22px;
            border-radius: 50px;
            text-decoration: none;
            opacity: 0.8;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            z-index: 9999;
        }

        .floating-btn:hover {
            opacity: 1;
            transform: scale(1.05);
        }

        /* Floating button */
        #chatbot-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #6a11cb;
            color: white;
            border-radius: 50%;
            padding: 18px;
            cursor: pointer;
            font-size: 22px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 999;
            opacity: 0.9;
            transition: 0.3s;
        }

        #chatbot-button:hover {
            opacity: 1;
        }

        /* Chat window */
        #chatbot-window {
            position: fixed;
            bottom: 80px;
            right: 20px;
            width: 360px;
            height: 550px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
            display: none;
            flex-direction: column;
            z-index: 1000;
            overflow: hidden;
        }

        #chatbot-header {
            background: #6a11cb;
            color: white;
            padding: 12px;
            border-radius: 12px 12px 0 0;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        #chatbot-close {
            cursor: pointer;
            font-size: 16px;
            font-weight: normal;
        }

        #chatbot-messages {
            flex: 1;
            padding: 12px;
            overflow-y: auto;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* Chat bubbles */
        .chat-bubble {
            max-width: 75%;
            padding: 10px 14px;
            border-radius: 16px;
            line-height: 1.4;
            font-size: 14px;
            word-wrap: break-word;
        }

        .user-bubble {
            background: #6a11cb;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .bot-bubble {
            background: #f1f1f1;
            color: #333;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }

        /* Typing indicator */
        .typing-indicator {
            display: flex;
            gap: 4px;
            align-items: center;
            justify-content: flex-start;
            padding: 8px 14px;
            background: #f1f1f1;
            border-radius: 16px;
            width: fit-content;
            animation: fadeIn 0.3s ease-in-out;
        }

        .typing-indicator span {
            width: 6px;
            height: 6px;
            background: #888;
            border-radius: 50%;
            display: inline-block;
            animation: bounce 1.2s infinite;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes bounce {

            0%,
            80%,
            100% {
                transform: scale(0.8);
                opacity: 0.5;
            }

            40% {
                transform: scale(1);
                opacity: 1;
            }
        }

        #chatbot-input-area {
            display: flex;
            padding: 10px;
            border-top: 1px solid #ddd;
            background: #fafafa;
        }

        #chatbot-input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid #ccc;
            border-radius: 20px;
            outline: none;
            font-size: 14px;
        }

        #chatbot-input:focus {
            border-color: #6a11cb;
        }

        #chatbot-send {
            margin-left: 8px;
            padding: 10px 16px;
            border: none;
            background: #6a11cb;
            color: white;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.2s;
        }

        #chatbot-send:hover {
            background: #4a00e0;
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

    <!-- Warning message container (initially hidden) -->
    <div id="warningMessage" class="warning-message" style="display: none;">
        <span id="warningText"></span>
        <button class="close-btn"
            onclick="document.getElementById('warningMessage').style.display='none'">&times;</button>
    </div>

    <!-- Promotion Slider -->
    <?php if (!empty($promotions)): ?>
        <div class="promo-slider">
            <div class="slides">
                <?php foreach ($promotions as $promo): ?>
                    <div class="slide">
                        <img src="<?php echo htmlspecialchars($promo['image']); ?>"
                            alt="<?php echo htmlspecialchars($promo['title']); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="prev">&#10094;</button>
            <button class="next">&#10095;</button>
        </div>
    <?php endif; ?>

    <main>
        <!-- Search Results Section -->
        <?php if ($hasSearch && !$selectedCategoryId): ?>
            <div class="results-section">
                <!-- Points Range Filter Dropdown -->
                <div class="points-range-filter">
                    <button class="filter-dropbtn">
                        Filter by Points <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="filter-dropdown-content">
                        <a
                            href="homepage.php?search=<?php echo urlencode($_GET['search'] ?? ''); ?>&min_points=&max_points=1000">Less
                            than 1000 points</a>
                        <a
                            href="homepage.php?search=<?php echo urlencode($_GET['search'] ?? ''); ?>&min_points=1000&max_points=4000">1000
                            - 4000 points</a>
                        <a
                            href="homepage.php?search=<?php echo urlencode($_GET['search'] ?? ''); ?>&min_points=4000&max_points=">More
                            than 4000 points</a>
                    </div>
                </div>

                <!-- Category Dropdown -->
                <div class="inline-category-dropdown">
                    <button class="inline-category-dropbtn">
                        Category <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="inline-category-dropdown-content">
                        <a href="homepage.php?show_all=1">All Vouchers</a>
                        <a href="homepage.php">Top Picks</a>
                        <a href="homepage.php?category=fashion">Fashion</a>
                        <a href="homepage.php?category=food%20and%20beverage">Food and Beverage</a>
                        <a href="homepage.php?category=travel">Travel</a>
                        <a href="homepage.php?category=sports">Sports</a>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="inline-search-container">
                    <form method="get" action="homepage.php" class="inline-search-form">
                        <input type="text" name="search" class="inline-search-input" placeholder="Search vouchers..."
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="inline-search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>

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
                                    <img src="<?php echo htmlspecialchars($voucher['image']); ?>"
                                        alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                                </a>
                                
                                <div class="voucher-content">
                                    <!-- Title with consistent height (clamped to 2 lines) -->
                                    <p class="voucher-title">
                                        <?php echo htmlspecialchars($voucher['title']); ?>
                                    </p>
                                    
                                    <div class="points-display">
                                        <?php echo htmlspecialchars($voucher['points']); ?> Points
                                    </div>
                                    
                                    <?php if (isset($voucher['total_quantity'])): ?>
                                        <small>Total Redeemed: <?php echo $voucher['total_quantity']; ?></small>
                                    <?php endif; ?>
                                    
                                    <div class="button-container">
                                        <a href="process_redeem.php?id=<?php echo $voucher['voucher_id']; ?>" 
                                        class="btn redeem-btn"
                                        data-points="<?php echo $voucher['points']; ?>"
                                        data-title="<?php echo htmlspecialchars($voucher['title']); ?>">
                                            REDEEM NOW
                                        </a>
                                        <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                                    </div>
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
                <!-- Points Range Filter Dropdown -->
                <div class="points-range-filter">
                    <button class="filter-dropbtn">
                        Filter by Points <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="filter-dropdown-content">
                        <a href="homepage.php?min_points=&max_points=1000">Less than 1000 points</a>
                        <a href="homepage.php?min_points=1000&max_points=4000">1000 - 4000 points</a>
                        <a href="homepage.php?min_points=4000&max_points=">More than 4000 points</a>
                    </div>
                </div>

                <!-- Category Dropdown -->
                <div class="inline-category-dropdown">
                    <button class="inline-category-dropbtn">
                        Category <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="inline-category-dropdown-content">
                        <a href="homepage.php?show_all=1">All Vouchers</a>
                        <a href="homepage.php">Top Picks</a>
                        <a href="homepage.php?category=fashion">Fashion</a>
                        <a href="homepage.php?category=food%20and%20beverage">Food and Beverage</a>
                        <a href="homepage.php?category=travel">Travel</a>
                        <a href="homepage.php?category=sports">Sports</a>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="inline-search-container">
                    <form method="get" action="homepage.php" class="inline-search-form">
                        <input type="hidden" name="min_points" value="<?php echo isset($_GET['min_points']) ? htmlspecialchars($_GET['min_points']) : ''; ?>">
                        <input type="hidden" name="max_points" value="<?php echo isset($_GET['max_points']) ? htmlspecialchars($_GET['max_points']) : ''; ?>">
                        <input type="text" name="search" class="inline-search-input" placeholder="Search vouchers..."
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="inline-search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>

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
                                    <img src="<?php echo htmlspecialchars($voucher['image']); ?>"
                                        alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                                </a>
                                
                                <div class="voucher-content">
                                    <!-- Title with consistent height (clamped to 2 lines) -->
                                    <p class="voucher-title">
                                        <?php echo htmlspecialchars($voucher['title']); ?>
                                    </p>
                                    
                                    <div class="points-display">
                                        <?php echo htmlspecialchars($voucher['points']); ?> Points
                                    </div>
                                    
                                    <?php if (isset($voucher['total_quantity'])): ?>
                                        <small>Total Redeemed: <?php echo $voucher['total_quantity']; ?></small>
                                    <?php endif; ?>
                                    
                                    <div class="button-container">
                                        <a href="process_redeem.php?id=<?php echo $voucher['voucher_id']; ?>" 
                                        class="btn redeem-btn"
                                        data-points="<?php echo $voucher['points']; ?>"
                                        data-title="<?php echo htmlspecialchars($voucher['title']); ?>">
                                            REDEEM NOW
                                        </a>
                                        <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                                    </div>
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


        <!-- All Vouchers Section -->
        <?php if ($showAll): ?>
            <div class="results-section">
                <!-- Points Range Filter Dropdown -->
                <div class="points-range-filter">
                    <button class="filter-dropbtn">
                        Filter by Points <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="filter-dropdown-content">
                        <a href="homepage.php?show_all=1&min_points=&max_points=1000">Less than 1000 points</a>
                        <a href="homepage.php?show_all=1&min_points=1000&max_points=4000">1000 - 4000 points</a>
                        <a href="homepage.php?show_all=1&min_points=4000&max_points=">More than 4000 points</a>
                    </div>
                </div>

                <!-- Category Dropdown -->
                <div class="inline-category-dropdown">
                    <button class="inline-category-dropbtn">
                        Category <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="inline-category-dropdown-content">
                        <a href="homepage.php?show_all=1">All Vouchers</a>
                        <a href="homepage.php">Top Picks</a>
                        <a href="homepage.php?category=fashion">Fashion</a>
                        <a href="homepage.php?category=food%20and%20beverage">Food and Beverage</a>
                        <a href="homepage.php?category=travel">Travel</a>
                        <a href="homepage.php?category=sports">Sports</a>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="inline-search-container">
                    <form method="get" action="homepage.php" class="inline-search-form">
                        <input type="hidden" name="show_all" value="1">
                        <input type="text" name="search" class="inline-search-input" placeholder="Search vouchers..."
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="inline-search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>

                <div class="results-header">
                    <h2>All Vouchers</h2>
                    <span class="results-count"><?php echo count($allResults); ?> found</span>
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
                    <?php if (!empty($allResults)): ?>
                        <?php foreach ($allResults as $voucher): ?>
                            <div class="voucher-card">
                                <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                                    <img src="<?php echo htmlspecialchars($voucher['image']); ?>"
                                        alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                                </a>
                                
                                <div class="voucher-content">
                                    <!-- Title with consistent height (clamped to 2 lines) -->
                                    <p class="voucher-title">
                                        <?php echo htmlspecialchars($voucher['title']); ?>
                                    </p>
                                    
                                    <div class="points-display">
                                        <?php echo htmlspecialchars($voucher['points']); ?> Points
                                    </div>
                                    
                                    <?php if (isset($voucher['total_quantity'])): ?>
                                        <small>Total Redeemed: <?php echo $voucher['total_quantity']; ?></small>
                                    <?php endif; ?>
                                    
                                    <div class="button-container">
                                        <a href="process_redeem.php?id=<?php echo $voucher['voucher_id']; ?>" 
                                        class="btn redeem-btn"
                                        data-points="<?php echo $voucher['points']; ?>"
                                        data-title="<?php echo htmlspecialchars($voucher['title']); ?>">
                                            REDEEM NOW
                                        </a>
                                        <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <h3>No vouchers available</h3>
                            <p>Please check back later.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Category Results Section -->
        <?php if ($categoryResults !== null): ?>
            <div class="results-section">
                <!-- Points Range Filter Dropdown -->
                <div class="points-range-filter">
                    <button class="filter-dropbtn">
                        Filter by Points <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="filter-dropdown-content">
                        <a
                            href="homepage.php?category=<?php echo urlencode($_GET['category']); ?>&min_points=&max_points=1000">Less
                            than 1000 points</a>
                        <a
                            href="homepage.php?category=<?php echo urlencode($_GET['category']); ?>&min_points=1000&max_points=4000">1000
                            - 4000 points</a>
                        <a
                            href="homepage.php?category=<?php echo urlencode($_GET['category']); ?>&min_points=4000&max_points=">More
                            than 4000 points</a>
                    </div>
                </div>

                <!-- Category Dropdown -->
                <div class="inline-category-dropdown">
                    <button class="inline-category-dropbtn">
                        Category <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="inline-category-dropdown-content">
                        <a href="homepage.php?show_all=1">All Vouchers</a>
                        <a href="homepage.php">Top Picks</a>
                        <a href="homepage.php?category=fashion">Fashion</a>
                        <a href="homepage.php?category=food%20and%20beverage">Food and Beverage</a>
                        <a href="homepage.php?category=travel">Travel</a>
                        <a href="homepage.php?category=sports">Sports</a>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="inline-search-container">
                    <form method="get" action="homepage.php" class="inline-search-form">
                        <input type="hidden" name="category" value="<?php echo isset($_GET['category']) ? htmlspecialchars($_GET['category']) : ''; ?>">
                        <input type="text" name="search" class="inline-search-input" placeholder="Search vouchers..."
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="inline-search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>

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
                                    <img src="<?php echo htmlspecialchars($voucher['image']); ?>"
                                        alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                                </a>
                                
                                <div class="voucher-content">
                                    <!-- Title with consistent height (clamped to 2 lines) -->
                                    <p class="voucher-title">
                                        <?php echo htmlspecialchars($voucher['title']); ?>
                                    </p>
                                    
                                    <div class="points-display">
                                        <?php echo htmlspecialchars($voucher['points']); ?> Points
                                    </div>
                                    
                                    <?php if (isset($voucher['total_quantity'])): ?>
                                        <small>Total Redeemed: <?php echo $voucher['total_quantity']; ?></small>
                                    <?php endif; ?>
                                    
                                    <div class="button-container">
                                        <a href="process_redeem.php?id=<?php echo $voucher['voucher_id']; ?>" 
                                        class="btn redeem-btn"
                                        data-points="<?php echo $voucher['points']; ?>"
                                        data-title="<?php echo htmlspecialchars($voucher['title']); ?>">
                                            REDEEM NOW
                                        </a>
                                        <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                                    </div>
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

        
        <?php if (!$hasSearch && !$hasPointsFilter && !$selectedCategoryId && !$showAll): ?>
            <div class="results-section">
                <!-- Points Range Filter Dropdown -->
                <div class="points-range-filter">
                    <button class="filter-dropbtn">
                        Filter by Points <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="filter-dropdown-content">
                        <a href="homepage.php?min_points=&max_points=1000">
                            < 1000 points</a>
                                <a href="homepage.php?min_points=1000&max_points=4000">1000 - 4000 points</a>
                                <a href="homepage.php?min_points=4000&max_points=">> 4000 points</a>
                    </div>
                </div>

                <!-- Category Dropdown -->
                <div class="inline-category-dropdown">
                    <button class="inline-category-dropbtn">
                        Category <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="inline-category-dropdown-content">
                        <a href="homepage.php?show_all=1">All Vouchers</a>
                        <a href="homepage.php">Top Picks</a>
                        <a href="homepage.php?category=fashion">Fashion</a>
                        <a href="homepage.php?category=food%20and%20beverage">Food and Beverage</a>
                        <a href="homepage.php?category=travel">Travel</a>
                        <a href="homepage.php?category=sports">Sports</a>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="inline-search-container">
                    <form method="get" action="homepage.php" class="inline-search-form">
                        <input type="text" name="search" class="inline-search-input" placeholder="Search vouchers..."
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="inline-search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>

                <div class="results-header">
                    <h2>Top Pick Vouchers</h2>
                    <span class="results-count"><?php echo count($topVouchers); ?> vouchers</span>
                </div>
                <div class="voucher-grid">
                    <?php if (!empty($topVouchers)): ?>
                        <?php foreach ($topVouchers as $voucher): ?>
                            <div class="voucher-card">
                                <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                                    <img src="<?php echo htmlspecialchars($voucher['image']); ?>"
                                        alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                                </a>
                                
                                <div class="voucher-content">
                                    <!-- Title with consistent height (clamped to 2 lines) -->
                                    <p class="voucher-title">
                                        <?php echo htmlspecialchars($voucher['title']); ?>
                                    </p>
                                    
                                    <div class="points-display">
                                        <?php echo htmlspecialchars($voucher['points']); ?> Points
                                    </div>
                                    
                                    <?php if (isset($voucher['total_quantity'])): ?>
                                        <small>Total Redeemed: <?php echo $voucher['total_quantity']; ?></small>
                                    <?php endif; ?>
                                    
                                    <div class="button-container">
                                        <a href="process_redeem.php?id=<?php echo $voucher['voucher_id']; ?>" 
                                        class="btn redeem-btn"
                                        data-points="<?php echo $voucher['points']; ?>"
                                        data-title="<?php echo htmlspecialchars($voucher['title']); ?>">
                                            REDEEM NOW
                                        </a>
                                        <a href="cart.php?action=add&id=<?= $voucher['voucher_id']; ?>" class="btn">ADD TO CART</a>
                                    </div>
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
                button.addEventListener('click', function (e) {
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

            // Points Range Filter Dropdown functionality
            const filterDropdowns = document.querySelectorAll('.points-range-filter');
            filterDropdowns.forEach(dropdown => {
                const dropbtn = dropdown.querySelector('.filter-dropbtn');

                if (dropbtn) {
                    dropbtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        dropdown.classList.toggle('active');
                    });

                    // Close dropdown when clicking outside
                    document.addEventListener('click', function (e) {
                        if (!dropdown.contains(e.target)) {
                            dropdown.classList.remove('active');
                        }
                    });
                }
            });

            // Handle Add to Cart buttons with popup
            const addToCartButtons = document.querySelectorAll('a[href*="cart.php?action=add"]');
            const cartPopup = document.getElementById('cartPopup');
            const closePopup = document.getElementById('closePopup');
            const addMoreBtn = document.getElementById('addMoreBtn');
            const viewCartBtn = document.getElementById('viewCartBtn');

            // Add event listeners to all "ADD TO CART" buttons
            addToCartButtons.forEach(button => {
                button.addEventListener('click', function (e) {
                    e.preventDefault(); // Prevent default link behavior

                    // Get the voucher ID from the href
                    const href = this.getAttribute('href');
                    const url = new URL(href, window.location.origin);
                    const voucherId = url.searchParams.get('id');

                    // Make AJAX request to add item to cart
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', href, true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    xhr.onreadystatechange = function () {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            console.log('AJAX response received:', xhr.responseText);
                            try {
                                const response = JSON.parse(xhr.responseText);
                                console.log('Parsed response:', response);
                                if (response.success) {
                                    // Show the popup
                                    console.log('Showing popup...');
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
                                        console.log('Cart badge updated:', cartBadge.textContent, 'Display:', cartBadge.style.display);
                                    } else {
                                        console.error('Cart badge element not found!');
                                    }
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                console.error('Raw response:', xhr.responseText);
                            }
                        }
                    };
                    xhr.send();
                });
            });

            // Close popup when clicking the X button
            if (closePopup) {
                closePopup.addEventListener('click', function () {
                    cartPopup.style.display = 'none';
                });
            }

            // Close popup when clicking "Add more" button
            if (addMoreBtn) {
                addMoreBtn.addEventListener('click', function () {
                    cartPopup.style.display = 'none';
                });
            }

            // Navigate to cart when clicking "View Cart" button
            if (viewCartBtn) {
                viewCartBtn.addEventListener('click', function () {
                    window.location.href = 'cart.php';
                });
            }

            // Close popup when clicking outside the modal
            window.addEventListener('click', function (event) {
                if (event.target === cartPopup) {
                    cartPopup.style.display = 'none';
                }
            });

            // Debug: Log popup element to console
            console.log('Cart popup element:', cartPopup);
            console.log('Add to cart buttons found:', addToCartButtons.length);
        });

        // Auto-hide success message after 5 seconds
        document.addEventListener("DOMContentLoaded", function () {
            const successMessage = document.querySelector('[style*="background:#d4edda"]');
            if (successMessage) {
                setTimeout(function () {
                    successMessage.style.display = 'none';
                }, 5000);
            }
        });

        // Inline Category Dropdown functionality
        const inlineCategoryDropdowns = document.querySelectorAll('.inline-category-dropdown');
        inlineCategoryDropdowns.forEach(dropdown => {
            const dropbtn = dropdown.querySelector('.inline-category-dropbtn');

            if (dropbtn) {
                dropbtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    dropdown.classList.toggle('active');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function (e) {
                    if (!dropdown.contains(e.target)) {
                        dropdown.classList.remove('active');
                    }
                });
            }
        });

        // Inline Search Bar functionality
        const inlineSearchForms = document.querySelectorAll('.inline-search-form');
        inlineSearchForms.forEach(form => {
            // Add event listener to prevent form submission if empty
            form.addEventListener('submit', function(e) {
                const searchInput = form.querySelector('.inline-search-input');
                if (searchInput && searchInput.value.trim() === '') {
                    e.preventDefault();
                }
            });
        });
    </script>
    <?php include 'footer.php'; ?>
    <!-- Floating Chatbot Button -->
    <div id="chatbot-button">💬</div>

    <!-- Chatbot Window -->
    <div id="chatbot-window">
        <div id="chatbot-header">
            Optima Bot
            <span id="chatbot-close">✖</span>
        </div>
        <div id="chatbot-messages"></div>
        <div id="chatbot-input-area">
            <input type="text" id="chatbot-input" placeholder="Ask about vouchers or services..." />
            <button id="chatbot-send">➤</button>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const button = document.getElementById("chatbot-button");
            const windowEl = document.getElementById("chatbot-window");
            const closeBtn = document.getElementById("chatbot-close");
            const input = document.getElementById("chatbot-input");
            const sendBtn = document.getElementById("chatbot-send");
            const messages = document.getElementById("chatbot-messages");

            // Toggle chatbot
            button.addEventListener("click", () => {
                windowEl.style.display = "flex";
                // Show greeting on open
                if (messages.children.length === 0) {
                    appendMessage("bot", "Hello! 👋 I’m Optima Bot. How can I help you with vouchers or Optima Bank services?");
                }
            });
            closeBtn.addEventListener("click", () => {
                windowEl.style.display = "none";
            });

            // Append message
            function appendMessage(sender, text) {
                const msg = document.createElement("div");
                msg.classList.add("chat-bubble", sender === "bot" ? "bot-bubble" : "user-bubble");

                // Format bot messages
                if (sender === "bot") {
                    // Replace * with bullet points
                    text = text.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>"); // bold
                    text = text.replace(/\* (.*?)(\n|$)/g, "• $1<br>"); // bullet list
                    text = text.replace(/\n/g, "<br>"); // new lines
                    msg.innerHTML = text;
                } else {
                    msg.textContent = text; // user messages normal text
                }

                messages.appendChild(msg);
                messages.scrollTop = messages.scrollHeight;
            }


            // Add typing indicator
            function showTyping() {
                const typing = document.createElement("div");
                typing.classList.add("typing-indicator");
                typing.innerHTML = "<span></span><span></span><span></span>";
                typing.id = "typing";
                messages.appendChild(typing);
                messages.scrollTop = messages.scrollHeight;
            }
            function removeTyping() {
                const typing = document.getElementById("typing");
                if (typing) typing.remove();
            }

            // Send message
            function sendMessage() {
                const text = input.value.trim();
                if (!text) return;
                appendMessage("user", text);
                input.value = "";

                // Check greetings
                // Check greetings (EXACT match only, not substring)
                const greetings = ["hi", "hello", "hey", "good morning", "good afternoon"];
                if (greetings.some(g => text.toLowerCase().trim() === g)) {
                    appendMessage("bot", "Hello! 👋 How can I help you with vouchers or Optima Bank services today?");
                    return;
                }


                // Show typing indicator
                showTyping();

                // Send to backend
                fetch("ai_chatbot.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ message: text })
                })
                    .then(res => res.json())
                    .then(data => {
                        removeTyping();
                        appendMessage("bot", data.reply || "Sorry, I didn’t understand that.");
                    })
                    .catch(() => {
                        removeTyping();
                        appendMessage("bot", "⚠️ Error connecting to chatbot.");
                    });
            }
            sendBtn.addEventListener("click", sendMessage);
            input.addEventListener("keypress", e => {
                if (e.key === "Enter") sendMessage();
            });
        });
    </script>
</body>

</html>