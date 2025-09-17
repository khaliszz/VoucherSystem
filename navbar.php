<?php
// navbar.php
// assumes $_SESSION is already started in the parent file
require_once 'connection.php';

// fetch categories for dropdown (if needed everywhere)
$catSql = "SELECT category_id, name FROM category";
$catStmt = $conn->prepare($catSql);
$catStmt->execute();
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Ensure cart count is available (homepage.php sets it, but default to 0 here)
if (!isset($cartCount)) {
    $cartCount = 0;
}

// ✅ Fetch user profile image for navbar display
$userProfileImage = 'images/default-avatar.png'; // default fallback
if (isset($_SESSION['user_id'])) {
    $profileSql = "SELECT profile_image FROM users WHERE user_id = ?";
    $profileStmt = $conn->prepare($profileSql);
    $profileStmt->execute([$_SESSION['user_id']]);
    $profileResult = $profileStmt->fetch(PDO::FETCH_ASSOC);
    if ($profileResult && !empty($profileResult['profile_image'])) {
        $userProfileImage = $profileResult['profile_image'];
    }
}
?>
<style>
    .navbar-cart-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: var(--white-color);
        border: 2px solid var(--border-color);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        position: relative;
    }

    .navbar-cart-btn:hover {
        transform: scale(1.05);
        border-color: #6a5af9;
        box-shadow: 0 4px 12px rgba(106, 90, 249, 0.2);
    }

    .navbar-cart-btn .cart-icon {
        width: 24px;
        height: 24px;
        fill: var(--text-color);
        transition: fill 0.3s ease;
    }

    .navbar-cart-btn:hover .cart-icon {
        fill: #6a5af9;
    }

    /* ✅ Cart badge for item count */
    .cart-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ff4757;
        color: white;
        border-radius: 50%;
        min-width: 20px;
        height: 20px;
        padding: 0 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 600;
        font-family: 'Poppins', sans-serif;
    }
</style>

<header>
    <nav>
        <a href="homepage.php">Home</a>

        <!-- Dropdown Category -->
        <div class="dropdown">
            <a href="#">Category ▾</a>
            <div class="dropdown-content">
                <?php foreach ($categories as $cat): ?>
                    <a href="category.php?id=<?php echo $cat['category_id']; ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </nav>

    <div style="display:flex; align-items:center; gap:15px;">
        <!-- ✅ Cart Button with dynamic badge -->
        <a href="cart.php" class="navbar-cart-btn" title="Shopping Cart">
            <svg class="cart-icon" viewBox="0 0 24 24">
                <path d="M7 18c-1.1 0-2 0.9-2 2s0.9 2 2 2 2-0.9 2-2-0.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-0.16 0.28-0.25 0.61-0.25 0.96 0 1.1 0.9 2 2 2h12v-2H7.42c-0.14 0-0.25-0.11-0.25-0.25l0.03-0.12L8.1 13h7.45c0.75 0 1.41-0.41 1.75-1.03L21.7 4H5.21l-0.94-2H1zm16 16c-1.1 0-2 0.9-2 2s0.9 2 2 2 2-0.9 2-2-0.9-2-2-2z"/>
            </svg>
            <?php if ($cartCount > 0): ?>
                <span class="cart-badge"><?= ($cartCount > 99 ? '99+' : $cartCount) ?></span>
            <?php endif; ?>
        </a>

        <!-- Profile Button -->
        <a href="profile.php" class="profile-btn">
            <img src="<?php echo htmlspecialchars($userProfileImage); ?>" alt="Profile" class="profile-img">
        </a>
    </div>
</header>
