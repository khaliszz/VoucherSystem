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
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 30px;
        border-bottom: 1px solid #f0f0f0;
    }

    .navbar-left {
        display: flex;
        align-items: center;
        gap: 30px;
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

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .navbar-search-container {
        padding: 15px 30px;
        background: var(--white-color);
    }

    .search-area {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        gap: 20px;
    }

    .search-form {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-grow: 1;
    }

    .search-input {
        flex: 1;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 25px;
        font-size: 0.95rem;
        transition: border-color 0.3s ease;
        min-width: 200px;
    }

    .search-input:focus {
        outline: none;
        border-color: #6a5af9;
    }

    .points-filter-dropdown {
        position: relative;
        display: inline-block;
    }

    .dropdown-toggle {
        padding: 12px 20px;
        border: 2px solid #e0e0e0;
        border-radius: 25px;
        background: white;
        cursor: pointer;
        font-size: 0.9rem;
        color: var(--text-color);
        transition: all 0.3s ease;
        min-width: 150px;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .dropdown-toggle:hover {
        border-color: #6a5af9;
    }

    .dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        min-width: 250px;
        z-index: 1000;
        padding: 15px;
        margin-top: 5px;
    }

    .dropdown-menu.show {
        display: block;
    }

    .points-range-inputs {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 15px;
    }

    .points-range-inputs input {
        padding: 8px 12px;
        border: 1px solid #e0e0e0;
        border-radius: 5px;
        width: 80px;
        font-size: 0.9rem;
    }

    .quick-filters-dropdown {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .quick-filter-link {
        padding: 8px 12px;
        text-decoration: none;
        color: var(--text-color);
        border-radius: 5px;
        transition: background 0.3s ease;
        font-size: 0.85rem;
    }

    .quick-filter-link:hover {
        background: #f0f0ff;
        color: #6a5af9;
    }

    .search-btn {
        padding: 12px 25px;
        border: none;
        border-radius: 25px;
        background: var(--button-gradient);
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.95rem;
        white-space: nowrap;
    }

    .search-btn:hover {
        background: var(--button-hover-gradient);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    .clear-btn {
        padding: 10px 20px;
        border: 2px solid #6a5af9;
        border-radius: 25px;
        background: transparent;
        color: #6a5af9;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        text-decoration: none;
        white-space: nowrap;
    }

    .clear-btn:hover {
        background: #6a5af9;
        color: white;
    }

    .category-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
        margin-top: 15px;
    }

    .category-btn {
        padding: 8px 15px;
        border: none;
        border-radius: 20px;
        background: var(--button-gradient);
        color: white;
        font-weight: 600;
        cursor: pointer;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .category-btn:hover {
        background: var(--button-hover-gradient);
        transform: translateY(-2px);
    }

    .category-btn.active {
        background: #4834d4;
        box-shadow: 0 3px 10px rgba(72, 52, 212, 0.3);
    }

    .user-points-display {
        font-size: 1rem;
        font-weight: 600;
        color: #6a5af9;
        background: linear-gradient(135deg, #f0f0ff 0%, #e6e6ff 100%);
        padding: 8px 16px;
        border-radius: 20px;
        border: 2px solid #6a5af9;
    }

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
    }

    .profile-btn {
        display: inline-block;
        border-radius: 50%;
        overflow: hidden;
        width: 45px;
        height: 45px;
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
        border: 3px solid var(--white-color);
        transition: border-color 0.3s ease;
    }

    .profile-btn:hover .profile-img {
        border-color: #6a5af9;
    }

    .search-actions {
        display: flex;
        gap: 5px;
    }
    
     /* Add some space to the left of the Home button */
    .nav-home-link {
        margin-left: 10px;
    }

    /* Mobile responsiveness */
    @media (max-width: 992px) {
        .navbar-top {
            flex-direction: column;
            gap: 15px;
            padding: 15px;
        }

        .search-area {
            flex-direction: column;
            align-items: stretch;
            gap: 15px;
        }

        .search-form {
            flex-wrap: wrap;
            justify-content: center;
        }

        .navbar-right {
            justify-content: center;
            width: 100%;
        }
    }

    @media (max-width: 768px) {
        .navbar-search-container {
            padding: 10px 15px;
        }

        .search-form {
            flex-direction: column;
            gap: 10px;
        }

        .search-input {
            min-width: 100%;
        }

        .category-buttons {
            justify-content: center;
        }

        .user-points-display {
            font-size: 0.9rem;
            padding: 6px 12px;
        }

        .search-actions {
            width: 100%;
            justify-content: center;
        }

        .search-actions .search-btn,
        .search-actions .clear-btn {
            flex: 1;
        }
    }
</style>

<div class="navbar-container">
    <!-- Top Navigation and Search Section -->
    <div class="navbar-search-container">
        <div class="navbar-top">
            <div class="navbar-left">
                <nav>
                    <a href="homepage.php" class="nav-home-link">
                     <i class="fa-solid fa-house" style="margin-right: 5px"></i>   Home
                       </a>
                    <!-- Removed Category dropdown as requested -->
                </nav>
            </div>

            <!-- Search form and category buttons go here -->
            <div class="search-area">
                <form method="get" action="homepage.php" class="search-form" id="searchForm">
                    <input type="hidden" name="category" id="categoryField" value="<?php echo isset($_GET['category']) ? htmlspecialchars($_GET['category']) : ''; ?>">

                    <input type="text" name="search" class="search-input" placeholder="Search vouchers..."
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">

                    <div class="points-filter-dropdown">
                        <div class="dropdown-toggle" onclick="toggleDropdown()">
                            <i class="fas fa-filter"></i> Filters by points ▾
                        </div>
                        <div class="dropdown-menu" id="pointsDropdown">
                            <div class="points-range-inputs">
                                <label>Points:</label>
                                <input type="number" name="min_points" placeholder="Min" id="minPoints"
                                       value="<?php echo isset($_GET['min_points']) ? htmlspecialchars($_GET['min_points']) : ''; ?>">
                                <span>-</span>
                                <input type="number" name="max_points" placeholder="Max" id="maxPoints"
                                       value="<?php echo isset($_GET['max_points']) ? htmlspecialchars($_GET['max_points']) : ''; ?>">
                            </div>

                            <div class="quick-filters-dropdown">
                                <a href="javascript:void(0)" class="quick-filter-link"
                                   onclick="setPointRange(500, 1000)">500-1000 Points</a>
                                <a href="javascript:void(0)" class="quick-filter-link"
                                   onclick="setPointRange(1001, 2000)">1001-2000 Points</a>
                                <a href="javascript:void(0)" class="quick-filter-link"
                                   onclick="setPointRange(2001, 3000)">2001-3000 Points</a>
                                <a href="javascript:void(0)" class="quick-filter-link"
                                   onclick="setPointRange(3001, 4000)">3001-4000 Points</a>
                                <a href="javascript:void(0)" class="quick-filter-link"
                                   onclick="setPointRange(4001, '')">4000+ Points</a>
                            </div>
                        </div>
                    </div>

                    <div class="search-actions">
                        <button type="submit" class="search-btn">Search</button>
                        <?php
                        $clearUrl = 'homepage.php';
                        if (isset($_GET['category']) && !empty($_GET['category'])) {
                            $clearUrl .= '?category=' . urlencode($_GET['category']);
                        }
                        ?>
                        <a href="<?php echo $clearUrl; ?>" class="clear-btn">Clear</a>
                    </div>
                </form>

                <div class="navbar-right">
                    <!-- User Points Display -->
                    <div class="user-points-display">
                        <i class="fas fa-coins"></i> <?php echo htmlspecialchars($userPoints); ?> points
                    </div>

                    <!-- Cart Button with dynamic badge -->
                    <a href="cart.php" class="navbar-cart-btn" title="Shopping Cart">
                        <i class="fas fa-shopping-cart" style="margin-right: 5px" ></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="cart-badge"><?= ($cartCount > 99 ? '99+' : $cartCount) ?></span>
                        <?php endif; ?>
                    </a>

                    <!-- Profile Button -->
                    <a href="profile.php" class="profile-btn">
                        <img src="<?php echo htmlspecialchars($userProfileImage); ?>" alt="Profile" class="profile-img">
                    </a>
                </div>
            </div>
        </div>

        <!-- Category Buttons -->
        <div class="category-buttons">
            <?php
            $currentCategory = isset($_GET['category']) ? $_GET['category'] : '';
            $categoryNames = ['fashion', 'food and beverage', 'travel', 'sports'];

            foreach ($categoryNames as $catName):
                $isActive = (strtolower($currentCategory) === strtolower($catName)) ? 'active' : '';
                $url = 'homepage.php?category=' . urlencode(strtolower($catName));

                // Preserve search and filter parameters
                if (isset($_GET['search']) && !empty($_GET['search'])) {
                    $url .= '&search=' . urlencode($_GET['search']);
                }
                if (isset($_GET['min_points']) && !empty($_GET['min_points'])) {
                    $url .= '&min_points=' . urlencode($_GET['min_points']);
                }
                if (isset($_GET['max_points']) && !empty($_GET['max_points'])) {
                    $url .= '&max_points=' . urlencode($_GET['max_points']);
                }
                ?>
                <a href="<?php echo $url; ?>" class="category-btn <?php echo $isActive; ?>">
                    <?php echo ucwords($catName); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    function toggleDropdown() {
        const dropdown = document.getElementById('pointsDropdown');
        dropdown.classList.toggle('show');
    }

    function setPointRange(min, max) {
        document.getElementById('minPoints').value = min;
        document.getElementById('maxPoints').value = max;
        document.getElementById('pointsDropdown').classList.remove('show');

        // Submit the form when a quick filter is selected
        document.getElementById('searchForm').submit();
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function (event) {
        const dropdown = document.querySelector('.points-filter-dropdown');
        if (!dropdown.contains(event.target)) {
            document.getElementById('pointsDropdown').classList.remove('show');
        }
    });

    // Set active category based on URL
    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const category = urlParams.get('category');
        if (category) {
            document.getElementById('categoryField').value = category;
        }

        // Set points values if they exist in URL
        const minPoints = urlParams.get('min_points');
        const maxPoints = urlParams.get('max_points');
        if (minPoints) document.getElementById('minPoints').value = minPoints;
        if (maxPoints) document.getElementById('maxPoints').value = maxPoints;
    });
</script>