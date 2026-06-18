<!-- header.php -->
<?php
require_once 'db.php';
require_once 'jwt.php';
$meOrgId = 1;
$meName = "SELVAKUMAR J";
$meRole = "Project Lead";
$meCode = "T-130555";
$meAvatar = "SJ";

$jwtToken = $_COOKIE['vyala_taskpad_jwt_token'] ?? '';
$jwtPayload = verify_jwt($jwtToken);
if ($jwtPayload) {
    $meOrgId = (int) ($jwtPayload['org_id'] ?? 1);
    $meId = $jwtPayload['id'];
    $stmt = $pdo->prepare("SELECT * FROM `employees` WHERE id = ?");
    $stmt->execute([$meId]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($me) {
        $meName = $me['name'];
        $meRole = $me['role'];
        $meCode = $me['emp_code'] ?: 'T-130555';
        $meAvatar = $me['avatar'] ?: substr($meName, 0, 2);
    }
}

try {
    $stmtNotCount = $pdo->prepare("SELECT COUNT(*) FROM `notifications` WHERE `org_id` = ?");
    $stmtNotCount->execute([$meOrgId]);
    $notifCount = $stmtNotCount->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $notifCount = 0;
}
?>
<header class="main-header">
    <div class="header-left">
        <button id="sidebar-toggle" class="header-btn" title="Toggle Menu">
            <i data-lucide="menu"></i>
        </button>
        <div class="header-logo">
            <div class="logo-check-svg">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="3.5"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" fill="#eff6ff" />
                    <path d="m9 12 2 2 4-4" />
                </svg>
            </div>
            <span class="logo-task">Vyala Software <span class="logo-pad">TaskPad</span></span>
        </div>
        <?php if (isset($jwtPayload['role']) && $jwtPayload['role'] === 'Super Admin'): ?>
            <div class="org-switcher-wrapper" style="margin-left: 20px; display: flex; align-items: center; gap: 8px;">
                <i data-lucide="globe" style="width: 16px; height: 16px; color: #64748b;"></i>
                <select id="super-org-switcher" onchange="window.location.href='dashboard.php?switch_org_id='+this.value" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 12px; font-family: inherit; font-size: 13px; font-weight: 500; color: #0f172a; outline: none; cursor: pointer; transition: all 0.2s;">
                    <option value="0" <?= $meOrgId === 0 ? 'selected' : '' ?>>Global View (All Orgs)</option>
                    <?php
                    try {
                        $stmtOrgs = $pdo->query("SELECT id, name FROM `organizations` ORDER BY name ASC");
                        while ($org = $stmtOrgs->fetch(PDO::FETCH_ASSOC)) {
                            $selected = ($meOrgId === (int)$org['id']) ? 'selected' : '';
                            echo '<option value="' . $org['id'] . '" ' . $selected . '>' . htmlspecialchars($org['name']) . '</option>';
                        }
                    } catch (PDOException $e) {}
                    ?>
                </select>
            </div>
        <?php endif; ?>
        <h2 class="page-title-hidden" style="display:none;">Dashboard</h2>
    </div>

    <div class="header-right">
        <!-- Sun Light Mode Switch Widget -->
        <div class="light-mode-toggle-wrapper">
            <button class="sun-toggle-btn" id="sun-toggle">
                <span class="sun-circle">
                    <i data-lucide="sun"></i>
                </span>
            </button>
        </div>

        <!-- Notifications Bell -->
        <?php if ($jwtPayload['role'] !== 'Admin'): ?>
            <div class="notification-dropdown-wrapper">
                <button class="header-btn notif-btn" id="notif-toggle">
                    <i data-lucide="bell"></i>
                    <span class="badge"
                        style="background-color: #dc2626; color: white; border: 1.5px solid white; font-size: 9px; min-width: 14px; height: 14px; top: -1px; right: -1px;<?= $notifCount > 0 ? '' : ' display: none;' ?>"><?= $notifCount ?></span>
                </button>
                <div class="dropdown-menu" id="notif-dropdown">
                    <div class="dropdown-header">
                        <h3>Notifications</h3>
                        <span class="clear-all">Clear All</span>
                    </div>
                    <div class="dropdown-content">
                        <?php
                        try {
                            $stmtNot = $pdo->prepare("SELECT * FROM `notifications` WHERE `org_id` = ? ORDER BY id DESC LIMIT 5");
                            $stmtNot->execute([$meOrgId]);
                            $notifs = $stmtNot->fetchAll(PDO::FETCH_ASSOC);
                            if (empty($notifs)) {
                                echo '<p class="empty-notif">No new notifications</p>';
                            } else {
                                foreach ($notifs as $n) {
                                    $catClass = 'notif-' . $n['category'];
                                    $icon = 'info';
                                    if ($n['category'] === 'danger' || $n['category'] === 'warning')
                                        $icon = 'alert-triangle';
                                    if ($n['category'] === 'success')
                                        $icon = 'check-circle';
                                    echo '
                                <div class="dropdown-item ' . $catClass . '">
                                    <div class="item-icon"><i data-lucide="' . $icon . '"></i></div>
                                    <div class="item-text">
                                        <p>' . htmlspecialchars($n['message']) . '</p>
                                        <span class="item-time">Just now</span>
                                    </div>
                                </div>';
                                }
                            }
                        } catch (PDOException $e) {
                            echo '<p class="empty-notif">Failed to load alerts</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- User Profile (SJ, SELVAKUMAR J) -->
        <div class="user-profile-wrapper" style="position: relative; display: flex; align-items: center;">
            <div class="user-profile" id="profile-toggle">
                <div class="avatar-initials"
                    style="background-color: #dc2626; color: white; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; font-family: var(--font-sans);">
                    <?= htmlspecialchars($meAvatar) ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($meName) ?></span>
                    <span class="user-role"><?= htmlspecialchars($meCode) ?></span>
                </div>
                <i data-lucide="chevron-down" class="profile-arrow"></i>
            </div>

            <!-- Direct Logout Icon Button -->
            <a href="logout.php" class="logout-circle-btn" title="Logout">
                <i data-lucide="log-out"></i>
            </a>

            <!-- User Dropdown Menu -->
            <div class="profile-dropdown-menu" id="profile-dropdown">
                <div class="profile-dropdown-content">
                    <?php if ($jwtPayload['role'] !== 'Admin'): ?>
                        <a href="#settings" class="profile-dropdown-item">Profile Settings</a>
                        <a href="#projects" class="profile-dropdown-item">Organization Management</a>
                        <a href="#projects" class="profile-dropdown-item">Help</a>
                    <?php endif; ?>
                    <a href="logout.php"
                        class="profile-dropdown-item <?= $jwtPayload['role'] === 'Admin' ? '' : 'border-top' ?>">Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>