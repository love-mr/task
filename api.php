<?php
// api.php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata');
require_once 'db.php';
require_once 'jwt.php';

// Verify JWT token from cookie
$jwtToken = $_COOKIE['vyala_taskpad_jwt_token'] ?? '';
$jwtPayload = verify_jwt($jwtToken);
if (!$jwtPayload) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized request']);
    exit;
}

$response = ['success' => false, 'message' => 'Invalid action'];

$action = $_REQUEST['action'] ?? '';

try {
    if ($action === 'create_task') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $projectId = (int) ($_POST['project_id'] ?? 0);
            $assignedTo = (int) ($_POST['assigned_to'] ?? 0);
            $priority = trim($_POST['priority'] ?? 'Low');
            $dueDate = trim($_POST['due_date'] ?? '');
            $meOrgId = (int) ($jwtPayload['org_id'] ?? 1);

            if (empty($title) || !$projectId || !$assignedTo || empty($dueDate)) {
                throw new Exception("Title, Project, Assignee, and Due Date are required.");
            }

            // Insert task
            $stmt = $pdo->prepare("INSERT INTO `tasks` (`title`, `description`, `project_id`, `assigned_to`, `priority`, `status`, `due_date`, `org_id`) VALUES (?, ?, ?, ?, ?, 'Todo', ?, ?)");
            $stmt->execute([$title, $description, $projectId, $assignedTo, $priority, $dueDate, $meOrgId]);
            $taskId = $pdo->lastInsertId();

            // Log activity
            $projName = $pdo->query("SELECT name FROM projects WHERE id = $projectId")->fetchColumn() ?: "Project";
            $actStmt = $pdo->prepare("INSERT INTO `activities` (`action`, `details`, `project_name`, `logged_date`, `org_id`) VALUES (?, ?, ?, ?, ?)");
            $actStmt->execute(["Created Task : \"$title\"", "Create Task", $projName, date('d M, Y g:i A'), $meOrgId]);

            // Insert notification
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`message`, `category`, `org_id`) VALUES (?, ?, ?)");
            $notifStmt->execute(["New task '$title' assigned under $projName", 'info', $meOrgId]);

            $response = [
                'success' => true,
                'message' => 'Task created successfully'
            ];
        } else if ($action === 'create_project') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $clientId = (int) ($_POST['client_id'] ?? 0);
            $priority = trim($_POST['priority'] ?? 'Medium');
            $assignedTo = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? (int)$_POST['assigned_to'] : null;
            $meId = $jwtPayload['id'];
            $meOrgId = (int) ($jwtPayload['org_id'] ?? 1);

            if (empty($name) || !$clientId) {
                throw new Exception("Project Name and Client are required.");
            }

            // Insert project
            $stmt = $pdo->prepare("INSERT INTO `projects` (`name`, `description`, `client_id`, `status`, `priority`, `created_by`, `assigned_to`, `org_id`) VALUES (?, ?, ?, 'Active', ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $clientId, $priority, $meId, $assignedTo, $meOrgId]);
            $newProjectId = (int) $pdo->lastInsertId();

            // Insert project team members
            $memberIds = isset($_POST['member_ids']) ? (array) $_POST['member_ids'] : [];
            if (!empty($memberIds) && $newProjectId > 0) {
                $memberStmt = $pdo->prepare("INSERT IGNORE INTO `project_members` (`project_id`, `employee_id`) VALUES (?, ?)");
                foreach ($memberIds as $mId) {
                    $mId = (int) $mId;
                    if ($mId > 0) {
                        $memberStmt->execute([$newProjectId, $mId]);
                    }
                }
            }


            // Log activity
            $actStmt = $pdo->prepare("INSERT INTO `activities` (`action`, `details`, `project_name`, `logged_date`, `org_id`) VALUES (?, ?, ?, ?, ?)");
            $actStmt->execute(["Launched Project : \"$name\"", "Create Project", $name, date('d M, Y g:i A'), $meOrgId]);

            // Insert notification
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`message`, `category`, `org_id`) VALUES (?, ?, ?)");
            $notifStmt->execute(["New project '$name' has been launched", 'success', $meOrgId]);

            $response = [
                'success' => true,
                'message' => 'Project created successfully'
            ];
        } else if ($action === 'create_client') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $meOrgId = (int) ($jwtPayload['org_id'] ?? 1);

            if (empty($name) || empty($phone)) {
                throw new Exception("Client Name and Phone are required.");
            }

            // Insert client
            $stmt = $pdo->prepare("INSERT INTO `clients` (`name`, `email`, `phone`, `org_id`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email ?: null, $phone, $meOrgId]);

            // Insert notification
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`message`, `category`, `org_id`) VALUES (?, ?, ?)");
            $notifStmt->execute(["Client '$name' has been added", 'success', $meOrgId]);

            $response = [
                'success' => true,
                'message' => 'Client added successfully'
            ];
        } else if ($action === 'update_client') {
            $id = (int) ($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if (!$id || empty($name) || empty($phone)) {
                throw new Exception("ID, Client Name, and Phone are required.");
            }

            $stmt = $pdo->prepare("UPDATE `clients` SET `name` = ?, `email` = ?, `phone` = ? WHERE `id` = ?");
            $stmt->execute([$name, $email ?: null, $phone, $id]);

            $response = [
                'success' => true,
                'message' => 'Client updated successfully'
            ];
        } else if ($action === 'delete_client') {
            $id = (int) ($_POST['id'] ?? 0);

            if (!$id) {
                throw new Exception("Invalid Client ID.");
            }

            // Clear project FK reference
            $stmtClear = $pdo->prepare("UPDATE `projects` SET `client_id` = NULL WHERE `client_id` = ?");
            $stmtClear->execute([$id]);

            $stmt = $pdo->prepare("DELETE FROM `clients` WHERE `id` = ?");
            $stmt->execute([$id]);

            $response = [
                'success' => true,
                'message' => 'Client deleted successfully'
            ];
        } else if ($action === 'create_timesheet') {
            $taskId = (int) ($_POST['task_id'] ?? 0);
            $employeeId = (int) ($_POST['employee_id'] ?? 0);
            $hours = (float) ($_POST['hours'] ?? 0);
            $date = trim($_POST['date'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!$taskId || !$employeeId || $hours <= 0 || empty($date)) {
                throw new Exception("Task, Employee, valid Hours, and Date are required.");
            }

            // Insert timesheet
            $stmt = $pdo->prepare("INSERT INTO `timesheets` (`task_id`, `employee_id`, `hours`, `date`, `description`) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$taskId, $employeeId, $hours, $date, $description]);

            // Log activity
            $taskTitle = $pdo->query("SELECT title FROM tasks WHERE id = $taskId")->fetchColumn() ?: "Task";
            $projId = $pdo->query("SELECT project_id FROM tasks WHERE id = $taskId")->fetchColumn() ?: 0;
            $projName = $pdo->query("SELECT name FROM projects WHERE id = $projId")->fetchColumn() ?: "Project";

            $actStmt = $pdo->prepare("INSERT INTO `activities` (`action`, `details`, `project_name`, `logged_date`) VALUES (?, ?, ?, ?)");
            $actStmt->execute(["Logged work hours : \"$taskTitle\" ($hours hrs)", "Logged Hours", $projName, date('d M, Y g:i A')]);

            $response = [
                'success' => true,
                'message' => 'Work hours logged successfully'
            ];
        } else if ($action === 'create_employee') {
            $name = trim($_POST['name'] ?? '');
            $role = trim($_POST['role'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $empCode = trim($_POST['emp_code'] ?? '');

            if (empty($name) || empty($role) || empty($email) || empty($password)) {
                throw new Exception("Name, Role, Email, and Password are required.");
            }

            if (!empty($empCode)) {
                // Check unique employee code
                $checkCode = $pdo->prepare("SELECT COUNT(*) FROM `employees` WHERE `emp_code` = ?");
                $checkCode->execute([$empCode]);
                if ($checkCode->fetchColumn() > 0) {
                    throw new Exception("Employee ID '$empCode' is already in use.");
                }
            } else {
                // Automatically generate unique employee code sequentially (e.g. T-130556)
                $latestCodeStmt = $pdo->query("SELECT emp_code FROM employees WHERE emp_code LIKE 'T-%' ORDER BY id DESC LIMIT 1");
                $latestCode = $latestCodeStmt->fetchColumn();

                if ($latestCode && preg_match('/T-(\d+)/', $latestCode, $matches)) {
                    $nextNum = (int) $matches[1] + 1;
                } else {
                    $nextNum = 130556;
                }
                $empCode = 'T-' . $nextNum;
            }

            // Check if email already exists
            $checkEmail = $pdo->prepare("SELECT COUNT(*) FROM `employees` WHERE `email` = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetchColumn() > 0) {
                throw new Exception("Email is already registered.");
            }

            // Create avatar initials (e.g. SELVAKUMAR J -> SJ)
            $parts = explode(' ', $name);
            $avatar = '';
            foreach ($parts as $p) {
                if (!empty($p))
                    $avatar .= strtoupper(substr($p, 0, 1));
            }
            if (empty($avatar))
                $avatar = 'U';
            $avatar = substr($avatar, 0, 2);

            // Hash password
            $hashedPass = password_hash($password, PASSWORD_DEFAULT);

            // Insert employee
            $meOrgId = (int) ($jwtPayload['org_id'] ?? 1);
            $stmt = $pdo->prepare("INSERT INTO `employees` (`name`, `role`, `email`, `password`, `emp_code`, `avatar`, `org_id`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?, 'Active')");
            $stmt->execute([$name, $role, $email, $hashedPass, $empCode ?: null, $avatar, $meOrgId]);

            // Insert notification
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`message`, `category`, `org_id`) VALUES (?, ?, ?)");
            $notifStmt->execute(["New employee '$name' has been added to the system.", 'success', $meOrgId]);

            $response = [
                'success' => true,
                'message' => 'Employee created successfully'
            ];
        } else if ($action === 'update_settings') {
            $name = trim($_POST['name'] ?? '');
            $role = trim($_POST['role'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $empCode = trim($_POST['emp_code'] ?? '');
            $avatar = trim($_POST['avatar'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($name) || empty($role) || empty($email)) {
                throw new Exception("Name, Role, and Email are required.");
            }

            $meId = $jwtPayload['id'];

            // Check if email already exists for another employee
            $checkEmail = $pdo->prepare("SELECT COUNT(*) FROM `employees` WHERE `email` = ? AND id != ?");
            $checkEmail->execute([$email, $meId]);
            if ($checkEmail->fetchColumn() > 0) {

                throw new Exception("Email is already registered by another employee.");
            }

            if (!empty($empCode)) {
                // Check if employee code already exists for another employee
                $checkCode = $pdo->prepare("SELECT COUNT(*) FROM `employees` WHERE `emp_code` = ? AND id != ?");
                $checkCode->execute([$empCode, $meId]);
                if ($checkCode->fetchColumn() > 0) {
                    throw new Exception("Employee ID '$empCode' is already in use by another employee.");
                }
            }

            // Determine avatar initials if blank
            if (empty($avatar)) {
                $parts = explode(' ', $name);
                $avatar = '';
                foreach ($parts as $p) {
                    if (!empty($p))
                        $avatar .= strtoupper(substr($p, 0, 1));
                }
                if (empty($avatar))
                    $avatar = 'U';
                $avatar = substr($avatar, 0, 2);
            }

            // Update employee
            if (!empty($password)) {
                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE `employees` SET `name` = ?, `role` = ?, `email` = ?, `emp_code` = ?, `avatar` = ?, `password` = ? WHERE id = ?");
                $stmt->execute([$name, $role, $email, $empCode ?: null, $avatar, $hashedPass, $meId]);
            } else {
                $stmt = $pdo->prepare("UPDATE `employees` SET `name` = ?, `role` = ?, `email` = ?, `emp_code` = ?, `avatar` = ? WHERE id = ?");
                $stmt->execute([$name, $role, $email, $empCode ?: null, $avatar, $meId]);
            }

            // Update JWT Token Cookie since name might have changed
            $newPayload = [
                'id' => $meId,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'emp_code' => $empCode
            ];
            $newToken = generate_jwt($newPayload);
            setcookie('vyala_taskpad_jwt_token', $newToken, time() + 86400, '/', '', false, true);

            $response = [
                'success' => true,
                'message' => 'Profile settings updated successfully'
            ];
        } else if ($action === 'update_task_status') {
            $taskId = (int) ($_POST['task_id'] ?? 0);
            $status = trim($_POST['status'] ?? '');
            if (!$taskId || !in_array($status, ['Todo', 'In Progress', 'In Review', 'Completed'])) {
                throw new Exception("Invalid task or status.");
            }
            // Fetch task details for activity / notification
            $task = $pdo->query("SELECT t.title, p.name as proj_name FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.id = $taskId")->fetch(PDO::FETCH_ASSOC);
            if (!$task) {
                throw new Exception("Task not found.");
            }

            $stmt = $pdo->prepare("UPDATE `tasks` SET `status` = ? WHERE id = ?");
            $stmt->execute([$status, $taskId]);

            // --- Auto-Scheduling Logic ---
            if ($status === 'In Progress') {
                $pdo->prepare("UPDATE `tasks` SET `actual_start_date` = ? WHERE id = ? AND `actual_start_date` IS NULL")->execute([date('Y-m-d'), $taskId]);
            } else if ($status === 'Completed') {
                $compDate = date('Y-m-d');
                $pdo->prepare("UPDATE `tasks` SET `actual_completion_date` = ? WHERE id = ?")->execute([$compDate, $taskId]);
                
                // Recalculate subsequent tasks
                $tData = $pdo->query("SELECT project_id, sequence_order FROM tasks WHERE id = $taskId")->fetch(PDO::FETCH_ASSOC);
                if ($tData && $tData['sequence_order'] > 0) {
                    $pid = $tData['project_id'];
                    $seq = $tData['sequence_order'];
                    
                    // Get all subsequent tasks
                    $subsequent = $pdo->query("SELECT id, estimated_duration FROM tasks WHERE project_id = $pid AND sequence_order > $seq ORDER BY sequence_order ASC")->fetchAll(PDO::FETCH_ASSOC);
                    
                    $currentBaseDate = $compDate;
                    foreach ($subsequent as $sub) {
                        $days = (int)$sub['estimated_duration'];
                        if ($days <= 0) $days = 1;
                        $newDueDate = date('Y-m-d', strtotime("$currentBaseDate + $days days"));
                        $pdo->prepare("UPDATE tasks SET due_date = ? WHERE id = ?")->execute([$newDueDate, $sub['id']]);
                        $currentBaseDate = $newDueDate;
                    }
                }
            }
            // -----------------------------

            // Log activity
            $actStmt = $pdo->prepare("INSERT INTO `activities` (`action`, `details`, `project_name`, `logged_date`, `org_id`) VALUES (?, ?, ?, ?, ?)");
            $actStmt->execute(["Moved task \"{$task['title']}\" to $status", "Update Task Status", $task['proj_name'], date('d M, Y g:i A'), $jwtPayload['org_id']]);

            // Add notification
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`message`, `category`, `org_id`) VALUES (?, ?, ?)");
            $notifStmt->execute(["Task '{$task['title']}' status changed to $status", 'info', $jwtPayload['org_id']]);

            $response = ['success' => true, 'message' => 'Task status updated successfully'];
        } else if ($action === 'create_note') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $category = trim($_POST['category'] ?? 'Work');
            $tags = trim($_POST['tags'] ?? '');
            $meId = $jwtPayload['id'];

            if (empty($title) || empty($content)) {
                throw new Exception("Title and Content are required.");
            }

            $stmt = $pdo->prepare("INSERT INTO `pin_notes` (`title`, `content`, `category`, `tags`, `owner_id`, `org_id`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $category, $tags ?: null, $meId, $jwtPayload['org_id']]);

            $response = ['success' => true, 'message' => 'Note created successfully'];
        } else if ($action === 'update_note') {
            $noteId = (int) ($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $category = trim($_POST['category'] ?? 'Work');
            $tags = trim($_POST['tags'] ?? '');
            $meId = $jwtPayload['id'];

            if (!$noteId || empty($title) || empty($content)) {
                throw new Exception("ID, Title, and Content are required.");
            }

            // Verify ownership
            $check = $pdo->prepare("SELECT COUNT(*) FROM `pin_notes` WHERE id = ? AND owner_id = ?");
            $check->execute([$noteId, $meId]);
            if ($check->fetchColumn() === 0) {
                throw new Exception("Unauthorized or note does not exist.");
            }

            $stmt = $pdo->prepare("UPDATE `pin_notes` SET `title` = ?, `content` = ?, `category` = ?, `tags` = ? WHERE id = ?");
            $stmt->execute([$title, $content, $category, $tags ?: null, $noteId]);

            $response = ['success' => true, 'message' => 'Note updated successfully'];
        } else if ($action === 'delete_note') {
            $noteId = (int) ($_POST['id'] ?? 0);
            $meId = $jwtPayload['id'];

            if (!$noteId) {
                throw new Exception("Invalid Note ID.");
            }

            // Verify ownership
            $check = $pdo->prepare("SELECT COUNT(*) FROM `pin_notes` WHERE id = ? AND owner_id = ?");
            $check->execute([$noteId, $meId]);
            if ($check->fetchColumn() === 0) {
                throw new Exception("Unauthorized or note does not exist.");
            }

            $stmt = $pdo->prepare("DELETE FROM `pin_notes` WHERE id = ?");
            $stmt->execute([$noteId]);

            $response = ['success' => true, 'message' => 'Note deleted successfully'];
        } else if ($action === 'create_discussion') {
            $title = trim($_POST['title'] ?? '');
            $type = trim($_POST['type'] ?? 'General');

            if (empty($title)) {
                throw new Exception("Discussion Title is required.");
            }

            $stmt = $pdo->prepare("INSERT INTO `discussions` (`title`, `type`, `date_logged`, `org_id`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $type, date('d M Y'), $jwtPayload['org_id']]);
            $discId = $pdo->lastInsertId();
            $meId = $jwtPayload['id'];

            // Automatically add the creator as the first member
            $stmtMem = $pdo->prepare("INSERT INTO `discussion_members` (`discussion_id`, `employee_id`) VALUES (?, ?)");
            $stmtMem->execute([$discId, $meId]);

            $response = ['success' => true, 'message' => 'Discussion channel created successfully', 'discussion_id' => $discId];
        } else if ($action === 'send_message') {
            $discId = (int) ($_POST['discussion_id'] ?? 0);
            $message = trim($_POST['message'] ?? '');
            $meId = $jwtPayload['id'];

            if (!$discId || empty($message)) {
                throw new Exception("Discussion and message content are required.");
            }

            // Check if discussion exists
            $check = $pdo->prepare("SELECT COUNT(*) FROM `discussions` WHERE id = ?");
            $check->execute([$discId]);
            if ($check->fetchColumn() === 0) {
                throw new Exception("Discussion channel not found.");
            }

            // Handle optional attachment
            $attachmentName = null;
            $attachmentType = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['attachment'];
                $fileName = basename($file['name']);
                $uploadsDir = 'uploads/';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                $targetFile = $uploadsDir . time() . '_' . $fileName;
                if (move_uploaded_file($file['tmp_name'], $targetFile)) {
                    $attachmentName = $fileName;
                    $attachmentType = pathinfo($fileName, PATHINFO_EXTENSION);
                }
            }

            $stmt = $pdo->prepare("INSERT INTO `discussion_messages` (`discussion_id`, `sender_id`, `message`, `attachment_name`, `attachment_type`) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$discId, $meId, $message, $attachmentName, $attachmentType]);

            if ($attachmentName) {
                $upd = $pdo->prepare("UPDATE `discussions` SET `attachment_name` = ?, `attachment_type` = ? WHERE id = ?");
                $upd->execute([$attachmentName, $attachmentType, $discId]);
            }

            $response = ['success' => true, 'message' => 'Message sent successfully'];
        } else if ($action === 'get_messages') {
            $discId = (int) ($_REQUEST['discussion_id'] ?? 0);
            if (!$discId) {
                throw new Exception("Discussion ID is required.");
            }

            $stmt = $pdo->prepare("
                SELECT m.*, e.name as sender_name, e.avatar as sender_avatar
                FROM `discussion_messages` m
                JOIN `employees` e ON m.sender_id = e.id
                WHERE m.discussion_id = ?
                ORDER BY m.id ASC
            ");
            $stmt->execute([$discId]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($messages as &$msg) {
                $msg['time_text'] = date('h:i A', strtotime($msg['created_at']));
            }

            $response = ['success' => true, 'messages' => $messages];
        } else if ($action === 'upload_document') {
            $meId = $jwtPayload['id'];
            $projectId = isset($_POST['project_id']) && $_POST['project_id'] !== '' ? (int) $_POST['project_id'] : null;

            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Please select a file to upload.");
            }

            $file = $_FILES['file'];
            $fileName = basename($file['name']);
            $fileSizeVal = $file['size'];

            if ($fileSizeVal >= 1048576) {
                $fileSize = round($fileSizeVal / 1048576, 2) . ' MB';
            } else {
                $fileSize = round($fileSizeVal / 1024, 2) . ' KB';
            }

            $uploadsDir = 'uploads/';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }

            $targetPath = $uploadsDir . time() . '_' . $fileName;
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception("Failed to save the uploaded file.");
            }

            $meOrgId = (int) ($jwtPayload['org_id'] ?? 1);
            $stmt = $pdo->prepare("INSERT INTO `documents` (`name`, `filepath`, `owner_id`, `project_id`, `size`, `org_id`) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fileName, $targetPath, $meId, $projectId, $fileSize, $meOrgId]);

            // Add notification
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`message`, `category`, `org_id`) VALUES (?, ?, ?)");
            $notifStmt->execute(["Uploaded document '$fileName'", 'success', $meOrgId]);

            $response = ['success' => true, 'message' => 'Document uploaded successfully'];
        } else if ($action === 'delete_document') {
            $docId = (int) ($_POST['id'] ?? 0);
            $meId = $jwtPayload['id'];
            $meRole = $jwtPayload['role'];

            if (!$docId) {
                throw new Exception("Invalid Document ID.");
            }

            $stmt = $pdo->prepare("SELECT * FROM `documents` WHERE id = ?");
            $stmt->execute([$docId]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$doc) {
                throw new Exception("Document not found.");
            }

            if ($doc['owner_id'] != $meId && !in_array($meRole, ['Admin', 'Project Lead'])) {
                throw new Exception("You do not have permission to delete this document.");
            }

            if (file_exists($doc['filepath'])) {
                unlink($doc['filepath']);
            }

            $del = $pdo->prepare("DELETE FROM `documents` WHERE id = ?");
            $del->execute([$docId]);

            $response = ['success' => true, 'message' => 'Document deleted successfully'];
        } else if ($action === 'check_in') {
            $meId = $jwtPayload['id'];
            $todayDate = date('Y-m-d');
            $timeNow = date('H:i:s');

            $check = $pdo->prepare("SELECT COUNT(*) FROM `attendance` WHERE employee_id = ? AND date = ?");
            $check->execute([$meId, $todayDate]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("You have already checked in today.");
            }

            $status = (strtotime($timeNow) > strtotime('09:30:00')) ? 'Late' : 'Present';

            $stmt = $pdo->prepare("INSERT INTO `attendance` (`employee_id`, `date`, `check_in`, `status`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$meId, $todayDate, $timeNow, $status]);

            $response = ['success' => true, 'message' => 'Checked in successfully at ' . date('h:i A', strtotime($timeNow)), 'time' => date('h:i A', strtotime($timeNow)), 'status' => $status];
        } else if ($action === 'check_out') {
            $meId = $jwtPayload['id'];
            $todayDate = date('Y-m-d');
            $timeNow = date('H:i:s');

            $stmt = $pdo->prepare("SELECT * FROM `attendance` WHERE employee_id = ? AND date = ?");
            $stmt->execute([$meId, $todayDate]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                throw new Exception("You must check in first before checking out.");
            }
            if ($record['check_out'] !== null) {
                throw new Exception("You have already checked out today.");
            }

            $upd = $pdo->prepare("UPDATE `attendance` SET `check_out` = ? WHERE id = ?");
            $upd->execute([$timeNow, $record['id']]);

            $response = ['success' => true, 'message' => 'Checked out successfully at ' . date('h:i A', strtotime($timeNow)), 'time' => date('h:i A', strtotime($timeNow))];
        } else if ($action === 'clear_notifications') {
            $meOrgId = (int) ($jwtPayload['org_id'] ?? 1);
            $stmt = $pdo->prepare("DELETE FROM `notifications` WHERE `org_id` = ?");
            $stmt->execute([$meOrgId]);
            $response = ['success' => true, 'message' => 'All notifications cleared'];
        } else if ($action === 'create_department') {
            $name = trim($_POST['name'] ?? '');
            $icon = trim($_POST['icon'] ?? 'git-branch');
            $color = trim($_POST['color'] ?? '#2563eb');
            $bg = trim($_POST['bg'] ?? '#eff6ff');

            if (empty($name)) {
                throw new Exception("Department Name is required.");
            }

            $check = $pdo->prepare("SELECT COUNT(*) FROM `departments` WHERE `name` = ?");
            $check->execute([$name]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("Department Name already exists.");
            }

            $stmt = $pdo->prepare("INSERT INTO `departments` (`name`, `icon`, `color`, `bg`) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $icon, $color, $bg]);

            $response = ['success' => true, 'message' => 'Department created successfully'];
        } else if ($action === 'update_department') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');

            if (!$id || empty($name)) {
                throw new Exception("ID and Department Name are required.");
            }

            $check = $pdo->prepare("SELECT COUNT(*) FROM `departments` WHERE `name` = ? AND id != ?");
            $check->execute([$name, $id]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("Department Name already exists.");
            }

            $stmt = $pdo->prepare("UPDATE `departments` SET `name` = ? WHERE id = ?");
            $stmt->execute([$name, $id]);

            $response = ['success' => true, 'message' => 'Department updated successfully'];
        } else if ($action === 'delete_department') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                throw new Exception("Invalid Department ID.");
            }

            $stmt = $pdo->prepare("DELETE FROM `departments` WHERE id = ?");
            $stmt->execute([$id]);

            $response = ['success' => true, 'message' => 'Department deleted successfully'];
        } else if ($action === 'approve_org') {
            if ($jwtPayload['role'] !== 'Admin') throw new Exception("Unauthorized.");
            $orgId = (int)($_POST['org_id'] ?? 0);
            if (!$orgId) throw new Exception("Invalid Organization ID.");
            
            $pdo->prepare("UPDATE `organizations` SET `status` = 'Active' WHERE id = ?")->execute([$orgId]);
            $pdo->prepare("UPDATE `employees` SET `status` = 'Approved' WHERE org_id = ? AND role = 'Project Lead'")->execute([$orgId]);
            $response = ['success' => true, 'message' => 'Organization approved successfully'];
        } else if ($action === 'reject_org') {
            if ($jwtPayload['role'] !== 'Admin') throw new Exception("Unauthorized.");
            $orgId = (int)($_POST['org_id'] ?? 0);
            if (!$orgId) throw new Exception("Invalid Organization ID.");
            
            $pdo->prepare("UPDATE `organizations` SET `status` = 'Rejected' WHERE id = ?")->execute([$orgId]);
            $response = ['success' => true, 'message' => 'Organization rejected successfully'];
        } else if ($action === 'get_discussion_members') {
            $discId = (int)($_REQUEST['discussion_id'] ?? 0);
            $meId = $jwtPayload['id'];
            if (!$discId) {
                throw new Exception("Discussion ID is required.");
            }

            // Get members
            $stmt = $pdo->prepare("
                SELECT e.id, e.name, e.role, e.avatar
                FROM `discussion_members` dm
                JOIN `employees` e ON dm.employee_id = e.id
                WHERE dm.discussion_id = ?
                ORDER BY e.name ASC
            ");
            $stmt->execute([$discId]);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Self-healing: if empty, add current user
            if (empty($members)) {
                $stmtAddMe = $pdo->prepare("INSERT IGNORE INTO `discussion_members` (`discussion_id`, `employee_id`) VALUES (?, ?)");
                $stmtAddMe->execute([$discId, $meId]);

                $stmt->execute([$discId]);
                $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Get non-members
            $stmtNon = $pdo->prepare("
                SELECT id, name, role, avatar
                FROM `employees`
                WHERE id NOT IN (
                    SELECT employee_id FROM `discussion_members` WHERE discussion_id = ?
                )
                ORDER BY name ASC
            ");
            $stmtNon->execute([$discId]);
            $nonMembers = $stmtNon->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'success' => true,
                'members' => $members,
                'non_members' => $nonMembers
            ];
        } else if ($action === 'add_discussion_member') {
            $discId = (int)($_POST['discussion_id'] ?? 0);
            $empId = (int)($_POST['employee_id'] ?? 0);
            if (!$discId || !$empId) {
                throw new Exception("Discussion ID and Employee ID are required.");
            }
            $stmt = $pdo->prepare("INSERT IGNORE INTO `discussion_members` (`discussion_id`, `employee_id`) VALUES (?, ?)");
            $stmt->execute([$discId, $empId]);
            $response = ['success' => true, 'message' => 'Member added successfully'];
        } else if ($action === 'remove_discussion_member') {
            $discId = (int)($_POST['discussion_id'] ?? 0);
            $empId = (int)($_POST['employee_id'] ?? 0);
            if (!$discId || !$empId) {
                throw new Exception("Discussion ID and Employee ID are required.");
            }
            $stmt = $pdo->prepare("DELETE FROM `discussion_members` WHERE `discussion_id` = ? AND `employee_id` = ?");
            $stmt->execute([$discId, $empId]);
            $response = ['success' => true, 'message' => 'Member removed successfully'];
        } else if ($action === 'approve_employee') {
            if ($jwtPayload['role'] !== 'Admin' && $jwtPayload['role'] !== 'Project Lead') {
                throw new Exception("Unauthorized.");
            }
            $empId = (int)($_POST['employee_id'] ?? 0);
            if (!$empId) {
                throw new Exception("Invalid Employee ID.");
            }

            // Scoping / Verification
            $stmtCheck = $pdo->prepare("SELECT org_id, name FROM `employees` WHERE id = ?");
            $stmtCheck->execute([$empId]);
            $empInfo = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$empInfo) {
                throw new Exception("Employee not found.");
            }
            if ($jwtPayload['role'] === 'Project Lead' && $empInfo['org_id'] != $jwtPayload['org_id']) {
                throw new Exception("Unauthorized to approve employees of another organization.");
            }

            $stmt = $pdo->prepare("UPDATE `employees` SET `status` = 'Approved' WHERE `id` = ?");
            $stmt->execute([$empId]);

            $empName = $empInfo['name'];
            $meOrgId = (int) ($jwtPayload['org_id'] ?? 1);
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`message`, `category`, `org_id`) VALUES (?, ?, ?)");
            $notifStmt->execute(["Employee '$empName' has been approved.", 'success', $meOrgId]);

            $response = ['success' => true, 'message' => 'Employee approved successfully'];
        } else if ($action === 'toggle_user_status') {
            if ($jwtPayload['role'] !== 'Admin' && $jwtPayload['role'] !== 'Project Lead') {
                throw new Exception("Unauthorized.");
            }
            $empId = (int)($_POST['employee_id'] ?? 0);
            $newStatus = trim($_POST['new_status'] ?? '');
            if (!$empId || !in_array($newStatus, ['Approved', 'Deactivated'])) {
                throw new Exception("Invalid Employee ID or status value.");
            }
            if ($empId === (int)$jwtPayload['id']) {
                throw new Exception("You cannot change your own account status.");
            }

            // Scoping / Verification
            $stmtCheck = $pdo->prepare("SELECT org_id, name FROM `employees` WHERE id = ?");
            $stmtCheck->execute([$empId]);
            $empInfo = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$empInfo) {
                throw new Exception("Employee not found.");
            }
            if ($jwtPayload['role'] === 'Project Lead' && $empInfo['org_id'] != $jwtPayload['org_id']) {
                throw new Exception("Unauthorized to modify employees of another organization.");
            }

            $stmt = $pdo->prepare("UPDATE `employees` SET `status` = ? WHERE `id` = ?");
            $stmt->execute([$newStatus, $empId]);

            $empName = $empInfo['name'];
            $meOrgId = (int) ($jwtPayload['org_id'] ?? 1);
            $notifStmt = $pdo->prepare("INSERT INTO `notifications` (`message`, `category`, `org_id`) VALUES (?, ?, ?)");
            $notifStmt->execute(["User '$empName' has been " . strtolower($newStatus === 'Approved' ? 'activated' : 'deactivated') . ".", 'info', $meOrgId]);

            $response = ['success' => true, 'message' => "User status updated to $newStatus"];
        } else if ($action === 'update_employee') {
            if ($jwtPayload['role'] !== 'Admin' && $jwtPayload['role'] !== 'Project Lead') {
                throw new Exception("Unauthorized.");
            }
            $empId = (int)($_POST['employee_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $role = trim($_POST['role'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $empCode = trim($_POST['emp_code'] ?? '');
            $status = trim($_POST['status'] ?? 'Approved');

            if (!$empId || empty($name) || empty($role) || empty($email)) {
                throw new Exception("Employee ID, Name, Role, and Email are required.");
            }

            // Scoping / Verification
            $stmtCheck = $pdo->prepare("SELECT org_id FROM `employees` WHERE id = ?");
            $stmtCheck->execute([$empId]);
            $empOrg = $stmtCheck->fetchColumn();
            if ($empOrg === false) {
                throw new Exception("Employee not found.");
            }
            if ($jwtPayload['role'] === 'Project Lead' && $empOrg != $jwtPayload['org_id']) {
                throw new Exception("Unauthorized to update employees of another organization.");
            }

            // Check if email already exists for another employee
            $checkEmail = $pdo->prepare("SELECT COUNT(*) FROM `employees` WHERE `email` = ? AND `id` != ?");
            $checkEmail->execute([$email, $empId]);
            if ($checkEmail->fetchColumn() > 0) {
                throw new Exception("Email is already registered by another employee.");
            }

            // Check if employee code already exists for another employee
            if (!empty($empCode)) {
                $checkCode = $pdo->prepare("SELECT COUNT(*) FROM `employees` WHERE `emp_code` = ? AND `id` != ?");
                $checkCode->execute([$empCode, $empId]);
                if ($checkCode->fetchColumn() > 0) {
                    throw new Exception("Employee ID '$empCode' is already in use by another employee.");
                }
            }

            if (!empty($password)) {
                $hashedPass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE `employees` SET `name` = ?, `role` = ?, `email` = ?, `password` = ?, `emp_code` = ?, `status` = ? WHERE `id` = ?");
                $stmt->execute([$name, $role, $email, $hashedPass, $empCode, $status, $empId]);
            } else {
                $stmt = $pdo->prepare("UPDATE `employees` SET `name` = ?, `role` = ?, `email` = ?, `emp_code` = ?, `status` = ? WHERE `id` = ?");
                $stmt->execute([$name, $role, $email, $empCode, $status, $empId]);
            }

            $response = ['success' => true, 'message' => 'Employee details updated successfully'];
        } else if ($action === 'delete_employee') {
            if ($jwtPayload['role'] !== 'Admin' && $jwtPayload['role'] !== 'Project Lead') {
                throw new Exception("Unauthorized.");
            }
            $empId = (int)($_POST['employee_id'] ?? 0);
            if (!$empId) {
                throw new Exception("Invalid Employee ID.");
            }
            if ($empId === (int)$jwtPayload['id']) {
                throw new Exception("You cannot delete your own account.");
            }

            // Scoping / Verification
            $stmtCheck = $pdo->prepare("SELECT org_id FROM `employees` WHERE id = ?");
            $stmtCheck->execute([$empId]);
            $empOrg = $stmtCheck->fetchColumn();
            if ($empOrg === false) {
                throw new Exception("Employee not found.");
            }
            if ($jwtPayload['role'] === 'Project Lead' && $empOrg != $jwtPayload['org_id']) {
                throw new Exception("Unauthorized to delete employees of another organization.");
            }

            // Perform deletion
            $stmt = $pdo->prepare("DELETE FROM `employees` WHERE `id` = ?");
            $stmt->execute([$empId]);

            $response = ['success' => true, 'message' => 'Employee deleted successfully'];
        } else if ($action === 'get_active_users') {
            $meId = $jwtPayload['id'];
            $meOrgId = (int) ($jwtPayload['org_id'] ?? 1);
            
            if ($jwtPayload['role'] === 'Admin') {
                // Admin can see all employees across organizations
                $stmt = $pdo->prepare("SELECT id, name, role, email, avatar FROM `employees` WHERE id != ? AND `role` != 'Admin' ORDER BY name ASC");
                $stmt->execute([$meId]);
            } else {
                // Fetch all employees except self and Admin role within same organization
                $stmt = $pdo->prepare("SELECT id, name, role, email, avatar FROM `employees` WHERE id != ? AND `role` != 'Admin' AND `org_id` = ? ORDER BY name ASC");
                $stmt->execute([$meId, $meOrgId]);
            }
            
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'users' => $users];

        } else if ($action === 'start_direct_conversation') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            $meId = $jwtPayload['id'];

            if (!$userId) {
                throw new Exception("User ID is required.");
            }
            if ($userId === $meId) {
                throw new Exception("You cannot start a direct conversation with yourself.");
            }

            // Check if there is an existing 'Direct' discussion with exactly these two users
            $stmt = $pdo->prepare("
                SELECT d.id 
                FROM `discussions` d
                JOIN `discussion_members` dm1 ON d.id = dm1.discussion_id AND dm1.employee_id = ?
                JOIN `discussion_members` dm2 ON d.id = dm2.discussion_id AND dm2.employee_id = ?
                WHERE d.type = 'Direct'
                LIMIT 1
            ");
            $stmt->execute([$meId, $userId]);
            $discId = $stmt->fetchColumn();

            if (!$discId) {
                // Get other user name
                $stmtUser = $pdo->prepare("SELECT name FROM `employees` WHERE id = ?");
                $stmtUser->execute([$userId]);
                $otherName = $stmtUser->fetchColumn() ?: "Direct Chat";

                // Create new direct discussion
                $stmtNew = $pdo->prepare("INSERT INTO `discussions` (`title`, `type`, `date_logged`) VALUES (?, 'Direct', ?)");
                $stmtNew->execute([$otherName, date('d M Y')]);
                $discId = $pdo->lastInsertId();

                // Add members
                $stmtMem = $pdo->prepare("INSERT INTO `discussion_members` (`discussion_id`, `employee_id`) VALUES (?, ?), (?, ?)");
                $stmtMem->execute([$discId, $meId, $discId, $userId]);
            }

            $response = [
                'success' => true,
                'discussion_id' => $discId
            ];
        } else if ($action === 'get_client_history') {
            $clientId = (int) ($_GET['client_id'] ?? 0);
            if (!$clientId) {
                throw new Exception("Client ID is required.");
            }

            // 1. Client Details
            $stmtClient = $pdo->prepare("SELECT * FROM `clients` WHERE id = ?");
            $stmtClient->execute([$clientId]);
            $clientDetails = $stmtClient->fetch(PDO::FETCH_ASSOC);

            if (!$clientDetails) {
                throw new Exception("Client not found.");
            }

            // 2. Client Projects
            $stmtProj = $pdo->prepare("
                SELECT p.*,
                       (SELECT COUNT(*) FROM `tasks` t WHERE t.project_id = p.id) as total_tasks,
                       (SELECT COUNT(*) FROM `tasks` t WHERE t.project_id = p.id AND t.status = 'Completed') as completed_tasks
                FROM `projects` p
                WHERE p.client_id = ?
                ORDER BY p.id DESC
            ");
            $stmtProj->execute([$clientId]);
            $projects = $stmtProj->fetchAll(PDO::FETCH_ASSOC);

            // 3. Client Activities
            $activities = [];
            if (!empty($projects)) {
                $projNames = array_map(function($p) { return $p['name']; }, $projects);
                $placeholders = implode(',', array_fill(0, count($projNames), '?'));
                $stmtAct = $pdo->prepare("
                    SELECT * FROM `activities` 
                    WHERE project_name IN ($placeholders)
                    ORDER BY id DESC
                ");
                $stmtAct->execute($projNames);
                $activities = $stmtAct->fetchAll(PDO::FETCH_ASSOC);
            }

            // 4. Client Transactions
            $transactions = [];
            if (!empty($projects)) {
                $projIds = array_map(function($p) { return $p['id']; }, $projects);
                $placeholders = implode(',', array_fill(0, count($projIds), '?'));
                $stmtTrans = $pdo->prepare("
                    SELECT ts.*, t.title as task_title, p.name as project_name, e.name as employee_name
                    FROM `timesheets` ts
                    JOIN `tasks` t ON ts.task_id = t.id
                    JOIN `projects` p ON t.project_id = p.id
                    JOIN `employees` e ON ts.employee_id = e.id
                    WHERE p.id IN ($placeholders)
                    ORDER BY ts.date DESC, ts.id DESC
                ");
                $stmtTrans->execute($projIds);
                $transactions = $stmtTrans->fetchAll(PDO::FETCH_ASSOC);
            }

            // 5. Client Timeline
            $timeline = [];
            foreach ($projects as $p) {
                $timeline[] = [
                    'date' => date('Y-m-d H:i:s', strtotime($p['created_at'])),
                    'type' => 'project_created',
                    'title' => 'Project Launched',
                    'description' => "Project '{$p['name']}' was launched.",
                    'meta' => $p['priority'] . ' Priority'
                ];
            }
            foreach ($transactions as $ts) {
                $timeline[] = [
                    'date' => date('Y-m-d H:i:s', strtotime($ts['date'] . ' 00:00:00')),
                    'type' => 'work_logged',
                    'title' => 'Work Hours Logged',
                    'description' => "{$ts['employee_name']} logged {$ts['hours']} hrs on task '{$ts['task_title']}' in '{$ts['project_name']}'.",
                    'meta' => $ts['description']
                ];
            }
            // Sort timeline descending
            usort($timeline, function($a, $b) {
                return strcmp($b['date'], $a['date']);
            });

            $response = [
                'success' => true,
                'client_details' => $clientDetails,
                'projects' => $projects,
                'activities' => $activities,
                'transactions' => $transactions,
                'timeline' => $timeline
            ];
        } else if ($action === 'save_layout') {
            $projectId = (int)($_POST['project_id'] ?? 0);
            $startDate = trim($_POST['start_date'] ?? '');
            $targetDate = trim($_POST['target_date'] ?? '');
            $sequenceData = json_decode($_POST['sequence_data'] ?? '[]', true);
            $meOrgId = (int)($jwtPayload['org_id'] ?? 1);

            if (!$projectId || empty($sequenceData)) {
                throw new Exception("Project ID and sequence data are required.");
            }

            if ($targetDate) {
                $stmt = $pdo->prepare("UPDATE projects SET due_date = ? WHERE id = ?");
                $stmt->execute([$targetDate, $projectId]);
            }

            $prevTaskId = null;
            foreach ($sequenceData as $t) {
                $assigneeId = (int)($jwtPayload['id']);
                if (!empty($t['assignee']) && is_numeric($t['assignee'])) {
                    $assigneeId = (int)$t['assignee'];
                }

                $stmt = $pdo->prepare("INSERT INTO `tasks` 
                    (`title`, `description`, `project_id`, `assigned_to`, `priority`, `status`, `org_id`, 
                     `estimated_duration`, `sequence_order`, `depends_on`) 
                     VALUES (?, ?, ?, ?, ?, 'Todo', ?, ?, ?, ?)");
                $stmt->execute([
                    $t['title'], 
                    'Created via Layout Module',
                    $projectId,
                    $assigneeId,
                    $t['priority'],
                    $meOrgId,
                    (int)$t['days'],
                    (int)$t['order'],
                    $prevTaskId
                ]);
                $prevTaskId = $pdo->lastInsertId();
            }

            // Log activity
            $projName = $pdo->query("SELECT name FROM projects WHERE id = $projectId")->fetchColumn() ?: "Project";
            $actStmt = $pdo->prepare("INSERT INTO `activities` (`action`, `details`, `project_name`, `logged_date`, `org_id`) VALUES (?, ?, ?, ?, ?)");
            $actStmt->execute(["Generated Layout Task Sequence", "Layout Setup", $projName, date('d M, Y g:i A'), $meOrgId]);

            $response = [
                'success' => true,
                'message' => 'Layout saved successfully.'
            ];
        } else if ($action === 'create_building') {
            $name = trim($_POST['name'] ?? '');
            $type = trim($_POST['type'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $totalFloors = (int)($_POST['total_floors'] ?? 0);
            $totalUnits = (int)($_POST['total_units'] ?? 0);
            $totalArea = (float)($_POST['total_area'] ?? 0);
            $ownerName = trim($_POST['owner_name'] ?? '');
            $contactNumber = trim($_POST['contact_number'] ?? '');
            $status = trim($_POST['status'] ?? 'Available');
            $meOrgId = (int)($jwtPayload['org_id'] ?? 1);

            if (empty($name)) throw new Exception("Building name is required.");

            $documentPath = null;
            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $documentPath = 'uploads/' . time() . '_' . basename($_FILES['document']['name']);
                move_uploaded_file($_FILES['document']['tmp_name'], $documentPath);
            }

            $stmt = $pdo->prepare("INSERT INTO `buildings` (`name`, `type`, `address`, `total_floors`, `total_units`, `total_area`, `owner_name`, `contact_number`, `status`, `document_path`, `org_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $type, $address, $totalFloors, $totalUnits, $totalArea, $ownerName, $contactNumber, $status, $documentPath, $meOrgId]);

            $response = ['success' => true, 'message' => 'Building created successfully'];
        } else if ($action === 'update_building') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Invalid ID.");
            $name = trim($_POST['name'] ?? '');
            $type = trim($_POST['type'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $totalFloors = (int)($_POST['total_floors'] ?? 0);
            $totalUnits = (int)($_POST['total_units'] ?? 0);
            $totalArea = (float)($_POST['total_area'] ?? 0);
            $ownerName = trim($_POST['owner_name'] ?? '');
            $contactNumber = trim($_POST['contact_number'] ?? '');
            $status = trim($_POST['status'] ?? 'Available');

            if (empty($name)) throw new Exception("Building name is required.");

            $docUpdate = "";
            $params = [$name, $type, $address, $totalFloors, $totalUnits, $totalArea, $ownerName, $contactNumber, $status];

            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $documentPath = 'uploads/' . time() . '_' . basename($_FILES['document']['name']);
                move_uploaded_file($_FILES['document']['tmp_name'], $documentPath);
                $docUpdate = ", `document_path` = ?";
                $params[] = $documentPath;
            }

            $params[] = $id;

            $stmt = $pdo->prepare("UPDATE `buildings` SET `name`=?, `type`=?, `address`=?, `total_floors`=?, `total_units`=?, `total_area`=?, `owner_name`=?, `contact_number`=?, `status`=? $docUpdate WHERE id=?");
            $stmt->execute($params);

            $response = ['success' => true, 'message' => 'Building updated successfully'];
        } else if ($action === 'delete_building') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Invalid ID.");
            $pdo->prepare("DELETE FROM `buildings` WHERE id = ?")->execute([$id]);
            $response = ['success' => true, 'message' => 'Building deleted successfully'];
            
        } else if ($action === 'create_single_plot') {
            $plotNumber = trim($_POST['plot_number'] ?? '');
            $layoutName = trim($_POST['layout_name'] ?? '');
            $surveyNumber = trim($_POST['survey_number'] ?? '');
            $area = (float)($_POST['area'] ?? 0);
            $location = trim($_POST['location'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $facingDirection = trim($_POST['facing_direction'] ?? '');
            $status = trim($_POST['status'] ?? 'Available');
            $ownerName = trim($_POST['owner_name'] ?? '');
            $meOrgId = (int)($jwtPayload['org_id'] ?? 1);

            if (empty($plotNumber)) throw new Exception("Plot number is required.");

            $stmt = $pdo->prepare("INSERT INTO `single_plots` (`plot_number`, `layout_name`, `survey_number`, `area`, `location`, `price`, `facing_direction`, `status`, `owner_name`, `org_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$plotNumber, $layoutName, $surveyNumber, $area, $location, $price, $facingDirection, $status, $ownerName, $meOrgId]);

            $response = ['success' => true, 'message' => 'Plot created successfully'];
        } else if ($action === 'update_single_plot') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Invalid ID.");
            $plotNumber = trim($_POST['plot_number'] ?? '');
            $layoutName = trim($_POST['layout_name'] ?? '');
            $surveyNumber = trim($_POST['survey_number'] ?? '');
            $area = (float)($_POST['area'] ?? 0);
            $location = trim($_POST['location'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $facingDirection = trim($_POST['facing_direction'] ?? '');
            $status = trim($_POST['status'] ?? 'Available');
            $ownerName = trim($_POST['owner_name'] ?? '');

            if (empty($plotNumber)) throw new Exception("Plot number is required.");

            $stmt = $pdo->prepare("UPDATE `single_plots` SET `plot_number`=?, `layout_name`=?, `survey_number`=?, `area`=?, `location`=?, `price`=?, `facing_direction`=?, `status`=?, `owner_name`=? WHERE id=?");
            $stmt->execute([$plotNumber, $layoutName, $surveyNumber, $area, $location, $price, $facingDirection, $status, $ownerName, $id]);

            $response = ['success' => true, 'message' => 'Plot updated successfully'];
        } else if ($action === 'delete_single_plot') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Invalid ID.");
            $pdo->prepare("DELETE FROM `single_plots` WHERE id = ?")->execute([$id]);
            $response = ['success' => true, 'message' => 'Plot deleted successfully'];

        } else if ($action === 'create_ual_record') {
            $caseNumber = trim($_POST['case_number'] ?? '');
            $ownerName = trim($_POST['owner_name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $totalLandArea = (float)($_POST['total_land_area'] ?? 0);
            $govCeilingLimit = (float)($_POST['gov_ceiling_limit'] ?? 0);
            $excessLandArea = (float)($_POST['excess_land_area'] ?? 0);
            $approvalStatus = trim($_POST['approval_status'] ?? 'Pending');
            $govOrderNumber = trim($_POST['gov_order_number'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');
            $meOrgId = (int)($jwtPayload['org_id'] ?? 1);

            if (empty($caseNumber)) throw new Exception("Case number is required.");

            $documentPath = null;
            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $documentPath = 'uploads/' . time() . '_' . basename($_FILES['document']['name']);
                move_uploaded_file($_FILES['document']['tmp_name'], $documentPath);
            }

            $stmt = $pdo->prepare("INSERT INTO `ual_records` (`case_number`, `owner_name`, `address`, `total_land_area`, `gov_ceiling_limit`, `excess_land_area`, `approval_status`, `gov_order_number`, `remarks`, `document_path`, `org_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$caseNumber, $ownerName, $address, $totalLandArea, $govCeilingLimit, $excessLandArea, $approvalStatus, $govOrderNumber, $remarks, $documentPath, $meOrgId]);

            $response = ['success' => true, 'message' => 'UAL record created successfully'];
        } else if ($action === 'update_ual_record') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Invalid ID.");
            $caseNumber = trim($_POST['case_number'] ?? '');
            $ownerName = trim($_POST['owner_name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $totalLandArea = (float)($_POST['total_land_area'] ?? 0);
            $govCeilingLimit = (float)($_POST['gov_ceiling_limit'] ?? 0);
            $excessLandArea = (float)($_POST['excess_land_area'] ?? 0);
            $approvalStatus = trim($_POST['approval_status'] ?? 'Pending');
            $govOrderNumber = trim($_POST['gov_order_number'] ?? '');
            $remarks = trim($_POST['remarks'] ?? '');

            if (empty($caseNumber)) throw new Exception("Case number is required.");

            $docUpdate = "";
            $params = [$caseNumber, $ownerName, $address, $totalLandArea, $govCeilingLimit, $excessLandArea, $approvalStatus, $govOrderNumber, $remarks];

            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $documentPath = 'uploads/' . time() . '_' . basename($_FILES['document']['name']);
                move_uploaded_file($_FILES['document']['tmp_name'], $documentPath);
                $docUpdate = ", `document_path` = ?";
                $params[] = $documentPath;
            }

            $params[] = $id;

            $stmt = $pdo->prepare("UPDATE `ual_records` SET `case_number`=?, `owner_name`=?, `address`=?, `total_land_area`=?, `gov_ceiling_limit`=?, `excess_land_area`=?, `approval_status`=?, `gov_order_number`=?, `remarks`=? $docUpdate WHERE id=?");
            $stmt->execute($params);

            $response = ['success' => true, 'message' => 'UAL record updated successfully'];
        } else if ($action === 'delete_ual_record') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Invalid ID.");
            $pdo->prepare("DELETE FROM `ual_records` WHERE id = ?")->execute([$id]);
            $response = ['success' => true, 'message' => 'UAL record deleted successfully'];

        } else if ($action === 'create_land_survey') {
            $surveyNumber = trim($_POST['survey_number'] ?? '');
            $villageName = trim($_POST['village_name'] ?? '');
            $taluk = trim($_POST['taluk'] ?? '');
            $district = trim($_POST['district'] ?? '');
            $landType = trim($_POST['land_type'] ?? '');
            $ownerName = trim($_POST['owner_name'] ?? '');
            $totalArea = (float)($_POST['total_area'] ?? 0);
            $latitude = trim($_POST['latitude'] ?? '');
            $longitude = trim($_POST['longitude'] ?? '');
            $meOrgId = (int)($jwtPayload['org_id'] ?? 1);

            if (empty($surveyNumber)) throw new Exception("Survey number is required.");

            $documentPath = null;
            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $documentPath = 'uploads/' . time() . '_' . basename($_FILES['document']['name']);
                move_uploaded_file($_FILES['document']['tmp_name'], $documentPath);
            }

            $stmt = $pdo->prepare("INSERT INTO `land_surveys` (`survey_number`, `village_name`, `taluk`, `district`, `land_type`, `owner_name`, `total_area`, `latitude`, `longitude`, `document_path`, `org_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$surveyNumber, $villageName, $taluk, $district, $landType, $ownerName, $totalArea, $latitude, $longitude, $documentPath, $meOrgId]);

            $response = ['success' => true, 'message' => 'Land survey created successfully'];
        } else if ($action === 'update_land_survey') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Invalid ID.");
            $surveyNumber = trim($_POST['survey_number'] ?? '');
            $villageName = trim($_POST['village_name'] ?? '');
            $taluk = trim($_POST['taluk'] ?? '');
            $district = trim($_POST['district'] ?? '');
            $landType = trim($_POST['land_type'] ?? '');
            $ownerName = trim($_POST['owner_name'] ?? '');
            $totalArea = (float)($_POST['total_area'] ?? 0);
            $latitude = trim($_POST['latitude'] ?? '');
            $longitude = trim($_POST['longitude'] ?? '');

            if (empty($surveyNumber)) throw new Exception("Survey number is required.");

            $docUpdate = "";
            $params = [$surveyNumber, $villageName, $taluk, $district, $landType, $ownerName, $totalArea, $latitude, $longitude];

            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $documentPath = 'uploads/' . time() . '_' . basename($_FILES['document']['name']);
                move_uploaded_file($_FILES['document']['tmp_name'], $documentPath);
                $docUpdate = ", `document_path` = ?";
                $params[] = $documentPath;
            }

            $params[] = $id;

            $stmt = $pdo->prepare("UPDATE `land_surveys` SET `survey_number`=?, `village_name`=?, `taluk`=?, `district`=?, `land_type`=?, `owner_name`=?, `total_area`=?, `latitude`=?, `longitude`=? $docUpdate WHERE id=?");
            $stmt->execute($params);

            $response = ['success' => true, 'message' => 'Land survey updated successfully'];
        } else if ($action === 'delete_land_survey') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Invalid ID.");
            $pdo->prepare("DELETE FROM `land_surveys` WHERE id = ?")->execute([$id]);
            $response = ['success' => true, 'message' => 'Land survey deleted successfully'];
        }
    } catch (Throwable $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }

echo json_encode($response);