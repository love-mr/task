<?php
$apiPath = __DIR__ . '/api.php';
$content = file_get_contents($apiPath);

$apiCode = <<<PHP

        // ==========================================
        // SURVEY MANAGEMENT MODULE
        // ==========================================

        if (\$action === 'create_survey_record') {
            \$survey_number = trim(\$_POST['survey_number'] ?? '');
            if (!\$survey_number) throw new Exception("Survey Number is required.");

            \$sub_division_number = trim(\$_POST['sub_division_number'] ?? '');
            \$owner_name = trim(\$_POST['owner_name'] ?? '');
            \$village_name = trim(\$_POST['village_name'] ?? '');
            \$taluk = trim(\$_POST['taluk'] ?? '');
            \$district = trim(\$_POST['district'] ?? '');
            \$land_type = trim(\$_POST['land_type'] ?? '');
            \$total_area = floatval(\$_POST['total_area'] ?? 0);
            \$patta_number = trim(\$_POST['patta_number'] ?? '');
            \$fmb_number = trim(\$_POST['fmb_number'] ?? '');
            \$latitude = trim(\$_POST['latitude'] ?? '');
            \$longitude = trim(\$_POST['longitude'] ?? '');
            \$survey_date = !empty(\$_POST['survey_date']) ? \$_POST['survey_date'] : null;
            \$status = trim(\$_POST['status'] ?? 'Pending');
            \$remarks = trim(\$_POST['remarks'] ?? '');
            
            \$documentPath = null;
            if (isset(\$_FILES['document']) && \$_FILES['document']['error'] === UPLOAD_ERR_OK) {
                \$uploadDir = 'uploads/';
                if (!is_dir(\$uploadDir)) mkdir(\$uploadDir, 0777, true);
                \$documentPath = \$uploadDir . time() . '_' . basename(\$_FILES['document']['name']);
                move_uploaded_file(\$_FILES['document']['tmp_name'], \$documentPath);
            }

            \$stmt = \$pdo->prepare("INSERT INTO `survey_management` (`survey_number`, `sub_division_number`, `owner_name`, `village_name`, `taluk`, `district`, `land_type`, `total_area`, `patta_number`, `fmb_number`, `latitude`, `longitude`, `survey_date`, `status`, `document_path`, `remarks`, `org_id`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            \$stmt->execute([
                \$survey_number, \$sub_division_number, \$owner_name, \$village_name, \$taluk, \$district, \$land_type, \$total_area, \$patta_number, \$fmb_number, \$latitude, \$longitude, \$survey_date, \$status, \$documentPath, \$remarks, \$meOrgId
            ]);
            
            \$newId = \$pdo->lastInsertId();

            // Log History
            \$histStmt = \$pdo->prepare("INSERT INTO `survey_history` (`survey_id`, `action`, `performed_by`, `details`) VALUES (?, ?, ?, ?)");
            \$histStmt->execute([\$newId, 'Created', \$meId, "Survey record created."]);

            \$response = ['success' => true, 'message' => 'Survey record created successfully'];
        }
        else if (\$action === 'update_survey_record') {
            \$id = (int)(\$_POST['id'] ?? 0);
            if (!\$id) throw new Exception("Invalid ID.");

            \$survey_number = trim(\$_POST['survey_number'] ?? '');
            \$sub_division_number = trim(\$_POST['sub_division_number'] ?? '');
            \$owner_name = trim(\$_POST['owner_name'] ?? '');
            \$village_name = trim(\$_POST['village_name'] ?? '');
            \$taluk = trim(\$_POST['taluk'] ?? '');
            \$district = trim(\$_POST['district'] ?? '');
            \$land_type = trim(\$_POST['land_type'] ?? '');
            \$total_area = floatval(\$_POST['total_area'] ?? 0);
            \$patta_number = trim(\$_POST['patta_number'] ?? '');
            \$fmb_number = trim(\$_POST['fmb_number'] ?? '');
            \$latitude = trim(\$_POST['latitude'] ?? '');
            \$longitude = trim(\$_POST['longitude'] ?? '');
            \$survey_date = !empty(\$_POST['survey_date']) ? \$_POST['survey_date'] : null;
            \$status = trim(\$_POST['status'] ?? 'Pending');
            \$remarks = trim(\$_POST['remarks'] ?? '');

            \$docUpdate = "";
            \$params = [
                \$survey_number, \$sub_division_number, \$owner_name, \$village_name, \$taluk, \$district, 
                \$land_type, \$total_area, \$patta_number, \$fmb_number, \$latitude, \$longitude, \$survey_date, \$status, \$remarks
            ];

            if (isset(\$_FILES['document']) && \$_FILES['document']['error'] === UPLOAD_ERR_OK) {
                \$uploadDir = 'uploads/';
                if (!is_dir(\$uploadDir)) mkdir(\$uploadDir, 0777, true);
                \$documentPath = \$uploadDir . time() . '_' . basename(\$_FILES['document']['name']);
                move_uploaded_file(\$_FILES['document']['tmp_name'], \$documentPath);
                \$docUpdate = ", `document_path` = ?";
                \$params[] = \$documentPath;
            }

            \$params[] = \$id;
            \$params[] = \$meOrgId;

            \$stmt = \$pdo->prepare("UPDATE `survey_management` SET `survey_number`=?, `sub_division_number`=?, `owner_name`=?, `village_name`=?, `taluk`=?, `district`=?, `land_type`=?, `total_area`=?, `patta_number`=?, `fmb_number`=?, `latitude`=?, `longitude`=?, `survey_date`=?, `status`=?, `remarks`=? \$docUpdate WHERE id=? AND org_id=?");
            \$stmt->execute(\$params);

            // Log History
            \$histStmt = \$pdo->prepare("INSERT INTO `survey_history` (`survey_id`, `action`, `performed_by`, `details`) VALUES (?, ?, ?, ?)");
            \$histStmt->execute([\$id, 'Updated', \$meId, "Survey record details updated."]);

            \$response = ['success' => true, 'message' => 'Survey record updated successfully'];
        }
        else if (\$action === 'archive_survey_record') {
            \$id = (int)(\$_POST['id'] ?? 0);
            if (!\$id) throw new Exception("Invalid ID.");
            
            \$stmt = \$pdo->prepare("UPDATE `survey_management` SET `is_archived` = 1 WHERE id = ? AND org_id = ?");
            \$stmt->execute([\$id, \$meOrgId]);

            // Log History
            \$histStmt = \$pdo->prepare("INSERT INTO `survey_history` (`survey_id`, `action`, `performed_by`, `details`) VALUES (?, ?, ?, ?)");
            \$histStmt->execute([\$id, 'Archived', \$meId, "Survey record archived."]);

            \$response = ['success' => true, 'message' => 'Survey record archived successfully'];
        }
        else if (\$action === 'verify_survey_record') {
            \$id = (int)(\$_POST['id'] ?? 0);
            \$status = trim(\$_POST['status'] ?? 'Verified');
            if (!\$id) throw new Exception("Invalid ID.");

            \$stmt = \$pdo->prepare("UPDATE `survey_management` SET `status` = ? WHERE id = ? AND org_id = ?");
            \$stmt->execute([\$status, \$id, \$meOrgId]);

            // Log History
            \$histStmt = \$pdo->prepare("INSERT INTO `survey_history` (`survey_id`, `action`, `performed_by`, `details`) VALUES (?, ?, ?, ?)");
            \$histStmt->execute([\$id, 'Status Changed', \$meId, "Status updated to " . \$status]);

            \$response = ['success' => true, 'message' => 'Survey status updated successfully'];
        }
        else if (\$action === 'get_survey_history') {
            \$id = (int)(\$_GET['id'] ?? 0);
            if (!\$id) throw new Exception("Invalid ID.");

            \$stmt = \$pdo->prepare("
                SELECT h.*, e.name as user_name 
                FROM `survey_history` h
                LEFT JOIN `employees` e ON h.performed_by = e.id
                WHERE h.survey_id = ? 
                ORDER BY h.id DESC
            ");
            \$stmt->execute([\$id]);
            \$history = \$stmt->fetchAll(PDO::FETCH_ASSOC);

            \$response = ['success' => true, 'history' => \$history];
        }
        else if (\$action === 'export_survey_csv') {
            // Build query based on filters
            \$query = "SELECT * FROM `survey_management` WHERE org_id = ? AND is_archived = 0";
            \$params = [\$meOrgId];

            if (!empty(\$_GET['survey_number'])) {
                \$query .= " AND survey_number LIKE ?";
                \$params[] = '%' . \$_GET['survey_number'] . '%';
            }
            if (!empty(\$_GET['village_name'])) {
                \$query .= " AND village_name LIKE ?";
                \$params[] = '%' . \$_GET['village_name'] . '%';
            }
            if (!empty(\$_GET['taluk'])) {
                \$query .= " AND taluk LIKE ?";
                \$params[] = '%' . \$_GET['taluk'] . '%';
            }
            if (!empty(\$_GET['district'])) {
                \$query .= " AND district LIKE ?";
                \$params[] = '%' . \$_GET['district'] . '%';
            }
            if (!empty(\$_GET['status'])) {
                \$query .= " AND status = ?";
                \$params[] = \$_GET['status'];
            }
            
            \$query .= " ORDER BY id DESC";
            
            \$stmt = \$pdo->prepare(\$query);
            \$stmt->execute(\$params);
            \$records = \$stmt->fetchAll(PDO::FETCH_ASSOC);

            // Output CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="survey_records_' . date('Ymd_His') . '.csv"');
            
            \$output = fopen('php://output', 'w');
            fputcsv(\$output, ['ID', 'Survey Number', 'Sub Div', 'Owner Name', 'Village', 'Taluk', 'District', 'Land Type', 'Total Area', 'Patta', 'FMB', 'Status', 'Date']);
            
            foreach (\$records as \$r) {
                fputcsv(\$output, [
                    \$r['id'], \$r['survey_number'], \$r['sub_division_number'], \$r['owner_name'], \$r['village_name'],
                    \$r['taluk'], \$r['district'], \$r['land_type'], \$r['total_area'], \$r['patta_number'], \$r['fmb_number'],
                    \$r['status'], \$r['survey_date']
                ]);
            }
            fclose(\$output);
            exit;
        }

PHP;

// Inject right before the last closing brace of try block in api.php
// Basically, just before `} catch (Throwable $e) {`
$content = preg_replace('/(\} catch \(Throwable \$e\) \{)/i', $apiCode . "\n$1", $content);

file_put_contents($apiPath, $content);
echo "API Injection successful.";
