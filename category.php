<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'connection.php';

// ✅ Fetch all categories for dropdown
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

// ✅ Fetch vouchers in this category
$sql = "SELECT * FROM voucher WHERE category_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$category_id]);
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'header.php'; ?>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main>
        <h1><?php echo htmlspecialchars($category['name']); ?> Vouchers</h1>

        <div class="voucher-grid">
            <?php if (!empty($vouchers)): ?>
                <?php foreach ($vouchers as $voucher): ?>
                    <div class="voucher-card">
                        <img src="<?php echo htmlspecialchars($voucher['image']); ?>" 
                             alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                        <h3><?php echo htmlspecialchars($voucher['title']); ?></h3>
                        <p><?php echo $voucher['points']; ?> points</p>
                        <button>REDEEM NOW</button>
                        <button>ADD TO CART</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No vouchers available in this category.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
