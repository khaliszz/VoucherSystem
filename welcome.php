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
    <h2>Welcome! 🎉</h2>
    
    <div class="user-info">
        <?php if (isset($_SESSION['user_picture']) && $_SESSION['user_picture']): ?>
            <img src="<?php echo htmlspecialchars($_SESSION['user_picture']); ?>" 
                 alt="Profile Picture" class="profile-pic">
        <?php endif; ?>
        
        <h3>Hello, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>!</h3>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
        
        <p><strong>User ID:</strong> <?php echo htmlspecialchars($_SESSION['user_id'] ?? 'N/A'); ?></p>
    </div>
    
    <p>You are logged in successfully.</p>
    <a href="logout.php" class="logout-btn">Logout</a>
</body>
</html>
