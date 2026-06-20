<?php
// dashboard.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
date_default_timezone_set('Asia/Kolkata');
require_once 'jwt.php';

$jwtToken = $_COOKIE['vyala_taskpad_jwt_token'] ?? '';
$jwtPayload = verify_jwt($jwtToken);
if (!$jwtPayload) {
    header("Location: login.php");
    exit;
}

if (isset($_GET['switch_org_id']) && isset($jwtPayload['role']) && $jwtPayload['role'] === 'Super Admin') {
    $newOrgId = (int)$_GET['switch_org_id'];
    $jwtPayload['org_id'] = $newOrgId;
    $newJwt = generate_jwt($jwtPayload);
    setcookie('vyala_taskpad_jwt_token', $newJwt, time() + 86400, '/', '', false, true);
    header("Location: dashboard.php");
    exit;
}

$meId = $jwtPayload['id'];
$meName = $jwtPayload['name'];
$meAvatar = 'U';

require_once 'db.php';

// Dynamically fetch current user info from DB to avoid stale JWT data
try {
    $stmtUser = $pdo->prepare("SELECT org_id, role, name, status FROM `employees` WHERE id = ?");
    $stmtUser->execute([$meId]);
    $dbUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if ($dbUser) {
        $jwtPayload['org_id'] = (int)$dbUser['org_id'];
        $jwtPayload['role'] = $dbUser['role'];
        $jwtPayload['name'] = $dbUser['name'];
        $meName = $dbUser['name'];
        if ($dbUser['status'] === 'Deactivated' || $dbUser['status'] === 'Pending') {
            setcookie('vyala_taskpad_jwt_token', '', time() - 3600, '/');
            header("Location: login.php?error=" . urlencode("Your account is pending approval or has been deactivated."));
            exit;
        }
    }
} catch (PDOException $ex) {}

// Validate organization status for active session
$meOrgId = (int)($jwtPayload['org_id'] ?? 0);
$isPlatformAdmin = ($jwtPayload['role'] === 'Super Admin' || ($jwtPayload['role'] === 'Admin' && $jwtPayload['email'] === 'admin'));
if ($meOrgId > 0 && !$isPlatformAdmin) {
    try {
        $stmtOrgCheck = $pdo->prepare("SELECT status FROM `organizations` WHERE id = ?");
        $stmtOrgCheck->execute([$meOrgId]);
        $orgStatus = $stmtOrgCheck->fetchColumn();
        if ($orgStatus !== 'Active') {
            // Destroy invalid session cookie
            setcookie('vyala_taskpad_jwt_token', '', time() - 3600, '/');
            header("Location: login.php?error=" . urlencode("Your organization is not yet approved by the administrator."));
            exit;
        }
    } catch (PDOException $ex) {}
}

try {
    $isAdmin = ($jwtPayload['role'] === 'Admin' || $jwtPayload['role'] === 'Super Admin');
    $isOrgAdmin = ($jwtPayload['role'] === 'Project Lead');
    $meOrgId = (int)($jwtPayload['org_id'] ?? 1);

    // Fetch logged-in user avatar
    try {
        $stmtMe = $pdo->prepare("SELECT avatar FROM employees WHERE id = ?");
        $stmtMe->execute([$meId]);
        $meAvatar = $stmtMe->fetchColumn() ?: 'U';
    } catch (PDOException $e) {
        // Fallback if query fails
    }

    // =====================================================================
    // MULTI-TENANT DB SETUP (auto-run, safe with IF NOT EXISTS)
    // =====================================================================

    // 1. Organizations table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `organizations` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `name`       VARCHAR(255) NOT NULL,
            `slug`       VARCHAR(100) DEFAULT NULL,
            `status`     ENUM('Pending','Active','Rejected') NOT NULL DEFAULT 'Pending',
            `phone`      VARCHAR(50) DEFAULT NULL,
            `address`    TEXT DEFAULT NULL,
            `details`    TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_slug` (`slug`)
        ) ENGINE=InnoDB");
    } catch (PDOException $e) {}

    // 2. Seed default org (id=1) for existing data if none exists
    try {
        $orgCount = $pdo->query("SELECT COUNT(*) FROM `organizations`")->fetchColumn();
        if ($orgCount == 0) {
            $pdo->exec("INSERT INTO `organizations` (`id`,`name`,`slug`,`status`) VALUES (1,'Default Organization','default','Active')");
        }
    } catch (PDOException $e) {}

    // 3. Alter tables to add columns safely without using unsupported IF NOT EXISTS syntax in ALTER TABLE
    $tablesToAlter = [
        'organizations' => ['phone' => 'VARCHAR(50) DEFAULT NULL', 'address' => 'TEXT DEFAULT NULL', 'details' => 'TEXT DEFAULT NULL'],
        'employees' => ['org_id' => 'INT NOT NULL DEFAULT 1', 'status' => "VARCHAR(50) NOT NULL DEFAULT 'Active'"],
        'projects' => ['org_id' => 'INT NOT NULL DEFAULT 1', 'fee_amount' => 'DECIMAL(15,2) DEFAULT 0.00'],
        'tasks' => [
            'org_id' => 'INT NOT NULL DEFAULT 1',
            'estimated_duration' => 'INT DEFAULT 0',
            'sequence_order' => 'INT DEFAULT 0',
            'depends_on' => 'INT DEFAULT NULL',
            'actual_start_date' => 'DATE DEFAULT NULL',
            'actual_completion_date' => 'DATE DEFAULT NULL'
        ],
        'documents' => ['org_id' => 'INT NOT NULL DEFAULT 1', 'encrypted' => "TINYINT(1) NOT NULL DEFAULT 0", 'enc_iv' => 'VARCHAR(255) DEFAULT NULL', 'original_name' => 'VARCHAR(255) DEFAULT NULL', 'mime_type' => 'VARCHAR(100) DEFAULT NULL'],
        'discussions' => ['org_id' => 'INT NOT NULL DEFAULT 1', 'is_direct' => 'TINYINT(1) NOT NULL DEFAULT 0'],
        'pin_notes' => ['org_id' => 'INT NOT NULL DEFAULT 1'],
        'clients' => ['org_id' => 'INT NOT NULL DEFAULT 1'],
        'activities' => ['org_id' => 'INT NOT NULL DEFAULT 1'],
        'attendance' => ['org_id' => 'INT NOT NULL DEFAULT 1'],
        'timesheets' => ['org_id' => 'INT NOT NULL DEFAULT 1'],
        'notifications' => ['org_id' => 'INT NOT NULL DEFAULT 1'],
        'goal_tracker' => ['org_id' => 'INT NOT NULL DEFAULT 1'],
        'document_logs' => ['org_id' => 'INT NOT NULL DEFAULT 1'],
    ];

    foreach ($tablesToAlter as $tbl => $cols) {
        foreach ($cols as $colName => $colDef) {
            try {
                $checkCol = $pdo->query("SHOW COLUMNS FROM `$tbl` LIKE '$colName'")->fetch();
                if (!$checkCol) {
                    $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN `$colName` $colDef");
                }
            } catch (PDOException $e) {}
        }
    }

    // 9. project_members table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `project_members` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NOT NULL,
            `employee_id` INT NOT NULL,
            UNIQUE KEY `uniq_proj_emp` (`project_id`, `employee_id`),
            INDEX `idx_pm_project` (`project_id`),
            INDEX `idx_pm_employee` (`employee_id`)
        ) ENGINE=InnoDB");
    } catch (PDOException $e) {}

    // 10. documents table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `documents` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `filepath` VARCHAR(500) NOT NULL,
            `owner_id` INT DEFAULT NULL,
            `project_id` INT DEFAULT NULL,
            `org_id` INT NOT NULL DEFAULT 1,
            `size` VARCHAR(50) DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_doc_owner` (`owner_id`),
            INDEX `idx_doc_org` (`org_id`)
        ) ENGINE=InnoDB");
    } catch (PDOException $e) {}

    // 11. discussion_members table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `discussion_members` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `discussion_id` INT NOT NULL,
            `employee_id` INT NOT NULL,
            UNIQUE KEY `uniq_disc_emp` (`discussion_id`, `employee_id`),
            INDEX `idx_dm_discussion` (`discussion_id`),
            INDEX `idx_dm_employee` (`employee_id`)
        ) ENGINE=InnoDB");
    } catch (PDOException $e) {}

    // 11b. discussion_keys table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `discussion_keys` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `discussion_id` INT NOT NULL,
            `employee_id` INT NOT NULL,
            `encrypted_key` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_disc_emp_key` (`discussion_id`, `employee_id`),
            INDEX `idx_dk_discussion` (`discussion_id`),
            INDEX `idx_dk_employee` (`employee_id`)
        ) ENGINE=InnoDB");
    } catch (PDOException $e) {}

    // 12. buildings table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `buildings` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `type` VARCHAR(100) DEFAULT NULL,
            `address` TEXT DEFAULT NULL,
            `total_floors` INT DEFAULT 0,
            `total_units` INT DEFAULT 0,
            `total_area` DECIMAL(10,2) DEFAULT 0,
            `owner_name` VARCHAR(255) DEFAULT NULL,
            `contact_number` VARCHAR(50) DEFAULT NULL,
            `status` ENUM('Available','Sold','Rented') DEFAULT 'Available',
            `document_path` VARCHAR(500) DEFAULT NULL,
            `org_id` INT NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
    } catch (PDOException $e) {}

    // 13. single_plots table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `single_plots` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `plot_number` VARCHAR(100) NOT NULL,
            `layout_name` VARCHAR(255) DEFAULT NULL,
            `survey_number` VARCHAR(100) DEFAULT NULL,
            `area` DECIMAL(10,2) DEFAULT 0,
            `location` VARCHAR(255) DEFAULT NULL,
            `price` DECIMAL(15,2) DEFAULT 0,
            `facing_direction` VARCHAR(100) DEFAULT NULL,
            `status` VARCHAR(100) DEFAULT 'Available',
            `owner_name` VARCHAR(255) DEFAULT NULL,
            `org_id` INT NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
    } catch (PDOException $e) {}

    // 14. ual_records table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `ual_records` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `case_number` VARCHAR(100) NOT NULL,
            `owner_name` VARCHAR(255) DEFAULT NULL,
            `address` TEXT DEFAULT NULL,
            `total_land_area` DECIMAL(10,2) DEFAULT 0,
            `gov_ceiling_limit` DECIMAL(10,2) DEFAULT 0,
            `excess_land_area` DECIMAL(10,2) DEFAULT 0,
            `approval_status` VARCHAR(100) DEFAULT 'Pending',
            `gov_order_number` VARCHAR(100) DEFAULT NULL,
            `document_path` VARCHAR(500) DEFAULT NULL,
            `remarks` TEXT DEFAULT NULL,
            `org_id` INT NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
    } catch (PDOException $e) {}

    // 15. land_surveys table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `land_surveys` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `survey_number` VARCHAR(100) NOT NULL,
            `village_name` VARCHAR(255) DEFAULT NULL,
            `taluk` VARCHAR(255) DEFAULT NULL,
            `district` VARCHAR(255) DEFAULT NULL,
            `land_type` VARCHAR(100) DEFAULT NULL,
            `owner_name` VARCHAR(255) DEFAULT NULL,
            `total_area` DECIMAL(10,2) DEFAULT 0,
            `latitude` VARCHAR(50) DEFAULT NULL,
            `longitude` VARCHAR(50) DEFAULT NULL,
            `document_path` VARCHAR(500) DEFAULT NULL,
            `org_id` INT NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
    } catch (PDOException $e) {}



    // 1. Fetch Top Stats Counts
    if ($isAdmin) {
        $totalTasks = 0;
        $assignedToMe = 0;
        $dueToday = 0;
        $pastDue = 0;
    } else {
        // Total Task: count of tasks assigned to me
        $totalTasks = $pdo->query("SELECT COUNT(*) FROM `tasks` t JOIN `projects` p ON t.project_id = p.id WHERE t.`assigned_to` = $meId")->fetchColumn() ?: 0;
        
        // Assigned to me: tasks assigned to the logged-in employee
        $assignedToMe = $totalTasks;

        // Past due tasks: tasks not completed and due date before today
        $today = date('Y-m-d');
        $dueToday = $pdo->query("SELECT COUNT(*) FROM `tasks` t JOIN `projects` p ON t.project_id = p.id WHERE t.`assigned_to` = $meId AND t.`due_date` = '$today'")->fetchColumn() ?: 0;
        $pastDue = $pdo->query("SELECT COUNT(*) FROM `tasks` t JOIN `projects` p ON t.project_id = p.id WHERE t.`assigned_to` = $meId AND t.`status` != 'Completed' AND t.`due_date` < '$today'")->fetchColumn() ?: 0;
    }

    // User task counts (for normal employees / dashboard metrics)
    $user_assignedTasks = $pdo->query("SELECT COUNT(*) FROM `tasks` t JOIN `projects` p ON t.project_id = p.id WHERE t.`assigned_to` = $meId AND t.org_id = $meOrgId")->fetchColumn() ?: 0;
    $user_inProgressTasks = $pdo->query("SELECT COUNT(*) FROM `tasks` t JOIN `projects` p ON t.project_id = p.id WHERE t.`assigned_to` = $meId AND t.org_id = $meOrgId AND t.status = 'In Progress'")->fetchColumn() ?: 0;
    $user_completedTasks = $pdo->query("SELECT COUNT(*) FROM `tasks` t JOIN `projects` p ON t.project_id = p.id WHERE t.`assigned_to` = $meId AND t.org_id = $meOrgId AND t.status = 'Completed'")->fetchColumn() ?: 0;
    $user_pendingTasks = $pdo->query("SELECT COUNT(*) FROM `tasks` t JOIN `projects` p ON t.project_id = p.id WHERE t.`assigned_to` = $meId AND t.org_id = $meOrgId AND t.status != 'Completed'")->fetchColumn() ?: 0;

    // RSK Approvals Dashboard Metrics
    $rsk_totalProjects = $pdo->query("SELECT COUNT(*) FROM `projects` WHERE org_id = $meOrgId")->fetchColumn() ?: 0;
    $rsk_pendingProjects = $pdo->query("SELECT COUNT(*) FROM `projects` WHERE status = 'Pending' AND org_id = $meOrgId")->fetchColumn() ?: 0;
    $rsk_approvedProjects = $pdo->query("SELECT COUNT(*) FROM `projects` WHERE status = 'Active' OR status = 'Completed' AND org_id = $meOrgId")->fetchColumn() ?: 0;
    $rsk_rejectedProjects = $pdo->query("SELECT COUNT(*) FROM `projects` WHERE status IN ('Rejected', 'Query') AND org_id = $meOrgId")->fetchColumn() ?: 0;
    $rsk_activeClients = $pdo->query("SELECT COUNT(*) FROM `clients` WHERE org_id = $meOrgId")->fetchColumn() ?: 0;
    $rsk_surveyWorks = $pdo->query("SELECT COUNT(*) FROM `tasks` t JOIN `projects` p ON t.project_id = p.id WHERE (t.title LIKE '%Survey%' OR t.description LIKE '%Survey%') AND t.status != 'Completed' AND t.org_id = $meOrgId")->fetchColumn() ?: 0;
    $rsk_totalEmployees = $pdo->query("SELECT COUNT(*) FROM `employees` WHERE role != 'Admin' AND org_id = $meOrgId")->fetchColumn() ?: 0;
    $rsk_totalTasks = $pdo->query("SELECT COUNT(*) FROM `tasks` t JOIN `projects` p ON t.project_id = p.id WHERE t.org_id = $meOrgId")->fetchColumn() ?: 0;

    // --- PIPELINE & SERVICES DYNAMIC COUNTS ---
    $pipelineRaw = $pdo->prepare("SELECT pipeline_stage, COUNT(*) as cnt FROM projects WHERE org_id = ? AND pipeline_stage IS NOT NULL AND pipeline_stage != '' GROUP BY pipeline_stage");
    $pipelineRaw->execute([$meOrgId]);
    $pipelineCounts = [];
    foreach($pipelineRaw->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pipelineCounts[$row['pipeline_stage']] = (int)$row['cnt'];
    }

    $servicesRaw = $pdo->prepare("SELECT service_type, COUNT(*) as cnt, SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as comp, SUM(CASE WHEN status!='Completed' AND status!='Rejected' THEN 1 ELSE 0 END) as pend FROM projects WHERE org_id = ? AND service_type IS NOT NULL AND service_type != '' GROUP BY service_type");
    $servicesRaw->execute([$meOrgId]);
    $serviceCounts = [];
    foreach($servicesRaw->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $serviceCounts[$row['service_type']] = [
            'total' => (int)$row['cnt'],
            'comp'  => (int)$row['comp'],
            'pend'  => (int)$row['pend']
        ];
    }

    // Project status counts for donut chart
    $statusCountsRaw = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM projects WHERE org_id = ? GROUP BY status");
    $statusCountsRaw->execute([$meOrgId]);
    $projectStatusCounts = ['Completed'=>0,'Active'=>0,'Pending'=>0,'Rejected'=>0];
    foreach($statusCountsRaw->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $projectStatusCounts[$row['status']] = (int)$row['cnt'];
    }
    // -------------------------------------------

    // --- GOAL TRACKER DATA ---
    $today = date('Y-m-d');
    try {
        // Auto-update overdue goals
        $pdo->prepare("UPDATE `goal_tracker` SET status = 'Overdue' WHERE org_id = ? AND target_date < ? AND status NOT IN ('Completed')")->execute([$meOrgId, $today]);
        $goalsRaw = $pdo->prepare("SELECT * FROM `goal_tracker` WHERE org_id = ? ORDER BY target_date ASC");
        $goalsRaw->execute([$meOrgId]);
        $goalsList = $goalsRaw->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $goalsList = []; }
    $goal_total     = count($goalsList);
    $goal_completed = count(array_filter($goalsList, fn($g) => $g['status'] === 'Completed'));
    $goal_overdue   = count(array_filter($goalsList, fn($g) => $g['status'] === 'Overdue'));
    $goal_pending   = $goal_total - $goal_completed;
    // -------------------------------------------

    // Fetch recent notifications for dashboard card
    $stmtNotif = $pdo->prepare("SELECT * FROM `notifications` WHERE org_id = ? ORDER BY id DESC LIMIT 5");
    $stmtNotif->execute([$meOrgId]);
    $recentNotifications = $stmtNotif->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch Projects Lists
    $projects = $pdo->prepare("
        SELECT p.*, c.name as client_name,
               (SELECT COUNT(*) FROM `tasks` t WHERE t.project_id = p.id) as total_tasks,
               (SELECT COUNT(*) FROM `tasks` t WHERE t.project_id = p.id AND t.status = 'Completed') as completed_tasks,
               (SELECT COUNT(*) FROM `documents` d WHERE d.project_id = p.id) as total_documents,
               (SELECT GROUP_CONCAT(pm.employee_id SEPARATOR ',') FROM `project_members` pm WHERE pm.project_id = p.id) as member_ids
        FROM `projects` p
        LEFT JOIN `clients` c ON p.client_id = c.id
        WHERE p.org_id = ?
        ORDER BY p.id ASC
    ");
    $projects->execute([$meOrgId]);
    $projects = $projects->fetchAll(PDO::FETCH_ASSOC);

    // Build project member details map (project_id => array of member info)
    $projMemberDetails = [];
    try {
        $pmRows = $pdo->prepare("
            SELECT pm.project_id, e.id, e.name, e.avatar
            FROM `project_members` pm
            JOIN `employees` e ON pm.employee_id = e.id
            WHERE e.org_id = ?
        ");
        $pmRows->execute([$meOrgId]);
        $pmRows = $pmRows->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pmRows as $pmr) {
            $projMemberDetails[$pmr['project_id']][] = $pmr;
        }
    } catch (PDOException $e) { $projMemberDetails = []; }

    // Recent projects for dashboard card list
    $recentProjects = array_slice($projects, 0, 6);


    // 3. Fetch Tasks Lists (All & Recent)
    if ($isAdmin) {
        $tasksList = [];
    } else {
        $tasksList = $pdo->prepare("
            SELECT t.*, p.name as project_name, e.name as employee_name, e.avatar as employee_avatar
            FROM `tasks` t
            JOIN `projects` p ON t.project_id = p.id
            LEFT JOIN `employees` e ON t.assigned_to = e.id
            WHERE t.assigned_to = ? AND t.org_id = ?
            ORDER BY t.id DESC
        ");
        $tasksList->execute([$meId, $meOrgId]);
        $tasksList = $tasksList->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. Fetch Discussions List (matching Screenshot 3 Recent Discussions)
    $discussionsList = $pdo->prepare("
        SELECT d.id, d.type, d.attachment_name, d.attachment_type, d.date_logged, d.created_at,
               (SELECT COUNT(*) FROM `discussion_members` dm WHERE dm.discussion_id = d.id) as member_count,
               CASE 
                   WHEN d.type = 'Direct' THEN (
                       SELECT name FROM `employees` e 
                       JOIN `discussion_members` dm ON e.id = dm.employee_id 
                       WHERE dm.discussion_id = d.id AND dm.employee_id != ? 
                       LIMIT 1
                   )
                   ELSE d.title 
               END as title,
               CASE 
                   WHEN d.type = 'Direct' THEN (
                       SELECT avatar FROM `employees` e 
                       JOIN `discussion_members` dm ON e.id = dm.employee_id 
                       WHERE dm.discussion_id = d.id AND dm.employee_id != ? 
                       LIMIT 1
                   )
                   ELSE NULL 
               END as direct_avatar
        FROM `discussions` d
        WHERE d.org_id = ? AND (d.type IN ('General', 'Task')
           OR (d.type = 'Direct' AND EXISTS (
               SELECT 1 FROM `discussion_members` dm WHERE dm.discussion_id = d.id AND dm.employee_id = ?
           )))
        ORDER BY d.id ASC
    ");
    $discussionsList->execute([$meId, $meId, $meOrgId, $meId]);
    $discussionsList = $discussionsList->fetchAll(PDO::FETCH_ASSOC);

    // 5. Fetch Activities List (matching Screenshot 4 Activity list)
    $activitiesList = $pdo->prepare("SELECT * FROM `activities` WHERE org_id = ? ORDER BY id ASC");
    $activitiesList->execute([$meOrgId]);
    $activitiesList = $activitiesList->fetchAll(PDO::FETCH_ASSOC);

    // Fetch All Employees list for the directory (exclude Admin role)
    $employeesList = $pdo->prepare("SELECT * FROM `employees` WHERE `role` != 'Admin' AND `org_id` = ? ORDER BY id ASC");
    $employeesList->execute([$meOrgId]);
    $employeesList = $employeesList->fetchAll(PDO::FETCH_ASSOC);

    // 6. Fetch Team Incomplete Tasks Counts (matching Screenshot 2)
    // Dhanapathi R, Dinakaran S, Kalpana G (dynamic fallback for org)
    $teamCounts = [];
    $employees = $pdo->prepare("SELECT id, name, avatar FROM `employees` WHERE `role` != 'Admin' AND `org_id` = ? LIMIT 3");
    $employees->execute([$meOrgId]);
    $employees = $employees->fetchAll(PDO::FETCH_ASSOC);
    foreach ($employees as $emp) {
        $stmtInc = $pdo->prepare("SELECT COUNT(*) FROM `tasks` WHERE `assigned_to` = ? AND `org_id` = ? AND `status` != 'Completed'");
        $stmtInc->execute([$emp['id'], $meOrgId]);
        $incCount = $stmtInc->fetchColumn() ?: 0;
        $teamCounts[] = [
            'name' => $emp['name'],
            'avatar' => $emp['avatar'],
            'count' => $incCount
        ];
    }

    // 7. Dropdowns for modals (scoped to org)
    $dropdownProjects = $pdo->prepare("SELECT id, name FROM `projects` WHERE org_id = ? ORDER BY name ASC");
    $dropdownProjects->execute([$meOrgId]);
    $dropdownProjects = $dropdownProjects->fetchAll(PDO::FETCH_ASSOC);

    $dropdownEmployees = $pdo->prepare("SELECT id, name FROM `employees` WHERE role != 'Admin' AND org_id = ? ORDER BY name ASC");
    $dropdownEmployees->execute([$meOrgId]);
    $dropdownEmployees = $dropdownEmployees->fetchAll(PDO::FETCH_ASSOC);

    $dropdownClients = $pdo->prepare("SELECT id, name, email, phone, created_at FROM `clients` WHERE org_id = ? ORDER BY name ASC");
    $dropdownClients->execute([$meOrgId]);
    $dropdownClients = $dropdownClients->fetchAll(PDO::FETCH_ASSOC);

    // Fetch departments list
    $departmentsList = [];
    try {
        $departmentsList = $pdo->prepare("
            SELECT d.*, 
                   (SELECT COUNT(*) FROM `employees` e WHERE e.role LIKE CONCAT('%', d.name, '%') AND e.org_id = ?) as employee_count
            FROM `departments` d 
            ORDER BY d.id ASC
        ");
        $departmentsList->execute([$meOrgId]);
        $departmentsList = $departmentsList->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    // 8. Seeding Chart Data variables:
    // Completed vs Incomplete count by month for line chart (from DB)
    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $chartCompleted = array_fill(0, 12, 0);
    $chartIncomplete = array_fill(0, 12, 0);
    $chartRows = $pdo->prepare("
        SELECT MONTH(created_at) as m,
               SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as c,
               SUM(CASE WHEN status!='Completed' THEN 1 ELSE 0 END) as ic
        FROM tasks
        WHERE YEAR(created_at) = YEAR(CURDATE()) AND org_id = ?
        GROUP BY MONTH(created_at)
    ");
    $chartRows->execute([$meOrgId]);
    $chartRows = $chartRows->fetchAll(PDO::FETCH_ASSOC);
    foreach ($chartRows as $r) {
        $idx = (int)$r['m'] - 1;
        $chartCompleted[$idx] = (int)$r['c'];
        $chartIncomplete[$idx] = (int)$r['ic'];
    }
    // If all zero (seeded data has old dates), do not fall back to static sample data
    if (array_sum($chartCompleted) === 0) {
        $chartCompleted = array_fill(0, 12, 0);
        $chartIncomplete = array_fill(0, 12, 0);
    }

    // Priority counts for priority donut chart
    $priorityCounts = [
        'Low'    => 0,
        'Medium' => 0,
        'High'   => 0,
    ];
    try {
        $stmtLow = $pdo->prepare("SELECT COUNT(*) FROM `tasks` WHERE `priority` = 'Low' AND org_id = ?");
        $stmtLow->execute([$meOrgId]);
        $priorityCounts['Low'] = $stmtLow->fetchColumn() ?: 0;

        $stmtMed = $pdo->prepare("SELECT COUNT(*) FROM `tasks` WHERE `priority` = 'Medium' AND org_id = ?");
        $stmtMed->execute([$meOrgId]);
        $priorityCounts['Medium'] = $stmtMed->fetchColumn() ?: 0;

        $stmtHigh = $pdo->prepare("SELECT COUNT(*) FROM `tasks` WHERE `priority` = 'High' AND org_id = ?");
        $stmtHigh->execute([$meOrgId]);
        $priorityCounts['High'] = $stmtHigh->fetchColumn() ?: 0;
    } catch (PDOException $e) {}

    // Do not load static mock priorities when database is empty
    if ($priorityCounts['Low'] + $priorityCounts['Medium'] + $priorityCounts['High'] === 0) {
        $priorityCounts = ['Low' => 0, 'Medium' => 0, 'High' => 0];
    }

    // Fetch Pin Notes
    $pinNotes = $pdo->prepare("SELECT * FROM `pin_notes` WHERE org_id = ? ORDER BY id DESC");
    $pinNotes->execute([$meOrgId]);
    $pinNotes = $pinNotes->fetchAll(PDO::FETCH_ASSOC);


    // Calculate actual directory storage
    $totalStorageBytes = 0;
    $uploadsDir = __DIR__ . '/uploads';
    if (is_dir($uploadsDir)) {
        $files = array_diff(scandir($uploadsDir), array('.', '..'));
        foreach ($files as $file) {
            $filePath = $uploadsDir . '/' . $file;
            if (is_file($filePath)) {
                $totalStorageBytes += filesize($filePath);
            }
        }
    }
    $totalStorageMB = round($totalStorageBytes / (1024 * 1024), 2);
    $storageProgressPct = min(100, ($totalStorageBytes / (5.0 * 1024 * 1024 * 1024)) * 100);


    // Calculate attendance stats dynamically
    $todayDate = date('Y-m-d');
    $stmtPres = $pdo->prepare("SELECT COUNT(DISTINCT a.employee_id) FROM `attendance` a JOIN `employees` e ON a.employee_id = e.id WHERE a.date = ? AND e.org_id = ?");
    $stmtPres->execute([$todayDate, $meOrgId]);
    $presentToday = $stmtPres->fetchColumn() ?: 0;

    $stmtTotalEmp = $pdo->prepare("SELECT COUNT(*) FROM `employees` WHERE `role` != 'Admin' AND `org_id` = ?");
    $stmtTotalEmp->execute([$meOrgId]);
    $totalEmployees = $stmtTotalEmp->fetchColumn() ?: 0;

    $absentToday = max(0, $totalEmployees - $presentToday);

    $stmtLate = $pdo->prepare("SELECT COUNT(*) FROM `attendance` a JOIN `employees` e ON a.employee_id = e.id WHERE a.date = ? AND a.status = 'Late' AND e.org_id = ?");
    $stmtLate->execute([$todayDate, $meOrgId]);
    $lateToday = $stmtLate->fetchColumn() ?: 0;

    $leavesThisMonth = 0; // Simple placeholder since we have no leaves table

    // Fetch Real Estate Modules Data
    $buildingsList = $pdo->prepare("SELECT * FROM `buildings` WHERE org_id = ? ORDER BY id DESC");
    $buildingsList->execute([$meOrgId]);
    $buildingsList = $buildingsList->fetchAll(PDO::FETCH_ASSOC);

    $singlePlotsList = $pdo->prepare("SELECT * FROM `single_plots` WHERE org_id = ? ORDER BY id DESC");
    $singlePlotsList->execute([$meOrgId]);
    $singlePlotsList = $singlePlotsList->fetchAll(PDO::FETCH_ASSOC);

    $ualRecordsList = $pdo->prepare("SELECT * FROM `ual_records` WHERE org_id = ? ORDER BY id DESC");
    $ualRecordsList->execute([$meOrgId]);
    $ualRecordsList = $ualRecordsList->fetchAll(PDO::FETCH_ASSOC);

    $landSurveysList = $pdo->prepare("SELECT * FROM `land_surveys` WHERE org_id = ? ORDER BY id DESC");
    $landSurveysList->execute([$meOrgId]);
    $landSurveysList = $landSurveysList->fetchAll(PDO::FETCH_ASSOC);

    $surveyManagementList = $pdo->prepare("SELECT * FROM `survey_management` WHERE org_id = ? AND is_archived = 0 ORDER BY id DESC");
    $surveyManagementList->execute([$meOrgId]);
    $surveyManagementList = $surveyManagementList->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database access error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vyala Software TaskPad – Dashboard</title>
    <!-- CSS Stylesheet -->
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        window.VYALA_USER_ROLE = "<?= $jwtPayload['role'] ?>";
        window.VYALA_IS_ORG_ADMIN = <?= $isOrgAdmin ? 'true' : 'false' ?>;
    </script>
    <style>
    /* ======================== PRINT STYLES ======================== */
    @media print {
        /* Hide all nav/chrome */
        .sidebar, .main-wrapper > header, header.app-header,
        .tab-nav, .content-tabs, .sidebar-nav,
        .btn, button, select, input[type="text"], input[type="date"],
        .filter-dropdown-wrapper, .standard-search-wrapper,
        .project-card-status-row select, .btn-delete-project,
        #btn-export-report, .header-btn,
        .app-container > aside,
        [class*="header"], .rsk-panel-link,
        .tab-view:not(#view-reports) { display: none !important; }

        /* Reset layout for print */
        body, html { margin: 0; padding: 0; background: #fff !important; }
        .app-container { display: block !important; }
        .main-wrapper { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .content-area { padding: 0 !important; }
        #view-reports { display: block !important; padding: 12px !important; }

        /* Report panel print styling */
        #view-reports .section-card,
        #view-reports .rsk-panel,
        #view-reports table { box-shadow: none !important; border: 1px solid #ccc !important; }
        #view-reports h2, #view-reports h3 { color: #000 !important; }
        #view-reports table th { background: #eee !important; color: #000 !important; }
        #view-reports table td { color: #333 !important; }
        
        /* Page breaks */
        #view-reports .section-card { page-break-inside: avoid; margin-bottom: 16px; }
        
        /* Print header */
        #view-reports::before {
            content: "Vyala Software TaskPad – Report";
            display: block;
            font-size: 20px;
            font-weight: 700;
            text-align: center;
            padding: 12px 0;
            border-bottom: 2px solid #333;
            margin-bottom: 16px;
        }
    }
    </style>
</head>
<body>

    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Workspace Wrapper -->
        <main class="main-wrapper">
            <!-- Header layout -->
            <?php include 'header.php'; ?>

            <!-- Viewport tabs content -->
            <div class="content-area">

                <!-- ==========================================================================
                     TAB 1: DASHBOARD VIEW
                     ========================================================================== -->
                <?php if ($jwtPayload['role'] !== 'Admin'): ?>
                                <div id="view-dashboard" class="tab-view active">
                    <!-- EXACT REPLICA OF RSK DASHBOARD -->
                    <style>
                        /* Custom RSK Dashboard Styles */
                        .rsk-grid-8 { display: grid; grid-template-columns: repeat(8, 1fr); gap: 12px; margin-bottom: 20px; }
                        .rsk-card-sm { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 10px; display: flex; flex-direction: column; align-items: center; justify-content: space-between; text-align: center; box-shadow: 0 1px 2px rgba(0,0,0,0.03); cursor: pointer; transition: all 0.2s ease; }
                        .rsk-card-sm:hover { border-color: #2563eb; box-shadow: 0 4px 12px rgba(37,99,235,0.08); transform: translateY(-1px); }
                        .rsk-icon-sm { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; }
                        .rsk-card-title { font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 4px; line-height: 1.2; height: 26px; display: flex; align-items: center; text-transform: capitalize; }
                        .rsk-card-value { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
                        .rsk-card-link { font-size: 10px; color: #3b82f6; text-decoration: none; font-weight: 600; }
                        .rsk-card-link:hover { text-decoration: underline; }

                        .rsk-row-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
                        <?php if ($isOrgAdmin): ?>
                        .rsk-row-3 { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 20px; margin-bottom: 20px; }
                        <?php else: ?>
                        .rsk-row-3 { display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; margin-bottom: 20px; }
                        <?php endif; ?>
                        .rsk-row-4 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px; }

                        .rsk-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; flex-direction: column; }
                        .rsk-panel-title { font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
                        .rsk-panel-link { font-size: 11px; color: #3b82f6; text-decoration: none; font-weight: 600; }

                        /* Pipeline Stepper */
                        .rsk-stepper { display: flex; justify-content: space-between; align-items: flex-start; position: relative; margin-top: 10px; padding: 0 10px; }
                        .rsk-stepper::before { content: ''; position: absolute; top: 12px; left: 20px; right: 20px; height: 2px; background: #e2e8f0; z-index: 1; }
                        .rsk-step { display: flex; flex-direction: column; align-items: center; z-index: 2; position: relative; width: 60px; }
                        .rsk-step-circle { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: #fff; margin-bottom: 8px; border: 2px solid #fff; box-shadow: 0 0 0 1px #e2e8f0; }
                        .rsk-step-title { font-size: 9px; font-weight: 600; color: #475569; text-align: center; line-height: 1.2; height: 22px; }
                        .rsk-step-count { background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 8px; }

                        /* Custom Tables */
                        .rsk-table { width: 100%; border-collapse: collapse; font-size: 11px; }
                        .rsk-table th { text-align: left; padding: 8px; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
                        .rsk-table td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-weight: 500; }
                        .rsk-progress-bar { height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; width: 60px; display: inline-block; vertical-align: middle; }
                        .rsk-progress-fill { height: 100%; background: #10b981; border-radius: 3px; }

                        /* Quick Actions */
                        .rsk-qa-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
                        .rsk-qa-btn { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 6px; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: all 0.2s; }
                        .rsk-qa-btn:hover { border-color: #3b82f6; box-shadow: 0 2px 8px rgba(37,99,235,0.1); }
                        .rsk-qa-icon { width: 24px; height: 24px; margin-bottom: 6px; display: flex; align-items: center; justify-content: center; }
                        .rsk-qa-text { font-size: 10px; font-weight: 600; color: #475569; text-align: center; }

                        /* Pills */
                        .rsk-pill { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; display: inline-block; }
                    </style>

                    <!-- Top 8 Cards -->
                    <div class="rsk-grid-8">
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#eff6ff; color:#3b82f6;"><i data-lucide="folder"></i></div>
                            <div class="rsk-card-title">Total<br>Projects</div>
                            <div class="rsk-card-value"><?= $rsk_totalProjects ?></div>
                            <a href="#projects" class="rsk-card-link tab-trigger" data-target="projects" data-filter-status="All">View all projects &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#fef3c7; color:#f59e0b;"><i data-lucide="hourglass"></i></div>
                            <div class="rsk-card-title">Pending<br>Projects</div>
                            <div class="rsk-card-value"><?= $rsk_pendingProjects ?></div>
                            <a href="#projects" class="rsk-card-link tab-trigger" data-target="projects" data-filter-status="Pending">View pending &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#dcfce7; color:#10b981;"><i data-lucide="check-circle-2"></i></div>
                            <div class="rsk-card-title">Approved<br>Projects</div>
                            <div class="rsk-card-value"><?= $rsk_approvedProjects ?></div>
                            <a href="#projects" class="rsk-card-link tab-trigger" data-target="projects" data-filter-status="Active">View approved &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#fee2e2; color:#ef4444;"><i data-lucide="x-circle"></i></div>
                            <div class="rsk-card-title">Rejected /<br>Query</div>
                            <div class="rsk-card-value"><?= $rsk_rejectedProjects ?></div>
                            <a href="#projects" class="rsk-card-link tab-trigger" data-target="projects" data-filter-status="Rejected">View details &rarr;</a>
                        </div>
                        <?php if ($isOrgAdmin): ?>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#fce7f3; color:#ec4899;"><i data-lucide="users"></i></div>
                            <div class="rsk-card-title">Total<br>Employees</div>
                            <div class="rsk-card-value"><?= $rsk_totalEmployees ?></div>
                            <a href="#users" class="rsk-card-link tab-trigger" data-target="users">View employees &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#e0e7ff; color:#6366f1;"><i data-lucide="check-square"></i></div>
                            <div class="rsk-card-title">Total<br>Tasks</div>
                            <div class="rsk-card-value"><?= $rsk_totalTasks ?></div>
                            <a href="#tasks" class="rsk-card-link tab-trigger" data-target="tasks">View tasks &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#f3e8ff; color:#8b5cf6;"><i data-lucide="users"></i></div>
                            <div class="rsk-card-title">Active<br>Clients</div>
                            <div class="rsk-card-value"><?= $rsk_activeClients ?></div>
                            <a href="#clients" class="rsk-card-link tab-trigger" data-target="clients">View clients &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#cffafe; color:#06b6d4;"><i data-lucide="map"></i></div>
                            <div class="rsk-card-title">Survey Works<br>In Progress</div>
                            <div class="rsk-card-value"><?= $rsk_surveyWorks ?></div>
                            <a href="#surveymanagement" class="rsk-card-link tab-trigger" data-target="surveymanagement">View surveys &rarr;</a>
                        </div>
                        <?php else: ?>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#e0e7ff; color:#6366f1;"><i data-lucide="clipboard-list"></i></div>
                            <div class="rsk-card-title">Assigned<br>Tasks</div>
                            <div class="rsk-card-value"><?= $user_assignedTasks ?></div>
                            <a href="#tasks" class="rsk-card-link tab-trigger" data-target="tasks">View tasks &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#fef3c7; color:#d97706;"><i data-lucide="play-circle"></i></div>
                            <div class="rsk-card-title">In Progress<br>Tasks</div>
                            <div class="rsk-card-value"><?= $user_inProgressTasks ?></div>
                            <a href="#tasks" class="rsk-card-link tab-trigger" data-target="tasks" data-filter-status="In Progress">View active &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#dcfce7; color:#16a34a;"><i data-lucide="check-circle-2"></i></div>
                            <div class="rsk-card-title">Completed<br>Tasks</div>
                            <div class="rsk-card-value"><?= $user_completedTasks ?></div>
                            <a href="#tasks" class="rsk-card-link tab-trigger" data-target="tasks" data-filter-status="Completed">View completed &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#fee2e2; color:#ef4444;"><i data-lucide="alert-circle"></i></div>
                            <div class="rsk-card-title">Pending<br>Tasks</div>
                            <div class="rsk-card-value"><?= $user_pendingTasks ?></div>
                            <a href="#tasks" class="rsk-card-link tab-trigger" data-target="tasks" data-filter-status="Todo">View pending &rarr;</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Row 2: Pipeline & Service-wise -->
                    <div class="rsk-row-2">
                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span><i data-lucide="bar-chart-2" style="width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:4px; color:#3b82f6;"></i> Project Pipeline</span>
                                <a href="#" class="rsk-panel-link">View All &rarr;</a>
                            </div>
                            <div class="rsk-stepper">
                                <?php
                                $pipelineStages = [
                                    ['Lead Received', '#3b82f6'],
                                    ['Eligibility Check', '#06b6d4'],
                                    ['Fee Discussion', '#10b981'],
                                    ['Advance Received', '#22c55e'],
                                    ['Draft Preparation', '#84cc16'],
                                    ['Client Approval', '#eab308'],
                                    ['Document Collection', '#f59e0b'],
                                    ['Application Submitted', '#f97316'],
                                    ['NOC Process', '#ef4444'],
                                    ['Approval Received', '#ec4899'],
                                    ['Project Completed', '#a855f7']
                                ];
                                foreach($pipelineStages as $idx => $stage):
                                    $count = $pipelineCounts[$stage[0]] ?? 0;
                                ?>
                                <div class="rsk-step" style="cursor:pointer;" onclick="openStageModal('<?= htmlspecialchars($stage[0], ENT_QUOTES) ?>')" title="Click to see projects in '<?= htmlspecialchars($stage[0], ENT_QUOTES) ?>'">
                                    <div class="rsk-step-circle" style="background: <?= $stage[1] ?>; box-shadow: 0 0 0 2px <?= $stage[1] ?>33;"><?= $idx+1 ?></div>
                                    <div class="rsk-step-title" style="color:#3b82f6; cursor:pointer;"><?= str_replace(' ', '<br>', htmlspecialchars($stage[0])) ?></div>
                                    <div class="rsk-step-count" style="<?= $count > 0 ? 'background:#eff6ff; border-color:#bfdbfe; color:#2563eb;' : '' ?>"><?= $count ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Service-wise Projects</span>
                                <a href="#" class="rsk-panel-link">View All &rarr;</a>
                            </div>
                            <table class="rsk-table">
                                <thead>
                                    <tr><th>Service Type</th><th>Total</th><th>Pending</th><th>Completed</th><th>Completion %</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $serviceNames = [
                                        ['Layout',      'layout',   '#3b82f6'],
                                        ['Building',    'home',     '#f59e0b'],
                                        ['Single Plot', 'square',   '#eab308'],
                                        ['UAL',         'maximize', '#10b981'],
                                        ['Land Survey', 'map',      '#a855f7']
                                    ];
                                    $totTotal=0; $totPend=0; $totComp=0;
                                    foreach($serviceNames as $sn):
                                        $c = $serviceCounts[$sn[0]] ?? ['total'=>0,'comp'=>0,'pend'=>0];
                                        $totTotal += $c['total'];
                                        $totPend  += $c['pend'];
                                        $totComp  += $c['comp'];
                                        $pct = $c['total'] > 0 ? round(($c['comp']/$c['total'])*100) : 0;
                                    ?>
                                    <tr>
                                        <td style="color:#0f172a; font-weight:600;"><i data-lucide="<?= $sn[1] ?>" style="width:12px; height:12px; color:<?= $sn[2] ?>; display:inline-block; vertical-align:middle; margin-right:4px;"></i> <?= $sn[0] ?></td>
                                        <td style="text-align:center;"><?= $c['total'] ?></td>
                                        <td style="text-align:center;"><?= $c['pend'] ?></td>
                                        <td style="text-align:center;"><?= $c['comp'] ?></td>
                                        <td>
                                            <div class="rsk-progress-bar"><div class="rsk-progress-fill" style="width:<?= $pct ?>%;"></div></div>
                                            <span style="font-size:10px; color:#64748b; margin-left:4px;"><?= $pct ?>%</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr style="border-top: 2px solid #e2e8f0;">
                                        <td style="font-weight:700; color:#0f172a;">Total</td>
                                        <td style="text-align:center; font-weight:700; color:#0f172a;"><?= $totTotal ?></td>
                                        <td style="text-align:center; font-weight:700; color:#0f172a;"><?= $totPend ?></td>
                                        <td style="text-align:center; font-weight:700; color:#0f172a;"><?= $totComp ?></td>
                                        <td style="font-weight:700; color:#0f172a;"><?= $totTotal > 0 ? round(($totComp/$totTotal)*100) : 0 ?>%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ===== GOAL TRACKER SECTION ===== -->
                    <?php if ($isOrgAdmin): ?>
                    <div style="margin-bottom: 18px;">
                        <div class="rsk-panel" style="padding: 0;">
                            <!-- Header -->
                            <div style="display:flex; justify-content:space-between; align-items:center; padding: 16px 20px; border-bottom: 1px solid #e2e8f0;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:32px; height:32px; background:linear-gradient(135deg,#6366f1,#8b5cf6); border-radius:8px; display:flex; align-items:center; justify-content:center; color:#fff;">
                                        <i data-lucide="target" style="width:16px;height:16px;"></i>
                                    </div>
                                    <div>
                                        <div style="font-size:14px; font-weight:700; color:#0f172a;">Goal Tracker</div>
                                        <div style="font-size:11px; color:#64748b;">Monthly organizational goals &amp; milestones</div>
                                    </div>
                                </div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <select id="goal-filter-select" onchange="filterGoals(this.value)" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; padding: 6px 12px; font-size: 11px; font-weight: 600; color: #475569; outline: none; cursor: pointer; font-family: inherit;">
                                        <option value="all">All Goals</option>
                                        <option value="month">This Month</option>
                                        <option value="year">This Year</option>
                                    </select>
                                    <button onclick="openGoalModal()" style="display:flex; align-items:center; gap:6px; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; border:none; border-radius:8px; padding:8px 14px; font-size:12px; font-weight:600; cursor:pointer; font-family:inherit; transition:all 0.2s;">
                                        <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Goal
                                    </button>
                                </div>
                            </div>
                            <!-- Summary Stats -->
                            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:0; border-bottom:1px solid #e2e8f0;">
                                <?php
                                $goalStatCards = [
                                    ['Total Goals', $goal_total, '#6366f1', '#eef2ff', 'target'],
                                    ['Completed', $goal_completed, '#10b981', '#dcfce7', 'check-circle'],
                                    ['Pending', $goal_pending, '#f59e0b', '#fef3c7', 'clock'],
                                    ['Overdue', $goal_overdue, '#ef4444', '#fee2e2', 'alert-circle'],
                                ];
                                foreach($goalStatCards as $i => $gs):
                                ?>
                                <div style="padding:14px 16px; <?= $i > 0 ? 'border-left:1px solid #e2e8f0;' : '' ?> text-align:center;">
                                    <div style="width:28px; height:28px; background:<?= $gs[3] ?>; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 6px; color:<?= $gs[2] ?>;">
                                        <i data-lucide="<?= $gs[4] ?>" style="width:13px;height:13px;"></i>
                                    </div>
                                    <div style="font-size:20px; font-weight:800; color:<?= $gs[2] ?>;"><?= $gs[1] ?></div>
                                    <div style="font-size:10px; color:#64748b; font-weight:600;"><?= $gs[0] ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Goals List -->
                            <div style="padding:16px 20px; display:flex; flex-direction:column; gap:12px;">
                                <div id="goal-empty-msg" style="display: <?= empty($goalsList) ? 'block' : 'none' ?>; text-align:center; padding:40px 20px; color:#94a3b8;">
                                    <i data-lucide="target" style="width:40px;height:40px;margin-bottom:12px;color:#cbd5e1;display:block;margin-left:auto;margin-right:auto;"></i>
                                    <div style="font-size:14px; font-weight:600; margin-bottom:4px;">No Goals Found</div>
                                    <div style="font-size:12px;">There are no goals matching the selected filter.</div>
                                </div>
                                <?php if (!empty($goalsList)): foreach($goalsList as $goal):
                                    $gStart = new DateTime($goal['start_date']);
                                    $gTarget = new DateTime($goal['target_date']);
                                    $gNow = new DateTime($today);
                                    $totalDays = max(1, (int)$gStart->diff($gTarget)->days);
                                    $elapsedDays = (int)$gStart->diff($gNow)->days;
                                    $timelinePos = min(100, max(0, round(($elapsedDays / $totalDays) * 100)));
                                    $remainDays = (int)$gNow->diff($gTarget)->days;
                                    $isPast = $gTarget < $gNow;
                                    $statusColors = [
                                        'Not Started' => ['bg'=>'#f1f5f9','text'=>'#475569','dot'=>'#94a3b8'],
                                        'In Progress' => ['bg'=>'#dbeafe','text'=>'#1d4ed8','dot'=>'#3b82f6'],
                                        'Completed'   => ['bg'=>'#dcfce7','text'=>'#15803d','dot'=>'#10b981'],
                                        'Overdue'     => ['bg'=>'#fee2e2','text'=>'#dc2626','dot'=>'#ef4444'],
                                    ];
                                    $sc = $statusColors[$goal['status']] ?? $statusColors['Not Started'];
                                    $barColor = $goal['status'] === 'Completed' ? '#10b981' : ($goal['status'] === 'Overdue' ? '#ef4444' : '#6366f1');
                                ?>
                                <div class="goal-card goal-item-row" data-target-date="<?= htmlspecialchars($goal['target_date']) ?>" style="border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; background:#fff; transition:all 0.2s;">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                                        <div style="flex:1; min-width:0;">
                                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                                <span style="font-size:13px; font-weight:700; color:#0f172a;"><?= htmlspecialchars($goal['title']) ?></span>
                                                <span style="background:<?= $sc['bg'] ?>; color:<?= $sc['text'] ?>; font-size:10px; font-weight:700; padding:2px 8px; border-radius:99px; display:inline-flex; align-items:center; gap:4px;">
                                                    <span style="width:5px;height:5px;border-radius:50%;background:<?= $sc['dot'] ?>;"></span>
                                                    <?= htmlspecialchars($goal['status']) ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($goal['description'])): ?>
                                            <div style="font-size:11px; color:#64748b; margin-top:3px;"><?= htmlspecialchars(substr($goal['description'], 0, 100)) ?><?= strlen($goal['description']) > 100 ? '…' : '' ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display:flex; gap:6px; margin-left:10px; flex-shrink:0;">
                                            <?php if ($goal['status'] !== 'Completed'): ?>
                                            <button onclick="markGoalComplete(<?= $goal['id'] ?>)" style="font-size:10px; font-weight:600; padding:4px 10px; background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; border-radius:6px; cursor:pointer; font-family:inherit;">✓ Done</button>
                                            <?php endif; ?>
                                            <button onclick="deleteGoal(<?= $goal['id'] ?>)" style="font-size:10px; padding:4px 8px; background:#fee2e2; color:#dc2626; border:1px solid #fecaca; border-radius:6px; cursor:pointer;">🗑</button>
                                        </div>
                                    </div>
                                    <!-- Timeline Bar -->
                                    <div style="margin-bottom:6px;">
                                        <div style="display:flex; justify-content:space-between; font-size:10px; color:#64748b; margin-bottom:4px;">
                                            <span>📅 Start: <?= date('d M Y', strtotime($goal['start_date'])) ?></span>
                                            <span style="font-weight:600; color:<?= $goal['status'] === 'Completed' ? '#10b981' : ($isPast ? '#ef4444' : '#6366f1') ?>;">
                                                <?= $goal['status'] === 'Completed' ? '✓ Completed' : ($isPast ? 'Overdue by ' . $remainDays . ' day' . ($remainDays != 1 ? 's' : '') : $remainDays . ' day' . ($remainDays != 1 ? 's' : '') . ' remaining') ?>
                                            </span>
                                            <span>🎯 Target: <?= date('d M Y', strtotime($goal['target_date'])) ?></span>
                                        </div>
                                        <div style="position:relative; height:8px; background:#f1f5f9; border-radius:99px; overflow:hidden;">
                                            <div style="height:100%; width:<?= $goal['progress'] ?>%; background:<?= $barColor ?>; border-radius:99px; transition:width 0.5s ease;"></div>
                                            <!-- Today marker -->
                                            <?php if ($goal['status'] !== 'Completed' && $timelinePos > 0 && $timelinePos < 100): ?>
                                            <div style="position:absolute; top:0; left:<?= $timelinePos ?>%; transform:translateX(-50%); height:100%; width:2px; background:#0f172a; opacity:0.5;"></div>
                                            <?php endif; ?>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; font-size:10px; color:#94a3b8; margin-top:3px;">
                                            <span>Progress: <?= $goal['progress'] ?>%</span>
                                            <?php if ($goal['status'] !== 'Completed'): ?>
                                            <span>Today marker at <?= $timelinePos ?>% of timeline</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <!-- Progress Update Slider (for In Progress goals) -->
                                    <?php if ($goal['status'] === 'In Progress' || $goal['status'] === 'Not Started'): ?>
                                    <div style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                                        <span style="font-size:10px; color:#64748b; white-space:nowrap;">Update %:</span>
                                        <input type="range" min="0" max="100" value="<?= $goal['progress'] ?>" 
                                               class="goal-progress-slider" data-goal-id="<?= $goal['id'] ?>"
                                               style="flex:1; height:4px; accent-color:#6366f1; cursor:pointer;">
                                        <span class="goal-progress-display" style="font-size:11px; font-weight:700; color:#6366f1; min-width:30px;"><?= $goal['progress'] ?>%</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- ===== ADD GOAL MODAL ===== -->
                    <div id="goalModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
                        <div style="background:#fff; border-radius:14px; width:90%; max-width:520px; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid #e2e8f0;">
                                <h3 style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">🎯 Add New Goal</h3>
                                <button onclick="closeGoalModal()" style="border:none; background:none; cursor:pointer; font-size:22px; color:#64748b; line-height:1;">&times;</button>
                            </div>
                            <div style="padding:24px;">
                                <div style="margin-bottom:14px;">
                                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:5px;">Goal Title *</label>
                                    <input type="text" id="goalTitle" placeholder="e.g. Complete Q1 client approvals" class="form-control" style="width:100%;">
                                </div>
                                <div style="margin-bottom:14px;">
                                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:5px;">Description</label>
                                    <textarea id="goalDesc" placeholder="Describe the goal..." class="form-control" rows="2" style="width:100%; resize:vertical;"></textarea>
                                </div>
                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                                    <div>
                                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:5px;">Start Date *</label>
                                        <input type="date" id="goalStartDate" class="form-control" style="width:100%;" value="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div>
                                        <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:5px;">Target Date *</label>
                                        <input type="date" id="goalTargetDate" class="form-control" style="width:100%;">
                                    </div>
                                </div>
                                <div style="margin-bottom:20px;">
                                    <label style="display:block; font-size:12px; font-weight:600; color:#374151; margin-bottom:5px;">Initial Status</label>
                                    <select id="goalStatus" class="form-control" style="width:100%;">
                                        <option value="Not Started">Not Started</option>
                                        <option value="In Progress">In Progress</option>
                                    </select>
                                </div>
                                <div style="display:flex; gap:10px; justify-content:flex-end;">
                                    <button onclick="closeGoalModal()" style="padding:9px 18px; background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; font-family:inherit;">Cancel</button>
                                    <button onclick="saveGoal()" style="padding:9px 18px; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#fff; border:none; border-radius:8px; cursor:pointer; font-size:13px; font-weight:600; font-family:inherit;">Save Goal</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                    function filterGoals(type) {
                        const goals = document.querySelectorAll('.goal-item-row');
                        const now = new Date();
                        const currentYear = now.getFullYear().toString();
                        const currentMonth = (now.getMonth() + 1).toString().padStart(2, '0');
                        const currentYM = `${currentYear}-${currentMonth}`;
                        
                        let visibleCount = 0;
                        
                        goals.forEach(goal => {
                            const targetDate = goal.getAttribute('data-target-date');
                            if (type === 'all') {
                                goal.style.display = 'block';
                                visibleCount++;
                            } else if (type === 'month') {
                                if (targetDate && targetDate.startsWith(currentYM)) {
                                    goal.style.display = 'block';
                                    visibleCount++;
                                } else {
                                    goal.style.display = 'none';
                                }
                            } else if (type === 'year') {
                                if (targetDate && targetDate.startsWith(currentYear)) {
                                    goal.style.display = 'block';
                                    visibleCount++;
                                } else {
                                    goal.style.display = 'none';
                                }
                            }
                        });
                        
                        const emptyMsg = document.getElementById('goal-empty-msg');
                        if (emptyMsg) {
                            emptyMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
                        }
                    }
                    function openGoalModal() {
                        document.getElementById('goalModal').style.display = 'flex';
                    }
                    function closeGoalModal() {
                        document.getElementById('goalModal').style.display = 'none';
                        document.getElementById('goalTitle').value = '';
                        document.getElementById('goalDesc').value = '';
                        document.getElementById('goalTargetDate').value = '';
                    }
                    function saveGoal() {
                        const title = document.getElementById('goalTitle').value.trim();
                        const desc = document.getElementById('goalDesc').value.trim();
                        const startDate = document.getElementById('goalStartDate').value;
                        const targetDate = document.getElementById('goalTargetDate').value;
                        const status = document.getElementById('goalStatus').value;
                        if (!title || !startDate || !targetDate) { alert('Title, Start Date and Target Date are required.'); return; }
                        if (targetDate <= startDate) { alert('Target date must be after start date.'); return; }
                        const fd = new FormData();
                        fd.append('action', 'create_goal');
                        fd.append('title', title);
                        fd.append('description', desc);
                        fd.append('start_date', startDate);
                        fd.append('target_date', targetDate);
                        fd.append('status', status);
                        fetch('api.php', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) { closeGoalModal(); window.location.reload(); }
                                else { alert(data.message || 'Failed to save goal.'); }
                            });
                    }
                    function markGoalComplete(goalId) {
                        if (!confirm('Mark this goal as completed?')) return;
                        const fd = new FormData();
                        fd.append('action', 'mark_goal_complete');
                        fd.append('goal_id', goalId);
                        fetch('api.php', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) window.location.reload();
                                else alert(data.message || 'Failed.');
                            });
                    }
                    function deleteGoal(goalId) {
                        if (!confirm('Delete this goal permanently?')) return;
                        const fd = new FormData();
                        fd.append('action', 'delete_goal');
                        fd.append('goal_id', goalId);
                        fetch('api.php', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) window.location.reload();
                                else alert(data.message || 'Failed.');
                            });
                    }
                    // Goal progress slider
                    document.addEventListener('DOMContentLoaded', function() {
                        document.querySelectorAll('.goal-progress-slider').forEach(function(slider) {
                            const display = slider.nextElementSibling;
                            slider.addEventListener('input', function() {
                                display.textContent = this.value + '%';
                            });
                            let timer;
                            slider.addEventListener('change', function() {
                                clearTimeout(timer);
                                const goalId = this.dataset.goalId;
                                const progress = this.value;
                                timer = setTimeout(function() {
                                    const fd = new FormData();
                                    fd.append('action', 'update_goal_progress');
                                    fd.append('goal_id', goalId);
                                    fd.append('progress', progress);
                                    fetch('api.php', { method: 'POST', body: fd })
                                        .then(r => r.json())
                                        .then(data => { if (!data.success) alert('Failed to update progress.'); });
                                }, 600);
                            });
                        });
                    });
                    </script>
                    <?php endif; ?>

                    <!-- Row 3: Recent, NOC, Notifications -->
                    <div class="rsk-row-3">
                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Recent Projects</span>
                                <a href="#projects" class="rsk-panel-link tab-trigger" data-target="projects">View All &rarr;</a>
                            </div>
                            <table class="rsk-table">
                                <thead>
                                    <tr><th>Project ID</th><th>Client Name</th><th>Service Type</th><th>Location</th><th>Current Status</th><th>Target Date</th></tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recentProjectsLimit = array_slice($projects, 0, 5);
                                    $statusPillMap = [
                                        'Active'    => 'background:#eff6ff; color:#2563eb;',
                                        'Pending'   => 'background:#fef3c7; color:#d97706;',
                                        'Completed' => 'background:#dcfce7; color:#16a34a;',
                                        'Rejected'  => 'background:#fee2e2; color:#dc2626;',
                                        'On Hold'   => 'background:#f3e8ff; color:#9333ea;',
                                    ];
                                    if (empty($recentProjectsLimit)): ?>
                                    <tr><td colspan="6" style="text-align:center; color:#94a3b8; padding:20px;">No projects yet. Add your first project!</td></tr>
                                    <?php else: foreach($recentProjectsLimit as $i => $rp):
                                        $rpStatus = $rp['status'] ?? 'Active';
                                        $rpPillStyle = $statusPillMap[$rpStatus] ?? 'background:#f1f5f9; color:#475569;';
                                        $rpPipeline = $rp['pipeline_stage'] ?? $rpStatus;
                                        $rpDue = $rp['due_date'] ? date('d M Y', strtotime($rp['due_date'])) : '-';
                                    ?>
                                    <tr>
                                        <td style="font-weight:600;">#<?= str_pad($rp['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                        <td><?= htmlspecialchars($rp['client_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($rp['service_type'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($rp['name']) ?></td>
                                        <td><span class="rsk-pill" style="<?= $rpPillStyle ?>"><?= htmlspecialchars($rpPipeline) ?></span></td>
                                        <td><i data-lucide="calendar" style="width:10px; height:10px; display:inline-block;"></i> <?= $rpDue ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($isOrgAdmin): ?>
                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>NOC Tracking</span>
                                <a href="#" class="rsk-panel-link">View All &rarr;</a>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: center; height: 180px;">
                                <?php
                                $svcColors = ['Layout'=>'#3b82f6','Building'=>'#f59e0b','Single Plot'=>'#eab308','UAL'=>'#10b981','Land Survey'=>'#a855f7'];
                                $svcTotal = array_sum(array_column($serviceCounts,'total')) ?: 1;
                                ?>
                                <div style="position: relative; width: 140px; height: 140px;">
                                    <canvas id="nocChart"></canvas>
                                    <div style="position: absolute; top:0; left:0; right:0; bottom:0; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                        <span style="font-size: 20px; font-weight: 700; color: #0f172a;"><?= $rsk_totalProjects ?></span>
                                        <span style="font-size: 8px; color: #64748b;">Total Projects</span>
                                    </div>
                                </div>
                                <div style="margin-left: 20px; font-size: 10px;">
                                    <?php foreach(['Layout','Building','Single Plot','UAL','Land Survey'] as $sn): 
                                        $sv = $serviceCounts[$sn] ?? ['total'=>0];
                                        $svPct = round(($sv['total']/$svcTotal)*100);
                                        $svColor = $svcColors[$sn];
                                    ?>
                                    <div style="display:flex; justify-content:space-between; margin-bottom:6px; width:160px;"><span style="color:#475569;"><span style="color:<?= $svColor ?>;">■</span> <?= $sn ?></span> <span style="font-weight:600; color:#0f172a;"><?= $sv['total'] ?> (<?= $svPct ?>%)</span></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div style="font-size: 9px; color: #94a3b8; text-align: center; margin-top: 10px;">* Multiple NOCs may be applicable for a project</div>
                        </div>
                        <?php endif; ?>

                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Notifications</span>
                                <a href="#" class="rsk-panel-link" id="btn-view-all-notifications">View All &rarr;</a>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:16px;">
                                <?php if (empty($recentNotifications)): ?>
                                    <div style="text-align:center; padding: 20px; color:#64748b; font-size:11px;">
                                        <i data-lucide="bell-off" style="width:24px; height:24px; margin-bottom:8px; color:#94a3b8; display:inline-block;"></i>
                                        <div>No new notifications</div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentNotifications as $n): 
                                        $cat = $n['category'];
                                        $bg = '#eff6ff'; $color = '#3b82f6'; $icon = 'info';
                                        if ($cat === 'success') {
                                            $bg = '#dcfce7'; $color = '#10b981'; $icon = 'check';
                                        } else if ($cat === 'warning') {
                                            $bg = '#fef3c7'; $color = '#f59e0b'; $icon = 'alert-triangle';
                                        } else if ($cat === 'danger') {
                                            $bg = '#fee2e2'; $color = '#ef4444'; $icon = 'alert-circle';
                                        }
                                        $timeStr = time_elapsed_string($n['created_at']);
                                    ?>
                                        <div style="display:flex; align-items:flex-start; gap:10px;">
                                            <div style="width:24px; height:24px; background:<?= $bg ?>; color:<?= $color ?>; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;"><i data-lucide="<?= $icon ?>" style="width:12px; height:12px;"></i></div>
                                            <div style="flex:1;">
                                                <div style="font-size:11px; font-weight:600; color:#0f172a; line-height: 1.3;"><?= htmlspecialchars($n['message']) ?></div>
                                            </div>
                                            <div style="font-size:9px; color:#94a3b8; white-space:nowrap;"><?= $timeStr ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Status, Top Services, Quick Actions -->
                    <div class="rsk-row-4">
                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Projects by Status</span>
                                <a href="#" class="rsk-panel-link">View Report &rarr;</a>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: center; height: 180px;">
                                <?php
                                $stTotal = array_sum($projectStatusCounts) ?: 1;
                                $stCompleted = $projectStatusCounts['Completed'] ?? 0;
                                $stActive    = $projectStatusCounts['Active']    ?? 0;
                                $stPending   = $projectStatusCounts['Pending']   ?? 0;
                                $stRejected  = $projectStatusCounts['Rejected']  ?? 0;
                                ?>
                                <div style="position: relative; width: 140px; height: 140px;">
                                    <canvas id="statusChart"></canvas>
                                    <div style="position: absolute; top:0; left:0; right:0; bottom:0; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                        <span style="font-size: 20px; font-weight: 700; color: #0f172a;"><?= $rsk_totalProjects ?></span>
                                    </div>
                                </div>
                                <div style="margin-left: 20px; font-size: 11px;">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; width:130px;"><span style="color:#475569;"><span style="color:#10b981;">■</span> Completed</span> <span style="font-weight:600; color:#0f172a;"><?= $stCompleted ?> (<?= round($stCompleted/$stTotal*100) ?>%)</span></div>
                                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; width:130px;"><span style="color:#475569;"><span style="color:#3b82f6;">■</span> Active</span> <span style="font-weight:600; color:#0f172a;"><?= $stActive ?> (<?= round($stActive/$stTotal*100) ?>%)</span></div>
                                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; width:130px;"><span style="color:#475569;"><span style="color:#f59e0b;">■</span> Pending</span> <span style="font-weight:600; color:#0f172a;"><?= $stPending ?> (<?= round($stPending/$stTotal*100) ?>%)</span></div>
                                    <div style="display:flex; justify-content:space-between; width:130px;"><span style="color:#475569;"><span style="color:#ef4444;">■</span> Rejected</span> <span style="font-weight:600; color:#0f172a;"><?= $stRejected ?> (<?= round($stRejected/$stTotal*100) ?>%)</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Top Services by Task Volume</span>
                                <a href="#reports" class="rsk-panel-link tab-trigger" data-target="reports">View Report &rarr;</a>
                            </div>
                            <div style="height: 180px;">
                                <canvas id="servicesChart"></canvas>
                            </div>
                        </div>

                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Quick Actions</span>
                            </div>
                            <div class="rsk-qa-grid" style="height: 180px; align-content: center;">
                                <div class="rsk-qa-btn" data-action="new-project"><div class="rsk-qa-icon" style="color:#3b82f6;"><i data-lucide="plus-circle"></i></div><div class="rsk-qa-text">New Project</div></div>
                                <div class="rsk-qa-btn" data-action="add-client"><div class="rsk-qa-icon" style="color:#10b981;"><i data-lucide="user-plus"></i></div><div class="rsk-qa-text">Add Client</div></div>
                                <div class="rsk-qa-btn" data-action="new-survey"><div class="rsk-qa-icon" style="color:#f59e0b;"><i data-lucide="map"></i></div><div class="rsk-qa-text">New Survey</div></div>
                                <div class="rsk-qa-btn" data-action="upload-document"><div class="rsk-qa-icon" style="color:#a855f7;"><i data-lucide="upload-cloud"></i></div><div class="rsk-qa-text">Upload Document</div></div>
                                <div class="rsk-qa-btn" data-action="noc-tracker"><div class="rsk-qa-icon" style="color:#22c55e;"><i data-lucide="check-shield"></i></div><div class="rsk-qa-text">NOC Tracker</div></div>
                                <div class="rsk-qa-btn" data-action="payment-entry"><div class="rsk-qa-icon" style="color:#ec4899;"><i data-lucide="credit-card"></i></div><div class="rsk-qa-text">Payment Entry</div></div>
                                <div class="rsk-qa-btn" data-action="task-manager"><div class="rsk-qa-icon" style="color:#3b82f6;"><i data-lucide="clipboard-list"></i></div><div class="rsk-qa-text">Task Manager</div></div>
                                <div class="rsk-qa-btn" data-action="reports"><div class="rsk-qa-icon" style="color:#f97316;"><i data-lucide="bar-chart-2"></i></div><div class="rsk-qa-text">Reports</div></div>
                            </div>
                        </div>
                    </div>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(() => {
                            // Service-wise doughnut (replaces NOC)
                            var nocCanvas = document.getElementById('nocChart');
                            if (nocCanvas) {
                                var svcData = <?= json_encode(array_map(fn($sn) => $serviceCounts[$sn]['total'] ?? 0, ['Layout','Building','Single Plot','UAL','Land Survey'])) ?>;
                                new Chart(nocCanvas.getContext('2d'), {
                                    type: 'doughnut',
                                    data: {
                                        labels: ['Layout','Building','Single Plot','UAL','Land Survey'],
                                        datasets: [{
                                            data: svcData,
                                            backgroundColor: ['#3b82f6','#f59e0b','#eab308','#10b981','#a855f7'],
                                            borderWidth: 0,
                                            cutout: '75%'
                                        }]
                                    },
                                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: true } } }
                                });
                            }

                            // Status Chart – fully dynamic
                            new Chart(document.getElementById('statusChart').getContext('2d'), {
                                type: 'doughnut',
                                data: {
                                    labels: ['Completed','Active','Pending','Rejected'],
                                    datasets: [{
                                        data: [<?= $stCompleted ?>, <?= $stActive ?>, <?= $stPending ?>, <?= $stRejected ?>],
                                        backgroundColor: ['#10b981','#3b82f6','#f59e0b','#ef4444'],
                                        borderWidth: 0,
                                        cutout: '75%'
                                    }]
                                },
                                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: true } } }
                            });

                            // Services bar chart – dynamic from DB
                            var svcLabels = ['Layout','Building','Land Survey','Single Plot','UAL'];
                            var svcBarData = [
                                <?= ($serviceCounts['Layout']['total']      ?? 0) ?>,
                                <?= ($serviceCounts['Building']['total']    ?? 0) ?>,
                                <?= ($serviceCounts['Land Survey']['total'] ?? 0) ?>,
                                <?= ($serviceCounts['Single Plot']['total'] ?? 0) ?>,
                                <?= ($serviceCounts['UAL']['total']         ?? 0) ?>
                            ];
                            new Chart(document.getElementById('servicesChart').getContext('2d'), {
                                type: 'bar',
                                data: {
                                    labels: svcLabels,
                                    datasets: [{
                                        data: svcBarData,
                                        backgroundColor: ['#3b82f6','#f59e0b','#a855f7','#eab308','#10b981'],
                                        borderRadius: 4,
                                        barThickness: 10
                                    }]
                                },
                                options: {
                                    indexAxis: 'y',
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        x: { display: true, grid: { display: false }, ticks: { stepSize: 1, font:{size:9} } },
                                        y: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10, family: "'Outfit', sans-serif" }, color: '#475569' } }
                                    },
                                    plugins: { legend: { display: false } }
                                }
                            });

                            if(typeof lucide !== 'undefined') lucide.createIcons();
                        }, 500);
                    });
                    </script>

                    <!-- ===== PIPELINE STAGE MODAL ===== -->
                    <div id="stageModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
                        <div style="background:#fff; border-radius:12px; width:90%; max-width:700px; max-height:85vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                            <div style="display:flex; justify-content:space-between; align-items:center; padding:20px 24px; border-bottom:1px solid #e2e8f0;">
                                <h3 id="stageModalTitle" style="margin:0; font-size:16px; font-weight:700; color:#0f172a;">Stage Projects</h3>
                                <button onclick="closeStageModal()" style="border:none; background:none; cursor:pointer; font-size:20px; color:#64748b; line-height:1;">&times;</button>
                            </div>
                            <div style="padding:20px 24px; overflow-y:auto; flex:1;">
                                <div id="stageModalLoader" style="text-align:center; padding:40px; color:#64748b;">
                                    <div style="font-size:24px; margin-bottom:10px;">⏳</div>Loading projects...
                                </div>
                                <div id="stageModalContent" style="display:none;">
                                    <div id="stageModalSummary" style="display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap;"></div>
                                    <div id="stageProjectsList" style="display:flex; flex-direction:column; gap:14px;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                    function openStageModal(stageName) {
                        var modal = document.getElementById('stageModal');
                        modal.style.display = 'flex';
                        document.getElementById('stageModalTitle').innerText = '📋 Stage: ' + stageName;
                        document.getElementById('stageModalLoader').style.display = 'block';
                        document.getElementById('stageModalContent').style.display = 'none';

                        fetch('api.php?action=get_pipeline_stage_details&stage=' + encodeURIComponent(stageName))
                            .then(function(r){ return r.json(); })
                            .then(function(data) {
                                document.getElementById('stageModalLoader').style.display = 'none';
                                document.getElementById('stageModalContent').style.display = 'block';

                                var summary = document.getElementById('stageModalSummary');
                                summary.innerHTML = '<div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px 20px; text-align:center;"><div style="font-size:24px; font-weight:700; color:#2563eb;">' + (data.total || 0) + '</div><div style="font-size:11px; color:#64748b;">Total Projects</div></div>' +
                                    '<div style="background:#dcfce7; border:1px solid #bbf7d0; border-radius:8px; padding:12px 20px; text-align:center;"><div style="font-size:24px; font-weight:700; color:#16a34a;">' + (data.file_count || 0) + '</div><div style="font-size:11px; color:#64748b;">Total Files</div></div>';

                                var list = document.getElementById('stageProjectsList');
                                list.innerHTML = '';
                                if (!data.projects || data.projects.length === 0) {
                                    list.innerHTML = '<div style="text-align:center; padding:40px; color:#94a3b8; border:2px dashed #e2e8f0; border-radius:8px;">No projects in this stage yet.</div>';
                                    return;
                                }
                                data.projects.forEach(function(p) {
                                    var filesHtml = '';
                                    if (p.files && p.files.length > 0) {
                                        filesHtml = '<div style="margin-top:12px; background:#f8fafc; border-radius:6px; padding:10px 14px; border:1px solid #e2e8f0;">' +
                                            '<div style="font-size:10px; font-weight:700; color:#475569; text-transform:uppercase; margin-bottom:8px; letter-spacing:0.5px;">📎 Files (' + p.files.length + ')</div>' +
                                            '<div style="display:flex; flex-direction:column; gap:6px;">';
                                        p.files.forEach(function(f) {
                                            filesHtml += '<div style="display:flex; align-items:center; gap:8px; font-size:12px;">'
                                                + '<span style="color:#10b981;">📄</span>'
                                                + '<a href="uploads/' + f.filepath + '" target="_blank" style="color:#2563eb; text-decoration:none; font-weight:500;">' + f.name + '</a>'
                                                + '<span style="color:#94a3b8; font-size:10px;">' + (f.size || '') + '</span>'
                                                + '</div>';
                                        });
                                        filesHtml += '</div></div>';
                                    } else {
                                        filesHtml = '<div style="margin-top:8px; font-size:11px; color:#94a3b8; font-style:italic;">No files uploaded yet.</div>';
                                    }
                                    list.innerHTML += '<div style="border:1px solid #e2e8f0; border-radius:10px; padding:16px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.05);">'
                                        + '<div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px;">'
                                            + '<div>'
                                                + '<div style="font-size:14px; font-weight:700; color:#0f172a;">' + p.name + '</div>'
                                                + '<div style="font-size:11px; color:#64748b; margin-top:2px;">Client: ' + (p.client_name || 'N/A') + ' &nbsp;|&nbsp; Service: ' + (p.service_type || '-') + '</div>'
                                            + '</div>'
                                            + '<span style="background:#eff6ff; color:#2563eb; padding:3px 8px; border-radius:99px; font-size:10px; font-weight:600; white-space:nowrap;">' + p.status + '</span>'
                                        + '</div>'
                                        + filesHtml
                                        + '</div>';
                                });
                            })
                            .catch(function() {
                                document.getElementById('stageModalLoader').innerHTML = '<span style="color:#ef4444;">Error loading data. Please try again.</span>';
                            });
                    }
                    function closeStageModal() {
                        document.getElementById('stageModal').style.display = 'none';
                    }
                    // Close on outside click
                    document.getElementById('stageModal').addEventListener('click', function(e){
                        if(e.target === this) closeStageModal();
                    });
                    </script>
                </div>


                <!-- ==========================================================================
                     TAB: LAYOUT MODULE VIEW
                     ========================================================================== -->
                <div id="view-layout" class="tab-view">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0;">Layout Generation</h2>
                    </div>

                    <!-- Two-column top row: Create new | Load existing -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; align-items: start;">

                        <!-- Create New Sequence -->
                        <div class="section-card" style="padding: 24px;">
                            <h3 style="margin-bottom: 20px; font-size: 15px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                                <i data-lucide="plus-circle" style="width:15px;height:15px;vertical-align:middle;margin-right:5px;color:#3b82f6;"></i> Create Task Sequence
                            </h3>
                            
                            <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
                                <div class="form-group" style="margin-bottom:0;">
                                    <label class="form-label" for="layout-project">Select Project</label>
                                    <select class="form-control" id="layout-project">
                                        <option value="">-- Choose Project --</option>
                                        <?php foreach ($dropdownProjects as $p): ?>
                                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label" for="layout-start-date">Start Date</label>
                                        <input type="date" class="form-control" id="layout-start-date">
                                    </div>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label class="form-label" for="layout-target-date">Target Date</label>
                                        <input type="date" class="form-control" id="layout-target-date">
                                    </div>
                                </div>
                                <div style="display: flex; gap: 12px; align-items: flex-end;">
                                    <div class="form-group" style="margin-bottom: 0; flex:1;">
                                        <label class="form-label" for="layout-num-tasks">Number of Tasks</label>
                                        <input type="number" class="form-control" id="layout-num-tasks" min="1" max="50" value="5">
                                    </div>
                                    <button class="btn btn-secondary" id="btn-generate-sequence" style="height: 38px; white-space:nowrap;">Generate Inputs</button>
                                </div>
                            </div>

                            <div id="layout-sequence-container" style="display: none;">
                                <h4 style="margin-bottom: 12px; font-size: 13px; color: #475569; font-weight:600;">Task Sequence Definition</h4>
                                <div id="layout-tasks-wrapper" style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;">
                                    <!-- Dynamic rows go here -->
                                </div>
                                <div style="text-align: right; border-top: 1px solid #e2e8f0; padding-top: 12px;">
                                    <button class="btn btn-primary" id="btn-save-layout" style="padding: 8px 24px;">
                                        <i data-lucide="zap" style="width:14px;height:14px;vertical-align:middle;margin-right:4px;"></i>Save &amp; Generate Timeline
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Load Existing Timeline -->
                        <div class="section-card" style="padding: 24px;">
                            <h3 style="margin-bottom: 20px; font-size: 15px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">
                                <i data-lucide="bar-chart-horizontal" style="width:15px;height:15px;vertical-align:middle;margin-right:5px;color:#10b981;"></i> View Project Timeline
                            </h3>
                            <div class="form-group" style="margin-bottom: 14px;">
                                <label class="form-label" for="timeline-load-project">Select Project to View</label>
                                <select class="form-control" id="timeline-load-project">
                                    <option value="">-- Choose Project --</option>
                                    <?php foreach ($dropdownProjects as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button class="btn btn-secondary" id="btn-load-timeline" style="display: none; width:100%; background: linear-gradient(135deg,#1e3a5f,#2563eb); color:#fff; border:none;">
                                <i data-lucide="calendar-days" style="width:14px;height:14px;vertical-align:middle;margin-right:5px;"></i>Load Timeline
                            </button>
                            <p id="timeline-load-hint" style="margin-top:10px; font-size:11px; color:#94a3b8; text-align:center;">Select a project to view its Gantt chart.</p>
                        </div>
                    </div>

                    <!-- Timeline Display Area -->
                    <div id="layout-timeline-area" style="display:none;">
                        <div class="section-card" style="padding: 24px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                                <div>
                                    <h3 id="tl-project-title" style="margin:0; font-size:16px; font-weight:700; color:#0f172a;"></h3>
                                    <p id="tl-project-meta" style="margin:4px 0 0; font-size:12px; color:#64748b;"></p>
                                </div>
                                <div style="display:flex; gap:8px; align-items:center;">
                                    <span style="font-size:11px; color:#475569; font-weight:500;">GANTT TIMELINE</span>
                                    <div style="display:flex; gap:6px;">
                                        <span style="background:#dbeafe;color:#1d4ed8;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600;">Todo</span>
                                        <span style="background:#fef9c3;color:#a16207;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600;">In Progress</span>
                                        <span style="background:#dcfce7;color:#15803d;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600;">Completed</span>
                                    </div>
                                </div>
                            </div>
                            <div id="layout-gantt-chart" style="overflow-x:auto; min-height:120px;"></div>
                        </div>
                    </div>
                </div>

                <!-- ==========================================================================
                     TAB 2: TASKS VIEW
                     ========================================================================== -->
                <div id="view-tasks" class="tab-view">
                    <!-- Header Actions Panel -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0;">Task</h2>
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <!-- Standardized search box -->
                            <div class="standard-search-wrapper">
                                <i data-lucide="search"></i>
                                <input type="text" id="tasks-search" class="search-input" placeholder="Search tasks...">
                            </div>
                            <!-- Priority Filter -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="tasks-priority-filter-toggle" style="height: 32px; padding: 4px 10px; font-size: 11px;"><i data-lucide="filter"></i> Priority <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="tasks-priority-filter-dropdown" style="right: 0; min-width: 140px; display: none; position: absolute; z-index: 100;">
                                    <div class="filter-dropdown-content">
                                        <a href="#" class="filter-priority-item" data-priority="All">All Priorities</a>
                                        <a href="#" class="filter-priority-item" data-priority="Low">Low</a>
                                        <a href="#" class="filter-priority-item" data-priority="Medium">Medium</a>
                                        <a href="#" class="filter-priority-item" data-priority="High">High</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Status Filter -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="tasks-status-filter-toggle" style="height: 32px; padding: 4px 10px; font-size: 11px;">Status <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="tasks-status-filter-dropdown" style="right: 0; min-width: 140px; display: none; position: absolute; z-index: 100;">
                                    <div class="filter-dropdown-content">
                                        <a href="#" class="filter-status-item" data-status="All">All Statuses</a>
                                        <a href="#" class="filter-status-item" data-status="Pending">Pending</a>
                                        <a href="#" class="filter-status-item" data-status="Todo">Todo</a>
                                        <a href="#" class="filter-status-item" data-status="In Progress">In Progress</a>
                                        <a href="#" class="filter-status-item" data-status="In Review">In Review</a>
                                        <a href="#" class="filter-status-item" data-status="Completed">Completed</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Sort -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="tasks-sort-toggle" style="height: 32px; padding: 4px 10px; font-size: 11px;"><i data-lucide="arrow-up-down"></i> Sort <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="tasks-sort-dropdown" style="right: 0; min-width: 140px; display: none; position: absolute; z-index: 100;">
                                    <div class="filter-dropdown-content">
                                        <a href="#" class="sort-tasks-item" data-sort="default">Default</a>
                                        <a href="#" class="sort-tasks-item" data-sort="due-asc">Due Date (Asc)</a>
                                        <a href="#" class="sort-tasks-item" data-sort="due-desc">Due Date (Desc)</a>
                                        <a href="#" class="sort-tasks-item" data-sort="priority-high">Priority: High to Low</a>
                                        <a href="#" class="sort-tasks-item" data-sort="priority-low">Priority: Low to High</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Date Type -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="tasks-date-filter-toggle" style="height: 32px; padding: 4px 10px; font-size: 11px;"><i data-lucide="calendar"></i> Date Type <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="tasks-date-filter-dropdown" style="right: 0; min-width: 140px; display: none; position: absolute; z-index: 100;">
                                    <div class="filter-dropdown-content">
                                        <a href="#" class="filter-tasks-date-item" data-date-range="All">All Time</a>
                                        <a href="#" class="filter-tasks-date-item" data-date-range="Today">Today</a>
                                        <a href="#" class="filter-tasks-date-item" data-date-range="Yesterday">Yesterday</a>
                                        <a href="#" class="filter-tasks-date-item" data-date-range="ThisWeek">This Week</a>
                                        <a href="#" class="filter-tasks-date-item" data-date-range="ThisMonth">This Month</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Add Task Button -->
                            <button class="btn btn-primary" id="btn-tasks-add-task" style="height: 32px; padding: 4px 14px; font-size: 11px; background-color: #2563eb;"><i data-lucide="plus"></i> Add Task</button>
                        </div>
                    </div>

                    <!-- Sub Navigation Tabs -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; margin-bottom: 15px; padding-bottom: 8px;">
                        <div style="display: flex; gap: 20px;" id="tasks-view-sub-tabs">
                            <span class="task-sub-tab active" data-sub-view="list" style="font-size: 13px; font-weight: 600; color: #2563eb; border-bottom: 2px solid #2563eb; padding-bottom: 8px; cursor: pointer; display: flex; align-items: center; gap: 6px;"><i data-lucide="align-left" style="width: 14px; height: 14px;"></i> List</span>
                            <span class="task-sub-tab" data-sub-view="kanban" style="font-size: 13px; font-weight: 600; color: #64748b; padding-bottom: 8px; cursor: pointer; display: flex; align-items: center; gap: 6px;"><i data-lucide="columns" style="width: 14px; height: 14px;"></i> Kanban</span>
                            <span class="task-sub-tab" data-sub-view="calendar" style="font-size: 13px; font-weight: 600; color: #64748b; padding-bottom: 8px; cursor: pointer; display: flex; align-items: center; gap: 6px;"><i data-lucide="calendar-days" style="width: 14px; height: 14px;"></i> Calendar</span>
                        </div>
                        <div style="display: flex; gap: 16px; font-size: 11px; font-weight: 600; color: #64748b;">
                            <span style="cursor: pointer;">Draft Tasks</span>
                            <span style="cursor: pointer; display: flex; align-items: center; gap: 4px;"><i data-lucide="layout-grid" style="width: 12px; height: 12px;"></i> Customize</span>
                            <span style="cursor: pointer;"><i data-lucide="more-horizontal" style="width: 14px; height: 14px;"></i></span>
                        </div>
                    </div>

                    <!-- Task List View Container -->
                    <div id="tasks-list-view" class="task-sub-view-container active">
                        <div class="section-card" style="padding: 0; overflow: hidden; border-radius: 8px; border: 1px solid #e2e8f0; background: #ffffff;">
                        <!-- Table header block -->
                        <div style="display: flex; align-items: center; background-color: #f8fafc; border-bottom: 1px solid #cbd5e1; font-size: 11px; font-weight: 700; color: #475569; padding: 12px 16px; text-transform: uppercase; letter-spacing: 0.5px;">
                            <div style="flex: 2; text-align: left;">Task Name</div>
                            <div style="flex: 1; text-align: left;">Created Date</div>
                            <div style="flex: 1; text-align: left;">Due Date</div>
                            <div style="flex: 1; text-align: left;">Priority</div>
                            <div style="flex: 1; text-align: left;">Status</div>
                        </div>

                        <?php
                        $todayTasks = [];
                        $overdueTasks = [];
                        $otherTasks = [];
                        $currentDate = $today ?? date('Y-m-d');
                        foreach ($tasksList as $tk) {
                            // Extract just the date part from created_at
                            $createdDate = !empty($tk['created_at']) ? substr($tk['created_at'], 0, 10) : '';
                            $isCreatedToday = ($createdDate === $currentDate);
                            $isDueToday     = (!empty($tk['due_date']) && $tk['due_date'] === $currentDate);
                            $isOverdue      = (!empty($tk['due_date']) && $tk['due_date'] < $currentDate);

                            // Priority: created today OR due today → Today
                            // Overdue (due_date < today, not created today) → Overdue
                            // Everything else → Other/Later
                            if ($isCreatedToday || $isDueToday) {
                                $todayTasks[] = $tk;
                            } else if ($isOverdue) {
                                $overdueTasks[] = $tk;
                            } else {
                                $otherTasks[] = $tk;
                            }
                        }
                        
                        $groups = [
                            ['title' => 'Today', 'count' => count($todayTasks), 'tasks' => $todayTasks, 'id' => 'today'],
                            ['title' => 'Overdue', 'count' => count($overdueTasks), 'tasks' => $overdueTasks, 'id' => 'overdue'],
                            ['title' => 'Other / Later', 'count' => count($otherTasks), 'tasks' => $otherTasks, 'id' => 'other']
                        ];
                        
                        foreach ($groups as $grp):
                        ?>
                            <!-- Accordion Group -->
                            <div class="task-group-container">
                                <div class="task-group-header" data-group-id="<?= $grp['id'] ?>">
                                    <i data-lucide="chevron-down" class="accordion-caret" style="width: 14px; height: 14px; color: #64748b; transition: transform 0.2s;"></i>
                                    <h4><?= $grp['title'] ?></h4>
                                    <span class="task-group-count"><?= $grp['count'] ?></span>
                                </div>
                                <div class="task-group-body" id="group-body-<?= $grp['id'] ?>">
                                    <?php if (empty($grp['tasks'])): ?>
                                        <div style="padding: 16px; font-size: 12.5px; color: #94a3b8; text-align: center; background-color: #ffffff; border-bottom: 1px solid #f1f5f9;">No tasks in this category.</div>
                                    <?php else: ?>
                                        <?php foreach ($grp['tasks'] as $t): 
                                            $badge = 'pending';
                                            if ($t['status'] === 'Completed') $badge = 'completed';
                                            if ($t['status'] === 'In Review') $badge = 'review';
                                            if ($t['status'] === 'In Progress') $badge = 'process';

                                            $pri = 'pri-medium';
                                            if ($t['priority'] === 'High') $pri = 'pri-high';
                                            if ($t['priority'] === 'Low') $pri = 'pri-low';
                                        ?>
                                            <div class="task-row" data-task-id="<?= $t['id'] ?>" data-status="<?= htmlspecialchars($t['status']) ?>" data-priority="<?= htmlspecialchars($t['priority']) ?>" data-due-date="<?= $t['due_date'] ?: '' ?>" style="display: flex; align-items: center; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; background-color: #ffffff; font-size: 13px; color: #334155;">
                                                <div style="flex: 2; display: flex; flex-direction: column; text-align: left;">
                                                    <span style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($t['title']) ?></span>
                                                    <small class="text-muted" style="font-size: 11px; margin-top: 2px;"><?= htmlspecialchars($t['description']) ?></small>
                                                </div>
                                                <div style="flex: 1; text-align: left; font-size: 12.5px;"><?= date('d M Y', strtotime($t['created_at'])) ?></div>
                                                <div style="flex: 1; text-align: left; font-size: 12.5px;"><?= $t['due_date'] ? date('d M Y', strtotime($t['due_date'])) : 'N/A' ?></div>
                                                <div style="flex: 1; text-align: left;"><span class="priority-tag <?= $pri ?>"><?= $t['priority'] ?></span></div>
                                                <div style="flex: 1; text-align: left; display: flex; align-items: center; justify-content: space-between;">
                                                    <span class="status-badge <?= $badge ?>"><?= $t['status'] ?></span>
                                                    <button class="btn-icon btn-edit-task" data-id="<?= $t['id'] ?>" data-title="<?= htmlspecialchars($t['title'], ENT_QUOTES) ?>" data-due="<?= $t['due_date'] ?>" data-days="<?= $t['estimated_duration'] ?>" style="background:none; border:none; cursor:pointer; padding:4px;" title="Edit Task"><i data-lucide="edit-2" style="width:12px; height:12px; color:#64748b;"></i></button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Task Kanban View Container -->
                    <div id="tasks-kanban-view" class="task-sub-view-container" style="display: none;">
                        <div class="kanban-board-wrapper" style="display: flex; gap: 16px; overflow-x: auto; padding-bottom: 15px;">
                            <!-- Dynamically rendered by JS -->
                        </div>
                    </div>

                    <!-- Task Calendar View Container -->
                    <div id="tasks-calendar-view" class="task-sub-view-container" style="display: none;">
                        <div class="calendar-view-wrapper" style="padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <!-- Dynamically rendered by JS -->
                        </div>
                    </div>
                </div>

                <!-- ==========================================================================
                     TAB 3: PROJECTS VIEW
                     ========================================================================== -->
                <div id="view-projects" class="tab-view">
                    <!-- Header Actions Panel -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0;">Project</h2>
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <!-- Standardized search box -->
                            <div class="standard-search-wrapper">
                                <i data-lucide="search"></i>
                                <input type="text" id="projects-search" class="search-input" placeholder="Search projects...">
                            </div>
                            <!-- Priority Filter -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="projects-priority-filter-toggle" style="height: 32px; padding: 4px 10px; font-size: 11px;"><i data-lucide="filter"></i> Priority <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="projects-priority-filter-dropdown" style="right: 0; min-width: 140px; display: none; position: absolute; z-index: 100;">
                                    <div class="filter-dropdown-content">
                                        <a href="#" class="filter-project-priority-item" data-priority="All">All Priorities</a>
                                        <a href="#" class="filter-project-priority-item" data-priority="Low">Low</a>
                                        <a href="#" class="filter-project-priority-item" data-priority="Medium">Medium</a>
                                        <a href="#" class="filter-project-priority-item" data-priority="High">High</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Status Filter -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="projects-status-filter-toggle" style="height: 32px; padding: 4px 10px; font-size: 11px;">Status <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="projects-status-filter-dropdown" style="right: 0; min-width: 140px; display: none; position: absolute; z-index: 100;">
                                    <div class="filter-dropdown-content">
                                        <a href="#" class="filter-project-status-item" data-status="All">All Statuses</a>
                                        <a href="#" class="filter-project-status-item" data-status="Active">Active</a>
                                        <a href="#" class="filter-project-status-item" data-status="Completed">Completed</a>
                                        <a href="#" class="filter-project-status-item" data-status="Pending">Pending</a>
                                        <a href="#" class="filter-project-status-item" data-status="Rejected">Rejected</a>
                                        <a href="#" class="filter-project-status-item" data-status="On Hold">On Hold</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Sort -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="projects-sort-toggle" style="height: 32px; padding: 4px 10px; font-size: 11px;"><i data-lucide="arrow-up-down"></i> Sort <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="projects-sort-dropdown" style="right: 0; min-width: 160px; display: none; position: absolute; z-index: 100;">
                                    <div class="filter-dropdown-content">
                                        <a href="#" class="sort-projects-item" data-sort="default">Default</a>
                                        <a href="#" class="sort-projects-item" data-sort="name-asc">Project Name (A-Z)</a>
                                        <a href="#" class="sort-projects-item" data-sort="name-desc">Project Name (Z-A)</a>
                                        <a href="#" class="sort-projects-item" data-sort="created-newest">Newest Created</a>
                                        <a href="#" class="sort-projects-item" data-sort="created-oldest">Oldest Created</a>
                                        <a href="#" class="sort-projects-item" data-sort="completion-rate">Completion Rate</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Date Type -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="projects-date-filter-toggle" style="height: 32px; padding: 4px 10px; font-size: 11px;"><i data-lucide="calendar"></i> Date Type <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="projects-date-filter-dropdown" style="right: 0; min-width: 140px; display: none; position: absolute; z-index: 100;">
                                    <div class="filter-dropdown-content">
                                        <a href="#" class="filter-projects-date-item" data-date-range="All">All Time</a>
                                        <a href="#" class="filter-projects-date-item" data-date-range="Today">Today</a>
                                        <a href="#" class="filter-projects-date-item" data-date-range="Yesterday">Yesterday</a>
                                        <a href="#" class="filter-projects-date-item" data-date-range="ThisWeek">This Week</a>
                                        <a href="#" class="filter-projects-date-item" data-date-range="ThisMonth">This Month</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Create Project Button -->
                            <button class="btn btn-primary" id="btn-projects-add-project" style="height: 32px; padding: 4px 14px; font-size: 11px; background-color: #2563eb;"><i data-lucide="plus"></i> Create Project</button>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; margin-bottom: 15px; padding-bottom: 8px;">
                        <div style="display: flex; gap: 20px;">
                            <span class="proj-tab-btn active" style="font-size: 13px; font-weight: 600; color: #2563eb; border-bottom: 2px solid #2563eb; padding-bottom: 8px; cursor: pointer;">Created By Me</span>
                            <span class="proj-tab-btn" style="font-size: 13px; font-weight: 600; color: #64748b; padding-bottom: 8px; cursor: pointer;">My Team Project</span>
                            <?php if ($isOrgAdmin): ?>
                                <span class="proj-tab-btn" style="font-size: 13px; font-weight: 600; color: #64748b; padding-bottom: 8px; cursor: pointer;">All Projects</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section: Favourites -->
                    <h3 style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 10px;">Favourites</h3>

                    <div class="project-cards-grid" id="projects-grid-container">
                        <?php foreach ($projects as $p): 
                            $pct = $p['total_tasks'] > 0 ? round(($p['completed_tasks'] / $p['total_tasks']) * 100) : 0;
                            $isCompleted = ($p['status'] === 'Completed');
                            
                            $updated_text = "Last updated: 1 day ago";
                            if ($p['id'] % 3 == 0) $updated_text = "Last updated: 3 days ago";
                            else if ($p['id'] % 2 == 0) $updated_text = "Last updated: 2 days ago";
                        ?>                            <div class="project-grid-card" data-id="<?= $p['id'] ?>" data-project-name="<?= htmlspecialchars(strtolower($p['name'])) ?>" data-status="<?= htmlspecialchars($p['status']) ?>" data-priority="<?= htmlspecialchars($p['priority']) ?>" data-created-at="<?= htmlspecialchars($p['created_at']) ?>" data-completion-rate="<?= $pct ?>" data-created-by="<?= htmlspecialchars($p['created_by'] ?? '') ?>" data-assigned-to="<?= htmlspecialchars($p['assigned_to'] ?? '') ?>" data-team-member-ids="<?= htmlspecialchars($p['member_ids'] ?? '') ?>">
                                <div class="project-card-header">
                                    <div class="project-card-status-row" style="display:flex; align-items:center; gap:6px;">
                                        <div class="project-card-check-circle <?= $isCompleted ? 'completed' : '' ?>">
                                            <i data-lucide="<?= $isCompleted ? 'check-circle' : 'circle' ?>" style="width: 18px; height: 18px;"></i>
                                        </div>
                                        <?php if ($isOrgAdmin): ?>
                                            <select class="project-status-select" data-id="<?= $p['id'] ?>" style="font-size: 10.5px; padding: 2px 4px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff; cursor: pointer; color:#475569; font-weight:600; outline:none;">
                                                <option value="Active" <?= $p['status'] === 'Active' ? 'selected' : '' ?>>Active (Approved)</option>
                                                <option value="Pending" <?= $p['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="Completed" <?= $p['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="Rejected" <?= $p['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected</option>
                                                <option value="On Hold" <?= $p['status'] === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                                            </select>
                                            <button class="btn-delete-project" data-id="<?= $p['id'] ?>" style="background:none; border:none; color:#ef4444; cursor:pointer; padding: 2px; margin-left: 2px; display:inline-flex; align-items:center; justify-content:center;" title="Delete Project">
                                                <i data-lucide="trash-2" style="width:13px; height:13px;"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="status-badge <?= $isCompleted ? 'completed' : 'process' ?>" style="font-size: 10.5px; padding: 2px 8px;"><?= htmlspecialchars($p['status']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="project-card-updated"><?= $updated_text ?></span>
                                </div>

                                <div class="project-card-title"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="project-card-description"><?= htmlspecialchars($p['description'] ?: 'No description provided.') ?></div>

                                <div class="project-card-progress-section">
                                    <div class="project-card-progress-label">
                                        <span>Task Completed</span>
                                        <span><?= $p['completed_tasks'] ?>/<?= $p['total_tasks'] ?> (<?= $pct ?>%)</span>
                                    </div>
                                    <div class="project-card-progress-bar-bg">
                                        <div class="project-card-progress-bar-fill" style="width: <?= $pct ?>%;"></div>
                                    </div>
                                </div>

                                <div class="project-card-footer">
                                    <span style="font-size: 11px; font-weight: 600; color: #64748b;">Client: <strong style="color: #0f172a;"><?= htmlspecialchars($p['client_name'] ?: 'None') ?></strong></span>
                                    <div class="assignee-avatar-stack">
                                        <?php
                                        $pMembers = $projMemberDetails[$p['id']] ?? [];
                                        if (!empty($pMembers)) {
                                            foreach ($pMembers as $pm) {
                                                $avatarVal = $pm['avatar'];
                                                if ($avatarVal && (strpos($avatarVal, '.') !== false || strpos($avatarVal, 'uploads/') !== false)) {
                                                    echo '<img src="' . htmlspecialchars($avatarVal) . '" class="assignee-avatar-circle" title="' . htmlspecialchars($pm['name']) . '" style="object-fit: cover; width: 28px; height: 28px; border-radius: 50%; border: 2px solid #ffffff; margin-left: -6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
                                                } else {
                                                    $initials = strtoupper(substr(htmlspecialchars($avatarVal ?: $pm['name']), 0, 2));
                                                    echo '<div class="assignee-avatar-circle aac-' . strtolower($initials) . '" title="' . htmlspecialchars($pm['name']) . '">' . $initials . '</div>';
                                                }
                                            }
                                        } else {
                                            try {
                                                $stmtAss = $pdo->prepare("SELECT DISTINCT e.avatar, e.name FROM `tasks` t JOIN `employees` e ON t.assigned_to = e.id WHERE t.project_id = ? AND e.avatar IS NOT NULL AND e.avatar != ''");
                                                $stmtAss->execute([$p['id']]);
                                                $fallbackAss = $stmtAss->fetchAll(PDO::FETCH_ASSOC);
                                                if (empty($fallbackAss)) {
                                                    echo '<div class="assignee-avatar-circle aac-sj" title="SELVAKUMAR J">SJ</div>';
                                                } else {
                                                    foreach ($fallbackAss as $ass) {
                                                        $avatarVal = $ass['avatar'];
                                                        if ($avatarVal && (strpos($avatarVal, '.') !== false || strpos($avatarVal, 'uploads/') !== false)) {
                                                            echo '<img src="' . htmlspecialchars($avatarVal) . '" class="assignee-avatar-circle" title="' . htmlspecialchars($ass['name']) . '" style="object-fit: cover; width: 28px; height: 28px; border-radius: 50%; border: 2px solid #ffffff; margin-left: -6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">';
                                                        } else {
                                                            $ini = strtoupper(substr(htmlspecialchars($avatarVal ?: $ass['name']), 0, 2));
                                                            echo '<div class="assignee-avatar-circle aac-' . strtolower($ini) . '" title="' . htmlspecialchars($ass['name']) . '">' . $ini . '</div>';
                                                        }
                                                    }
                                                }
                                            } catch (PDOException $ex) {
                                                echo '<div class="assignee-avatar-circle aac-sj" title="SELVAKUMAR J">SJ</div>';
                                            }
                                        }
                                        ?>

                                    </div>
                                </div>
                                <?php if ($isCompleted): ?>
                                <div style="margin-top:10px; padding-top:10px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end;">
                                    <button onclick="downloadProjectPDF(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>')"
                                            style="display:flex; align-items:center; gap:6px; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; border:none; border-radius:7px; padding:6px 12px; font-size:11px; font-weight:600; cursor:pointer; font-family:inherit;">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        Download PDF
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>


                <!-- ==========================================================================
                     TABS 4-10: PLACEHOLDER VIEWS
                     ========================================================================== -->
                <div id="view-discussion" class="tab-view">
                    <div class="pane-split-layout">
                        <!-- Left Sidebar Pane -->
                        <div class="left-sidebar-pane">
                            <div class="discussion-sidebar-header">
                                <h3>Discussion</h3>
                                <div style="display: flex; gap: 8px;">
                                    <button class="header-btn" id="btn-new-discussion" title="New Discussion" style="width: 28px; height: 28px; border: 1px solid var(--sidebar-border); border-radius: 50%; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="plus" style="width: 14px; height: 14px;"></i></button>
                                    <button class="header-btn" id="btn-new-direct-message" title="Start Direct Message" style="width: 28px; height: 28px; border: 1px solid var(--sidebar-border); border-radius: 50%; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="message-square" style="width: 14px; height: 14px;"></i></button>
                                </div>
                            </div>

                            <div class="discussion-sidebar-search">
                                <div class="standard-search-wrapper" style="width: 100%;">
                                    <i data-lucide="search"></i>
                                    <input type="text" id="chat-search" class="search-input" placeholder="Search chats..." style="background: #f8fafc;">
                                </div>
                            </div>

                            <div class="chat-group-buttons">
                                <button class="chat-group-btn active" data-chat-group="General">General</button>
                                <button class="chat-group-btn" data-chat-group="Task">Task</button>
                                    <button class="chat-group-btn" data-chat-group="Direct">Direct</button>
                            </div>

                            <div class="chat-threads-title">CHATS</div>
                            <div class="chat-threads-list">
                                <?php if (empty($discussionsList)): ?>
                                    <div style="text-align: center; color: var(--text-muted); padding: 20px; font-size: 13px;" id="chat-threads-empty-placeholder">No discussions found. Click "+" to start one.</div>
                                <?php else: ?>
                                <?php foreach ($discussionsList as $index => $d): 
                                    $initials = substr($d['title'], 0, 2);
                                    $isActive = false;
                                    
                                    // Color mapping
                                    $discColor = 'da-ha';
                                    if (strpos($d['title'], 'KILUATTI') !== false) $discColor = 'da-ki';
                                    if (strpos($d['title'], 'KIZHMATTAI') !== false) $discColor = 'da-er';
                                    if (strpos($d['title'], 'THELLAR') !== false) $discColor = 'da-tb';
                                    if (strpos($d['title'], 'AGARAKO') !== false) $discColor = 'da-ag';
                                    if (strpos($d['title'], 'PULAVAN') !== false) $discColor = 'da-pu';
                                    if (strpos($d['title'], 'KASTAM') !== false) $discColor = 'da-ka';
                                    
                                    $preview = "No attachments";
                                    if ($d['attachment_name']) {
                                        $preview = "Attachment: " . $d['attachment_name'];
                                    }
                                    
                                    // Custom avatar rendering
                                    $avatarVal = $initials;
                                    $avatarHtml = '<div class="chat-thread-avatar ' . $discColor . '">' . htmlspecialchars($initials) . '</div>';
                                    if ($d['type'] === 'Direct' && !empty($d['direct_avatar'])) {
                                        $avatarVal = $d['direct_avatar'];
                                        if (strpos($avatarVal, '.') !== false || strpos($avatarVal, 'uploads/') !== false) {
                                            $avatarHtml = '<img src="' . htmlspecialchars($avatarVal) . '" class="chat-thread-avatar" style="object-fit: cover; border-radius: 50%; width: 36px; height: 36px;">';
                                        }
                                    }
                                ?>
                                    <div class="chat-thread-item <?= $isActive ? 'active' : '' ?>" data-chat-id="<?= $d['id'] ?>" data-chat-type="<?= htmlspecialchars($d['type']) ?>" data-chat-title="<?= htmlspecialchars($d['title']) ?>" data-chat-avatar="<?= htmlspecialchars($avatarVal) ?>" data-chat-color="<?= $discColor ?>" data-chat-members="<?= (int)($d['member_count'] ?? 1) ?>">
                                        <?= $avatarHtml ?>
                                        <div class="chat-thread-info">
                                            <div class="chat-thread-name-row" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                                <span class="chat-thread-name" style="flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($d['title']) ?></span>
                                                <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
                                                    <span class="chat-thread-time"><?= htmlspecialchars($d['date_logged']) ?></span>
                                                    <button class="thread-delete-btn" title="Delete Chat" style="background:transparent; border:none; color:#94a3b8; cursor:pointer; font-size:16px; padding:2px 4px; display:inline-flex; align-items:center; justify-content:center; line-height:1; transition:color 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">&times;</button>
                                                </div>
                                            </div>
                                            <span class="chat-thread-preview"><?= htmlspecialchars($preview) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Right Chat Window Content Pane -->
                        <div class="right-content-pane">
                            <!-- Chat window header -->
                            <div class="chat-window-header" style="display: none;">
                                <div class="chat-window-info">
                                    <div class="chat-thread-avatar da-ki" id="active-chat-avatar">KI</div>
                                    <div class="chat-window-details">
                                        <span class="chat-window-title" id="active-chat-title">KILUATTI SERVICES</span>
                                        <span class="chat-window-members">5 Members active in channel</span>
                                    </div>
                                </div>
                                <div class="chat-window-actions" style="display: flex; align-items: center; gap: 14px;">
                                    <button id="chat-header-members" title="Manage Members" style="background:transparent; border:none; cursor:pointer; color:var(--text-muted); display:flex; align-items:center; justify-content:center; padding:2px;"><i data-lucide="users" style="width: 16px; height: 16px;"></i></button>
                                    <button id="chat-delete-btn" title="Delete Discussion" style="background:transparent; border:none; cursor:pointer; color:#ef4444; display:flex; align-items:center; justify-content:center; padding:2px;"><i data-lucide="trash-2" style="width: 16px; height: 16px;"></i></button>
                                    <button id="chat-close-btn" title="Close Chat" style="background:transparent; border:none; cursor:pointer; color:#64748b; display:flex; align-items:center; justify-content:center; padding:2px;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'"><i data-lucide="x" style="width: 18px; height: 18px;"></i></button>
                                </div>
                            </div>

                            <!-- Chat window messages -->
                            <div class="chat-window-messages chat-messages-container" style="display: flex; flex-direction: column;">
                                <div style="text-align: center; color: var(--text-muted); padding: 40px; font-size: 13px;" id="chat-blank-placeholder">Select a discussion thread to start chatting.</div>
                            </div>

                            <!-- Chat window footer input -->
                            <div class="chat-window-footer" style="display: none;">
                                <form id="form-chat-send" style="width: 100%; display: flex; margin: 0; padding: 0;" enctype="multipart/form-data">
                                    <div class="chat-input-wrapper" style="width: 100%; display: flex; align-items: center; gap: 8px;">
                                        <input type="file" id="chat-file-input" name="attachment" style="display: none;">
                                        <button type="button" class="chat-input-btn" id="btn-chat-attach" title="Add Attachment" style="background: transparent; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-muted);"><i data-lucide="paperclip" style="width: 18px; height: 18px;"></i></button>
                                        <input type="text" id="chat-text-input" name="message" class="chat-text-input" placeholder="Type a message here..." style="flex: 1;" required>
                                        <button type="button" class="chat-input-btn" title="Emoji" style="background: transparent; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--text-muted);"><i data-lucide="smile" style="width: 18px; height: 18px;"></i></button>
                                        <button type="submit" class="chat-input-btn chat-send-btn" title="Send" style="border: none; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i data-lucide="send" style="width: 14px; height: 14px;"></i></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="view-documents" class="tab-view">
                    <div class="pane-split-layout">
                        <!-- Left Sidebar Pane -->
                        <div class="left-sidebar-pane">
                            <div class="documents-sidebar-header" style="margin-bottom: 20px;">
                                <h3 style="font-size: 16px; font-weight: 700; color: #0f172a;">Files</h3>
                            </div>
                            <div class="documents-sidebar-menu">
                                <a href="#" class="documents-menu-item active" data-doc-cat="all"><i data-lucide="folder"></i> All Files</a>
                                <a href="#" class="documents-menu-item" data-doc-cat="drawing"><i data-lucide="file"></i> CAD Drawings</a>
                                <a href="#" class="documents-menu-item" data-doc-cat="pdf"><i data-lucide="file-text"></i> PDF Documents</a>
                                <a href="#" class="documents-menu-item" data-doc-cat="image"><i data-lucide="image"></i> Images</a>
                                <a href="#" class="documents-menu-item" data-doc-cat="other"><i data-lucide="paperclip"></i> Other Files</a>
                            </div>

                            <!-- Storage card widget -->
                            <div class="storage-card-widget">
                                <span class="storage-card-title">My Storage</span>
                                <div class="storage-progress-bar-bg">
                                    <div class="storage-progress-bar-fill" style="width: <?= $storageProgressPct ?>%;"></div>
                                </div>
                                <span class="storage-card-text"><?= $totalStorageMB ?>MB of 5GB</span>
                            </div>
                        </div>

                        <!-- Right Content Pane -->
                        <div class="right-content-pane">
                            <!-- Header actions -->
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px;">
                                <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0;">Documents</h3>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="standard-search-wrapper">
                                        <i data-lucide="search"></i>
                                        <input type="text" id="docs-search" class="search-input" placeholder="Search documents...">
                                    </div>
                                    <!-- Add New Document Button -->
                                    <button class="btn btn-primary" id="btn-docs-add" style="height: 32px; padding: 4px 14px; font-size: 11px; background-color: #2563eb;"><i data-lucide="plus"></i> Add New</button>
                                </div>
                            </div>

                            <div style="flex: 1; overflow-y: auto;">
                                <!-- Folders Grid Section -->
                                <h4 class="folders-section-title">Folders Directory</h4>
                                <div class="folders-grid">
                                    <?php 
                                    $folderCount = 0;
                                    foreach ($projects as $p): 
                                        if (++$folderCount > 5) break; 
                                    ?>
                                        <div class="folder-card">
                                            <div class="folder-icon"><i data-lucide="folder" style="width: 24px; height: 24px; fill: #fbbf24;"></i></div>
                                            <div class="folder-info">
                                                <span class="folder-name" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></span>
                                                <span class="folder-count"><?= $p['total_documents'] ?> items</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Files Table Section -->
                                <h4 class="folders-section-title">Files Directory</h4>
                                <div class="table-responsive" style="margin-bottom:0;">
                                    <table class="table-custom">
                                        <thead>
                                            <tr>
                                                <th style="text-align: left;">File Name</th>
                                                <th style="text-align: left;">Owner</th>
                                                <th style="text-align: left;">Date</th>
                                                <th style="text-align: left;">Size</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            try {
                                                $stmtDocs = $pdo->prepare("
                                                    SELECT d.*, e.name as owner_name, p.name as project_name 
                                                    FROM `documents` d
                                                    LEFT JOIN `employees` e ON d.owner_id = e.id
                                                    LEFT JOIN `projects` p ON d.project_id = p.id
                                                    WHERE d.org_id = ?
                                                    ORDER BY d.id DESC
                                                ");
                                                $stmtDocs->execute([$meOrgId]);
                                                $realDocs = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);
                                            } catch (PDOException $e) {
                                                $realDocs = [];
                                            }
                                            ?>
                                            <tr id="doc-empty-row" style="display: <?= empty($realDocs) ? '' : 'none' ?>;">
                                                <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">No documents found.</td>
                                            </tr>
                                            <?php if (!empty($realDocs)): ?>
                                                <?php foreach ($realDocs as $doc): 
                                                    $ext = pathinfo($doc['name'], PATHINFO_EXTENSION);
                                                    $icon = 'file-text';
                                                    $color = '#ef4444';
                                                    $docCat = 'other';
                                                    if (in_array(strtolower($ext), ['dwg', 'dxf'])) {
                                                        $icon = 'file';
                                                        $color = '#3b82f6';
                                                        $docCat = 'drawing';
                                                    } else if (strtolower($ext) === 'pdf') {
                                                        $docCat = 'pdf';
                                                    } else if (in_array(strtolower($ext), ['png', 'jpg', 'jpeg', 'webp', 'gif'])) {
                                                        $icon = 'image';
                                                        $color = '#10b981';
                                                        $docCat = 'image';
                                                    }
                                                ?>
                                                    <tr class="document-row" data-doc-name="<?= htmlspecialchars(strtolower($doc['name'])) ?>" data-doc-cat="<?= $docCat ?>" data-project-name="<?= htmlspecialchars(strtolower($doc['project_name'] ?? '')) ?>">
                                                        <td style="text-align: left;">
                                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                                <i data-lucide="<?= $icon ?>" style="width: 18px; height: 18px; color: <?= $color ?>;"></i>
                                                                <strong style="color: #0f172a; font-size: 12.5px;"><?= htmlspecialchars($doc['name']) ?></strong>
                                                            </div>
                                                        </td>
                                                        <td style="text-align: left; font-size: 12.5px; font-weight: 500; color: #334155;"><?= htmlspecialchars($doc['owner_name']) ?></td>
                                                        <td style="text-align: left; font-size: 12.5px; color: var(--text-muted);"><?= date('d M Y', strtotime($doc['created_at'])) ?></td>
                                                        <td style="text-align: left; font-size: 12.5px; color: var(--text-muted);"><?= htmlspecialchars($doc['size']) ?></td>
                                                        <td>
                                                            <div style="display: flex; justify-content: center; gap: 8px;">
                                                                <button class="header-btn btn-doc-preview" data-doc-name="<?= htmlspecialchars($doc['name']) ?>" data-doc-path="<?= htmlspecialchars($doc['filepath']) ?>" title="Preview" style="width: 26px; height: 26px; border: 1px solid var(--sidebar-border); border-radius: 50%; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="eye" style="width: 12px; height: 12px;"></i></button>
                                                                <a href="<?= htmlspecialchars($doc['filepath']) ?>" download="<?= htmlspecialchars($doc['name']) ?>" class="header-btn" title="Download" style="width: 26px; height: 26px; border: 1px solid var(--sidebar-border); border-radius: 50%; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="download" style="width: 12px; height: 12px;"></i></a>
                                                                <button class="header-btn btn-doc-delete" data-doc-id="<?= $doc['id'] ?>" title="Delete" style="width: 26px; height: 26px; border: 1px solid var(--sidebar-border); border-radius: 50%; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="trash-2" style="width: 12px; height: 12px;"></i></button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div id="doc-pagination-container" style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border-top:1px solid #f1f5f9; background:#fff; margin-top: 15px; border-radius: 0 0 8px 8px; border: 1px solid #e2e8f0; border-top: none;">
                                    <span id="doc-pagination-info" style="font-size:12px; color:var(--text-muted);">Showing 1-5 of 0 files</span>
                                    <div style="display:flex; gap:8px;">
                                        <button class="btn btn-secondary" id="btn-doc-prev" style="height:28px; padding:2px 8px; font-size:11px;">Prev</button>
                                        <button class="btn btn-secondary" id="btn-doc-next" style="height:28px; padding:2px 8px; font-size:11px;">Next</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="view-notes" class="tab-view">
                    <!-- Header Actions Panel -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0;">Pin Notes</h2>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <!-- Search box -->
                            <div class="standard-search-wrapper">
                                <i data-lucide="search"></i>
                                <input type="text" id="notes-search" class="search-input" placeholder="Search notes...">
                            </div>
                            <!-- Tags Filter -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="notes-tags-filter-toggle" style="height: 32px; padding: 4px 10px; font-size: 11px;">Tags <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="notes-tags-filter-dropdown" style="right: 0; min-width: 140px; display: none; position: absolute; z-index: 100;">
                                    <div class="filter-dropdown-content" id="notes-tags-dropdown-list">
                                        <a href="#" class="filter-tag-item" data-tag="All">All Tags</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Create Note Button -->
                            <button class="btn btn-primary" id="btn-notes-add-note" style="height: 32px; padding: 4px 14px; font-size: 11px; background-color: #2563eb;"><i data-lucide="plus"></i> Add Note</button>
                        </div>
                    </div>

                    <!-- Sub Navigation Tabs -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; margin-bottom: 20px; padding-bottom: 8px;">
                        <div style="display: flex; gap: 20px;" id="notes-view-tabs">
                            <span class="note-tab-btn active" data-note-tab="all" style="font-size: 13px; font-weight: 600; color: #2563eb; border-bottom: 2px solid #2563eb; padding-bottom: 8px; cursor: pointer;">ALL</span>
                            <span class="note-tab-btn" data-note-tab="personal" style="font-size: 13px; font-weight: 600; color: #64748b; padding-bottom: 8px; cursor: pointer;">PERSONAL</span>
                            <span class="note-tab-btn" data-note-tab="work" style="font-size: 13px; font-weight: 600; color: #64748b; padding-bottom: 8px; cursor: pointer;">WORK</span>
                            <span class="note-tab-btn" data-note-tab="archive" style="font-size: 13px; font-weight: 600; color: #64748b; padding-bottom: 8px; cursor: pointer;">ARCHIVE</span>
                        </div>
                    </div>

                    <!-- Notes Cards Grid -->
                    <div class="notes-cards-grid" id="notes-grid-container">
                        <?php if (empty($pinNotes)): ?>
                            <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 40px;">No notes found. Click "Add Note" to create one.</div>
                        <?php else: ?>
                            <?php foreach ($pinNotes as $note): 
                                $date_text = "Updated: 1 day ago";
                                if (isset($note['created_at'])) {
                                    $date_text = date("d M, Y h:i A", strtotime($note['created_at']));
                                }
                            ?>
                                <div class="note-sticky-card" data-note-id="<?= $note['id'] ?>" data-category="<?= htmlspecialchars(strtolower($note['category'])) ?>" data-title="<?= htmlspecialchars(strtolower($note['title'])) ?>" data-content="<?= htmlspecialchars(strtolower($note['content'])) ?>" data-tags="<?= htmlspecialchars(strtolower($note['tags'] ?? '')) ?>">
                                    <div class="note-sticky-header">
                                        <h4 class="note-sticky-title"><?= htmlspecialchars($note['title']) ?></h4>
                                        <button class="note-pin-btn" title="Pin Note"><i data-lucide="pin" style="fill: #78350f; color: #78350f;"></i></button>
                                    </div>
                                    <div class="note-sticky-content"><?= nl2br(htmlspecialchars($note['content'])) ?></div>
                                    <?php if (!empty($note['tags'])): ?>
                                        <div class="note-card-tags-row" style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px;">
                                            <?php 
                                            $tagsArr = explode(',', $note['tags']);
                                            foreach ($tagsArr as $tg): 
                                                $tg = trim($tg);
                                                if ($tg !== ''):
                                            ?>
                                                <span class="note-tag-pill" style="font-size: 9px; font-weight: 600; padding: 2px 6px; border-radius: 4px; background: #e0f2fe; color: #0369a1;"><?= htmlspecialchars($tg) ?></span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="note-sticky-footer">
                                        <span style="font-size: 10.5px; font-weight: 500;"><?= $date_text ?></span>
                                        <div style="margin-left: auto; display: flex; gap: 8px;">
                                            <i data-lucide="edit-3" class="note-action-icon" title="Edit Note"></i>
                                            <i data-lucide="trash-2" class="note-action-icon" title="Delete Note"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="view-attendance" class="tab-view">
                    <!-- Header -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap: wrap; gap: 12px;">
                        <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0;">Attendance</h2>
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap: wrap;">
                            <!-- Standardized search bar -->
                            <div class="standard-search-wrapper">
                                <i data-lucide="search"></i>
                                <input type="text" id="attendance-search" class="search-input" placeholder="Search attendance...">
                            </div>
                            <!-- Employee Filter -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="attendance-emp-filter-toggle" style="height:32px; padding:4px 12px; font-size:11px;"><i data-lucide="users"></i> Employee <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="attendance-emp-filter-dropdown" style="right:0; min-width:160px; display:none; position:absolute; z-index:100;">
                                    <div class="filter-dropdown-content" id="attendance-emp-filter-list">
                                        <a href="#" class="filter-attendance-emp-item" data-emp="All">All Employees</a>
                                        <?php foreach ($dropdownEmployees as $emp): ?>
                                            <a href="#" class="filter-attendance-emp-item" data-emp="<?= htmlspecialchars($emp['name']) ?>"><?= htmlspecialchars($emp['name']) ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <!-- Status Filter -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="attendance-status-filter-toggle" style="height:32px; padding:4px 12px; font-size:11px;">Status <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="attendance-status-filter-dropdown" style="right:0; min-width:140px; display:none; position:absolute; z-index:100;">
                                    <div class="filter-dropdown-content">
                                        <a href="#" class="filter-attendance-status-item" data-status="All">All Statuses</a>
                                        <a href="#" class="filter-attendance-status-item" data-status="Present">Present</a>
                                        <a href="#" class="filter-attendance-status-item" data-status="Absent">Absent</a>
                                        <a href="#" class="filter-attendance-status-item" data-status="Late">Late</a>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $todayDate = date('Y-m-d');
                            $myAtt = null;
                            try {
                                $stmtMyAtt = $pdo->prepare("SELECT * FROM `attendance` WHERE employee_id = ? AND date = ?");
                                $stmtMyAtt->execute([$meId, $todayDate]);
                                $myAtt = $stmtMyAtt->fetch(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {}
                            
                            if (!$myAtt):
                            ?>
                                <button class="btn btn-primary" id="btn-attendance-checkin" style="height:32px; padding:4px 14px; font-size:11px; background:#10b981;"><i data-lucide="log-in"></i> Check In</button>
                            <?php elseif ($myAtt && $myAtt['check_out'] === null): ?>
                                <button class="btn btn-primary" id="btn-attendance-checkout" style="height:32px; padding:4px 14px; font-size:11px; background:#ef4444;"><i data-lucide="log-out"></i> Check Out</button>
                            <?php else: ?>
                                <span style="font-size: 11px; font-weight: 600; color: #10b981; border: 1px solid #10b981; padding: 4px 10px; border-radius: 6px; display: flex; align-items: center; gap: 4px;"><i data-lucide="check-circle" style="width:12px; height:12px;"></i> Done Today</span>
                            <?php endif; ?>
                            <!-- Date Range Filter -->
                            <div class="filter-dropdown-wrapper" style="position: relative; display: inline-block;">
                                <button class="btn btn-secondary" id="attendance-date-filter-toggle" style="height:32px; padding:4px 12px; font-size:11px;"><i data-lucide="calendar"></i> This Month <i data-lucide="chevron-down"></i></button>
                                <div class="filter-dropdown-menu" id="attendance-date-filter-dropdown" style="right:0; min-width:140px; display:none; position:absolute; z-index:100;">
                                    <div class="filter-dropdown-content">
                                        <a href="#" class="filter-attendance-date-item" data-range="All">All Time</a>
                                        <a href="#" class="filter-attendance-date-item" data-range="Today">Today</a>
                                        <a href="#" class="filter-attendance-date-item" data-range="ThisWeek">This Week</a>
                                        <a href="#" class="filter-attendance-date-item" data-range="ThisMonth">This Month</a>
                                    </div>
                                </div>
                            </div>
                            <!-- Export Button -->
                            <button class="btn btn-primary" id="btn-attendance-export" style="height:32px; padding:4px 14px; font-size:11px; background:#2563eb;"><i data-lucide="download"></i> Export</button>
                        </div>
                    </div>

                    <!-- Stat Cards Row -->
                    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:20px;">
                        <div class="stat-card c-projects"><div class="stat-header"><div class="stat-icon-wrapper"><i data-lucide="check-circle"></i></div><span class="stat-title">Present Today</span></div><div class="stat-body"><h3 class="stat-value" id="att-stat-present"><?= $presentToday ?></h3></div></div>
                        <div class="stat-card c-pending"><div class="stat-header"><div class="stat-icon-wrapper"><i data-lucide="x-circle"></i></div><span class="stat-title">Absent Today</span></div><div class="stat-body"><h3 class="stat-value" id="att-stat-absent"><?= $absentToday ?></h3></div></div>
                        <div class="stat-card c-approved"><div class="stat-header"><div class="stat-icon-wrapper"><i data-lucide="clock"></i></div><span class="stat-title">Late Check-Ins</span></div><div class="stat-body"><h3 class="stat-value" id="att-stat-late"><?= $lateToday ?></h3></div></div>
                        <div class="stat-card c-rejected"><div class="stat-header"><div class="stat-icon-wrapper"><i data-lucide="calendar-off"></i></div><span class="stat-title">Leaves This Month</span></div><div class="stat-body"><h3 class="stat-value" id="att-stat-leaves"><?= $leavesThisMonth ?></h3></div></div>
                    </div>

                    <!-- Attendance Table -->
                    <div class="section-card" style="padding:0; overflow:hidden;">
                        <div style="display:flex; align-items:center; background:#f8fafc; border-bottom:1px solid #e5e7eb; font-size:11px; font-weight:700; color:#475569; padding:12px 16px; text-transform:uppercase; letter-spacing:0.5px;">
                            <div style="flex:1.5">Employee</div>
                            <div style="flex:1">Date</div>
                            <div style="flex:1">Check In</div>
                            <div style="flex:1">Check Out</div>
                            <div style="flex:1">Total Hours</div>
                            <div style="flex:1">Status</div>
                        </div>
                        <?php
                        try {
                            $stmtAtt = $pdo->prepare("
                                SELECT a.*, e.name, e.avatar 
                                FROM `attendance` a
                                JOIN `employees` e ON a.employee_id = e.id
                                ORDER BY a.date DESC, a.check_in DESC
                            ");
                            $stmtAtt->execute();
                            $attDbRows = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {
                            $attDbRows = [];
                        }
                        
                        if (empty($attDbRows)):
                        ?>
                            <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 13px;">No attendance logs yet.</div>
                        <?php else: ?>
                            <?php foreach ($attDbRows as $r):
                                $initials = $r['avatar'] ?: substr($r['name'], 0, 2);
                                $inTime = $r['check_in'] ? date('h:i A', strtotime($r['check_in'])) : '-';
                                $outTime = $r['check_out'] ? date('h:i A', strtotime($r['check_out'])) : '-';
                                $hrs = '-';
                                if ($r['check_in'] && $r['check_out']) {
                                    $diff = strtotime($r['check_out']) - strtotime($r['check_in']);
                                    $h = floor($diff / 3600);
                                    $m = floor(($diff % 3600) / 60);
                                    $hrs = "{$h}h {$m}m";
                                }
                                $sc = $r['status'] === 'Present' ? 'completed' : ($r['status'] === 'Late' ? 'review' : 'pending');
                            ?>
                            <div class="attendance-row" data-emp-name="<?= htmlspecialchars($r['name']) ?>" data-date="<?= $r['date'] ?>" data-status="<?= htmlspecialchars($r['status']) ?>" data-check-in="<?= $inTime ?>" data-check-out="<?= $outTime ?>" data-hours="<?= $hrs ?>" style="display:flex; align-items:center; padding:12px 16px; border-bottom:1px solid #f1f5f9; font-size:13px;">
                                <div style="flex:1.5; display:flex; align-items:center; gap:10px;">
                                    <?= render_avatar($r['avatar'], $r['name'], 'background-color: #2563eb; color: #fff; width: 30px; height: 30px; font-weight: 700; font-size: 11px;', 'avatar-initials') ?>
                                    <span style="font-weight:600; color:#0f172a;"><?= htmlspecialchars($r['name']) ?></span>
                                </div>
                                <div style="flex:1; color:#64748b; font-size:12.5px;"><?= date('d M Y', strtotime($r['date'])) ?></div>
                                <div style="flex:1; color:#374151; font-weight:500;"><?= $inTime ?></div>
                                <div style="flex:1; color:#374151; font-weight:500;"><?= $outTime ?></div>
                                <div style="flex:1; color:#374151; font-weight:600;"><?= $hrs ?></div>
                                <div style="flex:1;"><span class="status-badge <?= $sc ?>"><?= htmlspecialchars($r['status']) ?></span></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="view-reports" class="tab-view">
                    <!-- Header -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0;">Reports</h2>
                        <button class="btn btn-primary" onclick="window.print();" style="height:32px; padding:4px 14px; font-size:11px; background:#2563eb;"><i data-lucide="download"></i> Export Report</button>
                    </div>

                    <?php if ($totalTasks === 0 && count($projects) === 0): ?>
                        <div class="section-card" style="text-align: center; color: var(--text-muted); padding: 80px 20px; margin-bottom: 20px; font-size: 14px;">
                            <i data-lucide="bar-chart-2" style="width: 36px; height: 36px; color: var(--text-muted); margin-bottom: 8px; stroke-width: 1.5;"></i>
                            <div>No data available</div>
                        </div>
                    <?php else: ?>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
                            <!-- Task Completion Summary -->
                            <div class="section-card">
                                <div class="card-header" style="border-bottom:none; padding-bottom:0; margin-bottom:0;">
                                    <h3>Task Completion Summary</h3>
                                    <select class="filter-select"><option>All Projects</option><?php foreach($dropdownProjects as $p): ?><option><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?></select>
                                </div>
                                <div style="height:220px; position:relative; margin-top:12px;">
                                    <canvas id="reportBarChart"></canvas>
                                </div>
                            </div>

                            <!-- Priority Distribution -->
                            <div class="section-card">
                                <div class="card-header" style="border-bottom:none; padding-bottom:0; margin-bottom:0;"><h3>Priority Distribution</h3></div>
                                <div style="height:200px; position:relative; display:flex; align-items:center; justify-content:center; margin-top:12px;">
                                    <canvas id="reportPieChart"></canvas>
                                </div>
                                <div style="display:flex; justify-content:center; gap:16px; font-size:11px; font-weight:700; margin-top:10px;">
                                    <span style="display:flex; align-items:center; gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#10b981;display:inline-block;"></span> Low</span>
                                    <span style="display:flex; align-items:center; gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#f59e0b;display:inline-block;"></span> Medium</span>
                                    <span style="display:flex; align-items:center; gap:4px;"><span style="width:10px;height:10px;border-radius:2px;background:#ef4444;display:inline-block;"></span> High</span>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Table -->
                        <div class="section-card" style="padding:0; overflow:hidden;">
                            <div style="padding:14px 16px; border-bottom:1px solid #e5e7eb; font-size:14px; font-weight:700; color:#0f172a;">Team Performance Summary</div>
                            <div style="display:flex; align-items:center; background:#f8fafc; border-bottom:1px solid #e5e7eb; font-size:11px; font-weight:700; color:#475569; padding:10px 16px; text-transform:uppercase;">
                                <div style="flex:2">Employee</div><div style="flex:1">Assigned</div><div style="flex:1">Completed</div><div style="flex:1">Pending</div><div style="flex:1">Rate</div>
                            </div>
                            <?php
                            if (empty($employeesList)):
                            ?>
                                <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 13px;">No employees registered yet.</div>
                            <?php
                            else:
                                foreach ($employeesList as $emp):
                                    $empId = (int)$emp['id'];
                                    $asgn = $pdo->query("SELECT COUNT(*) FROM `tasks` WHERE `assigned_to` = $empId")->fetchColumn() ?: 0;
                                    $comp = $pdo->query("SELECT COUNT(*) FROM `tasks` WHERE `assigned_to` = $empId AND `status` = 'Completed'")->fetchColumn() ?: 0;
                                    $pend = $asgn - $comp;
                                    $rate = $asgn > 0 ? round(($comp / $asgn) * 100) : 0;
                            ?>
                            <div style="display:flex; align-items:center; padding:11px 16px; border-bottom:1px solid #f1f5f9; font-size:13px;">
                                <div style="flex:2; display:flex; align-items:center; gap:8px;">
                                    <?php
                                    $empAvatarVal = $emp['avatar'];
                                    if ($empAvatarVal && (strpos($empAvatarVal, '.') !== false || strpos($empAvatarVal, 'uploads/') !== false)) {
                                        echo '<img src="' . htmlspecialchars($empAvatarVal) . '" alt="' . htmlspecialchars($emp['name']) . '" style="object-fit: cover; width: 28px; height: 28px; border-radius: 50%; border: 1px solid #e2e8f0;">';
                                    } else {
                                        $empInitials = strtoupper(substr(htmlspecialchars($empAvatarVal ?: $emp['name']), 0, 2));
                                        echo '<div style="background:#2563eb;color:#fff;width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;">' . $empInitials . '</div>';
                                    }
                                    ?>
                                    <span style="font-weight:600;color:#0f172a;"><?= htmlspecialchars($emp['name']) ?></span>
                                </div>
                                <div style="flex:1;font-weight:600;"><?= $asgn ?></div>
                                <div style="flex:1;"><span class="status-badge completed"><?= $comp ?></span></div>
                                <div style="flex:1;"><span class="status-badge pending"><?= $pend ?></span></div>
                                <div style="flex:1;">
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <div style="flex:1;height:6px;background:#e5e7eb;border-radius:3px;"><div style="height:6px;width:<?= $rate ?>%;background:#2563eb;border-radius:3px;"></div></div>
                                        <span style="font-size:11px;font-weight:700;color:#2563eb;"><?= $rate ?>%</span>
                                    </div>
                                </div>
                            </div>
                            <?php 
                                endforeach;
                            endif;
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ===== ORGANIZATIONS VIEW (Platform Admin and Org Admin) ===== -->
                <?php if ($jwtPayload['role'] === 'Admin' || $jwtPayload['role'] === 'Super Admin' || $jwtPayload['role'] === 'Project Lead'): ?>
                <div id="view-organizations" class="tab-view active">
                    <div class="section-card">
                        <?php if ($jwtPayload['role'] === 'Admin' || $jwtPayload['role'] === 'Super Admin'): ?>
                        <div class="card-header" style="border-bottom: none; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3>Organizations</h3>
                                <p class="text-muted" style="margin-top: 4px; font-size: 12px; text-transform: none; letter-spacing: normal;">Review and manage company organizations registered on the platform.</p>
                            </div>
                            <button class="btn btn-secondary" onclick="window.location.reload();" style="display: flex; align-items: center; gap: 8px;">
                                <i data-lucide="refresh-cw" style="width: 16px; height: 16px;"></i> Refresh
                            </button>
                        </div>

                        <?php
                        $orgs = $pdo->query("
                            SELECT o.*,
                                (SELECT name FROM `employees` e WHERE e.org_id = o.id AND e.role = 'Project Lead' LIMIT 1) as admin_name,
                                (SELECT email FROM `employees` e WHERE e.org_id = o.id AND e.role = 'Project Lead' LIMIT 1) as admin_email,
                                (SELECT COUNT(*) FROM `employees` e WHERE e.org_id = o.id AND e.role != 'Admin') as emp_count,
                                (SELECT COUNT(*) FROM `projects` p WHERE p.org_id = o.id) as proj_count
                            FROM `organizations` o
                            ORDER BY o.status ASC, o.created_at DESC
                        ")->fetchAll(PDO::FETCH_ASSOC);
                        $pendingOrgs  = array_filter($orgs, fn($o) => $o['status'] === 'Pending');
                        $activeOrgs   = array_filter($orgs, fn($o) => $o['status'] === 'Active');
                        $rejectedOrgs = array_filter($orgs, fn($o) => $o['status'] === 'Rejected');
                        ?>

                        <?php if (!empty($pendingOrgs)): ?>
                        <div style="margin-top: 24px; margin-bottom: 8px;">
                            <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #f59e0b; margin-bottom: 12px;">
                                ⏳ Pending Approval (<?= count($pendingOrgs) ?>)
                            </h4>
                            <div class="table-responsive" style="margin-bottom: 0;">
                                <table class="table-custom" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left; padding: 10px 16px;">Organization</th>
                                            <th style="text-align: left; padding: 10px 16px;">Admin Details</th>
                                            <th style="text-align: left; padding: 10px 16px;">Contact / Details</th>
                                            <th style="text-align: center; padding: 10px 16px;">Employees</th>
                                            <th style="text-align: center; padding: 10px 16px;">Projects</th>
                                            <th style="text-align: left; padding: 10px 16px;">Registered</th>
                                            <th style="text-align: center; padding: 10px 16px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($pendingOrgs as $org): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 16px;">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #f59e0b, #d97706); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 14px; flex-shrink: 0;">
                                                    <?= strtoupper(substr($org['name'], 0, 2)) ?>
                                                </div>
                                                <div>
                                                    <div style="font-size: 13.5px; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($org['name']) ?></div>
                                                    <div style="font-size: 11.5px; color: #64748b;"><?= htmlspecialchars($org['slug'] ?: '-') ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 12.5px; color: #0f172a;">
                                            <div style="font-weight: 600;"><?= htmlspecialchars($org['admin_name'] ?: '-') ?></div>
                                            <div style="color: #64748b; font-size: 11.5px;"><?= htmlspecialchars($org['admin_email'] ?: '-') ?></div>
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 12px; color: #64748b; max-width: 250px;">
                                            <div><strong>Phone:</strong> <?= htmlspecialchars($org['phone'] ?: '-') ?></div>
                                            <div><strong>Address:</strong> <?= htmlspecialchars($org['address'] ?: '-') ?></div>
                                            <div><strong>Details:</strong> <?= htmlspecialchars($org['details'] ?: '-') ?></div>
                                        </td>
                                        <td style="padding: 12px 16px; text-align: center; font-size: 13px; font-weight: 700; color: #2563eb;"><?= $org['emp_count'] ?></td>
                                        <td style="padding: 12px 16px; text-align: center; font-size: 13px; font-weight: 700; color: #7c3aed;"><?= $org['proj_count'] ?></td>
                                        <td style="padding: 12px 16px; font-size: 12.5px; color: #64748b;"><?= date('d M Y', strtotime($org['created_at'])) ?></td>
                                        <td style="padding: 12px 16px; text-align: center;">
                                            <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                                <button class="btn-approve-org" data-org-id="<?= $org['id'] ?>"
                                                    style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 700; background: #10b981; color: #fff; border: none; border-radius: 6px; cursor: pointer;">
                                                    ✓ Approve
                                                </button>
                                                <button class="btn-reject-org" data-org-id="<?= $org['id'] ?>"
                                                    style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 700; background: #e2e8f0; color: #475569; border: none; border-radius: 6px; cursor: pointer;">
                                                    ✗ Reject
                                                </button>
                                                <button class="btn-delete-org-direct" onclick="deleteOrganization(<?= $org['id'] ?>)" data-org-id="<?= $org['id'] ?>"
                                                    style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer;">
                                                    🗑 Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div style="margin-top: 24px;">
                            <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #10b981; margin-bottom: 12px;">
                                ✅ Active Organizations (<?= count($activeOrgs) ?>)
                            </h4>
                            <?php if (empty($activeOrgs)): ?>
                                <div style="text-align: center; color: var(--text-muted); padding: 40px; font-size: 14px;">No active organizations yet.</div>
                            <?php else: ?>
                            <div class="table-responsive" style="margin-bottom: 0;">
                                <table class="table-custom" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left; padding: 10px 16px;">Organization</th>
                                            <th style="text-align: left; padding: 10px 16px;">Admin Details</th>
                                            <th style="text-align: left; padding: 10px 16px;">Contact / Details</th>
                                            <th style="text-align: center; padding: 10px 16px;">Employees</th>
                                            <th style="text-align: center; padding: 10px 16px;">Projects</th>
                                            <th style="text-align: left; padding: 10px 16px;">Approved Since</th>
                                            <th style="text-align: center; padding: 10px 16px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($activeOrgs as $org): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 16px;">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #2563eb, #1d4ed8); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 14px; flex-shrink: 0;">
                                                    <?= strtoupper(substr($org['name'], 0, 2)) ?>
                                                </div>
                                                <span style="font-size: 13.5px; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($org['name']) ?></span>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 12.5px; color: #0f172a;">
                                            <div style="font-weight: 600;"><?= htmlspecialchars($org['admin_name'] ?: '-') ?></div>
                                            <div style="color: #64748b; font-size: 11.5px;"><?= htmlspecialchars($org['admin_email'] ?: '-') ?></div>
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 12px; color: #64748b; max-width: 250px;">
                                            <div><strong>Phone:</strong> <?= htmlspecialchars($org['phone'] ?: '-') ?></div>
                                            <div><strong>Address:</strong> <?= htmlspecialchars($org['address'] ?: '-') ?></div>
                                            <div><strong>Details:</strong> <?= htmlspecialchars($org['details'] ?: '-') ?></div>
                                        </td>
                                        <td style="padding: 12px 16px; text-align: center; font-size: 13px; font-weight: 700; color: #2563eb;"><?= $org['emp_count'] ?></td>
                                        <td style="padding: 12px 16px; text-align: center; font-size: 13px; font-weight: 700; color: #7c3aed;"><?= $org['proj_count'] ?></td>
                                        <td style="padding: 12px 16px; font-size: 12.5px; color: #64748b;"><?= date('d M Y', strtotime($org['created_at'])) ?></td>
                                        <td style="padding: 12px 16px; text-align: center;">
                                            <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                                <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; color: #065f46; background: #dcfce7; padding: 3px 10px; border-radius: 20px; border: 1px solid #bbf7d0;">
                                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>Active
                                                </span>
                                                <button class="btn-delete-org-direct" onclick="deleteOrganization(<?= $org['id'] ?>)" data-org-id="<?= $org['id'] ?>"
                                                    style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer;">
                                                    🗑 Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($rejectedOrgs)): ?>
                        <div style="margin-top: 24px;">
                            <h4 style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #ef4444; margin-bottom: 12px;">
                                ✗ Rejected (<?= count($rejectedOrgs) ?>)
                            </h4>
                            <div class="table-responsive" style="margin-bottom: 0;">
                                <table class="table-custom" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left; padding: 10px 16px;">Organization</th>
                                            <th style="text-align: left; padding: 10px 16px;">Admin Details</th>
                                            <th style="text-align: left; padding: 10px 16px;">Contact / Details</th>
                                            <th style="text-align: left; padding: 10px 16px;">Registered</th>
                                            <th style="text-align: center; padding: 10px 16px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($rejectedOrgs as $org): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 16px; font-size: 13.5px; font-weight: 600; color: #94a3b8;"><?= htmlspecialchars($org['name']) ?></td>
                                        <td style="padding: 12px 16px; font-size: 12.5px; color: #94a3b8;">
                                            <div style="font-weight: 600;"><?= htmlspecialchars($org['admin_name'] ?: '-') ?></div>
                                            <div style="color: #64748b; font-size: 11.5px;"><?= htmlspecialchars($org['admin_email'] ?: '-') ?></div>
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 12px; color: #64748b; max-width: 250px;">
                                            <div><strong>Phone:</strong> <?= htmlspecialchars($org['phone'] ?: '-') ?></div>
                                            <div><strong>Address:</strong> <?= htmlspecialchars($org['address'] ?: '-') ?></div>
                                            <div><strong>Details:</strong> <?= htmlspecialchars($org['details'] ?: '-') ?></div>
                                        </td>
                                        <td style="padding: 12px 16px; font-size: 12.5px; color: #94a3b8;"><?= date('d M Y', strtotime($org['created_at'])) ?></td>
                                        <td style="padding: 12px 16px; text-align: center;">
                                            <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                                                <span style="font-size: 10.5px; font-weight: 700; color: #991b1b; background: #fee2e2; padding: 3px 10px; border-radius: 20px; border: 1px solid #fecaca;">Rejected</span>
                                                <button class="btn-delete-org-direct" onclick="deleteOrganization(<?= $org['id'] ?>)" data-org-id="<?= $org['id'] ?>"
                                                    style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer;">
                                                    🗑 Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php else: ?>
                            <!-- Organization Lead (Project Lead): View/Edit Own Organization Details -->
                            <?php
                            $stmtOwnOrg = $pdo->prepare("SELECT * FROM `organizations` WHERE id = ?");
                            $stmtOwnOrg->execute([$meOrgId]);
                            $ownOrg = $stmtOwnOrg->fetch(PDO::FETCH_ASSOC);
                            ?>
                            <div class="card-header" style="border-bottom: none; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center; padding: 0 0 15px 0;">
                                <div>
                                    <h3 style="font-size: 18px; font-weight: 700; color: var(--text-dark);">Organization Details</h3>
                                    <p class="text-muted" style="margin-top: 4px; font-size: 12px; text-transform: none; letter-spacing: normal;">Manage your company profile details and contact information.</p>
                                </div>
                            </div>
                            
                            <?php if ($ownOrg): ?>
                                <form action="api.php?action=update_own_organization" method="POST" id="form-update-own-org" style="margin-top: 20px; max-width: 500px; display: flex; flex-direction: column; gap: 16px;">
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px;">Organization Name</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($ownOrg['name']) ?>" readonly style="background: #f1f5f9; cursor: not-allowed; width: 100%; height: 38px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                    </div>
                                    <div class="form-group">
                                        <label style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px;">Slug / Code</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($ownOrg['slug']) ?>" readonly style="background: #f1f5f9; cursor: not-allowed; width: 100%; height: 38px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                                    </div>
                                    <div class="form-group">
                                        <label for="org-phone" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px;">Phone Number</label>
                                        <input type="text" name="phone" id="org-phone" class="form-control" value="<?= htmlspecialchars($ownOrg['phone'] ?? '') ?>" required style="width: 100%; height: 38px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none;">
                                    </div>
                                    <div class="form-group">
                                        <label for="org-address" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px;">Address</label>
                                        <textarea name="address" id="org-address" class="form-control" rows="3" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; font-family: inherit; font-size: 14px; resize: vertical;"><?= htmlspecialchars($ownOrg['address'] ?? '') ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="org-details" style="font-weight: 600; margin-bottom: 6px; display: block; font-size: 13px;">Company Details / About</label>
                                        <textarea name="details" id="org-details" class="form-control" rows="4" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; font-family: inherit; font-size: 14px; resize: vertical;"><?= htmlspecialchars($ownOrg['details'] ?? '') ?></textarea>
                                    </div>
                                    <div>
                                        <button type="submit" class="welcome-btn" style="border: none; cursor: pointer; padding: 10px 20px; font-weight: 600; border-radius: 6px; display: inline-flex; align-items: center; gap: 8px;">
                                            Save Changes
                                        </button>
                                    </div>
                                </form>
                                <script>
                                document.getElementById('form-update-own-org')?.addEventListener('submit', async function(e) {
                                    e.preventDefault();
                                    const fd = new FormData(this);
                                    try {
                                        const r = await fetch(this.action, { method: 'POST', body: fd });
                                        const res = await r.json();
                                        if (res.success) {
                                            alert('Organization details updated successfully!');
                                            window.location.reload();
                                        } else {
                                            alert('Failed to update: ' + (res.message || 'Unknown error'));
                                        }
                                    } catch(err) {
                                        alert('Error updating organization.');
                                    }
                                });
                                </script>
                            <?php else: ?>
                                <p style="color: var(--text-muted);">Organization profile not found.</p>
                            <?php endif; ?>
                        <?php endif; ?>

                    </div>
                </div>
                <?php endif; ?>

                <?php if ($jwtPayload['role'] !== 'Admin'): ?>
                <div id="view-users" class="tab-view">
                    <div class="section-card">
                        <div class="card-header" style="border-bottom: none; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h3>Users Management</h3>
                                <p class="text-muted" style="margin-top: 4px; font-size: 12px; text-transform: none; letter-spacing: normal;">Manage registered user accounts and their access status.</p>
                            </div>
                            <?php if ($jwtPayload['role'] === 'Admin' || $jwtPayload['role'] === 'Project Lead'): ?>
                            <button class="welcome-btn" id="btn-add-employee">
                                <i data-lucide="user-plus"></i> Add Employee
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Stats row -->
                        <?php
                        $allUsers = $pdo->prepare("SELECT * FROM `employees` WHERE `role` != 'Admin' AND `org_id` = ? ORDER BY id ASC");
                        $allUsers->execute([$meOrgId]);
                        $allUsers = $allUsers->fetchAll(PDO::FETCH_ASSOC);
                        $activeCount = count(array_filter($allUsers, fn($u) => $u['status'] === 'Approved' || $u['status'] === 'Active'));
                        $pendingCount = count(array_filter($allUsers, fn($u) => $u['status'] === 'Pending'));
                        $deactivatedCount = count(array_filter($allUsers, fn($u) => $u['status'] === 'Deactivated'));
                        ?>
                        <div style="display: flex; gap: 12px; margin-top: 20px; margin-bottom: 24px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 8px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 10px 16px; flex: 1; min-width: 100px;">
                                <div style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></div>
                                <span style="font-size: 13px; font-weight: 700; color: #065f46;"><?= $activeCount ?> Active</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 16px; flex: 1; min-width: 100px;">
                                <div style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></div>
                                <span style="font-size: 13px; font-weight: 700; color: #92400e;"><?= $pendingCount ?> Pending</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 10px 16px; flex: 1; min-width: 100px;">
                                <div style="width: 8px; height: 8px; border-radius: 50%; background: #ef4444;"></div>
                                <span style="font-size: 13px; font-weight: 700; color: #991b1b;"><?= $deactivatedCount ?> Deactivated</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 16px; flex: 1; min-width: 100px;">
                                <i data-lucide="users" style="width: 14px; height: 14px; color: #2563eb;"></i>
                                <span style="font-size: 13px; font-weight: 700; color: #1e40af;"><?= count($allUsers) ?> Total</span>
                            </div>
                        </div>

                        <!-- Users Grid -->
                        <?php if (empty($allUsers)): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 60px 20px; font-size: 14px;">
                                <i data-lucide="users" style="width: 40px; height: 40px; stroke-width: 1.5; display: block; margin: 0 auto 12px auto;"></i>
                                No users registered yet.
                            </div>
                        <?php else: ?>
                        <!-- Users List Table -->
                        <div class="table-responsive" style="margin-bottom: 0;">
                            <table class="table-custom" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th style="text-align: left; padding: 10px 16px;">User</th>
                                        <th style="text-align: left; padding: 10px 16px;">Role</th>
                                        <th style="text-align: left; padding: 10px 16px;">Email</th>
                                        <th style="text-align: left; padding: 10px 16px;">Emp ID</th>
                                        <th style="text-align: center; padding: 10px 16px;">Status</th>
                                        <?php if ($jwtPayload['role'] === 'Admin' || $jwtPayload['role'] === 'Project Lead'): ?>
                                        <th style="text-align: center; padding: 10px 16px;">Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($allUsers as $usr):
                                    $usrStatus = $usr['status'] ?? 'Pending';
                                    $isActive = ($usrStatus === 'Approved' || $usrStatus === 'Active');
                                    $isDeactivated = ($usrStatus === 'Deactivated');
                                    $isPending = ($usrStatus === 'Pending');
                                    $avatarColors = ['#2563eb','#7c3aed','#db2777','#059669','#d97706','#dc2626'];
                                    $avatarBg = $avatarColors[abs(crc32($usr['name'])) % count($avatarColors)];
                                    $initials = $usr['avatar'] ?: strtoupper(substr($usr['name'], 0, 2));
                                ?>
                                <tr class="user-row" data-user-id="<?= $usr['id'] ?>" style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 12px 16px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="background: <?= $avatarBg ?>; color: #fff; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0;">
                                                <?= htmlspecialchars($initials) ?>
                                            </div>
                                            <span class="user-name-span" style="font-size: 13.5px; font-weight: 600; color: #0f172a;"><?= htmlspecialchars($usr['name']) ?></span>
                                        </div>
                                    </td>
                                    <td style="padding: 12px 16px; font-size: 12.5px; color: #64748b; font-weight: 500;"><?= htmlspecialchars($usr['role']) ?></td>
                                    <td style="padding: 12px 16px; font-size: 12.5px; color: #475569;"><?= htmlspecialchars($usr['email']) ?></td>
                                    <td style="padding: 12px 16px; font-size: 12px; color: #64748b; font-family: monospace;"><?= htmlspecialchars($usr['emp_code'] ?: '-') ?></td>
                                    <td class="user-status-cell" style="padding: 12px 16px; text-align: center;">
                                        <?php if ($isActive): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; color: #065f46; background: #dcfce7; padding: 3px 10px; border-radius: 20px; border: 1px solid #bbf7d0;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>Active
                                            </span>
                                        <?php elseif ($isDeactivated): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; color: #991b1b; background: #fee2e2; padding: 3px 10px; border-radius: 20px; border: 1px solid #fecaca;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span>Deactivated
                                            </span>
                                        <?php else: ?>
                                            <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; color: #92400e; background: #fef3c7; padding: 3px 10px; border-radius: 20px; border: 1px solid #fde68a;">
                                                <span style="width: 6px; height: 6px; border-radius: 50%; background: #f59e0b;"></span>Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($jwtPayload['role'] === 'Admin' || $jwtPayload['role'] === 'Project Lead'): ?>
                                    <td class="user-actions-cell" style="padding: 12px 16px; text-align: center;">
                                        <div style="display: flex; gap: 6px; justify-content: center;">
                                            <?php if ($isPending): ?>
                                                <button class="btn-user-approve" data-emp-id="<?= $usr['id'] ?>"
                                                    style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 600; background: #10b981; color: #fff; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                                    <i data-lucide="check" style="width: 11px; height: 11px;"></i> Approve
                                                </button>
                                                <button class="btn-user-deactivate" data-emp-id="<?= $usr['id'] ?>" data-current-status="<?= $usrStatus ?>"
                                                    style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                                    <i data-lucide="x" style="width: 11px; height: 11px;"></i> Reject
                                                </button>
                                            <?php elseif ($isActive): ?>
                                                <button class="btn-user-deactivate" data-emp-id="<?= $usr['id'] ?>" data-current-status="<?= $usrStatus ?>"
                                                    style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 600; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                                    <i data-lucide="user-x" style="width: 11px; height: 11px;"></i> Deactivate
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-user-activate" data-emp-id="<?= $usr['id'] ?>" data-current-status="<?= $usrStatus ?>"
                                                    style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 600; background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                                                    <i data-lucide="user-check" style="width: 11px; height: 11px;"></i> Activate
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn-delete-emp" data-emp-id="<?= $usr['id'] ?>" data-emp-name="<?= htmlspecialchars($usr['name']) ?>"
                                                style="width: 28px; height: 28px; background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Delete User">
                                                <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>



                    </div>
                </div>

                <?php if ($jwtPayload['role'] !== 'Admin'): ?>
                <div id="view-departments" class="tab-view">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h2 style="font-size:20px; font-weight:700; color:#0f172a; margin:0;">Departments</h2>
                        <button class="btn btn-primary" id="btn-add-department" style="height:32px; padding:4px 14px; font-size:11px; background:#2563eb;"><i data-lucide="plus"></i> Add Department</button>
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:20px;" id="departments-grid-container">
                        <?php
                        if (empty($departmentsList)):
                        ?>
                            <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 40px;" id="departments-empty-placeholder">No departments registered yet. Click "Add Department" to start.</div>
                        <?php
                        else:
                            foreach ($departmentsList as $d):
                                $empCount = (int)$d['employee_count'];
                                $icon = $d['icon'] ?: 'git-branch';
                                $color = $d['color'] ?: '#2563eb';
                                $bg = $d['bg'] ?: '#eff6ff';
                        ?>
                        <div class="section-card" style="display:flex; align-items:center; justify-content:space-between; padding:20px;">
                            <div style="display:flex; align-items:center; gap:16px;">
                                <div style="width:48px;height:48px;border-radius:12px;background:<?= $bg ?>;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                    <i data-lucide="<?= $icon ?>" style="width:22px;height:22px;"></i>
                                </div>
                                <div>
                                    <div style="font-size:15px;font-weight:700;color:#0f172a;"><?= htmlspecialchars($d['name']) ?></div>
                                    <div style="font-size:12px;color:#6b7280;margin-top:3px;"><?= $empCount ?> employee<?= $empCount != 1 ? 's' : '' ?></div>
                                </div>
                            </div>
                            <div style="display:flex; gap:8px;">
                                <button class="header-btn btn-dept-edit" data-dept-id="<?= $d['id'] ?>" data-dept-name="<?= htmlspecialchars($d['name']) ?>" data-dept-icon="<?= htmlspecialchars($icon) ?>" data-dept-color="<?= htmlspecialchars($color) ?>" data-dept-bg="<?= htmlspecialchars($bg) ?>" title="Edit" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="edit-2" style="width: 12px; height: 12px;"></i></button>
                                <button class="header-btn btn-dept-delete" data-dept-id="<?= $d['id'] ?>" title="Delete" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="trash-2" style="width: 12px; height: 12px;"></i></button>
                            </div>
                        </div>
                        <?php 
                            endforeach;
                        endif;
                        ?>
                    </div>
                </div>

                <!-- ==========================================================================
                     TAB 11: SETTINGS VIEW
                     ========================================================================== -->
                
                <!-- ==========================================================================
                     TAB 10: CLIENTS VIEW
                     ========================================================================== -->
                <div id="view-clients" class="tab-view">
                    <!-- Header Actions Panel -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0;">Clients Directory</h2>
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <!-- Standardized search box -->
                            <div class="standard-search-wrapper">
                                <i data-lucide="search"></i>
                                <input type="text" id="clients-search" class="search-input" placeholder="Search clients...">
                            </div>
                            <!-- Add Client Button -->
                            <button class="btn btn-primary" id="btn-clients-add" style="height: 32px; padding: 4px 14px; font-size: 11px; background-color: #2563eb;"><i data-lucide="plus"></i> Add Client</button>
                        </div>
                    </div>

                    <!-- Clients Table List -->
                    <div class="section-card" style="padding: 0; overflow: hidden;">
                        <div class="table-responsive">
                            <table class="table-custom" id="clients-table-list">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Created Date</th>
                                        <th style="width: 100px; text-align: center;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($dropdownClients)): ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">No clients registered yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dropdownClients as $c): ?>
                                            <tr class="client-row" data-client-id="<?= $c['id'] ?>" data-client-name="<?= htmlspecialchars(strtolower($c['name'])) ?>" data-client-email="<?= htmlspecialchars(strtolower($c['email'] ?? '')) ?>" data-client-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>">
                                                <td style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($c['name']) ?></td>
                                                <td><?= htmlspecialchars($c['email'] ?: 'N/A') ?></td>
                                                <td><?= htmlspecialchars($c['phone'] ?: 'N/A') ?></td>
                                                <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                                                <td style="text-align: center;">
                                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                                        <button class="header-btn btn-client-history" data-client-id="<?= $c['id'] ?>" title="View History" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="history" style="width: 12px; height: 12px;"></i></button>
                                                        <button class="header-btn btn-client-edit" data-client-id="<?= $c['id'] ?>" data-client-name="<?= htmlspecialchars($c['name']) ?>" data-client-email="<?= htmlspecialchars($c['email'] ?? '') ?>" data-client-phone="<?= htmlspecialchars($c['phone'] ?? '') ?>" title="Edit" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="edit-3" style="width: 12px; height: 12px;"></i></button>
                                                        <button class="header-btn btn-client-delete" data-client-id="<?= $c['id'] ?>" title="Delete" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="trash-2" style="width: 12px; height: 12px;"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

<div id="view-settings" class="tab-view">
                    <div class="settings-grid-layout" style="display: flex; gap: 24px; width: 100%; flex-wrap: wrap;">
                        <!-- Profile Card -->
                        <div class="section-card settings-profile-card" style="flex: 1; min-width: 280px; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 30px;">
                             <div class="settings-avatar-wrapper" style="position: relative; margin-bottom: 20px;">
                                 <?php if ($meAvatar && (strpos($meAvatar, '.') !== false || strpos($meAvatar, 'uploads/') !== false)): ?>
                                     <img src="<?= htmlspecialchars($meAvatar) ?>" alt="<?= htmlspecialchars($meName) ?>" class="settings-large-avatar" style="object-fit: cover; width: 80px; height: 80px; border-radius: 50%; border: 4px solid #ffffff; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
                                 <?php else: ?>
                                     <div class="settings-large-avatar" style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #ffffff; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; font-weight: 700; border: 4px solid #ffffff; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
                                         <?= htmlspecialchars($meAvatar) ?>
                                     </div>
                                 <?php endif; ?>
                             </div>
                            <h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 6px;"><?= htmlspecialchars($meName) ?></h3>
                            <span class="badge" style="background-color: #eff6ff; color: #2563eb; padding: 4px 12px; border-radius: 9999px; font-size: 11px; font-weight: 600; margin-bottom: 15px;"><?= htmlspecialchars($meRole) ?></span>
                            
                            <div style="width: 100%; border-top: 1px solid #f1f5f9; padding-top: 15px; text-align: left; font-size: 12.5px; display: flex; flex-direction: column; gap: 10px; color: #475569;">
                                <div><strong>Employee ID:</strong> <span style="font-family: monospace; color: #2563eb;"><?= htmlspecialchars($meCode) ?></span></div>
                                <div><strong>Email:</strong> <span><?= htmlspecialchars($me['email'] ?? '') ?></span></div>
                                <div><strong>Status:</strong> <span class="status-badge completed" style="font-size: 9px; padding: 2px 6px;">Active</span></div>
                            </div>
                        </div>

                        <!-- Form Card -->
                        <div class="section-card settings-form-card" style="flex: 2; min-width: 400px; padding: 30px;">
                            <div class="card-header" style="border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; margin-bottom: 20px;">
                                <h3>Account Information</h3>
                            </div>
                            <form id="form-update-settings" method="POST" enctype="multipart/form-data">
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label for="set-name">Full Name *</label>
                                        <input type="text" name="name" id="set-name" class="form-control" value="<?= htmlspecialchars($meName) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="set-role">Role *</label>
                                        <input type="text" name="role" id="set-role" class="form-control" value="<?= htmlspecialchars($meRole) ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="set-email">Email Address *</label>
                                        <input type="email" name="email" id="set-email" class="form-control" value="<?= htmlspecialchars($me['email'] ?? '') ?>" autocomplete="username" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="set-code">Employee Code</label>
                                        <input type="text" name="emp_code" id="set-code" class="form-control" value="<?= htmlspecialchars($meCode) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="set-avatar">Avatar Initials (e.g. SJ)</label>
                                        <input type="text" name="avatar" id="set-avatar" class="form-control" value="<?= htmlspecialchars(strpos($meAvatar, '/') !== false ? substr($meName, 0, 2) : $meAvatar) ?>" maxlength="2">
                                    </div>
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label for="set-photo">Profile Photo (Upload Image)</label>
                                        <?php if ($meAvatar && (strpos($meAvatar, '.') !== false || strpos($meAvatar, 'uploads/') !== false)): ?>
                                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
                                                <img src="<?= htmlspecialchars($meAvatar) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #cbd5e1;">
                                                <label style="display: flex; align-items: center; gap: 6px; font-weight: normal; cursor: pointer; color: var(--text-color);">
                                                    <input type="checkbox" name="remove_photo" value="1" style="width: auto;"> Remove current photo
                                                </label>
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" name="photo" id="set-photo" class="form-control" accept="image/*" style="padding: 6px;">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                                    <label for="set-password">Change Password (leave blank to keep current)</label>
                                    <input type="password" name="password" id="set-password" class="form-control" placeholder="New password" autocomplete="new-password">
                                </div>
                                <div style="margin-top: 24px; text-align: right;">
                                    <button type="submit" class="btn btn-primary" style="padding: 10px 20px;"><i data-lucide="save"></i> Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            <!-- ==========================================================================
         TAB: BUILDING MODULE
         ========================================================================== -->
    <div id="view-building" class="tab-view">
        <div class="section-card">
            <div class="card-header" style="border-bottom: none; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3>Building Module</h3>
                    <p class="text-muted" style="margin-top: 4px; font-size: 12px; text-transform: none; letter-spacing: normal;">Manage buildings, floors, units, owners and documents.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div class="standard-search-wrapper">
                        <i data-lucide="search"></i>
                        <input type="text" id="building-search" class="search-input" placeholder="Search Buildings...">
                    </div>
                    <button class="welcome-btn" id="btn-add-building">
                        <i data-lucide="plus"></i> Add Building
                    </button>
                </div>
            </div>
            <div class="table-responsive" style="margin-bottom: 0;">
                <table class="table-custom" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="text-align: left;">ID</th>
                            <th style="text-align: left;">Name</th>
                            <th style="text-align: left;">Type</th>
                            <th style="text-align: left;">Total Units</th>
                            <th style="text-align: left;">Owner</th>
                            <th style="text-align: left;">Status</th>
                            <th style="text-align: center; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="buildings-tbody">
                        <?php foreach ($buildingsList as $b): ?>
                        <tr>
                            <td>#<?= $b['id'] ?></td>
                            <td style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($b['name']) ?></td>
                            <td><?= htmlspecialchars($b['type']) ?></td>
                            <td><?= htmlspecialchars($b['total_units']) ?></td>
                            <td><?= htmlspecialchars($b['owner_name']) ?></td>
                            <td>
                                <?php
                                $status = strtolower($b['status']);
                                $badgeClass = ($status === 'available' ? 'completed' : ($status === 'sold' ? 'review' : 'pending'));
                                ?>
                                <span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars($b['status']) ?></span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; justify-content: center; gap: 8px;">
                                    <button class="header-btn btn-edit-building" data-id="<?= $b['id'] ?>" data-json='<?= json_encode($b, JSON_HEX_APOS) ?>' title="Edit" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="edit-2" style="width: 12px; height: 12px;"></i></button>
                                    <button class="header-btn btn-delete-building" data-id="<?= $b['id'] ?>" title="Delete" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="trash-2" style="width: 12px; height: 12px; color: #ef4444;"></i></button>
                                    <?php if($b['document_path']): ?>
                                    <a href="<?= htmlspecialchars($b['document_path']) ?>" target="_blank" class="header-btn" title="View Document" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; color: var(--text-muted);"><i data-lucide="file-text" style="width: 12px; height: 12px;"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($buildingsList)): ?>
                        <tr><td colspan="7" style="text-align:center; color: var(--text-muted); padding: 20px;">No buildings found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Building -->
    <div class="modal-overlay" id="modal-building">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modal-building-title">Add Building</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-building" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="building-action" value="create_building">
                <input type="hidden" name="id" id="building-id">
                <div class="modal-body" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group"><label>Building Name</label><input type="text" name="name" id="building-name" class="form-control" required></div>
                    <div class="form-group"><label>Type</label><input type="text" name="type" id="building-type" class="form-control"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Address</label><textarea name="address" id="building-address" class="form-control" rows="2"></textarea></div>
                    <div class="form-group"><label>Total Floors</label><input type="number" name="total_floors" id="building-floors" class="form-control"></div>
                    <div class="form-group"><label>Total Units</label><input type="number" name="total_units" id="building-units" class="form-control"></div>
                    <div class="form-group"><label>Total Area (sq.ft)</label><input type="number" step="0.01" name="total_area" id="building-area" class="form-control"></div>
                    <div class="form-group"><label>Owner Name</label><input type="text" name="owner_name" id="building-owner" class="form-control"></div>
                    <div class="form-group"><label>Contact Number</label><input type="text" name="contact_number" id="building-contact" class="form-control"></div>
                    <div class="form-group"><label>Status</label><select name="status" id="building-status" class="form-control"><option value="Available">Available</option><option value="Sold">Sold</option><option value="Rented">Rented</option></select></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Document Upload</label><input type="file" name="document" id="building-doc" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Building</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         TAB: SINGLE PLOT MODULE
         ========================================================================== -->
    <div id="view-singleplot" class="tab-view">
        <div class="section-card">
            <div class="card-header" style="border-bottom: none; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3>Single Plot Module</h3>
                    <p class="text-muted" style="margin-top: 4px; font-size: 12px; text-transform: none; letter-spacing: normal;">Manage individual plots, areas, pricing, and availability status.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div class="standard-search-wrapper">
                        <i data-lucide="search"></i>
                        <input type="text" id="singleplot-search" class="search-input" placeholder="Search Plots...">
                    </div>
                    <button class="welcome-btn" id="btn-add-singleplot">
                        <i data-lucide="plus"></i> Add Plot
                    </button>
                </div>
            </div>
            <div class="table-responsive" style="margin-bottom: 0;">
                <table class="table-custom" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Plot No.</th>
                            <th style="text-align: left;">Layout Name</th>
                            <th style="text-align: left;">Area</th>
                            <th style="text-align: left;">Price</th>
                            <th style="text-align: left;">Status</th>
                            <th style="text-align: center; width: 100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="singleplot-tbody">
                        <?php foreach ($singlePlotsList as $p): ?>
                        <tr>
                            <td style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($p['plot_number']) ?></td>
                            <td><?= htmlspecialchars($p['layout_name']) ?></td>
                            <td><?= htmlspecialchars($p['area']) ?></td>
                            <td style="font-weight: 600;">₹<?= htmlspecialchars($p['price']) ?></td>
                            <td>
                                <?php
                                $status = strtolower($p['status']);
                                $badgeClass = ($status === 'available' ? 'completed' : ($status === 'sold' ? 'review' : 'pending'));
                                ?>
                                <span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars($p['status']) ?></span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; justify-content: center; gap: 8px;">
                                    <button class="header-btn btn-edit-singleplot" data-id="<?= $p['id'] ?>" data-json='<?= json_encode($p, JSON_HEX_APOS) ?>' title="Edit" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="edit-2" style="width: 12px; height: 12px;"></i></button>
                                    <button class="header-btn btn-delete-singleplot" data-id="<?= $p['id'] ?>" title="Delete" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="trash-2" style="width: 12px; height: 12px; color: #ef4444;"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($singlePlotsList)): ?>
                        <tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding: 20px;">No plots found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Single Plot -->
    <div class="modal-overlay" id="modal-singleplot">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modal-singleplot-title">Add Plot</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-singleplot" method="POST">
                <input type="hidden" name="action" id="singleplot-action" value="create_single_plot">
                <input type="hidden" name="id" id="singleplot-id">
                <div class="modal-body" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group"><label>Plot Number</label><input type="text" name="plot_number" id="sp-plot" class="form-control" required></div>
                    <div class="form-group"><label>Layout Name</label><input type="text" name="layout_name" id="sp-layout" class="form-control"></div>
                    <div class="form-group"><label>Survey Number</label><input type="text" name="survey_number" id="sp-survey" class="form-control"></div>
                    <div class="form-group"><label>Area</label><input type="number" step="0.01" name="area" id="sp-area" class="form-control"></div>
                    <div class="form-group"><label>Location</label><input type="text" name="location" id="sp-location" class="form-control"></div>
                    <div class="form-group"><label>Price</label><input type="number" step="0.01" name="price" id="sp-price" class="form-control"></div>
                    <div class="form-group"><label>Facing Direction</label><input type="text" name="facing_direction" id="sp-facing" class="form-control"></div>
                    <div class="form-group"><label>Status</label><select name="status" id="sp-status" class="form-control"><option value="Available">Available</option><option value="Sold">Sold</option><option value="Reserved">Reserved</option></select></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Owner Name</label><input type="text" name="owner_name" id="sp-owner" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Plot</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         TAB: UAL MODULE
         ========================================================================== -->
    <div id="view-ual" class="tab-view">
        <div class="section-card">
            <div class="card-header" style="border-bottom: none; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3>UAL Module</h3>
                    <p class="text-muted" style="margin-top: 4px; font-size: 12px; text-transform: none; letter-spacing: normal;">Manage Urban Land Ceiling (UAL) records, ceiling limits, and government orders.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div class="standard-search-wrapper">
                        <i data-lucide="search"></i>
                        <input type="text" id="ual-search" class="search-input" placeholder="Search UAL...">
                    </div>
                    <button class="welcome-btn" id="btn-add-ual">
                        <i data-lucide="plus"></i> Add UAL Record
                    </button>
                </div>
            </div>
            <div class="table-responsive" style="margin-bottom: 0;">
                <table class="table-custom" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Case No.</th>
                            <th style="text-align: left;">Owner Name</th>
                            <th style="text-align: left;">Total Land</th>
                            <th style="text-align: left;">Excess Land</th>
                            <th style="text-align: left;">Status</th>
                            <th style="text-align: center; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ual-tbody">
                        <?php foreach ($ualRecordsList as $u): ?>
                        <tr>
                            <td style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($u['case_number']) ?></td>
                            <td><?= htmlspecialchars($u['owner_name']) ?></td>
                            <td><?= htmlspecialchars($u['total_land_area']) ?></td>
                            <td><?= htmlspecialchars($u['excess_land_area']) ?></td>
                            <td>
                                <?php
                                $status = strtolower($u['approval_status']);
                                $badgeClass = ($status === 'approved' ? 'completed' : ($status === 'rejected' ? 'review' : 'pending'));
                                ?>
                                <span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars($u['approval_status']) ?></span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; justify-content: center; gap: 8px;">
                                    <button class="header-btn btn-edit-ual" data-id="<?= $u['id'] ?>" data-json='<?= json_encode($u, JSON_HEX_APOS) ?>' title="Edit" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="edit-2" style="width: 12px; height: 12px;"></i></button>
                                    <button class="header-btn btn-delete-ual" data-id="<?= $u['id'] ?>" title="Delete" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="trash-2" style="width: 12px; height: 12px; color: #ef4444;"></i></button>
                                    <?php if($u['document_path']): ?>
                                    <a href="<?= htmlspecialchars($u['document_path']) ?>" target="_blank" class="header-btn" title="View Document" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; color: var(--text-muted);"><i data-lucide="file-text" style="width: 12px; height: 12px;"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($ualRecordsList)): ?>
                        <tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding: 20px;">No UAL records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: UAL -->
    <div class="modal-overlay" id="modal-ual">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modal-ual-title">Add UAL Record</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-ual" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="ual-action" value="create_ual_record">
                <input type="hidden" name="id" id="ual-id">
                <div class="modal-body" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group"><label>Case Number</label><input type="text" name="case_number" id="ual-case" class="form-control" required></div>
                    <div class="form-group"><label>Owner Name</label><input type="text" name="owner_name" id="ual-owner" class="form-control"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Address</label><textarea name="address" id="ual-address" class="form-control" rows="2"></textarea></div>
                    <div class="form-group"><label>Total Land Area</label><input type="number" step="0.01" name="total_land_area" id="ual-total" class="form-control"></div>
                    <div class="form-group"><label>Gov Ceiling Limit</label><input type="number" step="0.01" name="gov_ceiling_limit" id="ual-limit" class="form-control"></div>
                    <div class="form-group"><label>Excess Land Area</label><input type="number" step="0.01" name="excess_land_area" id="ual-excess" class="form-control"></div>
                    <div class="form-group"><label>Gov Order Number</label><input type="text" name="gov_order_number" id="ual-order" class="form-control"></div>
                    <div class="form-group"><label>Approval Status</label><select name="approval_status" id="ual-status" class="form-control"><option value="Pending">Pending</option><option value="Approved">Approved</option><option value="Rejected">Rejected</option></select></div>
                    <div class="form-group"><label>Document Upload</label><input type="file" name="document" id="ual-doc" class="form-control"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Remarks</label><input type="text" name="remarks" id="ual-remarks" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         TAB: LAND SURVEY MODULE
         ========================================================================== -->
    <div id="view-landsurvey" class="tab-view">
        <div class="section-card">
            <div class="card-header" style="border-bottom: none; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3>Land Survey Module</h3>
                    <p class="text-muted" style="margin-top: 4px; font-size: 12px; text-transform: none; letter-spacing: normal;">Track land survey records, locations, area dimensions, and map coordinates.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div class="standard-search-wrapper">
                        <i data-lucide="search"></i>
                        <input type="text" id="landsurvey-search" class="search-input" placeholder="Search Surveys...">
                    </div>
                    <button class="welcome-btn" id="btn-add-landsurvey">
                        <i data-lucide="plus"></i> Add Survey
                    </button>
                </div>
            </div>
            <div class="table-responsive" style="margin-bottom: 0;">
                <table class="table-custom" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Survey No.</th>
                            <th style="text-align: left;">Village</th>
                            <th style="text-align: left;">Owner Name</th>
                            <th style="text-align: left;">Total Area</th>
                            <th style="text-align: left;">Land Type</th>
                            <th style="text-align: center; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="landsurvey-tbody">
                        <?php foreach ($landSurveysList as $s): ?>
                        <tr>
                            <td style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($s['survey_number']) ?></td>
                            <td><?= htmlspecialchars($s['village_name']) ?></td>
                            <td><?= htmlspecialchars($s['owner_name']) ?></td>
                            <td><?= htmlspecialchars($s['total_area']) ?></td>
                            <td><?= htmlspecialchars($s['land_type']) ?></td>
                            <td style="text-align: center;">
                                <div style="display: flex; justify-content: center; gap: 8px;">
                                    <button class="header-btn btn-edit-landsurvey" data-id="<?= $s['id'] ?>" data-json='<?= json_encode($s, JSON_HEX_APOS) ?>' title="Edit" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="edit-2" style="width: 12px; height: 12px;"></i></button>
                                    <button class="header-btn btn-delete-landsurvey" data-id="<?= $s['id'] ?>" title="Delete" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="trash-2" style="width: 12px; height: 12px; color: #ef4444;"></i></button>
                                    <?php if($s['document_path']): ?>
                                    <a href="<?= htmlspecialchars($s['document_path']) ?>" target="_blank" class="header-btn" title="View Document" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; color: var(--text-muted);"><i data-lucide="file-text" style="width: 12px; height: 12px;"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($landSurveysList)): ?>
                        <tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding: 20px;">No land surveys found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Land Survey -->
    <div class="modal-overlay" id="modal-landsurvey">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modal-landsurvey-title">Add Survey Record</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-landsurvey" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="landsurvey-action" value="create_land_survey">
                <input type="hidden" name="id" id="landsurvey-id">
                <div class="modal-body" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group"><label>Survey Number</label><input type="text" name="survey_number" id="ls-survey" class="form-control" required></div>
                    <div class="form-group"><label>Village Name</label><input type="text" name="village_name" id="ls-village" class="form-control"></div>
                    <div class="form-group"><label>Taluk</label><input type="text" name="taluk" id="ls-taluk" class="form-control"></div>
                    <div class="form-group"><label>District</label><input type="text" name="district" id="ls-district" class="form-control"></div>
                    <div class="form-group"><label>Land Type</label><input type="text" name="land_type" id="ls-type" class="form-control"></div>
                    <div class="form-group"><label>Owner Name</label><input type="text" name="owner_name" id="ls-owner" class="form-control"></div>
                    <div class="form-group"><label>Total Area</label><input type="number" step="0.01" name="total_area" id="ls-area" class="form-control"></div>
                    <div class="form-group"><label>Latitude</label><input type="text" name="latitude" id="ls-lat" class="form-control"></div>
                    <div class="form-group"><label>Longitude</label><input type="text" name="longitude" id="ls-long" class="form-control"></div>
                    <div class="form-group"><label>Document Upload</label><input type="file" name="document" id="ls-doc" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Survey</button>
                </div>
            </form>
        </div>
    </div>

    <div id="view-surveymanagement" class="tab-view">
        <div class="section-card">
            <!-- Header layout identical to Employee card -->
            <div class="card-header" style="border-bottom: none; margin-bottom: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3>Survey Management</h3>
                    <p class="text-muted" style="margin-top: 4px; font-size: 12px; text-transform: none; letter-spacing: normal;">Track land survey records, owner details, location coordinates, and audit history.</p>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <div class="standard-search-wrapper">
                        <i data-lucide="search"></i>
                        <input type="text" id="filter-survey-number" class="search-input" placeholder="Search Survey Number...">
                    </div>
                    <button class="welcome-btn" id="btn-add-survey">
                        <i data-lucide="plus"></i> Add Survey Record
                    </button>
                    <button class="welcome-btn" id="btn-export-survey" style="background-color: var(--secondary); color: var(--text-dark);">
                        <i data-lucide="download"></i> Generate CSV
                    </button>
                    <button class="welcome-btn" id="btn-export-survey-pdf" onclick="window.print()" style="background-color: var(--secondary); color: var(--text-dark);">
                        <i data-lucide="file-text"></i> Generate PDF
                    </button>
                </div>
            </div>

            <!-- Filter Panel -->
            <div style="background: var(--bg-light, #f8fafc); padding: 12px 16px; border-radius: 8px; border: 1px solid var(--sidebar-border, #e2e8f0); margin: 15px 0 20px 0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <span style="font-size: 12px; font-weight: 600; color: var(--text-muted, #64748b);">Filters:</span>
                <input type="text" id="filter-survey-village" placeholder="Village" style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; width: 130px; background: #fff;">
                <input type="text" id="filter-survey-taluk" placeholder="Taluk" style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; width: 130px; background: #fff;">
                <input type="text" id="filter-survey-district" placeholder="District" style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; width: 130px; background: #fff;">
                <select id="filter-survey-status" style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; background: #fff;">
                    <option value="">All Statuses</option>
                    <option value="Pending">Pending</option>
                    <option value="Verified">Verified</option>
                    <option value="Rejected">Rejected</option>
                </select>
                <button class="header-btn" id="btn-clear-survey-filters" style="padding: 6px 12px; border: 1px solid var(--sidebar-border); border-radius: 6px; cursor: pointer; font-size: 13px; background: transparent;">Clear</button>
            </div>

            <!-- Table section -->
            <div class="table-responsive" style="margin-bottom: 0;">
                <table class="table-custom" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Survey / Sub Div</th>
                            <th style="text-align: left;">Owner Name</th>
                            <th style="text-align: left;">Location</th>
                            <th style="text-align: left;">Total Area</th>
                            <th style="text-align: left;">Status</th>
                            <th style="text-align: center; width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="survey-management-tbody">
                        <?php if (!empty($surveyManagementList)): ?>
                            <?php foreach ($surveyManagementList as $s): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars($s['survey_number']) ?></div>
                                        <div style="font-size: 11px; color: #64748b;">Sub: <?= htmlspecialchars($s['sub_division_number']) ?></div>
                                    </td>
                                    <td style="font-weight: 500; color: #0f172a;"><?= htmlspecialchars($s['owner_name']) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($s['village_name']) ?></div>
                                        <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($s['taluk']) ?>, <?= htmlspecialchars($s['district']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($s['total_area']) ?></td>
                                    <td>
                                        <?php
                                        $status = strtolower($s['status']);
                                        $badgeClass = ($status === 'verified' ? 'completed' : ($status === 'rejected' ? 'review' : 'pending'));
                                        ?>
                                        <span class="status-badge <?= $badgeClass ?>"><?= htmlspecialchars($s['status']) ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; justify-content: center; gap: 8px;">
                                            <button class="header-btn edit-survey" data-id="<?= $s['id'] ?>" data-json='<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>' title="Edit" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="edit-2" style="width: 12px; height: 12px;"></i></button>
                                            <button class="header-btn verify-survey" data-id="<?= $s['id'] ?>" title="Verify" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="check-circle" style="width: 12px; height: 12px; color: #10b981;"></i></button>
                                            <button class="header-btn history-survey" data-id="<?= $s['id'] ?>" title="History" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="clock" style="width: 12px; height: 12px; color: #8b5cf6;"></i></button>
                                            <button class="header-btn archive-survey" data-id="<?= $s['id'] ?>" title="Archive" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; cursor: pointer; color: var(--text-muted);"><i data-lucide="archive" style="width: 12px; height: 12px; color: #ef4444;"></i></button>
                                            <?php if ($s['document_path']): ?>
                                                <a href="<?= htmlspecialchars($s['document_path']) ?>" target="_blank" class="header-btn" title="View Document" style="border: 1px solid var(--sidebar-border); border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; background: transparent; color: var(--text-muted);"><i data-lucide="file-text" style="width: 12px; height: 12px; color: #06b6d4;"></i></a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">No active survey records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div> <!-- Closing content-area -->

            <!-- Footer -->
            <footer class="main-footer">
                <span>&copy; 2026 Vyala Software TaskPad. All rights reserved. Software Version 2.0.0</span>
            </footer>
        </main>
    </div>

    <!-- Modal: Survey Management -->
    <div class="modal-overlay" id="modal-surveymanagement">
        <div class="modal-container" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3 id="modal-surveymanagement-title">Add Survey Record</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-surveymanagement" enctype="multipart/form-data">
                <input type="hidden" name="id" id="sm-id">
                <input type="hidden" name="action" id="sm-action" value="create_survey_record">
                <div class="modal-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Survey Number *</label>
                        <input type="text" name="survey_number" id="sm-survey-number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Sub Division Number</label>
                        <input type="text" name="sub_division_number" id="sm-sub-division-number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" id="sm-owner-name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Village Name</label>
                        <input type="text" name="village_name" id="sm-village-name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Taluk</label>
                        <input type="text" name="taluk" id="sm-taluk" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>District</label>
                        <input type="text" name="district" id="sm-district" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Land Type</label>
                        <input type="text" name="land_type" id="sm-land-type" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Total Area</label>
                        <input type="number" step="0.01" name="total_area" id="sm-total-area" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Patta Number</label>
                        <input type="text" name="patta_number" id="sm-patta-number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>FMB Number</label>
                        <input type="text" name="fmb_number" id="sm-fmb-number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Latitude</label>
                        <input type="text" name="latitude" id="sm-latitude" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Longitude</label>
                        <input type="text" name="longitude" id="sm-longitude" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Survey Date</label>
                        <input type="date" name="survey_date" id="sm-survey-date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="sm-status" class="form-control">
                            <option value="Pending">Pending</option>
                            <option value="Verified">Verified</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Remarks</label>
                        <textarea name="remarks" id="sm-remarks" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Upload Document (Optional)</label>
                        <input type="file" name="document" id="sm-document" class="form-control">
                    </div>
                </div>
                <div class="modal-footer" style="grid-column: span 2;">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background-color: #2563eb;">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Survey History -->
    <div class="modal-overlay" id="modal-survey-history">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3>Survey Record History</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body">
                <div id="survey-history-content" style="max-height: 400px; overflow-y: auto;">
                    <p style="text-align: center; color: #64748b;">Loading history...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 1: Create Task -->
    <div class="modal-overlay" id="modal-task">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Create New Task</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-new-task" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="tk-title">Task Title</label>
                        <input type="text" name="title" id="tk-title" class="form-control" placeholder="e.g. Wireframe customer portal" required>
                    </div>
                    <div class="form-group">
                        <label for="tk-desc">Description</label>
                        <textarea name="description" id="tk-desc" class="form-control" rows="3" placeholder="Provide task requirements..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="tk-project">Project</label>
                        <select name="project_id" id="tk-project" class="form-control" required>
                            <option value="">Select Project</option>
                            <?php foreach ($dropdownProjects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tk-assignee">Assign To</label>
                        <select name="assigned_to" id="tk-assignee" class="form-control" required>
                            <option value="">Select Team Member</option>
                            <?php foreach ($dropdownEmployees as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tk-priority">Priority</label>
                        <select name="priority" id="tk-priority" class="form-control" required>
                            <option value="Low" selected>Low</option>
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tk-due">Due Date</label>
                        <input type="date" name="due_date" id="tk-due" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Task -->
    <div class="modal-overlay" id="modal-edit-task">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Edit Task Due Date & Duration</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-edit-task" method="POST">
                <input type="hidden" name="action" value="update_task_details">
                <input type="hidden" name="task_id" id="edit-tk-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Task Title</label>
                        <input type="text" id="edit-tk-title" class="form-control" readonly style="background:#f1f5f9; color:#64748b;">
                    </div>
                    <div class="form-group">
                        <label for="edit-tk-due">Due Date</label>
                        <input type="date" name="due_date" id="edit-tk-due" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-tk-days">Estimated Duration (Days)</label>
                        <input type="number" name="estimated_duration" id="edit-tk-days" class="form-control" min="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 2: Create Project -->
    <div class="modal-overlay" id="modal-project">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Create New Project</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-new-project" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="p-name">Project Name</label>
                        <input type="text" name="name" id="p-name" class="form-control" placeholder="e.g. THAMARAI SERVICES" required>
                    </div>
                    <div class="form-group">
                        <label for="p-desc">Description</label>
                        <textarea name="description" id="p-desc" class="form-control" rows="3" placeholder="Brief outline..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="p-priority">Priority</label>
                        <select name="priority" id="p-priority" class="form-control" required>
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="p-client">Client</label>
                        <select name="client_id" id="p-client" class="form-control" required>
                            <option value="">Select Client</option>
                            <?php foreach ($dropdownClients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Team Members</label>
                        <div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; max-height: 140px; overflow-y: auto; background: #fafafa;">
                            <?php foreach ($dropdownEmployees as $emp): ?>
                            <label style="display: flex; align-items: center; gap: 8px; padding: 5px 0; cursor: pointer; font-size: 13px; color: #334155;">
                                <input type="checkbox" name="member_ids[]" value="<?= $emp['id'] ?>" style="accent-color: #2563eb; width: 14px; height: 14px;">
                                <?= htmlspecialchars($emp['name']) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Project</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 3: Add Client -->
    <div class="modal-overlay" id="modal-client">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Add New Client</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-new-client" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="c-name">Client Name</label>
                        <input type="text" name="name" id="c-name" class="form-control" placeholder="e.g. Suresh Babu" required>
                    </div>
                    <div class="form-group">
                        <label for="c-email">Email</label>
                        <input type="email" name="email" id="c-email" class="form-control" placeholder="e.g. client@domain.com">
                    </div>
                    <div class="form-group">
                        <label for="c-phone">Phone</label>
                        <input type="text" name="phone" id="c-phone" class="form-control" placeholder="e.g. +91 98480 12345" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Client</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 4: Log Work Hours -->
    <div class="modal-overlay" id="modal-logtime">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Log Work Hours</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-new-timesheet" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="ts-task">Task</label>
                        <select name="task_id" id="ts-task" class="form-control" required>
                            <option value="">Select Task</option>
                            <?php
                            try {
                                $tasksDropdown = $pdo->query("SELECT id, title FROM tasks")->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($tasksDropdown as $t) {
                                    echo '<option value="' . $t['id'] . '">' . htmlspecialchars($t['title']) . '</option>';
                                }
                            } catch (Exception $e) {}
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ts-employee">Team Member</label>
                        <select name="employee_id" id="ts-employee" class="form-control" required>
                            <option value="">Select Member</option>
                            <?php foreach ($dropdownEmployees as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ts-hours">Hours Spent</label>
                        <input type="number" step="0.25" name="hours" id="ts-hours" class="form-control" placeholder="e.g. 4.5" required>
                    </div>
                    <div class="form-group">
                        <label for="ts-date">Date Logged</label>
                        <input type="date" name="date" id="ts-date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="ts-desc">Work Notes</label>
                        <textarea name="description" id="ts-desc" class="form-control" rows="3" placeholder="Completed items..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Log Hours</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 5: Create Employee -->
    <div class="modal-overlay" id="modal-employee">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Add New Employee</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-new-employee" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="emp-name">Full Name</label>
                        <input type="text" name="name" id="emp-name" class="form-control" placeholder="e.g. Kalpana G" required>
                    </div>
                    <div class="form-group">
                        <label for="emp-role">Role</label>
                        <input type="text" name="role" id="emp-role" class="form-control" placeholder="e.g. Developer, Designer" required>
                    </div>
                    <div class="form-group">
                        <label for="emp-email">Email Address</label>
                        <input type="email" name="email" id="emp-email" class="form-control" placeholder="e.g. kalpana@vyalasoftware.com" autocomplete="username" required>
                    </div>
                    <div class="form-group">
                        <label for="emp-password">Password</label>
                        <input type="password" name="password" id="emp-password" class="form-control" placeholder="Minimum 6 characters" autocomplete="new-password" required>
                    </div>
                    <div class="form-group">
                        <label for="emp-code">Employee Code (Optional)</label>
                        <input type="text" name="emp_code" id="emp-code" class="form-control" placeholder="e.g. T-130556">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Employee</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Employee -->
    <div class="modal-overlay" id="modal-edit-employee">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Edit Employee Details</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-edit-employee" method="POST">
                <input type="hidden" name="employee_id" id="edit-emp-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-emp-name">Full Name</label>
                        <input type="text" name="name" id="edit-emp-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-emp-role">Role</label>
                        <input type="text" name="role" id="edit-emp-role" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-emp-email">Email Address</label>
                        <input type="email" name="email" id="edit-emp-email" class="form-control" autocomplete="username" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-emp-password">Password (Optional)</label>
                        <input type="password" name="password" id="edit-emp-password" class="form-control" placeholder="Leave blank to keep unchanged" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="edit-emp-code">Employee Code</label>
                        <input type="text" name="emp_code" id="edit-emp-code" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit-emp-status">Status</label>
                        <select name="status" id="edit-emp-status" class="form-control" required>
                            <option value="Approved">Approved</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 6: Add Note -->
    <div class="modal-overlay" id="modal-add-note">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Add New Note</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-new-note" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="note-title">Title</label>
                        <input type="text" name="title" id="note-title" class="form-control" placeholder="e.g. Server Credentials" required>
                    </div>
                    <div class="form-group">
                        <label for="note-content">Content</label>
                        <textarea name="content" id="note-content" class="form-control" rows="5" placeholder="Note text..." required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="note-category">Category</label>
                        <select name="category" id="note-category" class="form-control" required>
                            <option value="Work">Work</option>
                            <option value="Personal">Personal</option>
                            <option value="Archive">Archive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="note-tags">Tags (comma-separated, e.g. server, todo)</label>
                        <input type="text" name="tags" id="note-tags" class="form-control" placeholder="e.g. credentials, important">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Note</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 7: Edit Note -->
    <div class="modal-overlay" id="modal-edit-note">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Edit Note</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-edit-note" method="POST">
                <input type="hidden" name="id" id="edit-note-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-note-title">Title</label>
                        <input type="text" name="title" id="edit-note-title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-note-content">Content</label>
                        <textarea name="content" id="edit-note-content" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit-note-category">Category</label>
                        <select name="category" id="edit-note-category" class="form-control" required>
                            <option value="Work">Work</option>
                            <option value="Personal">Personal</option>
                            <option value="Archive">Archive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-note-tags">Tags (comma-separated)</label>
                        <input type="text" name="tags" id="edit-note-tags" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Note</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 8: Add Discussion Channel -->
    <div class="modal-overlay" id="modal-discussion">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Create New Discussion</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-new-discussion" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="disc-title">Discussion Title</label>
                        <input type="text" name="title" id="disc-title" class="form-control" placeholder="e.g. KILUATTI SERVICES" required>
                    </div>
                    <div class="form-group">
                        <label for="disc-type">Type</label>
                        <select name="type" id="disc-type" class="form-control" required>
                            <option value="General">General (Topic/Project)</option>
                            <option value="Task">Task Discussion</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Channel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 9: Upload Document -->
    <div class="modal-overlay" id="modal-upload-doc">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Upload New Document</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-upload-document" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="doc-file">Select File</label>
                        <input type="file" name="file" id="doc-file" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="doc-project">Associate Project (Optional)</label>
                        <select name="project_id" id="doc-project" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($dropdownProjects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>

    
    <!-- Modal: Edit Client -->
    <div class="modal-overlay" id="modal-client-edit">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Edit Client</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-edit-client" method="POST">
                <input type="hidden" name="id" id="edit-c-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-c-name">Client Name</label>
                        <input type="text" name="name" id="edit-c-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-c-email">Email</label>
                        <input type="email" name="email" id="edit-c-email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="edit-c-phone">Phone</label>
                        <input type="text" name="phone" id="edit-c-phone" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Modal: Client History Timeline -->
    <div class="modal-overlay" id="modal-client-history">
        <div class="modal-container" style="max-width: 700px; width: 90%;">
            <div class="modal-header">
                <h3>Client History & Activity</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body" style="padding: 20px; max-height: 500px; overflow-y: auto;">
                <!-- Tab headers for client info -->
                <div style="display: flex; gap: 20px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px;">
                    <span class="client-history-tab active" data-history-tab="details" style="font-size: 13px; font-weight: 600; color: #2563eb; border-bottom: 2px solid #2563eb; padding-bottom: 8px; cursor: pointer;">Details</span>
                    <span class="client-history-tab" data-history-tab="projects" style="font-size: 13px; font-weight: 600; color: #64748b; padding-bottom: 8px; cursor: pointer;">Projects</span>
                    <span class="client-history-tab" data-history-tab="activities" style="font-size: 13px; font-weight: 600; color: #64748b; padding-bottom: 8px; cursor: pointer;">Activities</span>
                    <span class="client-history-tab" data-history-tab="transactions" style="font-size: 13px; font-weight: 600; color: #64748b; padding-bottom: 8px; cursor: pointer;">Transactions</span>
                    <span class="client-history-tab" data-history-tab="timeline" style="font-size: 13px; font-weight: 600; color: #64748b; padding-bottom: 8px; cursor: pointer;">Timeline</span>
                </div>

                <!-- Tab content blocks -->
                <div id="client-history-content-details" class="client-history-tab-content active">
                    <!-- Loaded dynamically -->
                </div>
                <div id="client-history-content-projects" class="client-history-tab-content" style="display: none;">
                    <!-- Loaded dynamically -->
                </div>
                <div id="client-history-content-activities" class="client-history-tab-content" style="display: none;">
                    <!-- Loaded dynamically -->
                </div>
                <div id="client-history-content-transactions" class="client-history-tab-content" style="display: none;">
                    <!-- Loaded dynamically -->
                </div>
                <div id="client-history-content-timeline" class="client-history-tab-content" style="display: none;">
                    <!-- Loaded dynamically -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal: Start Direct Chat (Active Users List) -->
    <div class="modal-overlay" id="modal-start-direct-chat">
        <div class="modal-container" style="max-width: 440px;">
            <div class="modal-header">
                <h3>Start Direct Message</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <div class="form-group" style="margin-bottom: 15px;">
                    <div class="standard-search-wrapper" style="width: 100%;">
                        <i data-lucide="search"></i>
                        <input type="text" id="direct-chat-member-search" class="search-input" placeholder="Search team members..." style="background: #f8fafc;">
                    </div>
                </div>
                <div id="direct-chat-users-list" style="display: flex; flex-direction: column; gap: 10px; max-height: 280px; overflow-y: auto; padding-right: 4px;">
                    <!-- Dynamically loaded -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Modal: Discussion Members -->
    <div class="modal-overlay" id="modal-discussion-members">
        <div class="modal-container" style="max-width: 420px;">
            <div class="modal-header">
                <h3>Manage Members</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body" style="padding: 20px;">
                <h4 style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.5px;">Active Members</h4>
                <div id="discussion-active-members-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 180px; overflow-y: auto; margin-bottom: 20px; border: 1px solid var(--card-border); padding: 8px; border-radius: 6px;">
                    <!-- JS rendered -->
                </div>
                <h4 style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px; letter-spacing: 0.5px;">Add Team Members</h4>
                <div id="discussion-non-members-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 180px; overflow-y: auto; border: 1px solid var(--card-border); padding: 8px; border-radius: 6px;">
                    <!-- JS rendered -->
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Document Preview -->
    <div class="modal-overlay" id="modal-doc-preview">
        <div class="modal-container" style="max-width: 640px;">
            <div class="modal-header">
                <h3 id="doc-preview-title">Document Preview</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body" id="doc-preview-body" style="text-align: center; max-height: 500px; overflow-y: auto; padding: 20px;">
                <!-- JS rendered -->
            </div>
        </div>
    </div>

    <!-- Modal: Add Department -->
    <div class="modal-overlay" id="modal-department">
        <div class="modal-container" style="max-width: 440px;">
            <div class="modal-header">
                <h3>Add New Department</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-new-department" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="dept-name">Department Name</label>
                        <input type="text" name="name" id="dept-name" class="form-control" placeholder="e.g. Quality Assurance" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Department -->
    <div class="modal-overlay" id="modal-department-edit">
        <div class="modal-container" style="max-width: 440px;">
            <div class="modal-header">
                <h3>Edit Department</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-edit-department" method="POST">
                <input type="hidden" name="id" id="edit-dept-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-dept-name">Department Name</label>
                        <input type="text" name="name" id="edit-dept-name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: All Notifications -->
    <div class="modal-overlay" id="modal-all-notifications">
        <div class="modal-container" style="max-width: 600px; width: 90%;">
            <div class="modal-header" style="border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                <h3 style="font-size: 14px; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="bell" style="width: 16px; height: 16px; color: #3b82f6;"></i>
                    All Notifications
                </h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body" style="max-height: 400px; overflow-y: auto; padding: 20px 0;">
                <div style="display:flex; flex-direction:column; gap:12px; padding: 0 4px;">
                    <?php
                    $stmtAllNotif = $pdo->prepare("SELECT * FROM `notifications` WHERE org_id = ? ORDER BY id DESC");
                    $stmtAllNotif->execute([$meOrgId]);
                    $allNotifications = $stmtAllNotif->fetchAll(PDO::FETCH_ASSOC);
                    if (empty($allNotifications)) {
                        echo '
                        <div style="text-align:center; padding: 40px 20px; color:#64748b;">
                            <i data-lucide="bell-off" style="width:36px; height:36px; margin-bottom:10px; color:#94a3b8; display:inline-block;"></i>
                            <div style="font-size:13px; font-weight:600; color:#0f172a;">No notifications yet</div>
                            <div style="font-size:11px; color:#94a3b8; margin-top:2px;">We\'ll notify you when tasks or projects are updated.</div>
                        </div>';
                    } else {
                        foreach ($allNotifications as $n) {
                            $cat = $n['category'];
                            $bg = '#eff6ff'; $color = '#3b82f6'; $icon = 'info';
                            if ($cat === 'success') {
                                $bg = '#dcfce7'; $color = '#10b981'; $icon = 'check';
                            } else if ($cat === 'warning') {
                                $bg = '#fef3c7'; $color = '#f59e0b'; $icon = 'alert-triangle';
                            } else if ($cat === 'danger') {
                                $bg = '#fee2e2'; $color = '#ef4444'; $icon = 'alert-circle';
                            }
                            $timeStr = time_elapsed_string($n['created_at']);
                            $fullDateTime = date('d M Y, h:i A', strtotime($n['created_at']));
                            echo '
                            <div style="display:flex; align-items:flex-start; gap:12px; padding: 12px; border-radius: 8px; border: 1px solid #f1f5f9; background: #fafafa;">
                                <div style="width:30px; height:30px; background:\'' . $bg . '\'; color:\'' . $color . '\'; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                    <i data-lucide="' . $icon . '" style="width:14px; height:14px;"></i>
                                </div>
                                <div style="flex:1;">
                                    <div style="font-size:12px; font-weight:600; color:#0f172a; line-height: 1.4;">' . htmlspecialchars($n['message']) . '</div>
                                    <div style="font-size:10px; color:#94a3b8; margin-top:6px; display:flex; align-items:center; gap:8px;">
                                        <span>' . $timeStr . '</span>
                                        <span style="color:#cbd5e1;">•</span>
                                        <span>' . $fullDateTime . '</span>
                                    </div>
                                </div>
                            </div>';
                        }
                    }
                    ?>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #f1f5f9; padding-top: 12px; display: flex; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary modal-close-btn">Close</button>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <!-- Export DB variables for JS rendering -->
    <script>
        window.VYALA_TASKPAD_DASHBOARD_DATA = {
            monthlyCompleted: {
                labels: <?= json_encode($months) ?>,
                completed: <?= json_encode($chartCompleted) ?>,
                incomplete: <?= json_encode($chartIncomplete) ?>
            },
            priorities: {
                labels: ['Low', 'Medium', 'High'],
                data: [
                    <?= (int)$priorityCounts['Low'] ?>,
                    <?= (int)$priorityCounts['Medium'] ?>,
                    <?= (int)$priorityCounts['High'] ?>
                ]
            },
            tasks: <?= json_encode($tasksList) ?>,
            employees: <?= json_encode($dropdownEmployees) ?>,
            projects: <?= json_encode($dropdownProjects) ?>,
            clients: <?= json_encode($dropdownClients) ?>,
            meId: <?= json_encode($meId) ?>,
            meAvatar: <?= json_encode($meAvatar) ?>,
            serverDate: <?= json_encode(date('Y-m-d')) ?>
        };

        function deleteOrganization(orgId) {
            if (confirm('Are you absolutely sure you want to delete this organization and ALL its projects, tasks, employees, and data? This action CANNOT be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_org');
                formData.append('org_id', orgId);
                fetch('api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            }
        }
    </script>

    <!-- JS CDN Packages -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="app.js?v=<?= time() ?>"></script>
    <!-- PDF Export Script -->
    <script>
    async function downloadProjectPDF(projectId, projectName) {
        // Show loading state
        const btn = event.currentTarget;
        const origText = btn.innerHTML;
        btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Loading...';
        btn.disabled = true;

        try {
            const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
            const res = await fetch('api.php?action=get_project_pdf_data&project_id=' + projectId, {
                headers: token ? { 'Authorization': 'Bearer ' + token } : {}
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.message || 'Failed to load project data.');

            const p = data.project;
            const tasks = data.tasks || [];
            const members = data.members || [];

            // Build styled HTML report
            const completedTasks = tasks.filter(t => t.status === 'Completed').length;
            const completionPct = tasks.length > 0 ? Math.round((completedTasks / tasks.length) * 100) : 0;

            let taskRows = tasks.map(t =>
                `<tr>
                    <td>${t.title || ''}</td>
                    <td>${t.assigned_name || 'Unassigned'}</td>
                    <td><span style="background:${t.status === 'Completed' ? '#dcfce7' : '#fef3c7'}; color:${t.status === 'Completed' ? '#15803d' : '#d97706'}; padding:2px 8px; border-radius:4px; font-size:11px;">${t.status || ''}</span></td>
                    <td>${t.due_date ? new Date(t.due_date).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'}) : '-'}</td>
                </tr>`
            ).join('');

            let memberRows = members.map(m =>
                `<tr><td>${m.name || ''}</td><td>${m.role || ''}</td></tr>`
            ).join('');

            const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Project Report - ${p.name || projectName}</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap');
  body { font-family: 'Outfit', Arial, sans-serif; color: #0f172a; margin: 0; padding: 40px; background: #f8fafc; }
  .header { background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: 28px 32px; border-radius: 12px; margin-bottom: 24px; }
  .header h1 { margin: 0 0 4px; font-size: 22px; font-weight: 800; }
  .header p { margin: 0; color: rgba(255,255,255,0.65); font-size: 13px; }
  .badge { display: inline-block; background: #10b981; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; margin-left: 10px; }
  .section { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px 24px; margin-bottom: 18px; }
  .section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 14px; }
  .meta-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
  .meta-item label { display: block; font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
  .meta-item span { font-size: 13px; font-weight: 600; color: #0f172a; }
  .progress-bar { height: 10px; background: #e2e8f0; border-radius: 99px; overflow: hidden; margin: 10px 0; }
  .progress-fill { height: 100%; background: #10b981; border-radius: 99px; }
  table { width: 100%; border-collapse: collapse; font-size: 12px; }
  th { background: #f1f5f9; padding: 8px 12px; text-align: left; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0; }
  td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; }
  tr:last-child td { border-bottom: none; }
  .footer { text-align: center; font-size: 10px; color: #94a3b8; margin-top: 24px; }
  @media print { body { background: white; padding: 20px; } }
</style>
</head>
<body>
<div class="header">
  <h1>${p.name || projectName} <span class="badge">✓ Completed</span></h1>
  <p>Project Completion Report &nbsp;|&nbsp; Generated on ${new Date().toLocaleDateString('en-GB', {day:'2-digit',month:'long',year:'numeric'})}</p>
</div>
<div class="section">
  <div class="section-title">Project Details</div>
  <div class="meta-grid">
    <div class="meta-item"><label>Client</label><span>${p.client_name || 'N/A'}</span></div>
    <div class="meta-item"><label>Service Type</label><span>${p.service_type || '-'}</span></div>
    <div class="meta-item"><label>Location</label><span>${p.name || '-'}</span></div>
    <div class="meta-item"><label>Start Date</label><span>${p.created_at ? new Date(p.created_at).toLocaleDateString('en-GB') : '-'}</span></div>
    <div class="meta-item"><label>Target Date</label><span>${p.due_date ? new Date(p.due_date).toLocaleDateString('en-GB') : '-'}</span></div>
    <div class="meta-item"><label>Priority</label><span>${p.priority || 'Normal'}</span></div>
  </div>
</div>
<div class="section">
  <div class="section-title">Task Completion Summary</div>
  <div style="display:flex; justify-content:space-between; align-items:center; font-size:13px; font-weight:600;">
    <span>Overall Progress</span><span style="color:#10b981;">${completedTasks}/${tasks.length} tasks completed (${completionPct}%)</span>
  </div>
  <div class="progress-bar"><div class="progress-fill" style="width:${completionPct}%;"></div></div>
</div>
${members.length > 0 ? `<div class="section"><div class="section-title">Team Members</div><table><thead><tr><th>Name</th><th>Role</th></tr></thead><tbody>${memberRows}</tbody></table></div>` : ''}
${tasks.length > 0 ? `<div class="section"><div class="section-title">Task Details</div><table><thead><tr><th>Task</th><th>Assigned To</th><th>Status</th><th>Due Date</th></tr></thead><tbody>${taskRows}</tbody></table></div>` : ''}
${p.description ? `<div class="section"><div class="section-title">Project Notes</div><p style="font-size:13px; color:#475569; margin:0;">${p.description}</p></div>` : ''}
<div class="footer">This report was automatically generated by the Task Management System.</div>
</body></html>`;

            const win = window.open('', '_blank');
            win.document.write(html);
            win.document.close();
            setTimeout(() => { win.print(); }, 600);
        } catch (err) {
            alert('PDF Error: ' + err.message);
        } finally {
            btn.innerHTML = origText;
            btn.disabled = false;
        }
    }
    </script>

    <!-- ============================================================
         PROJECT DETAILS MODAL
         ============================================================ -->
    <div id="projectDetailsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.65); z-index:10000; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
        <div style="background:#fff; border-radius:16px; width:94%; max-width:860px; max-height:92vh; display:flex; flex-direction:column; box-shadow:0 25px 60px rgba(0,0,0,0.3); overflow:hidden;">
            <!-- Modal Header -->
            <div style="background:linear-gradient(135deg,#1e3a5f,#2563eb); padding:20px 24px; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
                <div>
                    <div id="pdm-title" style="font-size:18px; font-weight:800; color:#fff; margin-bottom:2px;"></div>
                    <div id="pdm-org" style="font-size:12px; color:rgba(255,255,255,0.7);"></div>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <span id="pdm-status-badge" style="font-size:11px; font-weight:700; padding:4px 12px; border-radius:99px; background:rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.3);"></span>
                    <button onclick="closeProjectDetailsModal()" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); color:#fff; border-radius:8px; width:32px; height:32px; cursor:pointer; font-size:18px; line-height:1; display:flex; align-items:center; justify-content:center;">&times;</button>
                </div>
            </div>

            <!-- Loader -->
            <div id="pdm-loader" style="text-align:center; padding:60px 40px; color:#64748b;">
                <div style="font-size:32px; margin-bottom:12px;">⏳</div>
                <div style="font-weight:600;">Loading project details...</div>
            </div>

            <!-- Content -->
            <div id="pdm-content" style="display:none; overflow-y:auto; flex:1; padding:0;">

                <!-- Meta Info Grid -->
                <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:0; border-bottom:1px solid #e2e8f0;">
                    <div style="padding:14px 18px; text-align:center; border-right:1px solid #e2e8f0;">
                        <div style="font-size:10px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Client</div>
                        <div id="pdm-client" style="font-size:13px; font-weight:700; color:#0f172a;"></div>
                    </div>
                    <div style="padding:14px 18px; text-align:center; border-right:1px solid #e2e8f0;">
                        <div style="font-size:10px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Priority</div>
                        <div id="pdm-priority" style="font-size:13px; font-weight:700; color:#0f172a;"></div>
                    </div>
                    <div style="padding:14px 18px; text-align:center; border-right:1px solid #e2e8f0;">
                        <div style="font-size:10px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Start Date</div>
                        <div id="pdm-start" style="font-size:13px; font-weight:700; color:#0f172a;"></div>
                    </div>
                    <div style="padding:14px 18px; text-align:center;">
                        <div style="font-size:10px; color:#94a3b8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">Due Date</div>
                        <div id="pdm-due" style="font-size:13px; font-weight:700; color:#0f172a;"></div>
                    </div>
                </div>

                <div style="padding:20px 24px; display:flex; flex-direction:column; gap:20px;">

                    <!-- Progress -->
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <div style="font-size:13px; font-weight:700; color:#0f172a;">Overall Progress</div>
                            <div id="pdm-progress-text" style="font-size:13px; font-weight:800; color:#10b981;"></div>
                        </div>
                        <div style="height:10px; background:#e2e8f0; border-radius:99px; overflow:hidden;">
                            <div id="pdm-progress-bar" style="height:100%; background:linear-gradient(90deg,#10b981,#34d399); border-radius:99px; transition:width 0.6s ease;"></div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div id="pdm-desc-wrapper" style="display:none;">
                        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; margin-bottom:8px;">Description</div>
                        <div id="pdm-desc" style="font-size:13px; color:#334155; line-height:1.6; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px;"></div>
                    </div>

                    <!-- Created by -->
                    <div style="font-size:12px; color:#64748b;">Created by: <strong id="pdm-creator" style="color:#0f172a;"></strong></div>

                    <!-- Team Members -->
                    <div>
                        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; margin-bottom:10px;">Team Members</div>
                        <div id="pdm-members" style="display:flex; flex-wrap:wrap; gap:8px;"></div>
                    </div>

                    <!-- Tasks -->
                    <div>
                        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; margin-bottom:10px;">Tasks</div>
                        <div id="pdm-tasks" style="display:flex; flex-direction:column; gap:6px;"></div>
                    </div>

                    <!-- Timeline/Milestones -->
                    <div id="pdm-milestones-wrapper" style="display:none;">
                        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; margin-bottom:10px;">Timeline / Milestones</div>
                        <div id="pdm-milestones" style="display:flex; flex-direction:column; gap:8px;"></div>
                    </div>

                    <!-- Activity History -->
                    <div id="pdm-activity-wrapper" style="display:none;">
                        <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:#64748b; margin-bottom:10px;">Activity History</div>
                        <div id="pdm-activities" style="display:flex; flex-direction:column; gap:6px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // ================================================================
    // PROJECT DETAILS MODAL
    // ================================================================
    function openProjectDetailsModal(projectId) {
        const modal = document.getElementById('projectDetailsModal');
        const loader = document.getElementById('pdm-loader');
        const content = document.getElementById('pdm-content');

        modal.style.display = 'flex';
        loader.style.display = 'block';
        content.style.display = 'none';

        fetch(`api.php?action=get_project_details&project_id=${projectId}`)
        .then(r => r.json())
        .then(data => {
            loader.style.display = 'none';
            if (!data.success) { alert('Error: ' + data.message); return; }

            const p = data.project;
            const tasks = data.tasks || [];
            const members = data.members || [];
            const activities = data.activities || [];
            const milestones = data.milestones || [];

            // Header
            document.getElementById('pdm-title').textContent = p.name || '—';
            document.getElementById('pdm-org').textContent = p.org_name || '';
            document.getElementById('pdm-status-badge').textContent = p.status || '—';

            // Meta
            document.getElementById('pdm-client').textContent = p.client_name || 'None';
            document.getElementById('pdm-priority').textContent = p.priority || '—';
            document.getElementById('pdm-start').textContent = p.created_at ? new Date(p.created_at).toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'}) : '—';
            document.getElementById('pdm-due').textContent = p.due_date ? new Date(p.due_date).toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'}) : '—';

            // Progress
            const pct = data.progress || 0;
            document.getElementById('pdm-progress-text').textContent = `${data.completed_tasks}/${data.total_tasks} (${pct}%)`;
            document.getElementById('pdm-progress-bar').style.width = pct + '%';

            // Description
            if (p.description) {
                document.getElementById('pdm-desc').textContent = p.description;
                document.getElementById('pdm-desc-wrapper').style.display = 'block';
            }

            // Creator
            document.getElementById('pdm-creator').textContent = p.created_by_name || 'Unknown';

            // Members
            const membersEl = document.getElementById('pdm-members');
            if (members.length > 0) {
                membersEl.innerHTML = members.map(m => {
                    const initials = (m.avatar || m.name.split(' ').map(x=>x[0]).join('').slice(0,2)).toUpperCase();
                    return `<div style="display:flex; align-items:center; gap:8px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:24px; padding:5px 12px 5px 5px;">
                        <div style="width:28px; height:28px; border-radius:50%; background:linear-gradient(135deg,#2563eb,#6366f1); color:#fff; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700;">${initials}</div>
                        <div>
                            <div style="font-size:12px; font-weight:700; color:#0f172a;">${m.name}</div>
                            <div style="font-size:10px; color:#64748b;">${m.role}</div>
                        </div>
                    </div>`;
                }).join('');
            } else {
                membersEl.innerHTML = '<span style="font-size:12px; color:#94a3b8;">No team members assigned.</span>';
            }

            // Tasks
            const statusColors = { 'Pending': '#a855f7', 'Todo': '#3b82f6', 'In Progress': '#f59e0b', 'Completed': '#10b981', 'In Review': '#8b5cf6' };
            const priorityColors = { 'High': '#ef4444', 'Medium': '#f59e0b', 'Low': '#22c55e' };
            const tasksEl = document.getElementById('pdm-tasks');
            if (tasks.length > 0) {
                tasksEl.innerHTML = tasks.map(t => {
                    const sc = statusColors[t.status] || '#64748b';
                    const pc = priorityColors[t.priority] || '#64748b';
                    const due = t.due_date ? new Date(t.due_date).toLocaleDateString('en-GB',{day:'2-digit',month:'short'}) : '—';
                    return `<div style="display:flex; align-items:center; gap:10px; padding:8px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                        <div style="width:8px; height:8px; border-radius:50%; background:${sc}; flex-shrink:0;"></div>
                        <div style="flex:1; font-size:12px; font-weight:600; color:#0f172a;">${t.title}</div>
                        <div style="font-size:10px; color:#64748b;">👤 ${t.assigned_name || '—'}</div>
                        <div style="font-size:10px; background:${sc}22; color:${sc}; padding:2px 7px; border-radius:4px; font-weight:600;">${t.status}</div>
                        <div style="font-size:10px; background:${pc}22; color:${pc}; padding:2px 7px; border-radius:4px; font-weight:600;">${t.priority}</div>
                        <div style="font-size:10px; color:#94a3b8; white-space:nowrap;">📅 ${due}</div>
                    </div>`;
                }).join('');
            } else {
                tasksEl.innerHTML = '<div style="font-size:12px; color:#94a3b8; text-align:center; padding:20px;">No tasks found for this project.</div>';
            }

            // Milestones
            if (milestones.length > 0) {
                document.getElementById('pdm-milestones-wrapper').style.display = 'block';
                document.getElementById('pdm-milestones').innerHTML = milestones.map((m, i) => {
                    const sc = statusColors[m.status] || '#64748b';
                    return `<div style="display:flex; align-items:center; gap:12px; padding:8px 14px; border:1px solid #e2e8f0; border-radius:8px; background:#fff;">
                        <div style="width:24px; height:24px; border-radius:50%; background:${sc}; color:#fff; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; flex-shrink:0;">${i+1}</div>
                        <div style="flex:1; font-size:12px; font-weight:600; color:#0f172a;">${m.title}</div>
                        <div style="font-size:10px; color:#64748b;">👤 ${m.assignee || '—'}</div>
                        <div style="font-size:10px; background:${sc}22; color:${sc}; padding:2px 7px; border-radius:4px; font-weight:600;">${m.status}</div>
                        <div style="font-size:10px; color:#94a3b8;">📅 ${m.due_date || '—'}</div>
                    </div>`;
                }).join('');
            }

            // Activities
            if (activities.length > 0) {
                document.getElementById('pdm-activity-wrapper').style.display = 'block';
                document.getElementById('pdm-activities').innerHTML = activities.map(a => `
                    <div style="display:flex; align-items:flex-start; gap:10px; padding:8px 12px; background:#f8fafc; border-radius:6px;">
                        <div style="width:8px; height:8px; border-radius:50%; background:#6366f1; flex-shrink:0; margin-top:4px;"></div>
                        <div>
                            <div style="font-size:12px; font-weight:600; color:#0f172a;">${a.action}</div>
                            <div style="font-size:10px; color:#94a3b8;">${a.logged_date || ''}</div>
                        </div>
                    </div>`).join('');
            }

            content.style.display = 'block';
        })
        .catch(err => {
            loader.innerHTML = '<span style="color:#ef4444;">Error loading project. Please try again.</span>';
            console.error(err);
        });
    }

    function closeProjectDetailsModal() {
        const modal = document.getElementById('projectDetailsModal');
        modal.style.display = 'none';
        // Reset
        document.getElementById('pdm-content').style.display = 'none';
        document.getElementById('pdm-loader').style.display = 'block';
        document.getElementById('pdm-desc-wrapper').style.display = 'none';
        document.getElementById('pdm-milestones-wrapper').style.display = 'none';
        document.getElementById('pdm-activity-wrapper').style.display = 'none';
    }

    // Close on outside click
    document.getElementById('projectDetailsModal').addEventListener('click', function(e) {
        if (e.target === this) closeProjectDetailsModal();
    });

    // ================================================================
    // PROJECT CARD CLICK → OPEN DETAILS
    // ================================================================
    document.addEventListener('click', function(e) {
        // Project card click (not on interactive controls)
        const card = e.target.closest('.project-grid-card');
        if (card) {
            const clickedOnControl = e.target.closest('select, button, a, input');
            if (!clickedOnControl) {
                const projectId = card.getAttribute('data-id');
                if (projectId) {
                    openProjectDetailsModal(projectId);
                }
            }
        }
    });

    // ================================================================
    // PROJECT STATUS DROPDOWN CHANGE (Admin/Project Lead only)
    // ================================================================
    document.addEventListener('change', function(e) {
        const sel = e.target.closest('.project-status-select');
        if (!sel) return;
        const projectId = sel.getAttribute('data-id');
        const newStatus = sel.value;
        if (!projectId || !newStatus) return;

        const fd = new FormData();
        fd.append('action', 'update_project_status');
        fd.append('id', projectId);
        fd.append('status', newStatus);

        fetch('api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Update card data-status
                const card = sel.closest('.project-grid-card');
                if (card) card.setAttribute('data-status', newStatus);
            } else {
                alert('Failed to update: ' + res.message);
                // Revert
                location.reload();
            }
        })
        .catch(() => { alert('Network error.'); });
    });

    // ================================================================
    // PROJECT DELETE BUTTON (Admin/Project Lead only)
    // ================================================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-delete-project');
        if (!btn) return;
        e.stopPropagation();
        const projectId = btn.getAttribute('data-id');
        if (!projectId) return;

        if (!confirm('Are you sure you want to delete this project and ALL its tasks? This cannot be undone.')) return;

        const fd = new FormData();
        fd.append('action', 'delete_project');
        fd.append('id', projectId);

        fetch('api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                // Remove card from DOM
                const card = btn.closest('.project-grid-card');
                if (card) card.remove();
            } else {
                alert('Delete failed: ' + res.message);
            }
        })
        .catch(() => { alert('Network error while deleting project.'); });
    });
    </script>

    <!-- E2EE Encryption Logic -->
    <script src="e2ee.js?v=<?= time() ?>"></script>
    <script>
        window.vyalaMeId = <?= $jwtPayload['id'] ?>;
    </script>
</body>

</html>



