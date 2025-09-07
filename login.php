<?php
session_start();
include 'connection.php';

// Check if user is already logged in
if (isset($_SESSION['user_email'])) {
    header('Location: welcome.php');
    exit;
}


// Get error message if any
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        .error { color: red; margin: 10px 0; padding: 10px; background: #ffe6e6; border: 1px solid #ffcccc; }
        .success { color: green; margin: 10px 0; padding: 10px; background: #e6ffe6; border: 1px solid #ccffcc; }
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 50px auto; padding: 20px; }
        .google-btn { margin: 20px 0; }
    </style>
</head>
<body>
    <h2>Login</h2>
    
    <?php if ($error): ?>
        <div class="error">Error: <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="login_process.php">
        <input type="email" name="email" placeholder="Email" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <button type="submit">Login</button>
    </form>

    <br><hr><br>

    
    <p><a href="signup.php">Don't have an account? Sign up</a></p>
</body>
</html>
