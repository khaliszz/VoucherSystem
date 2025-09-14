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
$userPoints = $user['points'] ?? 0; // Default to 0 if no points

// ✅ All categories for dropdown
$catSql = "SELECT category_id, name FROM category";
$catStmt = $conn->prepare($catSql);
$catStmt->execute();
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Check category ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid category.");
}

$category_id = intval($_GET['id']);

// ✅ Fetch category name
$catNameSql = "SELECT name FROM category WHERE category_id = ?";
$catNameStmt = $conn->prepare($catNameSql);
$catNameStmt->execute([$category_id]);
$category = $catNameStmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    die("Category not found.");
}

// ✅ Handle search
$search = isset($_GET['search']) ? trim($_GET['search']) : "";

if (!empty($search)) {
    $sql = "SELECT * FROM voucher WHERE category_id = ? AND title LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$category_id, "%$search%"]);
} else {
    $sql = "SELECT * FROM voucher WHERE category_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$category_id]);
}

$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

        /* ✅ Copying homepage styles */
        .search-bar {
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            margin: 20px 0;
        }

        .search-input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
            width: 250px;
            font-family: 'Poppins', sans-serif;
        }

        .search-button {
            background: var(--button-gradient);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            color: var(--white-color);
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }

        .search-button:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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
        }

        .voucher-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }

        .voucher-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 12px;
            margin-bottom: 15px;
        }

        .voucher-card h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .voucher-card p {
            font-size: 0.9rem;
            color: var(--text-secondary-color);
            margin-bottom: 15px;
        }

        .voucher-card button {
            background: var(--button-gradient);
            border: none;
            padding: 12px 20px;
            margin: 5px;
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

         /*✅ Make the image clickable*/
        .voucher-card .image-link {
            display: block; /* Ensure the <a> tag fills the entire image area */
        }

         /* Style for User Points Display */
        .user-points {
            background: var(--white-color);
            padding: 10px 20px;
            border-radius: 8px;
            margin: 15px 30px; /* Adjusted for better spacing */
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-color);
            text-align: right; /* Align to the right */
        }

        main {
            padding: 40px 30px;
            margin-top: -30px; /* Adjust this value as needed */
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

     <!-- User Points Display -->
    <div class="user-points">
        Your Points: <?php echo htmlspecialchars($userPoints); ?>
    </div>

    <main>
        <h1><?php echo htmlspecialchars($category['name']); ?> Vouchers</h1>

        <!-- ✅ Search bar -->
        <form method="get" class="search-bar">
            <input type="hidden" name="id" value="<?php echo $category_id; ?>">
            <input type="text" name="search" placeholder="Search voucher..."
                   value="<?php echo htmlspecialchars($search); ?>" class="search-input">
            <button type="submit" class="search-button">Search</button>
        </form>

        <div class="voucher-grid">
            <?php if (!empty($vouchers)): ?>
                <?php foreach ($vouchers as $voucher): ?>
                    <div class="voucher-card">
                      <a href="voucher_details.php?id=<?php echo $voucher['voucher_id']; ?>" class="image-link">
                         <img src="<?php echo htmlspecialchars($voucher['image']); ?>"
                              alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                      </a>

                      <h3><?php echo htmlspecialchars($voucher['title']); ?></h3>
                      <p><?php echo $voucher['points']; ?> points</p>
                      <button>REDEEM NOW</button>
                      <button>ADD TO CART</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No vouchers found in this category.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>