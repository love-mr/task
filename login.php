<?php
// login.php
require_once 'db.php';
require_once 'jwt.php';

// If already logged in via JWT cookie, redirect to dashboard
$jwtToken = $_COOKIE['vyala_taskpad_jwt_token'] ?? '';
if (verify_jwt($jwtToken)) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';
$email = '';
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'signin';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($action === 'signup') {
        $name = trim($_POST['name'] ?? '');
        $role = "Member"; // Default role for self-signup

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'Full Name, Email, and Password are required for registration.';
        } else {
            try {
                // Check if email already exists
                $checkEmail = $pdo->prepare("SELECT COUNT(*) FROM `employees` WHERE `email` = ?");
                $checkEmail->execute([$email]);
                if ($checkEmail->fetchColumn() > 0) {
                    $error = 'Email is already registered. Please sign in instead.';
                } else {
                    // Generate sequential employee code
                    $latestCodeStmt = $pdo->query("SELECT emp_code FROM employees WHERE emp_code LIKE 'T-%' ORDER BY id DESC LIMIT 1");
                    $latestCode = $latestCodeStmt->fetchColumn();
                    if ($latestCode && preg_match('/T-(\d+)/', $latestCode, $matches)) {
                        $nextNum = (int)$matches[1] + 1;
                    } else {
                        $nextNum = 130556;
                    }
                    $empCode = 'T-' . $nextNum;

                    // Generate initials avatar
                    $parts = explode(' ', $name);
                    $avatar = '';
                    foreach ($parts as $p) {
                        if (!empty($p)) $avatar .= strtoupper(substr($p, 0, 1));
                    }
                    if (empty($avatar)) $avatar = 'U';
                    $avatar = substr($avatar, 0, 2);

                    // Insert employee
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO `employees` (`name`, `role`, `email`, `password`, `emp_code`, `avatar`, `status`) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
                    $stmt->execute([$name, $role, $email, $hashedPass, $empCode, $avatar]);

                    $success = 'Signup request submitted successfully! Please wait for admin approval.';
                    $email = '';
                    $name = '';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } else if ($action === 'admin_login') {
        // Admin Login logic
        if (empty($email) || empty($password)) {
            $error = 'Please enter admin username and password.';
        } else if ($email !== 'admin' || $password !== 'admin@123') {
            $error = 'Invalid admin credentials.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM `employees` WHERE `email` = 'admin'");
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $tokenPayload = [
                        'id'       => $user['id'],
                        'name'     => $user['name'],
                        'email'    => $user['email'],
                        'role'     => $user['role'],
                        'emp_code' => $user['emp_code'],
                        'org_id'   => 0   // 0 = platform admin, no org
                    ];
                    $jwt = generate_jwt($tokenPayload);
                    setcookie('vyala_taskpad_jwt_token', $jwt, time() + 86400, '/', '', false, true);
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = 'Admin account not found in database.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } else {
        // Sign in logic
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else if (strtolower($email) === 'admin') {
            $error = 'Please use the Admin Login tab to sign in as administrator.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT e.*, o.status as org_status FROM `employees` e LEFT JOIN `organizations` o ON e.org_id = o.id WHERE e.email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $empStatus = $user['status'] ?? 'Active';
                    $orgStatus = $user['org_status'] ?? 'Active';
                    if ($orgStatus === 'Pending') {
                        $error = 'Your organization is pending admin approval. Please wait.';
                    } else if ($orgStatus === 'Rejected') {
                        $error = 'Your organization access has been revoked. Contact support.';
                    } else if ($empStatus !== 'Approved' && $empStatus !== 'Active') {
                        $error = 'Your account is pending approval by your organization.';
                    } else {
                        $tokenPayload = [
                            'id'       => $user['id'],
                            'name'     => $user['name'],
                            'email'    => $user['email'],
                            'role'     => $user['role'],
                            'emp_code' => $user['emp_code'],
                            'org_id'   => (int)($user['org_id'] ?? 1)
                        ];
                        $jwt = generate_jwt($tokenPayload);
                        setcookie('vyala_taskpad_jwt_token', $jwt, time() + 86400, '/', '', false, true);
                        header("Location: dashboard.php");
                        exit;
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                // Fallback: organizations table may not exist yet
                try {
                    $stmt2 = $pdo->prepare("SELECT * FROM `employees` WHERE `email` = ?");
                    $stmt2->execute([$email]);
                    $user = $stmt2->fetch(PDO::FETCH_ASSOC);
                    if ($user && password_verify($password, $user['password'])) {
                        $tokenPayload = [
                            'id'       => $user['id'],
                            'name'     => $user['name'],
                            'email'    => $user['email'],
                            'role'     => $user['role'],
                            'emp_code' => $user['emp_code'],
                            'org_id'   => (int)($user['org_id'] ?? 1)
                        ];
                        $jwt = generate_jwt($tokenPayload);
                        setcookie('vyala_taskpad_jwt_token', $jwt, time() + 86400, '/', '', false, true);
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        $error = 'Invalid email or password.';
                    }
                } catch (PDOException $e2) {
                    $error = 'Database error: ' . $e2->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in - Vyala Software TaskPad</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #2563eb; /* Sky Blue */
            --primary-hover: #1d4ed8;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #cbd5e1;
            --font-sans: 'Outfit', sans-serif;
            --transition: all 0.2s ease-in-out;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background-color: #ffffff;
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        .login-page-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* Left Column: Blue Panel */
        .login-sidebar-blue {
            flex: 1;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            position: relative;
            border-top-right-radius: 40px;
            border-bottom-right-radius: 40px;
            max-width: 50%;
        }

        .blue-panel-graphic {
            width: 100%;
            max-width: 320px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            margin-bottom: 30px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .graphic-header {
            display: flex;
            align-items: center;
            gap: 6px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 8px;
        }

        .graphic-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .dot-red { background-color: #ef4444; }
        .dot-yellow { background-color: #eab308; }
        .dot-green { background-color: #22c55e; }

        .graphic-row {
            height: 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }
        .row-short { width: 40%; }
        .row-mid { width: 70%; }
        .row-long { width: 90%; }

        .graphic-chart {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 60px;
            margin-top: 8px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
            padding-bottom: 4px;
        }

        .chart-bar {
            width: 14px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 2px;
            transition: height 0.5s ease;
        }

        .blue-panel-title {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            text-align: center;
        }

        .blue-panel-sub {
            font-size: 14px;
            font-weight: 400;
            opacity: 0.9;
            text-align: center;
            margin-bottom: 30px;
            max-width: 320px;
            line-height: 1.5;
        }

        /* Tags grid inside sidebar */
        .sidebar-tags {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            max-width: 380px;
            margin-bottom: 40px;
        }

        .tag-pill {
            background-color: #ffffff;
            color: #2563eb;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .tag-pill.outline {
            background-color: transparent;
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: none;
        }

        .blue-panel-footer {
            font-size: 18px;
            font-weight: 700;
            font-style: italic;
            letter-spacing: 0.5px;
        }

        /* Right Column: Form Area */
        .login-form-area {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            background-color: #ffffff;
            min-height: 100vh;
        }

        .form-header {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 20px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 6px;
            user-select: none;
            margin-bottom: 4px;
        }

        .logo-task {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }

        .logo-check-svg {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-pad {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .logo-subtext {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: var(--text-muted);
        }

        .form-body-wrapper {
            width: 100%;
            max-width: 360px;
            margin: 40px 0;
        }

        .form-title-block {
            margin-bottom: 24px;
        }

        .form-title-block h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .form-title-block p {
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .alert-error {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: left;
        }

        .alert-error i {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        .input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-container i.field-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            width: 18px;
            height: 18px;
            pointer-events: none;
        }

        .input-container i.toggle-password {
            position: absolute;
            right: 14px;
            color: #94a3b8;
            width: 18px;
            height: 18px;
            cursor: pointer;
            transition: var(--transition);
        }

        .input-container i.toggle-password:hover {
            color: var(--text-dark);
        }

        .form-input {
            width: 100%;
            height: 46px;
            padding: 10px 45px 10px 45px;
            font-family: var(--font-sans);
            font-size: 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            outline: none;
            background-color: #f8fafc;
            color: var(--text-dark);
            transition: var(--transition);
        }

        .form-input:focus {
            border-color: var(--primary);
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-options {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .forgot-link {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .forgot-link:hover {
            color: var(--primary-hover);
        }

        .btn-signin {
            width: 100%;
            height: 44px;
            background-color: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-family: var(--font-sans);
            font-size: 14.5px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
        }

        .btn-signin:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 6px 14px rgba(37, 99, 235, 0.3);
        }

        .signup-block {
            margin-top: 24px;
            text-align: center;
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .signup-block a {
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
        }

        .signup-block a:hover {
            text-decoration: underline;
        }

        /* Footer links styling */
        .form-footer {
            display: flex;
            gap: 16px;
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        .form-footer a {
            color: var(--text-muted);
            text-decoration: none;
            transition: var(--transition);
        }

        .form-footer a:hover {
            color: var(--text-dark);
        }

        /* Demo credentials display */
        .demo-guide {
            margin-top: 20px;
            background-color: #f0f9ff;
            border: 1px dashed #bae6fd;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            color: #0369a1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .demo-guide span { font-weight: 600; user-select: all; }

        /* Mobile responsiveness styles */
        @media (max-width: 900px) {
            .login-sidebar-blue {
                display: none;
            }
            .login-form-area {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="login-page-container">
        
        <!-- Left Side: Branded Blue Feature Bar -->
        <div class="login-sidebar-blue">
            <div class="blue-panel-graphic">
                <div class="graphic-header">
                    <span class="graphic-dot dot-red"></span>
                    <span class="graphic-dot dot-yellow"></span>
                    <span class="graphic-dot dot-green"></span>
                </div>
                <div class="graphic-row row-long"></div>
                <div class="graphic-row row-mid"></div>
                <div class="graphic-chart">
                    <div class="chart-bar" style="height: 30%;"></div>
                    <div class="chart-bar" style="height: 55%;"></div>
                    <div class="chart-bar" style="height: 40%;"></div>
                    <div class="chart-bar" style="height: 80%;"></div>
                    <div class="chart-bar" style="height: 65%;"></div>
                </div>
                <div class="graphic-row row-short"></div>
            </div>
            
            <h1 class="blue-panel-title">Welcome to Vyala Task Pad</h1>
            <p class="blue-panel-sub">You're Just One Step Away from Peak Productivity!</p>
            
            <div class="sidebar-tags">
                <span class="tag-pill">Task Management</span>
                <span class="tag-pill">Project Management</span>
                <span class="tag-pill">Time Tracking</span>
                <span class="tag-pill outline">Workflow Management</span>
                <span class="tag-pill outline">Document Management System</span>
                <span class="tag-pill outline">Leave Management</span>
                <span class="tag-pill outline">Attendance Tracking</span>
            </div>
            
            <div class="blue-panel-footer">All in ONE place...!!!</div>
        </div>

        <!-- Right Side: Credentials Login Box -->
        <div class="login-form-area">
            
            <!-- Logo Header -->
            <div class="form-header">
                <div class="brand-logo">
                    <div class="logo-check-svg">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" fill="#eff6ff"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>
                    </div>
                    <span class="logo-task">Vyala Software <span class="logo-pad">TaskPad</span></span>
                </div>
                <div class="logo-subtext">BE ORGANIZED</div>
            </div>

            <!-- Main Input Form Body -->
            <div class="form-body-wrapper">
                <!-- Segmented Control Tabs -->
                <div style="display: flex; gap: 4px; background: #f1f5f9; border-radius: 8px; padding: 4px; margin-bottom: 24px; border: 1px solid #e2e8f0;">
                    <button type="button" class="tab-btn active" id="tab-login" style="flex: 1; border: none; background: #ffffff; padding: 8px 12px; font-family: inherit; font-size: 13px; font-weight: 600; color: #0f172a; border-radius: 6px; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">User Login</button>
                    <button type="button" class="tab-btn" id="tab-signup" style="flex: 1; border: none; background: transparent; padding: 8px 12px; font-family: inherit; font-size: 13px; font-weight: 600; color: #64748b; border-radius: 6px; cursor: pointer; transition: all 0.2s;">User Signup</button>
                    <button type="button" class="tab-btn" id="tab-admin" style="flex: 1; border: none; background: transparent; padding: 8px 12px; font-family: inherit; font-size: 13px; font-weight: 600; color: #64748b; border-radius: 6px; cursor: pointer; transition: all 0.2s;">Admin Login</button>
                </div>

                <div class="form-title-block">
                    <h2>Sign in</h2>
                    <p>Enter your credentials to access your account.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert-error">
                        <i data-lucide="alert-circle"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert-error" style="background-color: #dcfce7; border: 1px solid #bbf7d0; color: #15803d;">
                        <i data-lucide="check-circle"></i>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" id="auth-form">
                    <input type="hidden" name="action" id="auth-action" value="signin">
                    
                    <div class="form-group" id="group-name" style="display: none;">
                        <div class="input-container">
                            <i data-lucide="user" class="field-icon"></i>
                            <input type="text" name="name" id="name" class="form-input" placeholder="Full Name" value="<?= htmlspecialchars($name) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-container">
                            <i data-lucide="mail" class="field-icon"></i>
                            <input type="text" name="email" id="email" class="form-input" placeholder="Email or Username" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-container">
                            <i data-lucide="lock" class="field-icon"></i>
                            <input type="password" name="password" id="password" class="form-input" placeholder="Password" required>
                            <i data-lucide="eye" class="toggle-password" id="btn-toggle-pass"></i>
                        </div>
                    </div>

                    <div class="form-options">
                        <a href="#" class="forgot-link">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn-signin">Sign In</button>
                </form>

                <div class="signup-block">
                    Don't have an account? <a href="#" id="toggle-signup-btn">Sign up Now</a>
                </div>
                <div class="signup-block" style="margin-top: 8px;">
                    Want to register your organization? <a href="register.php" style="color: var(--primary); font-weight: 600;">Register Company</a>
                </div>
            </div>

            <!-- Bottom Page Footer -->
            <div class="form-footer">
                <a href="#">Support</a> | 
                <a href="#">Resources</a> | 
                <a href="#">Guide</a> | 
                <a href="#">Pricing</a> | 
                <a href="#">Terms</a> | 
                <a href="#">Privacy</a>
            </div>

        </div>

    </div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Toggle password visibility handler
        const togglePassBtn = document.getElementById('btn-toggle-pass');
        const passInput = document.getElementById('password');
        if (togglePassBtn && passInput) {
            togglePassBtn.addEventListener('click', function() {
                const isPassType = passInput.type === 'password';
                passInput.type = isPassType ? 'text' : 'password';
                
                // Toggle icon
                togglePassBtn.setAttribute('data-lucide', isPassType ? 'eye-off' : 'eye');
                lucide.createIcons();
            });
        }

        // Tab styling state updater
        function updateTabState(activeId) {
            const tabIds = ['tab-login', 'tab-signup', 'tab-admin'];
            tabIds.forEach(id => {
                const tab = document.getElementById(id);
                if (tab) {
                    if (id === activeId) {
                        tab.style.background = '#ffffff';
                        tab.style.color = '#0f172a';
                        tab.style.boxShadow = '0 1px 3px rgba(0,0,0,0.1)';
                        tab.classList.add('active');
                    } else {
                        tab.style.background = 'transparent';
                        tab.style.color = '#64748b';
                        tab.style.boxShadow = 'none';
                        tab.classList.remove('active');
                    }
                }
            });
        }

        // Form switcher functions
        function showSignUp() {
            document.getElementById('auth-action').value = 'signup';
            document.getElementById('group-name').style.display = 'block';
            document.getElementById('name').required = true;
            
            document.getElementById('email').placeholder = 'Email Address';
            
            document.querySelector('.form-title-block h2').textContent = 'Sign up';
            document.querySelector('.form-title-block p').textContent = 'Create a new account to get started.';
            document.querySelector('.btn-signin').textContent = 'Sign Up';
            
            const signupBlock = document.querySelector('.signup-block');
            if (signupBlock) {
                signupBlock.style.display = 'block';
                signupBlock.childNodes[0].textContent = 'Already have an account? ';
            }
            const toggleBtn = document.getElementById('toggle-signup-btn');
            if (toggleBtn) toggleBtn.textContent = 'Sign in Now';
            
            document.querySelector('.demo-guide')?.style.setProperty('display', 'none');
            
            updateTabState('tab-signup');
        }

        function showSignIn() {
            document.getElementById('auth-action').value = 'signin';
            document.getElementById('group-name').style.display = 'none';
            document.getElementById('name').required = false;
            
            document.getElementById('email').placeholder = 'Email Address';
            
            document.querySelector('.form-title-block h2').textContent = 'Sign in';
            document.querySelector('.form-title-block p').textContent = 'Enter your credentials to access your account.';
            document.querySelector('.btn-signin').textContent = 'Sign In';
            
            const signupBlock = document.querySelector('.signup-block');
            if (signupBlock) {
                signupBlock.style.display = 'block';
                signupBlock.childNodes[0].textContent = 'Don\'t have an account? ';
            }
            const toggleBtn = document.getElementById('toggle-signup-btn');
            if (toggleBtn) toggleBtn.textContent = 'Sign up Now';
            
            document.querySelector('.demo-guide')?.style.setProperty('display', 'flex');
            
            updateTabState('tab-login');
        }

        function showAdminLogin() {
            document.getElementById('auth-action').value = 'admin_login';
            document.getElementById('group-name').style.display = 'none';
            document.getElementById('name').required = false;
            
            document.getElementById('email').placeholder = 'Admin Username';
            
            document.querySelector('.form-title-block h2').textContent = 'Admin Sign in';
            document.querySelector('.form-title-block p').textContent = 'Sign in as System Administrator.';
            document.querySelector('.btn-signin').textContent = 'Admin Sign In';
            
            const signupBlock = document.querySelector('.signup-block');
            if (signupBlock) signupBlock.style.display = 'none';
            
            document.querySelector('.demo-guide')?.style.setProperty('display', 'none');
            
            updateTabState('tab-admin');
        }

        // Add event listeners to segmented tab buttons
        document.getElementById('tab-login')?.addEventListener('click', showSignIn);
        document.getElementById('tab-signup')?.addEventListener('click', showSignUp);
        document.getElementById('tab-admin')?.addEventListener('click', showAdminLogin);

        // Footer toggle button link helper
        const signupToggleLink = document.getElementById('toggle-signup-btn');
        if (signupToggleLink) {
            signupToggleLink.addEventListener('click', function(e) {
                e.preventDefault();
                const currentAction = document.getElementById('auth-action').value;
                if (currentAction === 'signin') {
                    showSignUp();
                } else {
                    showSignIn();
                }
            });
        }

        // Check query param or hash on load
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (
                urlParams.get('tab') === 'signup' ||
                urlParams.get('mode') === 'signup' ||
                window.location.hash === '#signup'
            ) {
                showSignUp();
            } else if (
                urlParams.get('tab') === 'admin' ||
                window.location.hash === '#admin'
            ) {
                showAdminLogin();
            } else {
                showSignIn();
            }
        });
    </script>
</body>
</html>



