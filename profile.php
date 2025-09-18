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
$userSql = "SELECT username, email, phone_number, points, about_me, profile_image 
            FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --button-gradient: linear-gradient(90deg, #8963e8 0%, #6352e7 100%);
            --button-hover-gradient: linear-gradient(90deg, #9a7af0 0%, #7665f1 100%);
            --text-color: #333;
            --text-secondary-color: #777;
            --background-color: #f4f7fc;
            --white-color: #ffffff;
        }

        body { font-family: 'Poppins', sans-serif; background: var(--background-color); }

        .profile-cover {
            width: 100%; height: 200px;
            background: var(--primary-gradient);
            border-radius: 0 0 16px 16px;
        }

        .profile-container {
            max-width: 1000px; margin: -80px auto 30px auto;
            background: var(--white-color);
            border-radius: 16px; padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .profile-header {
            display: flex; align-items: center; gap: 20px;
            flex-wrap: wrap; margin-bottom: 20px;
        }

        .profile-image {
            width: 130px; height: 130px; border-radius: 50%;
            object-fit: cover; border: 5px solid white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .profile-info h2 { font-size: 1.8rem; margin-bottom: 5px; }
        .profile-info p { color: var(--text-secondary-color); margin-bottom: 6px; }

        .profile-actions { display: flex; gap: 12px; flex-wrap: wrap; }
        .action-btn {
            padding: 10px 20px; border: none; border-radius: 8px;
            cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;
            transition: all 0.3s ease;
        }
        .edit-btn { background: var(--button-gradient); color: white; }
        .edit-btn:hover { background: var(--button-hover-gradient); }
        .signout-btn { background: #dc3545; color: white; }
        .signout-btn:hover { background: #c82333; }

        .about-text {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #eee;
            font-size: 1rem;
            line-height: 1.6;
        }

        .points-section { margin: 20px 0; }
        .points-display {
            font-size: 2rem; font-weight: 700; color: #6a5af9;
            background: #f8f9fa; padding: 25px; border-radius: 8px;
            text-align: center; border: 1px solid #eee;
        }

        .history-list { display: flex; flex-direction: column; gap: 15px; }
        .history-item {
            display: flex; align-items: center; gap: 15px;
            padding: 15px; border: 1px solid #eee;
            border-radius: 10px; background: var(--white-color);
            box-shadow: 0 3px 8px rgba(0,0,0,0.05);
            justify-content: space-between;
        }
        .history-info h4 { margin: 0 0 5px; }
        .history-badge {
            padding: 5px 10px; border-radius: 6px;
            font-size: 0.8rem; font-weight: 600; color: white;
        }
        .badge-active { background: #28a745; }
        .badge-expired { background: #dc3545; }
        .badge-used { background: #6c757d; }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .see-more-btn {
            background: var(--button-gradient);
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .see-more-btn:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="profile-cover"></div>

<main>
    <div class="profile-container">
        <div class="profile-header">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Picture" class="profile-image">
            <?php else: ?>
                <img src="./images/default-avatar.png" alt="Profile Picture" class="profile-image">
            <?php endif; ?>
            
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone_number'] ?? 'No phone'); ?></p>
                <div class="profile-actions">
                    <a href="editprofile.php" class="action-btn edit-btn"><i class="fas fa-user-edit"></i> Edit Profile</a>
                    <a href="logout.php" class="action-btn signout-btn"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
                </div>
            </div>
        </div>

        <div class="section">
            <h3>About Me</h3>
            <div class="about-text">
                <?php echo htmlspecialchars($user['about_me'] ?? 'No information provided.'); ?>
            </div>
        </div>

        <div class="points-section">
            <h3>Your Points</h3>
            <div class="points-display"><?php echo htmlspecialchars($user['points']); ?> Points</div>
        </div>

        <div class="section">
    <div class="section-header">
        <h3>Voucher History</h3>
        <a href="voucher_history.php" class="see-more-btn">See More</a>
    </div>

    <div class="history-list">
        <div class="history-item">
            <div class="history-info">
                <h4>Free Coffee Voucher</h4>
                <p>Redeemed on Jan 2, 2025</p>
            </div>
            <span class="history-badge badge-used">Used</span>
        </div>
        <div class="history-item">
            <div class="history-info">
                <h4>10% Off Dining</h4>
                <p>Redeemed on Feb 15, 2025</p>
            </div>
            <span class="history-badge badge-active">Active</span>
        </div>
        <div class="history-item">
            <div class="history-info">
                <h4>RM20 Shopping Voucher</h4>
                <p>Expired on Mar 10, 2025</p>
            </div>
            <span class="history-badge badge-expired">Expired</span>
        </div>
    </div>
</div>

    </div>
</main>

</body>
</html>
