<?php
session_start();
include 'connection.php';

// Check if user is already logged in
if (isset($_SESSION['user_email'])) {
    header('Location: welcome.php');
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = "Username, email, and password are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // Check if email or username already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            if ($stmt->fetch()) {
                $error = "Email or username already exists. Please use a different one or try logging in.";
            } else {
                // Insert new user (plain text password as per your request, but not recommended)
                $sql = "INSERT INTO users (email, username, password) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $email,
                    $username,
                    $password
                ]);
                // Redirect to login with success message
                header("Location: login.php?success=" . urlencode("Signup successful! You can now login."));
                exit;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
    // Redirect to login with error message and show signup tab
    if (isset($error)) {
        header("Location: login.php?error=" . urlencode($error) . "&show=signup");
        exit;
    }
} else {
    // If accessed directly, redirect to login
    header("Location: login.php?show=signup");
    exit;
}
?>