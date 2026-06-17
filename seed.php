<?php
// seed.php
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("USE `vyala_taskpad`");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DROP TABLE IF EXISTS `timesheets`, `tasks`, `employees`, `projects`, `clients`, `discussions`, `activities`, `pin_notes`, `notifications`;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
} catch (PDOException $e) {
    // Database or tables might not exist yet, that's fine
}

require_once 'db.php';

try {
    echo "Vyala Task Pad database cleared and recreated.<br>\n";

    // 1. Seed Clients
    $clients = [
        ["Selvakumar J (Self)", "selva@vyala.com", "+91 99999 12345"],
        ["RSK Approvals Co", "info@rskapprovals.com", "+91 98765 43210"],
        ["Local Municipality", "admin@townplanning.gov", "+91 44223 11111"]
    ];
    $clientStmt = $pdo->prepare("INSERT INTO `clients` (`name`, `email`, `phone`) VALUES (?, ?, ?)");
    foreach ($clients as $c) {
        $clientStmt->execute($c);
    }
    $clientIds = $pdo->query("SELECT id FROM `clients`")->fetchAll(PDO::FETCH_COLUMN);
    echo "Clients seeded.<br>\n";

    // 2. Seed Employees with Passwords and codes
    $employees = [
        ["SELVAKUMAR J", "Project Lead", "selvakumar@vyalasoftware.com", password_hash("selva123", PASSWORD_DEFAULT), "T-130555", "SJ"],
        ["Dhanapathi R", "Developer", "dhanapathi@vyalasoftware.com", password_hash("dhanapathi123", PASSWORD_DEFAULT), "T-130551", "DR"],
        ["Dinakaran S", "Designer", "dinakaran@vyalasoftware.com", password_hash("dinakaran123", PASSWORD_DEFAULT), "T-130552", "DS"],
        ["Kalpana G", "QA Engineer", "kalpana@vyalasoftware.com", password_hash("kalpana123", PASSWORD_DEFAULT), "T-130553", "KG"]
    ];
    $empStmt = $pdo->prepare("INSERT INTO `employees` (`name`, `role`, `email`, `password`, `emp_code`, `avatar`) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($employees as $e) {
        $empStmt->execute($e);
    }
    $employeeIds = $pdo->query("SELECT id, name FROM `employees`")->fetchAll(PDO::FETCH_ASSOC);
    
    // Map employee IDs for easy reference
    $empMap = [];
    foreach ($employeeIds as $emp) {
        $empMap[$emp['name']] = $emp['id'];
    }
    echo "Employees seeded.<br>\n";

    // 3. Seed Projects (Exactly matching screenshot recent list and status text)
    // Projects: THAMARAI..., MAMAND..., PAIYUR UAL..., KIZHMATTAI UAL... etc.
    $projects = [
        ["PULAVANPADI UAL", "Agriculture land survey approvals", $clientIds[1], "Active"],
        ["RERA APPROVAL SERVICES", "RERA registration approvals", $clientIds[1], "Active"],
        ["KEEZHATTUR UAL", "Local authority certifications", $clientIds[2], "Active"],
        ["Other Works", "General office works and tasks mapping", $clientIds[1], "Active"],
        ["PERUMPADI SERVICES", "Site plans and draft drawing blueprints", $clientIds[1], "Active"],
        ["KIZHMATTAI UAL", "Land subdivision approval requests", $clientIds[2], "Active"],
        ["THELLAR BASKARAN UAL", "Water supply clearance tracking", $clientIds[2], "Active"],
        ["KASTAMPADI UAL", "Structural stability audits", $clientIds[2], "Active"],
        ["ERIPATTU", "NOC clearances and surveys log", $clientIds[2], "Active"]
    ];

    $projStmt = $pdo->prepare("INSERT INTO `projects` (`name`, `description`, `client_id`, `status`, `created_by`, `assigned_to`) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($projects as $p) {
        $projStmt->execute([$p[0], $p[1], $p[2], $p[3], $empMap['SELVAKUMAR J'], $empMap['SELVAKUMAR J']]);
    }
    $projectIds = $pdo->query("SELECT id, name FROM `projects`")->fetchAll(PDO::FETCH_COLUMN);
    echo "Projects seeded.<br>\n";

    // 4. Seed EXACTLY 7 Tasks
    // - Low priority: 7 (matching Priority Task Summary: Low 7, Medium 0, High 0)
    // - Assigned to me (Selvakumar J): 3 (matching "Assigned to me: 3" card)
    // - Due today: 0
    // - Past due tasks: 2 (matching "Past due tasks: 2" card)
    // - Completed: 3
    // - Incomplete: 4 (Dhanapathi R: 0, Dinakaran S: 4, Kalpana G: 0)
    // Let's create:
    // Task 1: Title: "FOLLOW UP", Completed, Low priority, Assigned to Selvakumar J. Project: MAMANDUR SERVICES (completed tasks)
    // Task 2: Title: "FOLLOW UP", Completed, Low priority, Assigned to Selvakumar J. Project: MAMANDUR SERVICES
    // Task 3: Title: "Approval order", Completed, Low priority, Assigned to Selvakumar J. Project: MAMANDUR SERVICES
    // Task 4: Title: "VPC Route sync", Incomplete (Todo), Low priority, Assigned to Dinakaran S, Due: 2026-05-10 (Past due 1). Project: THAMARAI SERVICES
    // Task 5: Title: "Security audits", Incomplete (In Progress), Low priority, Assigned to Dinakaran S, Due: 2026-06-01 (Past due 2). Project: THAMARAI SERVICES
    // Task 6: Title: "Subdivision checks", Incomplete (In Review), Low priority, Assigned to Dinakaran S, Due: 2026-06-20 (Not past due). Project: PAIYUR UAL
    // Task 7: Title: "Draft blueprint checks", Incomplete (Todo), Low priority, Assigned to Dinakaran S, Due: 2026-06-25 (Not past due). Project: PAIYUR UAL

    $tasks = [
        // PULAVANPADI UAL (Project 0): 2 Completed
        ["Land survey preparation", "Verify layout parameters", $projectIds[0], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-12"],
        ["Boundary confirmation", "Verify boundary maps", $projectIds[0], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-14"],
        
        // RERA APPROVAL SERVICES (Project 1): 2 Completed
        ["RERA documentation check", "Verify checklist alignment", $projectIds[1], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-10"],
        ["NOC draft review", "Prepare NOC draft blueprint", $projectIds[1], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-12"],
        
        // Other Works (Project 3): 3 Completed
        ["Weekly progress reports review", "Compile timesheets", $projectIds[3], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-14"],
        ["Client phone calls checks", "Verify contact details", $projectIds[3], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-15"],
        ["Draft checklist approvals", "Confirm layout codes", $projectIds[3], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-15"],
        
        // PERUMPADI SERVICES (Project 4): 6 Completed
        ["Site draft preparation", "Site measurements", $projectIds[4], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-10"],
        ["Survey alignments check", "Confirm boundaries", $projectIds[4], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-11"],
        ["AutoCAD layouts layout", "Prepare AutoCAD draft", $projectIds[4], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-12"],
        ["Municipal approvals log", "Submit files log", $projectIds[4], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-13"],
        ["NOC coordination task", "Contact municipal lead", $projectIds[4], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-14"],
        ["Final blueprint review", "Review structural design", $projectIds[4], $empMap['SELVAKUMAR J'], "Low", "Completed", "2026-06-15"],
        // Dinakaran S Incomplete Tasks (4 tasks, matching team widget count of 4)
        ["VPC Route sync", "Verify route maps sync", $projectIds[2], $empMap['Dinakaran S'], "Low", "Todo", "2026-05-10"],
        ["Security audits check", "Perform layout security check", $projectIds[6], $empMap['Dinakaran S'], "Low", "In Progress", "2026-06-01"],
        ["Subdivision checks verification", "Validate subdivisions blueprint", $projectIds[7], $empMap['Dinakaran S'], "Low", "In Review", "2026-06-20"],
        ["Draft blueprint validation", "Verify design draft", $projectIds[8], $empMap['Dinakaran S'], "Low", "Todo", "2026-06-25"]
    ];

    $taskStmt = $pdo->prepare("INSERT INTO `tasks` (`title`, `description`, `project_id`, `assigned_to`, `priority`, `status`, `due_date`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($tasks as $t) {
        $taskStmt->execute($t);
    }
    $taskIds = $pdo->query("SELECT id FROM `tasks`")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tasks seeded (13 completed tasks assigned to SELVAKUMAR J, 4 incomplete tasks assigned to Dinakaran S).<br>\n";

    // 5. Seed Timesheets
    $timesheets = [
        [$taskIds[0], $empMap['SELVAKUMAR J'], 4.5, "2026-06-12", "Completed initial follow-ups on Mamandur services"],
        [$taskIds[1], $empMap['SELVAKUMAR J'], 3.0, "2026-06-14", "Updated layout drawings specifications"],
        [$taskIds[2], $empMap['SELVAKUMAR J'], 6.5, "2026-06-10", "Prepared clearance records files"]
    ];
    $tsStmt = $pdo->prepare("INSERT INTO `timesheets` (`task_id`, `employee_id`, `hours`, `date`, `description`) VALUES (?, ?, ?, ?, ?)");
    foreach ($timesheets as $ts) {
        $tsStmt->execute($ts);
    }
    echo "Timesheets seeded.<br>\n";

    // 6. Seed Discussions (Matching Screenshot 3 Recent Discussion panel)
    $discussions = [
        ["General", "KILUATTI SERVICES", "FORM-C.pdf", "pdf", "2026"],
        ["General", "KIZHMATTAI", "kilmattai drawing.dwg", "dwg", "26 Feb, 2026"],
        ["Task", "THELLAR BASKARAN", null, null, "25 Feb, 2026"],
        ["Task", "AGARAKORAKOTTAI UAL", null, null, "25 Feb, 2026"],
        ["General", "PULAVANPADI UAL", null, null, "25 Feb, 2026"],
        ["Task", "KASTAMPADI UAL", null, null, "25 Feb, 2026"],
        ["General", "ERIPATTU", "eripattu manual ec.pdf", "pdf", "17 Feb, 2026"],
        ["Task", "HARINI K", null, null, "16 Feb, 2026"]
    ];

    $discStmt = $pdo->prepare("INSERT INTO `discussions` (`type`, `title`, `attachment_name`, `attachment_type`, `date_logged`) VALUES (?, ?, ?, ?, ?)");
    foreach ($discussions as $d) {
        $discStmt->execute($d);
    }
    echo "Discussions seeded.<br>\n";

    // 7. Seed Activities (Matching Screenshot 4 Activity list)
    $activities = [
        ["Mark as completed Task : \"FOLLOW UP\"", "Mark as completed Task", "RSK APPROVAL SERVICES", "20 Apr, 2026 2:33 PM"],
        ["Mark as completed Task : \"FOLLOW UP\"", "Mark as completed Task", "RSK APPROVAL SERVICES", "20 Apr, 2026 2:33 PM"],
        ["Mark as completed Task : \"Approval order \"", "Mark as completed Task", "RSK APPROVAL SERVICES", "20 Apr, 2026 1:11 PM"]
    ];

    $actStmt = $pdo->prepare("INSERT INTO `activities` (`action`, `details`, `project_name`, `logged_date`) VALUES (?, ?, ?, ?)");
    foreach ($activities as $a) {
        $actStmt->execute($a);
    }
    echo "Activities seeded.<br>\n";

    // Seed pin_notes
    $pdo->exec("DELETE FROM `pin_notes`;");
    $noteStmt = $pdo->prepare("INSERT INTO `pin_notes` (`title`, `content`) VALUES (?, ?)");
    $noteStmt->execute(["Note Dinakaran", "Install Vyala Task Pad app in mobile and pc\n\nOk"]);
    echo "Pin notes seeded.<br>\n";

    // 8. Seed Notifications (Red badge notifications)
    // Bell notification count = 37, so let's insert 37 rows, or just dynamic badge setting in code. Let's seed 5 clean notification logs.
    $notifications = [
        ["37 new actions pending review on RSK Approval Services", "danger"],
        ["Task 'Approval order' status completed", "success"]
    ];
    $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`message`, `category`) VALUES (?, ?)");
    foreach ($notifications as $n) {
        $notifStmt->execute($n);
    }
    echo "Notifications seeded.<br>\n";
    echo "<h2>Vyala Task Pad Database seeded successfully with screenshot data!</h2>\n";

} catch (PDOException $e) {
    die("Database seeding failed: " . $e->getMessage());
}
?>

