<?php
session_start();
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/google-config.php';
require __DIR__ . '/connection.php';

// Check if there's an error from Google OAuth
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    $error_description = $_GET['error_description'] ?? 'Unknown error';
    header('Location: login.php?error=' . urlencode($error . ': ' . $error_description));
    exit;
}

if (!isset($_GET['code'])) {
    header('Location: login.php?error=No auth code received');
    exit;
}

try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        throw new Exception('Google OAuth Error: ' . ($token['error_description'] ?? $token['error']));
    }

    $client->setAccessToken($token);
    $oauth2 = new Google_Service_Oauth2($client);
    $googleUser = $oauth2->userinfo->get();

    // Prepare user data
    $email = $googleUser->email;
    $name = $googleUser->name;
    $profile_image = $googleUser->picture ?? '';
    $username_base = preg_replace('/[^a-z0-9]/i', '', strtolower(explode('@', $email)[0]));
    $username = $username_base;
    $random_password = bin2hex(random_bytes(8)); // random password for Google users

    // Ensure unique username
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $i = 1;
    while (true) {
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() == 0) break;
        $username = $username_base . $i;
        $i++;
    }

    // Check if user exists by email
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Update profile image and set is_active true
        $stmt = $conn->prepare("UPDATE users SET profile_image = ?, is_active = TRUE WHERE email = ?");
        $stmt->execute([$profile_image, $email]);
        $user_id = $user['user_id'];
    } else {
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (email, username, password, profile_image, is_active, created_at) VALUES (?, ?, ?, ?, TRUE, NOW())");
        $stmt->execute([
            $email,
            $username,
            $random_password, // store random password (or hash if you want)
            $profile_image
        ]);
        $user_id = $conn->lastInsertId();
    }

    // Set session variables
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_email'] = $email;
    $_SESSION['username'] = $username;
    $_SESSION['profile_image'] = $profile_image;

    header('Location: welcome.php');
    exit;

} catch (Throwable $e) {
    error_log("Google OAuth error: " . $e->getMessage());
    header('Location: login.php?error=' . urlencode('Login failed: ' . $e->getMessage()));
    exit;
}