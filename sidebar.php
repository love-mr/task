<!-- sidebar.php -->
<?php
$displayOrgName = "VYALA";
$displayOrgSub = "SOFTWARE";
$displayOrgSlogan = "Your Project, Our Responsibility";

if (isset($pdo) && isset($jwtPayload['org_id']) && $jwtPayload['org_id'] > 0) {
    try {
        $stmtOrgName = $pdo->prepare("SELECT name FROM organizations WHERE id = ?");
        $stmtOrgName->execute([$jwtPayload['org_id']]);
        $fetchedName = $stmtOrgName->fetchColumn();
        if ($fetchedName) {
            $parts = explode(' ', $fetchedName, 2);
            $displayOrgName = strtoupper($parts[0]);
            $displayOrgSub = isset($parts[1]) ? strtoupper($parts[1]) : '';
        }
    } catch (PDOException $e) {
    }
}
?>
<aside class="sidebar" id="sidebar">
    <!-- Sidebar Brand Logo (Dynamic) -->
    <div class="sidebar-brand" style="justify-content: center; padding: 20px 0;">
        <a href="index.php" class="sidebar-logo-link"
            style="flex-direction: column; align-items: center; text-decoration: none;">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#facc15" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 21h18"></path>
                <path d="M9 8h1"></path>
                <path d="M9 12h1"></path>
                <path d="M9 16h1"></path>
                <path d="M14 8h1"></path>
                <path d="M14 12h1"></path>
                <path d="M14 16h1"></path>
                <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path>
            </svg>
            <div class="sidebar-logo-text" style="text-align: center; margin-top: 8px;">
                <span class="sl-name"
                    style="font-size: 16px; letter-spacing: 1px;"><?= htmlspecialchars($displayOrgName) ?> <span
                        style="color:#facc15;"><?= htmlspecialchars($displayOrgSub) ?></span></span>
                <span class="sl-sub"
                    style="font-size: 9px; opacity: 0.8; letter-spacing: 0.5px;"><?= htmlspecialchars($displayOrgSlogan) ?></span>
            </div>
        </a>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav" style="margin-top: 10px;">
        <ul>
            <?php if ($jwtPayload['role'] !== 'Admin'): ?>
                <li class="nav-item active" data-tab="dashboard">
                    <a href="#dashboard">
                        <i data-lucide="layout-dashboard"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="nav-item" data-tab="projects">
                    <a href="#projects">
                        <i data-lucide="folder"></i>
                        <span>Projects</span>
                    </a>
                </li>

                <li class="nav-item" data-tab="tasks">
                    <a href="#tasks">
                        <i data-lucide="check-square"></i>
                        <span>Tasks & Reminders</span>
                    </a>
                </li>

                <li class="nav-item" data-tab="layout">
                    <a href="#layout">
                        <i data-lucide="layout"></i>
                        <span>Layout</span>
                    </a>
                </li>
                <li class="nav-item" data-tab="building">
                    <a href="#building">
                        <i data-lucide="home"></i>
                        <span>Building</span>
                    </a>
                </li>
                <li class="nav-item" data-tab="singleplot">
                    <a href="#singleplot">
                        <i data-lucide="square"></i>
                        <span>Single Plot</span>
                    </a>
                </li>
                <li class="nav-item" data-tab="ual">
                    <a href="#ual">
                        <i data-lucide="maximize"></i>
                        <span>UAL</span>
                    </a>
                </li>
                <li class="nav-item" data-tab="landsurvey">
                    <a href="#landsurvey">
                        <i data-lucide="map"></i>
                        <span>Land Survey</span>
                    </a>
                </li>
                <li class="nav-item" data-tab="clients">
                    <a href="#clients">
                        <i data-lucide="users"></i>
                        <span>Clients</span>
                    </a>
                </li>
                <li class="nav-item" data-tab="documents">
                    <a href="#documents">
                        <i data-lucide="file-text"></i>
                        <span>Documents</span>
                    </a>
                </li>
                <li class="nav-item" data-tab="discussion">
                    <a href="#discussion">
                        <i data-lucide="message-square"></i>
                        <span>Group Discussion</span>
                    </a>
                </li>
                <li class="nav-item" data-tab="notes">
                    <a href="#notes">
                        <i data-lucide="sticky-note"></i>
                        <span>Notes</span>
                    </a>
                </li>
                <li class="nav-item" data-tab="surveymanagement">
                    <a href="#surveymanagement">
                        <i data-lucide="clipboard-list"></i>
                        <span>Survey Management</span>
                    </a>
                </li>
                <li class="nav-item" data-tab="users">
                    <a href="#users">
                        <i data-lucide="user-circle"></i>
                        <span>Employees</span>
                    </a>
                </li>
                <li class="nav-item" data-tab="reports">
                    <a href="#reports">
                        <i data-lucide="bar-chart-2"></i>
                        <span>Reports</span>
                    </a>
                </li>

                <li class="nav-item" data-tab="settings">
                    <a href="#settings">
                        <i data-lucide="settings"></i>
                        <span>Settings</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($jwtPayload['role'] === 'Admin' || $jwtPayload['role'] === 'Super Admin'): ?>
                <li class="nav-item <?= $jwtPayload['role'] === 'Admin' ? 'active' : '' ?>" data-tab="organizations">
                    <a href="#organizations">
                        <i data-lucide="building-2"></i>
                        <span>Organizations</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Help / Support card at bottom -->
    <div class="sidebar-help" style="margin-top: auto; padding: 20px;">
        <div class="help-card"
            style="background: transparent; border: 1px solid rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; text-align: center;">
            <div class="help-icon" style="color: #fff; margin-bottom: 8px; display: flex; justify-content: center;">
                <i data-lucide="headphones" style="width: 20px; height: 20px;"></i>
            </div>
            <div class="help-details">
                <span class="help-title"
                    style="color: rgba(255,255,255,0.7); font-size: 11px; display: block; margin-bottom: 4px;">Need
                    Help?</span>
                <a href="tel:" class="help-phone"
                    style="color: #fff; font-weight: 600; font-size: 13px; text-decoration: none;">[Add Number Here]</a>
            </div>
        </div>
    </div>
</aside>