<?php
session_start();
include 'connection.php';
include 'google-config.php';

// If user is already logged in, redirect to welcome.php
if (isset($_SESSION['user_id']) || isset($_SESSION['user_email'])) {
    header('Location: homepage.php');
    exit();
}

// Show messages from redirects
$message = '';
if (isset($_GET['error'])) {
    $message = '<div class="message error">' . htmlspecialchars($_GET['error']) . '</div>';
} elseif (isset($_GET['success'])) {
    $message = '<div class="message success">' . htmlspecialchars($_GET['success']) . '</div>';
}

// Logout logic
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit();
}

$googleLoginUrl = $client->createAuthUrl();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['user_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --button-gradient: linear-gradient(90deg, #8963e8 0%, #6352e7 100%);
            --button-hover-gradient: linear-gradient(90deg, #9a7af0 0%, #7665f1 100%);
            --text-color: #333;
            --text-secondary-color: #777;
            --border-color: #e0e0e0;
            --background-color: #f4f7fc;
            --white-color: #ffffff;
            --error-bg: #f8d7da;
            --error-text: #721c24;
            --success-bg: #d4edda;
            --success-text: #155724;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: var(--background-color);
            padding: 1rem;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 450px;
            background: var(--white-color);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .auth-wrapper.dashboard-view {
             max-width: 550px;
             text-align: center;
             padding: 3rem;
        }
        
        .dashboard-view h1 {
            font-size: 2.5rem;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .dashboard-view p {
            font-size: 1.2rem;
            color: var(--text-secondary-color);
            margin-bottom: 2rem;
        }
        
        .dashboard-view .logout-btn {
            display: inline-block;
            padding: 0.8rem 2.5rem;
            background: var(--button-gradient);
            color: var(--white-color);
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .dashboard-view .logout-btn:hover {
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            transform: translateY(-2px);
        }

        .auth-header {
            background: var(--primary-gradient);
            color: var(--white-color);
            padding: 2.5rem;
            text-align: center;
        }

        .auth-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .auth-header p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .auth-body {
            padding: 2.5rem;
        }

        .auth-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .auth-tabs .tab {
            flex: 1;
            padding: 1rem;
            text-align: center;
            font-weight: 600;
            color: var(--text-secondary-color);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .auth-tabs .tab.active {
            color: #6a5af9;
            border-bottom-color: #6a5af9;
        }

        .form-panel {
            display: none;
        }

        .form-panel.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #6a5af9;
            box-shadow: 0 0 0 3px rgba(106, 90, 249, 0.2);
        }

        .form-options {
            text-align: right;
            margin-bottom: 1.5rem;
        }

        .form-options a {
            color: #6a5af9;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            background: var(--button-gradient);
            color: var(--white-color);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            background: var(--button-hover-gradient);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: var(--text-secondary-color);
            margin: 2rem 0;
            font-size: 0.9rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border-color);
        }

        .divider:not(:empty)::before {
            margin-right: 1em;
        }

        .divider:not(:empty)::after {
            margin-left: 1em;
        }

        .social-login {
            display: flex;
            gap: 1rem;
        }

        .social-btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-color);
            font-weight: 500;
            transition: background-color 0.3s ease;
        }

        .social-btn:hover {
            background-color: #f9f9f9;
        }

        .social-btn img {
            width: 20px;
            margin-right: 0.75rem;
        }

        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            font-size: 0.95rem;
            text-align: center;
        }

        .message.error {
            background-color: var(--error-bg);
            color: var(--error-text);
        }

        .message.success {
            background-color: var(--success-bg);
            color: var(--success-text);
        }
        
        /* ========================================= */
        /*  *** NEW: Mobile Responsiveness Enhancement *** */
        /* ========================================= */
        @media (max-width: 500px) {
            .auth-header, .auth-body {
                padding: 2rem 1.5rem; /* Reduce side padding */
            }

            .auth-header h1 {
                font-size: 2rem;
            }
            
            .dashboard-view {
                padding: 2rem 1.5rem;
            }
            
            .dashboard-view h1 {
                font-size: 2rem;
            }
            
            .dashboard-view p {
                font-size: 1rem;
            }
        }

    </style>
</head>
<body>

    <?php if ($is_logged_in): ?>
        <div class="auth-wrapper dashboard-view">
            <h1>Welcome Back!</h1>
            <p>You are now logged in, <?php echo htmlspecialchars($_SESSION['user_display_name'] ?? $_SESSION['username'] ?? $_SESSION['user_email'] ?? 'User'); ?>.</p>
            <a href="?action=logout" class="logout-btn">Logout</a>
        </div>
    <?php else: ?>
        <div class="auth-wrapper">
            <header class="auth-header">
                <h1>Welcome</h1>
                <p>Access your account or create a new one</p>
            </header>
            <main class="auth-body">
                <div class="auth-tabs">
                    <div class="tab" id="tab-signin">Sign In</div>
                    <div class="tab" id="tab-signup">Sign Up</div>
                </div>

                <?php echo $message; ?>

                <!-- Sign In Form -->
                <div class="form-panel" id="panel-signin">
                    <form action="login_process.php" method="POST">
                        <div class="form-group">
                            <label for="login-email">Email Address</label>
                            <input type="email" id="login-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <input type="password" id="login-password" name="password" required>
                        </div>
                        <div class="form-options">
                            <a href="#">Forgot your password?</a>
                        </div>
                        <button type="submit" name="login" class="btn-submit">Sign In</button>
                    </form>
                </div>

                <!-- Sign Up Form -->
                <div class="form-panel" id="panel-signup">
                    <form action="signup.php" method="POST">
                        <div class="form-group">
                            <label for="signup-username">Username</label>
                            <input type="text" id="signup-username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="signup-email">Email Address</label>
                            <input type="email" id="signup-email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="signup-password">Password</label>
                            <input type="password" id="signup-password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="signup-confirm-password">Confirm Password</label>
                            <input type="password" id="signup-confirm-password" name="confirm_password" required>
                        </div>
                        <button type="submit" class="btn-submit">Create Account</button>
                    </form>
                </div>

                <div class="divider">or continue with</div>
                <div class="social-login">
                    <a href="<?php echo htmlspecialchars($googleLoginUrl); ?>" class="social-btn">
                        <img src="https://www.vectorlogo.zone/logos/google/google-icon.svg" alt="Google"> Google
                    </a>
                </div>
            </main>
        </div>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabSignin = document.getElementById('tab-signin');
            if (tabSignin) {
                const tabSignup = document.getElementById('tab-signup');
                const panelSignin = document.getElementById('panel-signin');
                const panelSignup = document.getElementById('panel-signup');

                function showPanel(panelToShow) {
                    panelSignin.classList.remove('active');
                    panelSignup.classList.remove('active');
                    tabSignin.classList.remove('active');
                    tabSignup.classList.remove('active');

                    if (panelToShow === 'signin') {
                        panelSignin.classList.add('active');
                        tabSignin.classList.add('active');
                    } else {
                        panelSignup.classList.add('active');
                        tabSignup.classList.add('active');
                    }
                }

                // Default to Sign In tab
                showPanel('<?php echo isset($_GET["show"]) && $_GET["show"] === "signup" ? "signup" : "signin"; ?>');

                tabSignin.addEventListener('click', () => showPanel('signin'));
                tabSignup.addEventListener('click', () => showPanel('signup'));
            }
        });
    </script>
</body>
</html>