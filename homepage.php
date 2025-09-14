<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'connection.php';

// Fetch top 5 vouchers based on total quantity redeemed
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background: #f5f7fa;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fff;
            padding: 15px 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        nav a {
            text-decoration: none;
            color: #333;
            font-weight: bold;
        }
        .profile-btn {
            display: inline-block;
            border-radius: 50%;
            overflow: hidden;
            width: 40px;
            height: 40px;
            cursor: pointer;
        }
        .profile-btn .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ddd;
            transition: border-color 0.3s ease;
        }
        .profile-btn:hover .profile-img {
            border-color: #6a5af9;
        }
        main {
            padding: 30px;
        }
        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .voucher-card {
            background: #fff;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .voucher-card img {
            width: 100%;
            height: 120px;
            background: #eee;
            margin-bottom: 10px;
            object-fit: cover;
        }
        .voucher-card button {
            background: #6a5af9;
            border: none;
            padding: 8px 12px;
            margin: 5px;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .voucher-card button:hover {
            background: #5548d6;
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="#">Home</a>
            <a href="#">Category</a>
        </nav>

        <!-- Profile Button -->
        <a href="welcome.php" class="profile-btn">
            <img src="<?php echo $_SESSION['profile_image'] ?? 'default-avatar.png'; ?>" 
                 alt="Profile" class="profile-img">
        </a>
    </header>

    <main>
        <h1>Home Page</h1>
        <h2>Top Pick Voucher</h2>

        <div class="voucher-grid">
            <?php if (!empty($topVouchers)): ?>
                <?php foreach ($topVouchers as $voucher): ?>
                    <div class="voucher-card">
                        <img src="<?php echo htmlspecialchars($voucher['image']); ?>" 
                             alt="<?php echo htmlspecialchars($voucher['title']); ?>">
                        <p><?php echo htmlspecialchars($voucher['title']); ?></p>
                        <p><small>Total Redeemed: <?php echo $voucher['total_quantity']; ?></small></p>
                        <button>REDEEM NOW</button>
                        <button>ADD TO CART</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No vouchers found.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
