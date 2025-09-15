<?php
// navbar.php
// assumes $_SESSION is already started in the parent file
require_once 'connection.php';

// fetch categories for dropdown (if needed everywhere)
$catSql = "SELECT category_id, name FROM category";
$catStmt = $conn->prepare($catSql);
$catStmt->execute();
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
?>
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

    <!-- Profile Button -->
    <a href="welcome.php" class="profile-btn">
        <img src="<?php echo $_SESSION['profile_image'] ?? 'default-avatar.png'; ?>" alt="Profile">
    </a>
</header>
