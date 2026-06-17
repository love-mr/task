<?php
$file = 'C:/xampp/htdocs/dummy/dashboard.php';
$content = file_get_contents($file);

$services = [
    'building' => 'Building',
    'singleplot' => 'Single Plot',
    'ual' => 'UAL',
    'landsurvey' => 'Land Survey'
];

$viewsHtml = "";
foreach ($services as $id => $title) {
    $viewsHtml .= <<<HTML

                <!-- ==========================================================================
                     TAB: $title MODULE VIEW
                     ========================================================================== -->
                <div id="view-$id" class="tab-view">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0;">$title Tasks (Pipeline Stages)</h2>
                    </div>
                    <div class="section-card" style="padding: 24px;">
                        <h3 style="margin-bottom: 20px; font-size: 16px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">Pipeline Stages Definition</h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
                            <?php foreach(\$pipelineStages as \$idx => \$stage): ?>
                                <div class="rsk-panel" style="padding: 16px; cursor: pointer; transition: all 0.2s;" onclick="openStageModal('<?= htmlspecialchars(\$stage[0], ENT_QUOTES) ?>')">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="rsk-step-circle" style="background: <?= \$stage[1] ?>; margin: 0; width: 32px; height: 32px; font-size: 14px;"><?= \$idx+1 ?></div>
                                        <div style="flex: 1;">
                                            <div style="font-size: 14px; font-weight: 700; color: #0f172a;"><?= htmlspecialchars(\$stage[0]) ?></div>
                                            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Click to view projects</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
HTML;
}

$find = '<div id="view-tasks" class="tab-view">';

if (strpos($content, $find) !== false && strpos($content, 'id="view-building"') === false) {
    $content = str_replace($find, $viewsHtml . "\n" . $find, $content);
    file_put_contents($file, $content);
    
    // Also update the local copy
    $localFile = 'c:/Users/acer/Desktop/dummy/dashboard.php';
    if (file_exists($localFile)) {
        file_put_contents($localFile, $content);
    }
    echo "Views injected successfully!\n";
} else {
    echo "Find string not found or views already exist.\n";
}
