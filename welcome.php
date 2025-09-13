<?php
session_start();
if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .user-info { background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .profile-pic { width: 100px; height: 100px; border-radius: 50%; margin: 10px 0; }
        .logout-btn { background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <h2>Welcome! I love... I loveeeeee🎉</h2>
    
    <div class="user-info">
        <?php if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])): ?>
            <?php 
            // Clean up the Google profile image URL to ensure it works
            $profile_image_url = $_SESSION['profile_image'];
            // Remove any size parameters and add a more reliable size
            $profile_image_url = preg_replace('/=s\d+-c$/', '=s200-c', $profile_image_url);
            $proxy_url = 'image_proxy.php?url=' . urlencode($profile_image_url);
            ?>
            <img src="<?php echo htmlspecialchars($profile_image_url); ?>" 
                 alt="Profile Picture" class="profile-pic"
                 onerror="this.onerror=null; this.src='<?php echo $proxy_url; ?>'; console.log('Direct image failed, trying proxy:', this.src);"
                 onload="console.log('Profile image loaded successfully:', this.src);">
        <?php else: ?>
            <div style="width: 100px; height: 100px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; margin: 10px 0;">
                <span style="color: #666;">No Image</span>
            </div>
        <?php endif; ?>

        <h3>Hello, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>!</h3>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
        
        <p><strong>User ID:</strong> <?php echo htmlspecialchars($_SESSION['user_id'] ?? 'N/A'); ?></p>
    </div>
    
    <p>You are logged in successfully.</p>
    <a href="logout.php" class="logout-btn">Logout</a>
</body>
</html>
