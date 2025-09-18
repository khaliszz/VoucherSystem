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
    <title>Create Account or Sign In</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Montserrat:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4b006e;
            --secondary-color: #d661a8;
            --text-color-dark: #1f2937;
            --text-color-light: #ffffff;
            --text-color-secondary: #6b7280;
            --border-color: #e5e7eb;
            --form-bg-color: #ffffff;
            --error-bg: #fee2e2;
            --error-text: #b91c1c;
            --success-bg: #dcfce7;
            --success-text: #166534;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(110deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            padding: 2rem;
        }

        .main-container {
            width: 100%;
            max-width: 1100px;
            display: flex;
            background-color: var(--form-bg-color);
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }

        .welcome-panel {
            flex-basis: 45%;
            background-color: var(--primary-color);
            padding: 5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: var(--text-color-light);
            text-align: center;
        }

        .welcome-logo {
            max-width: 350px;
            margin: 0 auto 3rem;
        }

        .welcome-panel h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .welcome-panel p {
            font-size: 1.2rem;
            line-height: 1.7;
            max-width: 400px;
            margin: 0 auto;
        }

        .form-panel-wrapper {
            flex-basis: 55%;
            padding: 3rem 4rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: min-height 0.4s ease-in-out;
        }
        
        .form-content-wrapper {
            width: 100%;
        }

        .form-panel-wrapper h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-color-dark);
            margin-bottom: 2rem;
        }

        .auth-tabs { display: flex; gap: 2rem; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); }
        .auth-tabs .tab { padding-bottom: 1rem; font-size: 1.1rem; font-weight: 500; color: var(--text-color-secondary); cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .auth-tabs .tab.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        
        .form-panel { display: none; }
        .form-panel.active { display: block; animation: fadeInForm 0.5s; }
        @keyframes fadeInForm { from { opacity: 0; } to { opacity: 1; } }
        
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-weight: 500; color: var(--text-color-dark); margin-bottom: 0.5rem; font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 0.9rem 1rem; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 0.5rem; transition: border-color 0.3s, box-shadow 0.3s; }
        .form-group input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(106, 90, 249, 0.15); }

        .form-options { text-align: right; margin: 0.75rem 0 1.5rem; }
        .form-options a { color: var(--primary-color); text-decoration: none; font-size: 0.9rem; font-weight: 500; }
        .form-options a:hover { text-decoration: underline; }
        
        .btn { width: 100%; padding: 1rem; font-size: 1rem; font-weight: 500; border-radius: 0.5rem; cursor: pointer; border: none; transition: all 0.3s; }
        .btn:disabled { background-color: #a399f5; cursor: not-allowed; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(106, 90, 249, 0.2); }
        
        .divider { text-align: center; color: var(--text-color-secondary); margin: 1.5rem 0; font-size: 0.9rem; }
        .btn-secondary { background-color: transparent; color: var(--text-color-dark); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; gap: 0.75rem; }
        .btn-secondary:hover { border-color: #9ca3af; background-color: #f9fafb; }
        .btn-secondary img { width: 20px; }
        
        #password-match-msg { font-size: 0.9rem; color: var(--error-text); margin-top: 0.5rem; display: none; }
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.5rem; font-size: 1rem; text-align: center; }
        .message.error { background-color: var(--error-bg); color: var(--error-text); }
        .message.success { background-color: var(--success-bg); color: var(--success-text); }

        @media (max-width: 992px) {
            body { padding: 0; align-items: flex-start; }
            .main-container { flex-direction: column; border-radius: 0; box-shadow: none; min-height: 100vh; }
            .welcome-panel { padding: 4rem 2rem; flex-basis: auto; }
            .form-panel-wrapper { flex-basis: auto; justify-content: flex-start; padding: 3rem 2rem; flex-grow: 1; }
        }
    </style>
</head>
<body>
    
    <div class="main-container">
        
        <div class="welcome-panel">
            <img src="images/logo.png" alt="OptimaBank Logo" class="welcome-logo">
            <h3>We appreciate our customers.</h3>
        </div>

        <div class="form-panel-wrapper" id="form-wrapper">
            <div class="form-content-wrapper" id="form-content">
                <h2>Welcome !</h2>
                <div class="auth-tabs">
                    <div class="tab active" data-tab="signin">Sign In</div>
                    <div class="tab" data-tab="signup">Sign Up</div>
                </div>

                <?php echo $message; // Display success/error messages here ?>

                <div id="panel-signin" class="form-panel active">
                    <!-- Logic from second file: action="login_process.php" -->
                    <form action="login_process.php" method="POST">
                        <div class="form-group">
                            <label for="login-email">Email Address</label>
                            <input type="email" id="login-email" name="email" placeholder="name@example.com" required>
                        </div>
                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <input type="password" id="login-password" name="password" placeholder="••••••••" required>
                        </div>
                        <div class="form-options">
                            <a href="#">Forgot your password?</a>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary">Sign In</button>
                    </form>
                </div>

                <div id="panel-signup" class="form-panel">
                    <!-- Logic from second file: action="signup.php" -->
                    <form action="signup.php" method="POST" id="signup-form">
                        <div class="form-group">
                            <label for="signup-username">Username</label>
                            <input type="text" id="signup-username" name="username" placeholder="Choose a username" required>
                        </div>
                        <div class="form-group">
                            <label for="signup-email">Email Address</label>
                            <input type="email" id="signup-email" name="email" placeholder="name@example.com" required>
                        </div>
                        <div class="form-group">
                            <label for="signup-password">Password</label>
                            <input type="password" id="signup-password" name="password" placeholder="Create a strong password" required>
                        </div>
                        <div class="form-group">
                            <label for="signup-confirm-password">Confirm Password</label>
                            <input type="password" id="signup-confirm-password" name="confirm_password" placeholder="Confirm your password" required>
                            <div id="password-match-msg">Passwords do not match.</div>
                        </div>
                        <button type="submit" id="signup-btn" class="btn btn-primary">Create Account</button>
                    </form>
                </div>

                <div class="divider">or</div>
                
                <!-- Logic from second file: PHP variable for Google login URL -->
                <a href="<?php echo htmlspecialchars($googleLoginUrl); ?>" style="text-decoration: none;">
                    <button type="button" class="btn btn-secondary">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" alt="Google Icon">
                        Continue with Google
                    </button>
                </a>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('.auth-tabs .tab');
            const panels = document.querySelectorAll('.form-panel');
            const formWrapper = document.getElementById('form-wrapper');
            const formContent = document.getElementById('form-content');

            function setFrameHeight() {
                const activePanel = document.querySelector('.form-panel.active');
                if (activePanel) {
                    const contentHeight = formContent.scrollHeight;
                    formWrapper.style.minHeight = `${contentHeight}px`;
                }
            }
            
            setTimeout(setFrameHeight, 50);

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabType = tab.dataset.tab;
                    document.querySelector('.form-panel.active').classList.remove('active');
                    document.getElementById('panel-' + tabType).classList.add('active');
                    
                    document.querySelector('.tab.active').classList.remove('active');
                    tab.classList.add('active');
                    setFrameHeight();
                });
            });
            
            // Logic to switch tab based on URL parameter (e.g., login.php?show=signup)
            const showParam = new URLSearchParams(window.location.search).get('show');
            if (showParam === 'signup') {
                document.querySelector('.tab[data-tab="signup"]').click();
            }

            // Real-time password validation logic
            const signupForm = document.getElementById('signup-form');
            const passwordInput = document.getElementById('signup-password');
            const confirmPasswordInput = document.getElementById('signup-confirm-password');
            const passwordMatchMsg = document.getElementById('password-match-msg');
            const signupBtn = document.getElementById('signup-btn');

            function validatePasswords() {
                if (passwordInput.value !== confirmPasswordInput.value && confirmPasswordInput.value.length > 0) {
                    passwordMatchMsg.style.display = 'block';
                    signupBtn.disabled = true;
                } else {
                    passwordMatchMsg.style.display = 'none';
                    signupBtn.disabled = false;
                }
            }

            passwordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);

            signupForm.addEventListener('submit', function(event) {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    event.preventDefault();
                    validatePasswords();
                }
            });
            
            window.addEventListener('resize', setFrameHeight);
        });
    </script>
</body>
</html>