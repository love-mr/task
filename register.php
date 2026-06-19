<?php
// register.php
require_once 'db.php';

$error = '';
$success = '';
$companyName = '';
$name = '';
$email = '';
$phone = '';
$address = '';
$details = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim($_POST['company_name'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $details = trim($_POST['details'] ?? '');

    if (empty($companyName) || empty($name) || empty($email) || empty($password) || empty($confirmPassword) || empty($phone) || empty($address)) {
        $error = 'All fields except details are required.';
    } else if ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check if organization slug or name already exists
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $companyName), '-'));
            $checkOrg = $pdo->prepare("SELECT COUNT(*) FROM `organizations` WHERE `name` = ? OR `slug` = ?");
            $checkOrg->execute([$companyName, $slug]);
            if ($checkOrg->fetchColumn() > 0) {
                $error = 'Organization name or slug is already taken.';
            } else {
                // Check if email already exists
                $checkEmail = $pdo->prepare("SELECT COUNT(*) FROM `employees` WHERE `email` = ?");
                $checkEmail->execute([$email]);
                if ($checkEmail->fetchColumn() > 0) {
                    $error = 'Email is already registered. Please sign in instead.';
                } else {
                    // Create organization with Pending status
                    $stmtOrg = $pdo->prepare("INSERT INTO `organizations` (`name`, `slug`, `status`, `phone`, `address`, `details`) VALUES (?, ?, 'Pending', ?, ?, ?)");
                    $stmtOrg->execute([$companyName, $slug, $phone, $address, $details]);
                    $orgId = $pdo->lastInsertId();

                    // Clean up any potential orphan records matching this organization ID (e.g. from manually deleted testing database entries)
                    try {
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                    } catch (PDOException $ex) {}

                    try {
                        $pdo->prepare("DELETE FROM `discussion_messages` WHERE `discussion_id` IN (SELECT `id` FROM `discussions` WHERE `org_id` = ?)")->execute([$orgId]);
                    } catch (PDOException $ex) {}
                    try {
                        $pdo->prepare("DELETE FROM `discussion_members` WHERE `discussion_id` IN (SELECT `id` FROM `discussions` WHERE `org_id` = ?)")->execute([$orgId]);
                    } catch (PDOException $ex) {}
                    try {
                        $pdo->prepare("DELETE FROM `project_members` WHERE `project_id` IN (SELECT `id` FROM `projects` WHERE `org_id` = ?)")->execute([$orgId]);
                    } catch (PDOException $ex) {}

                    $cleanupTables = ['employees', 'projects', 'tasks', 'timesheets', 'documents', 'discussions', 'clients', 'pin_notes', 'notifications', 'activities', 'attendance', 'buildings', 'single_plots', 'ual_records', 'land_surveys'];
                    foreach ($cleanupTables as $tbl) {
                        try {
                            $pdo->prepare("DELETE FROM `$tbl` WHERE `org_id` = ?")->execute([$orgId]);
                        } catch (PDOException $ex) {}
                    }

                    try {
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                    } catch (PDOException $ex) {}

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
                    if (empty($avatar)) $avatar = 'PL';
                    $avatar = substr($avatar, 0, 2);

                    // Insert employee (Project Lead) with Pending status
                    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                    $stmtEmp = $pdo->prepare("INSERT INTO `employees` (`name`, `role`, `email`, `password`, `emp_code`, `avatar`, `status`, `org_id`) VALUES (?, 'Project Lead', ?, ?, ?, ?, 'Pending', ?)");
                    $stmtEmp->execute([$name, $email, $hashedPass, $empCode, $avatar, $orgId]);

                    $success = 'Organization and Project Lead registered successfully! Please wait for system admin approval.';
                    $companyName = '';
                    $name = '';
                    $email = '';
                    $phone = '';
                    $address = '';
                    $details = '';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Organization - Vyala Software TaskPad</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #2563eb;
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
            color: #0f172a;
            margin-bottom: 6px;
        }

        .form-title-block p {
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .field-icon {
            position: absolute;
            left: 14px;
            width: 18px;
            height: 18px;
            color: var(--text-muted);
            pointer-events: none;
        }

        .form-input {
            width: 100%;
            height: 46px;
            padding: 12px 14px 12px 42px;
            font-family: inherit;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            outline: none;
            transition: var(--transition);
        }

        .form-input:focus {
            background-color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            width: 18px;
            height: 18px;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition);
        }

        .toggle-password:hover {
            color: #0f172a;
        }

        .btn-signin {
            width: 100%;
            height: 46px;
            border: none;
            background-color: var(--primary);
            color: #ffffff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 8px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }

        .btn-signin:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .signup-block {
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-muted);
            text-align: center;
        }

        .signup-block a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }

        .signup-block a:hover {
            text-decoration: underline;
        }

        .alert-error {
            background-color: #fef2f2;
            border: 1px solid #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .alert-error i {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
        }

        .form-footer {
            width: 100%;
            font-size: 12px;
            color: var(--text-muted);
            text-align: center;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
            margin-top: auto;
        }

        .form-footer a {
            color: var(--text-muted);
            text-decoration: none;
            margin: 0 4px;
        }

        .form-footer a:hover {
            color: #0f172a;
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            .login-page-container {
                flex-direction: column;
            }
            .login-sidebar-blue {
                max-width: 100%;
                border-top-right-radius: 0;
                border-bottom-right-radius: 30px;
                border-bottom-left-radius: 30px;
                padding: 40px 20px;
            }
            .login-form-area {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-page-container">
        
        <!-- Left Side Pane -->
        <div class="login-sidebar-blue">
            <div class="blue-panel-graphic">
                <div class="graphic-header">
                    <div class="graphic-dot dot-red"></div>
                    <div class="graphic-dot dot-yellow"></div>
                    <div class="graphic-dot dot-green"></div>
                </div>
                <div class="graphic-row row-long"></div>
                <div class="graphic-row row-mid"></div>
                <div class="graphic-chart">
                    <div class="chart-bar" style="height: 40%;"></div>
                    <div class="chart-bar" style="height: 85%;"></div>
                    <div class="chart-bar" style="height: 60%;"></div>
                </div>
                <div class="graphic-row row-short"></div>
            </div>
            
            <h1 class="blue-panel-title">Vyala Task Pad SaaS</h1>
            <p class="blue-panel-sub">Create a new workspace for your organization and manage projects, tasks and teams in one clean interface.</p>
            
            <div class="sidebar-tags">
                <span class="tag-pill">Company Account</span>
                <span class="tag-pill">Organization Isolation</span>
                <span class="tag-pill">Team Collaboration</span>
                <span class="tag-pill outline">Secure & Scalable</span>
            </div>
            
            <div class="blue-panel-footer">Collaborate Effortlessly</div>
        </div>

        <!-- Right Side: Registration Form -->
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
                <div class="logo-subtext">REGISTER ORGANIZATION</div>
            </div>

            <!-- Main Input Form Body -->
            <div class="form-body-wrapper">
                <div class="form-title-block">
                    <h2>Register your Company</h2>
                    <p>Setup a new tenant workspace and your Project Lead admin account.</p>
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

                <form action="register.php" method="POST">
                    
                    <div class="form-group">
                        <div class="input-container">
                            <i data-lucide="building" class="field-icon"></i>
                            <input type="text" name="company_name" id="company_name" class="form-input" placeholder="Company Name (e.g. Good3)" value="<?= htmlspecialchars($companyName) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-container">
                            <i data-lucide="user" class="field-icon"></i>
                            <input type="text" name="name" id="name" class="form-input" placeholder="Project Lead Name" value="<?= htmlspecialchars($name) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-container">
                            <i data-lucide="mail" class="field-icon"></i>
                            <input type="email" name="email" id="email" class="form-input" placeholder="Email Address" value="<?= htmlspecialchars($email) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-container">
                            <i data-lucide="phone" class="field-icon"></i>
                            <input type="tel" name="phone" id="phone" class="form-input" placeholder="Organization Phone Number" value="<?= htmlspecialchars($phone) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-container">
                            <i data-lucide="map-pin" class="field-icon"></i>
                            <input type="text" name="address" id="address" class="form-input" placeholder="Organization Address" value="<?= htmlspecialchars($address) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-container">
                            <i data-lucide="info" class="field-icon"></i>
                            <input type="text" name="details" id="details" class="form-input" placeholder="Organization Details" value="<?= htmlspecialchars($details) ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-container">
                            <i data-lucide="lock" class="field-icon"></i>
                            <input type="password" name="password" id="password" class="form-input" placeholder="Password" required>
                            <i data-lucide="eye" class="toggle-password" id="btn-toggle-pass"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="input-container">
                            <i data-lucide="lock" class="field-icon"></i>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Confirm Password" required>
                            <i data-lucide="eye" class="toggle-password" id="btn-toggle-confirm-pass"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-signin">Register & Submit</button>
                </form>

                <div class="signup-block">
                    Already registered? <a href="login.php">Sign in Here</a>
                </div>
            </div>

            <!-- Bottom Page Footer -->
            <div class="form-footer" style="display: flex; flex-direction: column; gap: 8px; align-items: center;">
                <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                    <a href="#">Support</a> | 
                    <a href="#">Pricing</a> | 
                    <a href="#">Terms</a> | 
                    <a href="#">Privacy</a>
                </div>
                <div style="margin-top: 6px; font-size: 11px; color: var(--text-muted);">
                    &copy; 2026 Vyala Software TaskPad. All rights reserved. Software Version 2.0.0
                </div>
            </div>

        </div>

    </div>

    <script>
        // Initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Password visibility toggle logic
        const togglePassBtn = document.getElementById('btn-toggle-pass');
        const passInput = document.getElementById('password');
        if (togglePassBtn && passInput) {
            togglePassBtn.addEventListener('click', function() {
                const type = passInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passInput.setAttribute('type', type);
                togglePassBtn.setAttribute('data-lucide', type === 'password' ? 'eye' : 'eye-off');
                lucide.createIcons();
            });
        }

        const toggleConfirmPassBtn = document.getElementById('btn-toggle-confirm-pass');
        const confirmPassInput = document.getElementById('confirm_password');
        if (toggleConfirmPassBtn && confirmPassInput) {
            toggleConfirmPassBtn.addEventListener('click', function() {
                const type = confirmPassInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPassInput.setAttribute('type', type);
                toggleConfirmPassBtn.setAttribute('data-lucide', type === 'password' ? 'eye' : 'eye-off');
                lucide.createIcons();
            });
        }
    </script>
</body>
</html>
