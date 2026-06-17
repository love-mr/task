<?php
$pageTitle  = 'User Guide & Documentation - Vyala Software TaskPad';
$metaDesc   = 'Step-by-step user guide and documentation for Vyala Software TaskPad. Learn how to set up, manage tasks, projects, reports and more.';
$currentPage = 'guide';
require_once 'includes/page_header.php';
?>
<style>
    main { flex: 1; background: #f9fafb; }

    .guide-layout {
        display: grid;
        grid-template-columns: 260px 1fr;
        max-width: 1200px;
        margin: 0 auto;
        gap: 0;
        min-height: calc(100vh - 68px);
    }

    /* Sidebar */
    .guide-sidebar {
        background: #fff;
        border-right: 1px solid #e5e7eb;
        padding: 32px 20px;
        position: sticky;
        top: 68px;
        height: calc(100vh - 68px);
        overflow-y: auto;
    }
    .guide-sidebar h3 {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 1.5px; color: #9ca3af; margin-bottom: 14px; padding-left: 8px;
    }
    .guide-nav { list-style: none; display: flex; flex-direction: column; gap: 2px; margin-bottom: 24px; }
    .guide-nav a {
        display: flex; align-items: center; gap: 10px;
        padding: 9px 12px; border-radius: 8px; font-size: 13.5px; font-weight: 500;
        color: #374151; text-decoration: none; transition: all .2s;
    }
    .guide-nav a i { width: 15px; height: 15px; flex-shrink: 0; }
    .guide-nav a:hover, .guide-nav a.active {
        background: #eff6ff; color: #2563eb; font-weight: 600;
    }
    .guide-nav a span.badge {
        margin-left: auto; background: #2563eb; color: #fff;
        font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 10px;
    }

    /* Content area */
    .guide-content { padding: 48px 56px; }

    .guide-hero {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        border-radius: 20px; padding: 40px 48px;
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 48px; gap: 24px;
    }
    .guide-hero-text h1 { font-size: 30px; font-weight: 800; color: #fff; margin-bottom: 10px; }
    .guide-hero-text p { font-size: 15px; color: rgba(255,255,255,0.85); line-height: 1.6; }
    .guide-hero-icon { font-size: 80px; opacity: 0.25; color: #fff; flex-shrink: 0; }
    .guide-hero-icon i { width: 80px; height: 80px; }

    /* Section cards */
    .section-card {
        background: #fff; border: 1px solid #e5e7eb;
        border-radius: 16px; padding: 36px 40px;
        margin-bottom: 28px; scroll-margin-top: 84px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .section-header {
        display: flex; align-items: center; gap: 16px; margin-bottom: 24px;
        padding-bottom: 20px; border-bottom: 1px solid #f1f5f9;
    }
    .section-num {
        width: 44px; height: 44px; border-radius: 12px;
        background: #eff6ff; color: #2563eb;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; font-weight: 800; flex-shrink: 0;
    }
    .section-icon-wrap {
        width: 44px; height: 44px; border-radius: 12px;
        background: #eff6ff; color: #2563eb;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .section-icon-wrap i { width: 22px; height: 22px; }
    .section-header h2 { font-size: 20px; font-weight: 800; color: #111827; }
    .section-header p { font-size: 13.5px; color: #6b7280; margin-top: 3px; }

    /* Steps */
    .steps { display: flex; flex-direction: column; gap: 14px; }
    .step {
        display: flex; gap: 16px; align-items: flex-start;
        background: #f9fafb; border: 1px solid #f1f5f9;
        border-radius: 10px; padding: 16px 20px;
    }
    .step-num {
        width: 28px; height: 28px; border-radius: 50%;
        background: #2563eb; color: #fff;
        font-size: 12px; font-weight: 700;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .step-body h4 { font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 4px; }
    .step-body p { font-size: 13px; color: #6b7280; line-height: 1.55; }

    /* Feature grid */
    .feature-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 14px; margin-top: 8px;
    }
    .feature-item {
        background: #f8fafc; border: 1px solid #e5e7eb;
        border-radius: 10px; padding: 16px;
        display: flex; align-items: flex-start; gap: 12px;
    }
    .feature-item i { width: 18px; height: 18px; color: #2563eb; flex-shrink: 0; margin-top: 2px; }
    .feature-item span { font-size: 13.5px; font-weight: 500; color: #374151; }

    /* Info box */
    .info-box {
        display: flex; gap: 12px; align-items: flex-start;
        background: #eff6ff; border: 1px solid #bfdbfe;
        border-radius: 10px; padding: 16px 18px; margin-top: 16px;
    }
    .info-box i { width: 18px; height: 18px; color: #2563eb; flex-shrink: 0; margin-top: 2px; }
    .info-box p { font-size: 13px; color: #1e40af; line-height: 1.55; }

    /* Security table */
    .sec-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .sec-table th { background: #f1f5f9; padding: 10px 14px; text-align: left; font-size: 12.5px; font-weight: 700; color: #374151; border: 1px solid #e5e7eb; }
    .sec-table td { padding: 10px 14px; font-size: 13px; color: #374151; border: 1px solid #e5e7eb; }
    .sec-table tr:nth-child(even) td { background: #f9fafb; }
    .check-yes { color: #16a34a; font-weight: 700; }
    .check-no  { color: #dc2626; font-weight: 700; }

    @media (max-width: 900px) {
        .guide-layout { grid-template-columns: 1fr; }
        .guide-sidebar { display: none; }
        .guide-content { padding: 28px 20px; }
        .guide-hero { flex-direction: column; padding: 28px 24px; }
        .section-card { padding: 24px 20px; }
    }
</style>

<main>
    <div class="guide-layout">
        <!-- Sidebar Nav -->
        <aside class="guide-sidebar">
            <h3>Contents</h3>
            <ul class="guide-nav">
                <li><a href="#overview" class="active"><i data-lucide="layout-dashboard"></i> System Overview</a></li>
                <li><a href="#registration"><i data-lucide="user-plus"></i> User Registration</a></li>
                <li><a href="#login"><i data-lucide="log-in"></i> Login Process</a></li>
                <li><a href="#dashboard"><i data-lucide="bar-chart-2"></i> Dashboard Usage</a></li>
                <li><a href="#tasks"><i data-lucide="check-square"></i> Task Management</a></li>
                <li><a href="#projects"><i data-lucide="folder"></i> Project Management</a></li>
                <li><a href="#admin"><i data-lucide="shield"></i> Admin Features</a></li>
                <li><a href="#reports"><i data-lucide="file-text"></i> Reports & Analytics</a></li>
                <li><a href="#security"><i data-lucide="lock"></i> Security Features</a></li>
            </ul>
            <h3>Quick Actions</h3>
            <ul class="guide-nav">
                <li><a href="login.php"><i data-lucide="log-in"></i> Login to App</a></li>
                <li><a href="login.php?tab=signup"><i data-lucide="user-plus"></i> Create Account</a></li>
                <li><a href="demo.php"><i data-lucide="calendar"></i> Book a Demo <span class="badge">Free</span></a></li>
                <li><a href="contact.php"><i data-lucide="headphones"></i> Get Support</a></li>
            </ul>
        </aside>

        <!-- Main content -->
        <div class="guide-content">
            <!-- Hero Banner -->
            <div class="guide-hero">
                <div class="guide-hero-text">
                    <h1>📖 Vyala Software TaskPad - User Guide</h1>
                    <p>Everything you need to know to get started with TaskPad. Follow the steps below to manage your tasks, projects and team effectively.</p>
                </div>
                <div class="guide-hero-icon"><i data-lucide="book-open"></i></div>
            </div>

            <!-- 1. System Overview -->
            <div class="section-card" id="overview">
                <div class="section-header">
                    <div class="section-icon-wrap"><i data-lucide="layout-dashboard"></i></div>
                    <div>
                        <h2>1. System Overview</h2>
                        <p>What Vyala Software TaskPad includes and how it works.</p>
                    </div>
                </div>
                <p style="font-size:14px; color:#374151; line-height:1.7; margin-bottom:20px;">
                    Vyala Software TaskPad is a comprehensive task and project management platform designed for Indian businesses. It provides all the tools you need to plan work, assign tasks, track time, manage attendance, and generate reports - in one unified interface.
                </p>
                <div class="feature-grid">
                    <?php
                    $features = [
                        ['check-square','Task Management'],['folder','Project Management'],
                        ['message-circle','Team Discussions'],['paperclip','Document Management'],
                        ['sticky-note','Notes & Reminders'],['clock','Time Tracking'],
                        ['calendar','Attendance Management'],['bar-chart-2','Reports & Analytics'],
                        ['users','Employee Directory'],['settings','Admin Controls'],
                    ];
                    foreach ($features as $f): ?>
                    <div class="feature-item">
                        <i data-lucide="<?= $f[0] ?>"></i>
                        <span><?= $f[1] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="info-box" style="margin-top:20px;">
                    <i data-lucide="info"></i>
                    <p><strong>System Requirements:</strong> Any modern browser (Chrome, Firefox, Edge, Safari). Works on desktop, tablet and mobile. Internet connection required.</p>
                </div>
            </div>

            <!-- 2. Registration -->
            <div class="section-card" id="registration">
                <div class="section-header">
                    <div class="section-icon-wrap"><i data-lucide="user-plus"></i></div>
                    <div>
                        <h2>2. User Registration</h2>
                        <p>How to create a new account on Vyala Software TaskPad.</p>
                    </div>
                </div>
                <div class="steps">
                    <?php
                    $steps = [
                        ['Visit the Login Page','Go to the login page and click the "Sign Up" option.'],
                        ['Enter Your Details','Fill in your Full Name, Email Address, and a strong Password (minimum 6 characters).'],
                        ['Submit the Form','Click "Create Account". Your account is created automatically with an Employee Code (e.g. T-130556).'],
                        ['Start Using TaskPad','You\'ll be redirected to the dashboard immediately after registration. No email verification required for internal users.'],
                    ];
                    foreach ($steps as $i => $s): ?>
                    <div class="step">
                        <div class="step-num"><?= $i+1 ?></div>
                        <div class="step-body">
                            <h4><?= $s[0] ?></h4>
                            <p><?= $s[1] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="info-box">
                    <i data-lucide="alert-circle"></i>
                    <p><strong>Admin Note:</strong> Admins can also add employees directly from the Users section in the dashboard - with custom roles, email, and password.</p>
                </div>
            </div>

            <!-- 3. Login -->
            <div class="section-card" id="login">
                <div class="section-header">
                    <div class="section-icon-wrap"><i data-lucide="log-in"></i></div>
                    <div>
                        <h2>3. Login Process</h2>
                        <p>Signing in to your TaskPad account securely.</p>
                    </div>
                </div>
                <div class="steps">
                    <?php
                    $steps = [
                        ['Go to Login Page','Navigate to the login page from the navigation menu or homepage.'],
                        ['Enter Credentials','Type in your registered Email and Password.'],
                        ['Click Sign In','Click the "Sign In" button to authenticate.'],
                        ['Session Stored','Your session is saved via a secure JWT cookie (24-hour expiry). You\'ll stay logged in without re-entering credentials.'],
                        ['Forgot Password?','Contact your Admin to reset your password from the Users section of the dashboard.'],
                    ];
                    foreach ($steps as $i => $s): ?>
                    <div class="step">
                        <div class="step-num"><?= $i+1 ?></div>
                        <div class="step-body">
                            <h4><?= $s[0] ?></h4>
                            <p><?= $s[1] ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 4. Dashboard -->
            <div class="section-card" id="dashboard">
                <div class="section-header">
                    <div class="section-icon-wrap"><i data-lucide="bar-chart-2"></i></div>
                    <div>
                        <h2>4. Dashboard Usage</h2>
                        <p>Understanding the main dashboard at a glance.</p>
                    </div>
                </div>
                <p style="font-size:14px; color:#374151; line-height:1.7; margin-bottom:16px;">
                    The Dashboard is the home screen after login. It shows a summary of your entire workspace:
                </p>
                <div class="feature-grid">
                    <?php
                    $items = [
                        ['hash','Total Tasks count'],['user','Assigned to Me'],
                        ['alert-circle','Due Today'],['alert-triangle','Overdue Tasks'],
                        ['trending-up','Task Completion Chart'],['pie-chart','Priority Breakdown'],
                        ['folder','Active Projects'],['message-circle','Recent Discussions'],
                    ];
                    foreach ($items as $it): ?>
                    <div class="feature-item">
                        <i data-lucide="<?= $it[0] ?>"></i>
                        <span><?= $it[1] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="info-box" style="margin-top:20px;">
                    <i data-lucide="navigation"></i>
                    <p>Use the <strong>left sidebar</strong> to navigate between Tasks, Projects, Discussions, Documents, Notes, Attendance, Reports, and Settings.</p>
                </div>
            </div>

            <!-- 5. Task Management -->
            <div class="section-card" id="tasks">
                <div class="section-header">
                    <div class="section-icon-wrap"><i data-lucide="check-square"></i></div>
                    <div>
                        <h2>5. Task Management</h2>
                        <p>Creating, assigning, and tracking tasks.</p>
                    </div>
                </div>
                <div class="steps">
                    <?php
                    $steps = [
                        ['Create a Task','Click "+ New Task" in the Tasks view. Fill in Title, Project, Assignee, Priority (Low/Medium/High), and Due Date.'],
                        ['View Tasks by Group','Tasks are grouped into Today, Overdue, and Other/Later for quick prioritisation.'],
                        ['Update Status','Click on any task row to update its status (Pending -> In Progress -> Completed).'],
                        ['Filter & Search','Use the filter bar to search tasks by name, status, or priority.'],
                        ['Track Completion','Completed tasks move to the archive group and are counted in your completion charts.'],
                    ];
                    foreach ($steps as $i => $s): ?>
                    <div class="step">
                        <div class="step-num"><?= $i+1 ?></div>
                        <div class="step-body"><h4><?= $s[0] ?></h4><p><?= $s[1] ?></p></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 6. Projects -->
            <div class="section-card" id="projects">
                <div class="section-header">
                    <div class="section-icon-wrap"><i data-lucide="folder"></i></div>
                    <div>
                        <h2>6. Project Management</h2>
                        <p>Managing client projects and tracking progress.</p>
                    </div>
                </div>
                <div class="steps">
                    <?php
                    $steps = [
                        ['Create a Project','Go to Projects -> click "+ New Project". Fill in Project Name, Description, and Client.'],
                        ['Add Client First','If the client doesn\'t exist, click "+ Add Client" to create them. You need a client to create a project.'],
                        ['Assign Tasks to Project','When creating tasks, select the project from the dropdown to link them.'],
                        ['Monitor Progress','Each project card shows a progress bar based on completed vs total tasks.'],
                        ['View Project Details','Click on any project to see all tasks assigned under it.'],
                    ];
                    foreach ($steps as $i => $s): ?>
                    <div class="step">
                        <div class="step-num"><?= $i+1 ?></div>
                        <div class="step-body"><h4><?= $s[0] ?></h4><p><?= $s[1] ?></p></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 7. Admin -->
            <div class="section-card" id="admin">
                <div class="section-header">
                    <div class="section-icon-wrap"><i data-lucide="shield"></i></div>
                    <div>
                        <h2>7. Admin Features</h2>
                        <p>Managing your organisation, users and departments.</p>
                    </div>
                </div>
                <div class="feature-grid">
                    <?php
                    $adminFeatures = [
                        ['users','Add / Manage Employees'],['building-2','Department Management'],
                        ['key','Reset User Passwords'],['mail','Manage Email Settings'],
                        ['settings','Organisation Configuration'],['clock','Attendance Records'],
                        ['file-text','Export Reports'],['trash-2','Delete / Archive Records'],
                    ];
                    foreach ($adminFeatures as $af): ?>
                    <div class="feature-item">
                        <i data-lucide="<?= $af[0] ?>"></i>
                        <span><?= $af[1] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="info-box" style="margin-top:20px;">
                    <i data-lucide="shield"></i>
                    <p><strong>Role-Based Access:</strong> Admin users have access to all features. Standard members can only view and manage their own tasks. Contact your admin to upgrade your role.</p>
                </div>
            </div>

            <!-- 8. Reports -->
            <div class="section-card" id="reports">
                <div class="section-header">
                    <div class="section-icon-wrap"><i data-lucide="file-text"></i></div>
                    <div>
                        <h2>8. Reports & Analytics</h2>
                        <p>Viewing performance data and generating reports.</p>
                    </div>
                </div>
                <div class="steps">
                    <?php
                    $steps = [
                        ['Navigate to Reports','Click "Reports" in the left sidebar.'],
                        ['Monthly Completion Chart','View a bar chart showing completed vs incomplete tasks each month.'],
                        ['Priority Distribution','See a pie chart breaking down tasks by Low, Medium, and High priority.'],
                        ['Team Performance Table','Track individual employee task completion rates.'],
                        ['Export Data','Use the export buttons to download reports as PDF or Excel (coming soon).'],
                    ];
                    foreach ($steps as $i => $s): ?>
                    <div class="step">
                        <div class="step-num"><?= $i+1 ?></div>
                        <div class="step-body"><h4><?= $s[0] ?></h4><p><?= $s[1] ?></p></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 9. Security -->
            <div class="section-card" id="security">
                <div class="section-header">
                    <div class="section-icon-wrap"><i data-lucide="lock"></i></div>
                    <div>
                        <h2>9. Security Features</h2>
                        <p>How we keep your data safe and secure.</p>
                    </div>
                </div>
                <table class="sec-table">
                    <thead>
                        <tr>
                            <th>Security Feature</th>
                            <th>Description</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $secRows = [
                            ['JWT Authentication','Secure token-based session management with 24-hour expiry','✔ Enabled'],
                            ['Password Hashing','All passwords are bcrypt-hashed (never stored in plain text)','✔ Enabled'],
                            ['HttpOnly Cookies','JWT stored in HttpOnly cookie - cannot be accessed via JavaScript','✔ Enabled'],
                            ['SQL Injection Protection','All queries use PDO prepared statements','✔ Enabled'],
                            ['XSS Prevention','All user output escaped with htmlspecialchars()','✔ Enabled'],
                            ['HTTPS Support','SSL/TLS encryption for data in transit (hosting-level)','✔ Supported'],
                            ['Role-Based Access','Admin / Member roles control data visibility','✔ Enabled'],
                            ['Audit Logs','Activity tracking for all major actions','⌛ Coming Soon'],
                        ];
                        foreach ($secRows as $r): ?>
                        <tr>
                            <td><strong><?= $r[0] ?></strong></td>
                            <td><?= $r[1] ?></td>
                            <td class="check-yes"><?= $r[2] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- CTA -->
            <div style="background: linear-gradient(135deg,#2563eb,#1d4ed8); border-radius:16px; padding:36px 40px; text-align:center; margin-top:8px;">
                <h2 style="color:#fff; font-size:24px; font-weight:800; margin-bottom:10px;">Ready to get started?</h2>
                <p style="color:rgba(255,255,255,0.85); font-size:15px; margin-bottom:24px;">Try Vyala Software TaskPad free for 15 days. No credit card required.</p>
                <div style="display:flex; gap:14px; justify-content:center; flex-wrap:wrap;">
                    <a href="login.php?tab=signup" style="background:#fff; color:#2563eb; padding:12px 28px; border-radius:22px; font-weight:700; font-size:14px; text-decoration:none;">Start Free Trial</a>
                    <a href="demo.php" style="border:2px solid rgba(255,255,255,0.5); color:#fff; padding:12px 28px; border-radius:22px; font-weight:700; font-size:14px; text-decoration:none;">Request a Demo</a>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Highlight active sidebar link on scroll
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    const navLinks = document.querySelectorAll('.guide-nav a[href^="#"]');
    const sections = document.querySelectorAll('.section-card[id]');
    window.addEventListener('scroll', function() {
        let current = '';
        sections.forEach(s => { if (window.scrollY >= s.offsetTop - 100) current = s.id; });
        navLinks.forEach(a => {
            a.classList.remove('active');
            if (a.getAttribute('href') === '#' + current) a.classList.add('active');
        });
    });
});
</script>

<?php require_once 'includes/page_footer.php'; ?>
</body>
</html>

