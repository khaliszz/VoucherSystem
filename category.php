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

// ✅ Handle Add to Cart
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

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Poppins', sans-serif;
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

        nav { display: flex; align-items: center; gap: 30px; }
        nav a { text-decoration: none; color: var(--text-color); font-weight: 600; }

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

        main { padding: 40px 30px; margin-top: -30px; }

        .search-bar {
            display: flex; gap: 10px; margin: 20px 0;
        }
        .search-input {
            padding: 10px; border: 1px solid #ccc; border-radius: 8px; width: 250px;
        }
        .search-button {
            background: var(--button-gradient); border: none; padding: 10px 20px;
            border-radius: 8px; color: var(--white-color); cursor: pointer;
            font-size: 0.9rem; font-weight: 600;
        }
        .search-button:hover {
            background: var(--button-hover-gradient);
        }

        .voucher-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px; margin-top: 20px;
        }

        .voucher-card {
            background: var(--white-color);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            transition: 0.3s ease;
        }

        .voucher-card img {
            width: 100%; height: 150px; object-fit: cover;
            border-radius: 12px; margin-bottom: 15px;
        }

        .voucher-card h3 { font-size: 1.2rem; margin-bottom: 8px; }
        .voucher-card p { font-size: 0.9rem; color: var(--text-secondary-color); margin-bottom: 15px; }

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
            min-width: 110px;
        }
        .voucher-card button:hover { background: var(--button-hover-gradient); }

        /* ✅ Success message */
        .success-msg {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 12px 15px;
            border-radius: 6px;
            margin: 15px 30px;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <header>
    <nav>
        <a href="homepage.php">Home</a>

        <!-- Category Dropdown -->
        <div class="dropdown">
            <a href="#" class="dropbtn">Category ▼</a>
            <div class="dropdown-content">
                <?php foreach ($categories as $cat): ?>
                    <a href="category.php?id=<?php echo $cat['category_id']; ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>
</header>

<style>
    .dropdown {
        position: relative;
        display: inline-block;
    }

    .dropbtn {
        text-decoration: none;
        color: var(--text-color);
        font-weight: 600;
        cursor: pointer;
    }

    .dropdown-content {
        display: none;
        position: absolute;
        background: var(--white-color);
        min-width: 180px;
        box-shadow: 0px 8px 16px rgba(0,0,0,0.1);
        border-radius: 8px;
        z-index: 1000;
    }

    .dropdown-content a {
        display: block;
        padding: 10px 15px;
        text-decoration: none;
        color: var(--text-color);
        font-weight: 500;
    }

    .dropdown-content a:hover {
        background: #f0f0f0;
    }

    .dropdown:hover .dropdown-content {
        display: block;
    }

    .dropdown:hover .dropbtn {
        color: #6352e7;
    }
</style>


    <!-- User Points Display -->
    <div class="user-points">
        Your Points: <?php echo htmlspecialchars($userPoints); ?>
    </div>

    <!-- ✅ Success Message -->
    <?php if (!empty($successMsg)): ?>
        <div class="success-msg"><?php echo $successMsg; ?></div>
    <?php endif; ?>

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

                      <!-- Redeem button -->
                      <form action="redeem.php" method="post" style="display:inline;">
                          <input type="hidden" name="voucher_id" value="<?php echo $voucher['voucher_id']; ?>">
                          <button type="submit">REDEEM NOW</button>
                      </form>

                      <!-- ✅ Add to cart button -->
                      <form method="post" action="category.php?id=<?php echo $category_id; ?>&search=<?php echo urlencode($search); ?>" style="display:inline;">
                          <input type="hidden" name="voucher_id" value="<?php echo $voucher['voucher_id']; ?>">
                          <button type="submit">ADD TO CART</button>
                      </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No vouchers found in this category.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
