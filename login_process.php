<?php
session_start();
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        $sql = "SELECT * FROM users WHERE email = ? AND password_hash IS NOT NULL";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // ✅ Login success
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_picture'] = $user['picture'];
            
            // Update last login
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            header("Location: welcome.php");
            exit;
        } else {
            // ❌ Wrong email or password
            header("Location: login.php?error=" . urlencode("Invalid email or password"));
            exit;
        }
    } catch (PDOException $e) {
        header("Location: login.php?error=" . urlencode("Database error: " . $e->getMessage()));
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
?>
