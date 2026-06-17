<?php
$f = 'C:/Users/acer/Desktop/dummy/api.php';
$content = file_get_contents($f);

// Remove the broken injection and replace with correct placement
$broken = <<<'PHP'
        }
        // end save_layout block

    // ===== GET PIPELINE STAGE DETAILS =====
    } else if ($action === 'get_pipeline_stage_details') {
        $stage = trim($_GET['stage'] ?? '');
        $meOrgId = (int)($jwtPayload['org_id'] ?? 1);

        if (empty($stage)) {
            $response = ['success' => false, 'message' => 'Stage name required.'];
        } else {
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.status, p.service_type, p.due_date, c.name as client_name
                FROM `projects` p
                LEFT JOIN `clients` c ON p.client_id = c.id
                WHERE p.org_id = ? AND p.pipeline_stage = ?
                ORDER BY p.id DESC
            ");
            $stmt->execute([$meOrgId, $stage]);
            $stageProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalFiles = 0;
            foreach ($stageProjects as &$sp) {
                $docStmt = $pdo->prepare("SELECT id, name, filepath, size FROM `documents` WHERE project_id = ? ORDER BY id ASC");
                $docStmt->execute([$sp['id']]);
                $sp['files'] = $docStmt->fetchAll(PDO::FETCH_ASSOC);
                $totalFiles += count($sp['files']);
            }
            unset($sp);

            $response = [
                'success'    => true,
                'stage'      => $stage,
                'total'      => count($stageProjects),
                'file_count' => $totalFiles,
                'projects'   => $stageProjects
            ];
        }

    }
} catch (Throwable $e) {
PHP;

$correct = <<<'PHP'
        }

    }
    
    // ===== GET PIPELINE STAGE DETAILS =====
    if ($action === 'get_pipeline_stage_details') {
        $stage = trim($_GET['stage'] ?? '');
        $meOrgId = (int)($jwtPayload['org_id'] ?? 1);

        if (empty($stage)) {
            $response = ['success' => false, 'message' => 'Stage name required.'];
        } else {
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.status, p.service_type, p.due_date, c.name as client_name
                FROM `projects` p
                LEFT JOIN `clients` c ON p.client_id = c.id
                WHERE p.org_id = ? AND p.pipeline_stage = ?
                ORDER BY p.id DESC
            ");
            $stmt->execute([$meOrgId, $stage]);
            $stageProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalFiles = 0;
            foreach ($stageProjects as &$sp) {
                $docStmt = $pdo->prepare("SELECT id, name, filepath, size FROM `documents` WHERE project_id = ? ORDER BY id ASC");
                $docStmt->execute([$sp['id']]);
                $sp['files'] = $docStmt->fetchAll(PDO::FETCH_ASSOC);
                $totalFiles += count($sp['files']);
            }
            unset($sp);

            $response = [
                'success'    => true,
                'stage'      => $stage,
                'total'      => count($stageProjects),
                'file_count' => $totalFiles,
                'projects'   => $stageProjects
            ];
        }
    }

} catch (Throwable $e) {
PHP;

if (strpos($content, $broken) !== false) {
    $content = str_replace($broken, $correct, $content);
    file_put_contents($f, $content);
    // Copy to htdocs too
    copy($f, 'C:/xampp/htdocs/dummy/api.php');
    echo "Fixed!\n";
} else {
    echo "Pattern not found. Showing tail:\n";
    $lines = explode("\n", $content);
    $total = count($lines);
    echo implode("\n", array_slice($lines, max(0,$total-60)));
}
