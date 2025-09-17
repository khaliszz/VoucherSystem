<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'connection.php';

// Fetch user details
$userId = $_SESSION['user_id'];
$userSql = "SELECT username, email, points, about_me, profile_image FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Fetch voucher history (placeholder for future implementation)
$voucherHistory = [];

// Fetch cart count for navbar
$cartCount = 0;
$cartSql = "SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?";
$cartStmt = $conn->prepare($cartSql);
$cartStmt->execute([$userId]);
$cartRow = $cartStmt->fetch(PDO::FETCH_ASSOC);
$cartCount = $cartRow['total'] ?? 0;

// Fetch categories for dropdown
$catSql = "SELECT category_id, name FROM category";
$catStmt = $conn->prepare($catSql);
$catStmt->execute();
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
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
        .dropdown { position: relative; display: inline-block; }
        .dropdown-content {
            display: none;
            position: absolute;
            top: 100%; left: 0;
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
        .dropdown:hover .dropdown-content { display: block; }

        .profile-btn {
            display: inline-block;
            border-radius: 50%;
            overflow: hidden;
            width: 45px; height: 45px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .profile-btn:hover { transform: scale(1.05); }
        .profile-btn .profile-img {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--white-color);
            transition: border-color 0.3s ease;
        }
        .profile-btn:hover .profile-img { border-color: #6a5af9; }

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

        .profile-container {
            background: var(--white-color);
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border-color);
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0 0 10px 0;
        }

        .profile-info p {
            font-size: 1.1rem;
            color: var(--text-secondary-color);
            margin: 5px 0;
        }

        .profile-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            min-width: 140px;
            text-align: center;
        }

        .edit-btn {
            background: var(--button-gradient);
            color: var(--white-color);
        }

        .edit-btn:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        .signout-btn {
            background: #dc3545;
            color: var(--white-color);
        }

        .signout-btn:hover {
            background: #c82333;
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
            transform: translateY(-2px);
        }

        .section {
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-color);
            margin: 0;
        }

        .see-more-btn {
            background: none;
            border: none;
            color: #6a5af9;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            text-decoration: underline;
            font-size: 1rem;
        }

        .about-text {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            min-height: 100px;
            font-size: 1rem;
            line-height: 1.6;
        }

        .points-display {
            font-size: 2rem;
            font-weight: 700;
            color: #6a5af9;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .history-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--white-color);
            transition: all 0.3s ease;
        }

        .history-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }

        .history-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
        }

        .history-info {
            flex: 1;
        }

        .history-info h4 {
            margin: 0 0 5px 0;
            font-size: 1.1rem;
        }

        .history-info p {
            margin: 0;
            color: var(--text-secondary-color);
            font-size: 0.9rem;
        }

        .history-quantity {
            font-weight: 600;
            color: #6a5af9;
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

            .profile-container {
                padding: 20px;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-info {
                width: 100%;
            }

            .profile-actions {
                justify-content: center;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .history-item {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .action-btn {
                width: 100%;
            }

            .history-item {
                padding: 10px;
            }
        }
    </style>
</head>
<body>

    <main>
        <div class="page-header">
            <h1>Profile Page</h1>
            <a href="homepage.php" class="back-btn">
                Back
            </a>
        </div>

        <div class="profile-container">
            <div class="profile-header">
                <?php
                $profile_image_url = $user['profile_image'] ?? '';
                if (!empty($profile_image_url)):
                ?>
                <img src="<?php echo htmlspecialchars($profile_image_url); ?>"
                alt="Profile Picture" class="profile-image"
                onerror="this.onerror=null; this.src='./images/default-avatar.png';">
                <?php else: ?>
                    <img src="./images/default-avatar.png" alt="Profile Picture" class="profile-image">
                    <?php endif; ?>

                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></h2>
                    <p><?php echo htmlspecialchars($user['email'] ?? 'Email not available'); ?></p>
                    
                    <div class="profile-actions">
                        <a href="editprofile.php" class="action-btn edit-btn">Edit Profile</a>
                        <a href="logout.php" class="action-btn signout-btn">Sign Out</a>
                    </div>
                </div>
            </div>

            <div class="section">
                <h3 class="section-title">About Me</h3>
                <div class="about-text">
                    <?php echo htmlspecialchars($user['about_me'] ?? 'No information provided.'); ?>
                </div>
            </div>

            <div class="section">
                <h3 class="section-title">Your Points</h3>
                <div class="points-display">
                    <?php echo htmlspecialchars($user['points'] ?? '0'); ?> Points
                </div>
            </div>

            <div class="section">
                <div class="section-header">
                    <h3 class="section-title">Voucher History</h3>
                    <button class="see-more-btn">See More</button>
                </div>
                <p>Voucher history functionality will be implemented soon.</p>
            </div>
        </div>
    </main>
</body>
</html>