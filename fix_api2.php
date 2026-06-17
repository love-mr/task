<?php
$f = 'C:/Users/acer/Desktop/dummy/api.php';
$content = file_get_contents($f);

// Find the position where our pipeline block starts
$marker = '    // ===== GET PIPELINE STAGE DETAILS =====';
$pos = strpos($content, $marker);

if ($pos === false) {
    die("Marker not found!\n");
}

// Remove everything from the marker to the end of the file
$beforePipeline = substr($content, 0, $pos);

// Append the correct code
$pipelineCode = <<<'PHP'
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
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
PHP;

$newContent = $beforePipeline . $pipelineCode;
file_put_contents($f, $newContent);
copy($f, 'C:/xampp/htdocs/dummy/api.php');
echo "Rewritten!\n";
