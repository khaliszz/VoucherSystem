<?php
// navbar.php
// assumes $_SESSION is already started in the parent file
require_once 'connection.php';

// fetch categories for dropdown (if needed everywhere)
$catSql = "SELECT category_id, name FROM category";
$catStmt = $conn->prepare($catSql);
$catStmt->execute();
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Ensure cart count is available (homepage.php sets it, but fetch from DB if not set)
if (!isset($cartCount)) {
    $cartCount = 0;
    if (isset($_SESSION['user_id'])) {
        $cartSql = "SELECT SUM(quantity) as total FROM cart_items WHERE user_id = ?";
        $cartStmt = $conn->prepare($cartSql);
        $cartStmt->execute([$_SESSION['user_id']]);
        $cartRow = $cartStmt->fetch(PDO::FETCH_ASSOC);
        $cartCount = $cartRow['total'] ?? 0;
    }
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

// ✅ Fetch user points for navbar display
$userPoints = 0;
if (isset($_SESSION['user_id'])) {
    $pointsSql = "SELECT points FROM users WHERE user_id = ?";
    $pointsStmt = $conn->prepare($pointsSql);
    $pointsStmt->execute([$_SESSION['user_id']]);
    $pointsResult = $pointsStmt->fetch(PDO::FETCH_ASSOC);
    if ($pointsResult) {
        $userPoints = $pointsResult['points'];
    }
}
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<style>
    .navbar-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 1000;
        background: var(--white-color);
        box-shadow: 0 2px 10px rgba(137, 99, 232, 0.3);
    }

    .navbar-top {
        display: grid;
        grid-template-columns: auto 1fr auto auto auto auto;
        align-items: center;
        padding: 15px 30px;
        border-bottom: 1px solid #f0f0f0;
        gap: 15px;
    }

    .navbar-left {
        display: contents;
    }

    .navbar-left a {
        text-decoration: none;
        color: var(--text-color);
        font-weight: 600;
        font-size: 1rem;
        transition: color 0.3s ease;
    }

    .navbar-left a:hover {
        color: #6a5af9;
    }

    .logo {
        height: 30px;
        cursor: pointer;
        transition: opacity 0.3s ease;
        border-radius: 4px;
    }

    .logo:hover {
        opacity: 0.8;
    }

    /* Home button - icon only */
    .home-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--white-color);
        border: 2px solid var(--border-color);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        color: var(--text-color);
    }

    .home-btn:hover {
        transform: scale(1.05);
        border-color: #6a5af9;
        color: #6a5af9;
        box-shadow: 0 4px 12px rgba(106, 90, 249, 0.2);
    }

    /* Category Dropdown Styles */
    .category-dropdown {
        position: relative;
        display: inline-block;
    }

    .category-dropbtn {
        background-color: var(--white-color);
        color: var(--text-color);
        padding: 6px 10px;
        font-size: 0.85rem;
        border: 2px solid var(--border-color);
        border-radius: 18px;
        cursor: pointer;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s ease;
    }

    .category-dropbtn:hover {
        border-color: #6a5af9;
        color: #6a5af9;
    }

    .category-dropdown-content {
        display: none;
        position: absolute;
        background-color: var(--white-color);
        min-width: 180px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        border-radius: 8px;
        z-index: 1;
        margin-top: 5px;
        overflow: hidden;
    }

    .category-dropdown-content a {
        color: var(--text-color);
        padding: 10px 14px;
        text-decoration: none;
        display: block;
        transition: background 0.2s ease;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.9rem;
    }

    .category-dropdown-content a:last-child {
        border-bottom: none;
    }

    .category-dropdown-content a:hover {
        background-color: #f8f9fa;
        color: #6a5af9;
    }

    .category-dropdown.active .category-dropdown-content {
        display: block;
    }

    /* Search bar container */
    .search-container {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        position: relative;
    }

    /* Search bar form */
    .search-form {
        display: flex;
        align-items: center;
        width: 100%;
        max-width: 350px;
    }

    .search-input {
        flex: 1;
        padding: 8px 10px;
        border: 2px solid #e0e0e0;
        border-radius: 18px;
        font-size: 0.85rem;
        transition: border-color 0.3s ease;
        padding-right: 35px; /* Space for the search button */
    }

    .search-input:focus {
        outline: none;
        border-color: #6a5af9;
    }

    /* Search button - circular inside search bar */
    .search-btn {
        background: var(--button-gradient);
        color: white;
        border: none;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        position: absolute;
        right: 8px;
    }

    .search-btn:hover {
        background: var(--button-hover-gradient);
        transform: scale(1.1);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    /* Points display */
    .user-points-display {
        font-size: 0.85rem;
        font-weight: 600;
        color: #6a5af9;
        background: linear-gradient(135deg, #f0f0ff 0%, #e6e6ff 100%);
        padding: 5px 10px;
        border-radius: 16px;
        border: 2px solid #6a5af9;
        white-space: nowrap;
    }

    /* Cart button */
    .navbar-cart-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
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

    .cart-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ff4757;
        color: white;
        border-radius: 50%;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6rem;
        font-weight: 600;
    }

    /* Profile button */
    .profile-btn {
        display: inline-block;
        border-radius: 50%;
        overflow: hidden;
        width: 40px;
        height: 40px;
        cursor: pointer;
        transition: transform 0.3s ease;
    }

    .profile-btn:hover {
        transform: scale(1.05);
    }

    .profile-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid var(--white-color);
        transition: border-color 0.3s ease;
    }

    .profile-btn:hover .profile-img {
        border-color: #6a5af9;
    }

    /* Mobile responsiveness - single row */
    @media (max-width: 992px) {
        .navbar-top {
            grid-template-columns: auto 1fr auto auto auto auto;
            gap: 8px;
            padding: 12px 15px;
        }

        .logo {
            height: 25px;
        }

        .category-dropbtn {
            padding: 5px 8px;
            font-size: 0.8rem;
        }

        .search-form {
            max-width: 250px;
        }

        .search-input {
            padding: 8px 10px;
            font-size: 0.85rem;
            padding-right: 35px;
        }

        .search-btn {
            width: 25px;
            height: 25px;
            font-size: 0.8rem;
        }

        .user-points-display {
            font-size: 0.8rem;
            padding: 4px 8px;
        }

        .navbar-cart-btn, .profile-btn {
            width: 35px;
            height: 35px;
        }

        .cart-badge {
            min-width: 16px;
            height: 16px;
            font-size: 0.5rem;
        }

        .profile-img {
            border: 2px solid var(--white-color);
        }
    }

    @media (max-width: 768px) {
        .navbar-top {
            grid-template-columns: auto 1fr auto auto auto auto;
            gap: 6px;
            padding: 10px 12px;
        }

        .logo {
            height: 22px;
        }

        .category-dropbtn {
            padding: 4px 6px;
            font-size: 0.75rem;
        }

        .search-form {
            max-width: 180px;
        }

        .search-input {
            padding: 6px 8px;
            font-size: 0.8rem;
            padding-right: 30px;
        }

        .search-btn {
            width: 22px;
            height: 22px;
            font-size: 0.75rem;
        }

        .user-points-display {
            font-size: 0.75rem;
            padding: 3px 6px;
        }

        .navbar-cart-btn, .profile-btn {
            width: 32px;
            height: 32px;
        }

        .cart-badge {
            min-width: 14px;
            height: 14px;
            font-size: 0.45rem;
        }
    }

    @media (max-width: 576px) {
        .navbar-top {
            grid-template-columns: auto 1fr auto auto auto auto;
            gap: 5px;
            padding: 8px 10px;
        }

        .logo {
            height: 20px;
        }

        .category-dropbtn {
            padding: 3px 5px;
            font-size: 0.7rem;
        }

        .search-form {
            max-width: 150px;
        }

        .search-input {
            padding: 5px 6px;
            font-size: 0.75rem;
            padding-right: 25px;
        }

        .search-btn {
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
        }

        .user-points-display {
            font-size: 0.7rem;
            padding: 2px 5px;
        }

        .navbar-cart-btn, .profile-btn {
            width: 30px;
            height: 30px;
        }

        .cart-badge {
            min-width: 12px;
            height: 12px;
            font-size: 0.4rem;
        }
    }

    @media (max-width: 480px) {
        .navbar-top {
            grid-template-columns: auto 1fr auto auto auto auto;
            gap: 4px;
            padding: 6px 8px;
        }

        .logo {
            height: 18px;
        }

        .category-dropbtn {
            padding: 3px 4px;
            font-size: 0.65rem;
        }

        .search-form {
            max-width: 120px;
        }

        .search-input {
            padding: 4px 5px;
            font-size: 0.7rem;
            padding-right: 22px;
        }

        .search-btn {
            width: 18px;
            height: 18px;
            font-size: 0.65rem;
        }

        .user-points-display {
            font-size: 0.65rem;
            padding: 2px 4px;
        }

        .navbar-cart-btn, .profile-btn, .home-btn {
            width: 28px;
            height: 28px;
        }

        .cart-badge {
            min-width: 10px;
            height: 10px;
            font-size: 0.35rem;
        }
    }
</style>

<div class="navbar-container">
    <div class="navbar-search-container">
        <div class="navbar-top">
            <!-- Column 1: Optima Logo (now functions as home button) -->
            <a href="homepage.php">
                <img src="images/optima.png" alt="Optima Logo" class="logo">
            </a>
            
            <!-- Column 2: Category Dropdown -->
            <div class="category-dropdown">
                <button class="category-dropbtn">
                    Category <i class="fas fa-chevron-down"></i>
                </button>
                <div class="category-dropdown-content">
                    <a href="homepage.php?show_all=1">All Vouchers</a>
                    <a href="homepage.php?category=fashion">Fashion</a>
                    <a href="homepage.php?category=food%20and%20beverage">Food and Beverage</a>
                    <a href="homepage.php?category=travel">Travel</a>
                    <a href="homepage.php?category=sports">Sports</a>
                </div>
            </div>
            
            <!-- Column 3: Search Bar -->
            <div class="search-container">
                <form method="get" action="homepage.php" class="search-form" id="searchForm">
                    <input type="hidden" name="category" id="categoryField" value="<?php echo isset($_GET['category']) ? htmlspecialchars($_GET['category']) : ''; ?>">
                    <input type="text" name="search" class="search-input" placeholder="Search vouchers..."
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            
            <!-- Column 4: Points Display -->
            <div class="user-points-display">
                <i class="fas fa-coins"></i> <?php echo htmlspecialchars($userPoints); ?> points
            </div>
            
            <!-- Column 5: Cart Button -->
            <a href="cart.php" class="navbar-cart-btn" title="Shopping Cart">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-badge" style="display: <?= $cartCount > 0 ? 'flex' : 'none' ?>"><?= ($cartCount > 99 ? '99+' : $cartCount) ?></span>
            </a>
            
            <!-- Column 6: Profile Button -->
            <a href="profile.php" class="profile-btn">
                <?php
                $img = htmlspecialchars($userProfileImage);
                $proxy = 'image_proxy.php?url=' . urlencode($img);
                ?>
                <img src="<?php echo $img; ?>"
                     alt="Profile"
                     class="profile-img"
                     referrerpolicy="no-referrer"
                     onerror="this.onerror=null; this.src='<?php echo $proxy; ?>';">
            </a>
        </div>
    </div>
</div>

<script>
    // Category dropdown functionality
    document.addEventListener('DOMContentLoaded', function () {
        const dropdown = document.querySelector('.category-dropdown');
        const dropbtn = document.querySelector('.category-dropbtn');
        
        if (dropbtn && dropdown) {
            dropbtn.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdown.classList.toggle('active');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('active');
                }
            });
        }
        
        // Set active category based on URL
        const urlParams = new URLSearchParams(window.location.search);
        const category = urlParams.get('category');
        if (category) {
            document.getElementById('categoryField').value = category;
        }

        // ✅ Preserve scroll position when clicking category links
        const categoryLinks = document.querySelectorAll('.category-dropdown-content a');
        
        categoryLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Save current scroll position
                const currentScrollY = window.scrollY;
                sessionStorage.setItem('scrollPosition', currentScrollY.toString());
            });
        });

        // Restore scroll position when page loads
        const savedScrollPosition = sessionStorage.getItem('scrollPosition');
        if (savedScrollPosition && window.location.pathname.includes('homepage.php')) {
            // Small delay to ensure page is fully loaded
            setTimeout(() => {
                window.scrollTo(0, parseInt(savedScrollPosition));
                // Clear the saved position after restoring
                sessionStorage.removeItem('scrollPosition');
            }, 100);
        }
    });
</script>