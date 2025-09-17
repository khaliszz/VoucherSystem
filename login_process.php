<?php
session_start();
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        $sql = "SELECT * FROM users WHERE email = ? AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Compare plain text password (not secure, but matches your schema)
        if ($user && $password === $user['password']) {
            // ✅ Login success
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['profile_image'] = $user['profile_image'];

            // Optionally update last login if you add that column
            // $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            // $updateStmt->execute([$user['user_id']]);

            header("Location: homepage.php");
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