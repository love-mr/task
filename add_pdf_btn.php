<?php
$dashboardPath = __DIR__ . '/dashboard.php';
$content = file_get_contents($dashboardPath);

$btn = '<button class="btn btn-secondary" id="btn-export-survey-pdf" style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; cursor: pointer; margin-left: 10px;" onclick="window.print()"><i data-lucide="file-text"></i> Generate PDF</button>';

$content = str_replace('Generate Report (CSV)', 'Generate CSV', $content);
$content = preg_replace('/(id="btn-export-survey".*?<\/button>)/s', "$1\n                            $btn", $content);

file_put_contents($dashboardPath, $content);
echo "PDF button added.";
