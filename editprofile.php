<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection
require_once 'connection.php';
require_once 'cloudinary_upload.php';

// Fetch user details
$userId = $_SESSION['user_id'];
$userSql = "SELECT username, email, phone_number, address, profile_image, about_me, points FROM users WHERE user_id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $address = $_POST['address'] ?? '';
    $profile_image_url = $user['profile_image']; // Default to existing image
    $about_me = $_POST['about_me'] ?? '';

    // Handle image upload if a new file is provided
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $cloudinary = new CloudinaryService();
        $uploadedUrl = $cloudinary->uploadImage($_FILES['profile_image']);
        if ($uploadedUrl) {
            $profile_image_url = $uploadedUrl;
        }
    }

    // Check if user is filling in all four items (profile image, phone number, address, and about me) for the first time
    $pointsAwarded = false;
    if (!empty($phone_number) && !empty($address) && !empty($about_me) && !empty($profile_image_url)) {
        // Check if user previously had empty values for these fields
        if (empty($user['phone_number']) && empty($user['address']) && empty($user['about_me']) && empty($user['profile_image'])) {
            // Award 1000 points for completing all four items
            $newPoints = $user['points'] + 1000;
            $pointsSql = "UPDATE users SET points = ? WHERE user_id = ?";
            $pointsStmt = $conn->prepare($pointsSql);
            $pointsStmt->execute([$newPoints, $userId]);
            $pointsAwarded = true;
            // Update user data in session
            $user['points'] = $newPoints;
        }
    }

    // Update user information including profile_image
    $updateSql = "UPDATE users SET username = ?, phone_number = ?, address = ?, profile_image = ?, about_me = ? WHERE user_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->execute([$username, $phone_number, $address, $profile_image_url, $about_me, $userId]);

    // Update session username if it changed
    if (!empty($username)) {
        $_SESSION['username'] = $username;
    }
    
    // Update session profile image
    $_SESSION['profile_image'] = $profile_image_url;

    $showSuccessModal = true;
    // Pass points awarded information to the modal
    if (isset($pointsAwarded) && $pointsAwarded) {
        $showPointsModal = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --button-gradient: linear-gradient(90deg, #8963e8 0%, #6352e7 100%);
            --button-hover-gradient: linear-gradient(90deg, #9a7af0 0%, #7665f1 100%);
            --success-gradient: linear-gradient(90deg, #28a745 0%, #218838 100%);
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
            padding-top: 100px;
        }

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

        .edit-container {
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

        .profile-image-container {
            position: relative;
            width: 120px;
            height: 120px;
        }

        .profile-image {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border-color);
        }

        .choose-image-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background: var(--button-gradient);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }

        .choose-image-btn:hover {
            background: var(--button-hover-gradient);
            transform: scale(1.1);
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

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #6a5af9;
        }

        .form-control.textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            min-width: 120px;
            text-align: center;
            text-decoration: none;
        }

        .cancel-btn {
            background: #6c757d;
            color: var(--white-color);
        }

        .cancel-btn:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .save-btn {
            background: var(--button-gradient);
            color: var(--white-color);
        }

        .save-btn:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--white-color);
            border-radius: 16px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: modalOpen 0.3s ease;
        }

        @keyframes modalOpen {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .success-icon {
            width: 70px;
            height: 70px;
            background: var(--success-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .success-icon::after {
            content: "✓";
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
        }

        .modal-content h2 {
            font-size: 1.8rem;
            margin-bottom: 15px;
        }

        .modal-content p {
            font-size: 1.1rem;
            color: var(--text-secondary-color);
            margin-bottom: 25px;
        }

        .close-modal-btn {
            background: var(--button-gradient);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-modal-btn:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
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

            .edit-container {
                padding: 20px;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-info {
                width: 100%;
            }

            .form-actions {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .btn {
                width: 100%;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main>
        <div class="page-header">
            <h1>Edit Profile</h1>
        </div>

        <div class="edit-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="profile-header">
                    <div class="profile-image-container">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                 alt="Profile Picture" class="profile-image"
                                 onerror="this.onerror=null; this.src='./images/default-avatar.png';">
                        <?php else: ?>
                            <img src="./images/default-avatar.png" alt="Profile Picture" class="profile-image">
                        <?php endif; ?>
                        <button type="button" class="choose-image-btn" onclick="document.getElementById('profileImageInput').click()">+</button>
                        <input type="file" id="profileImageInput" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(event)">
                    </div>

                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></h2>
                        <p><?php echo htmlspecialchars($user['email'] ?? 'Email not available'); ?></p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="text" id="phone_number" name="phone_number" class="form-control" 
                           value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control textarea"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="about_me">About Me</label>
                    <textarea id="about_me" name="about_me" class="form-control textarea"><?php echo htmlspecialchars($user['about_me'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
                    <a href="profile.php" class="btn cancel-btn">Cancel</a>
                    <button type="submit" class="btn save-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </main>

    <!-- Success Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <div class="success-icon"></div>
            <h2>Success!</h2>
            <?php if (isset($showPointsModal) && $showPointsModal): ?>
                <p>Your profile has been successfully updated!<br><strong>Congratulations! You've earned 1000 points for completing your profile!</strong></p>
            <?php else: ?>
                <p>Your profile has been successfully updated!</p>
            <?php endif; ?>
            <button class="close-modal-btn" onclick="closeModal()">OK</button>
        </div>
    </div>

    <script>
        // Show modal if update was successful
        <?php if (isset($showSuccessModal) && $showSuccessModal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('successModal').style.display = 'flex';
        });
        <?php endif; ?>

        function closeModal() {
            document.getElementById('successModal').style.display = 'none';
            // Redirect to profile page after closing modal
            window.location.href = 'profile.php';
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('successModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Handle image preview
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-image').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>