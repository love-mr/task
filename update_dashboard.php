<?php
$content = file_get_contents('c:/Users/acer/Desktop/dummy/dashboard.php');

// 1. Add PHP arrays to hold dynamic pipeline counts
$phpSnippet = <<<'PHP'
    // --- PIPELINE & SERVICES DYNAMIC COUNTS ---
    $pipelineRaw = $pdo->prepare("SELECT pipeline_stage, COUNT(*) as cnt FROM projects WHERE org_id = ? GROUP BY pipeline_stage");
    $pipelineRaw->execute([$meOrgId]);
    $pipelineCounts = [];
    foreach($pipelineRaw->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pipelineCounts[$row['pipeline_stage']] = $row['cnt'];
    }

    $servicesRaw = $pdo->prepare("SELECT service_type, COUNT(*) as cnt, SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as comp, SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pend FROM projects WHERE org_id = ? GROUP BY service_type");
    $servicesRaw->execute([$meOrgId]);
    $serviceCounts = [];
    foreach($servicesRaw->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $serviceCounts[$row['service_type']] = [
            'total' => $row['cnt'],
            'comp' => $row['comp'],
            'pend' => $row['pend']
        ];
    }
    // -------------------------------------------

    // 7. Dropdowns for modals (scoped to org)
PHP;
$content = str_replace('// 7. Dropdowns for modals (scoped to org)', $phpSnippet, $content);

// 2. Update the Pipeline display in view-dashboard
$oldPipeline = <<<'HTML'
                                <?php
                                $pipelineStages = [
                                    ['Lead Received', '#3b82f6', 12], ['Eligibility Check', '#06b6d4', 8], ['Fee Discussion', '#10b981', 10], 
                                    ['Advance Received', '#22c55e', 15], ['Draft Preparation', '#84cc16', 14], ['Client Approval', '#eab308', 9], 
                                    ['Document Collection', '#f59e0b', 11], ['Application Submitted', '#f97316', 13], ['NOC Process', '#ef4444', 16], 
                                    ['Approval Received', '#ec4899', 22], ['Project Completed', '#a855f7', 18]
                                ];
                                foreach($pipelineStages as $idx => $stage):
                                ?>
                                <div class="rsk-step">
                                    <div class="rsk-step-circle" style="background: <?= $stage[1] ?>;"><?= $idx+1 ?></div>
                                    <div class="rsk-step-title"><?= str_replace(' ', '<br>', $stage[0]) ?></div>
                                    <div class="rsk-step-count"><?= $stage[2] ?></div>
                                </div>
                                <?php endforeach; ?>
HTML;
$newPipeline = <<<'HTML'
                                <?php
                                $pipelineStages = [
                                    ['Lead Received', '#3b82f6'], ['Eligibility Check', '#06b6d4'], ['Fee Discussion', '#10b981'], 
                                    ['Advance Received', '#22c55e'], ['Draft Preparation', '#84cc16'], ['Client Approval', '#eab308'], 
                                    ['Document Collection', '#f59e0b'], ['Application Submitted', '#f97316'], ['NOC Process', '#ef4444'], 
                                    ['Approval Received', '#ec4899'], ['Project Completed', '#a855f7']
                                ];
                                foreach($pipelineStages as $idx => $stage):
                                    $count = $pipelineCounts[$stage[0]] ?? 0;
                                ?>
                                <div class="rsk-step" style="cursor: pointer;" onclick="openStageModal('<?= htmlspecialchars($stage[0], ENT_QUOTES) ?>')">
                                    <div class="rsk-step-circle" style="background: <?= $stage[1] ?>;"><?= $idx+1 ?></div>
                                    <div class="rsk-step-title" style="color: #3b82f6; text-decoration: underline;"><?= str_replace(' ', '<br>', $stage[0]) ?></div>
                                    <div class="rsk-step-count"><?= $count ?></div>
                                </div>
                                <?php endforeach; ?>
HTML;
$content = str_replace($oldPipeline, $newPipeline, $content);

// 3. Update the Service-wise display in view-dashboard
$oldServices = <<<'HTML'
                                    <?php
                                    $serviceRows = [
                                        ['Layout', 'layout', '#3b82f6', 25, 8, 17, 68],
                                        ['Building', 'home', '#3b82f6', 15, 5, 10, 67],
                                        ['Single Plot', 'square', '#eab308', 12, 2, 10, 83],
                                        ['UAL', 'maximize', '#10b981', 6, 3, 3, 50],
                                        ['Land Survey', 'map', '#10b981', 20, 4, 16, 80]
                                    ];
                                    $totTotal=0; $totPend=0; $totComp=0;
                                    foreach($serviceRows as $sr):
                                        $totTotal += $sr[3]; $totPend += $sr[4]; $totComp += $sr[5];
                                    ?>
                                    <tr>
                                        <td style="color:#0f172a; font-weight:600;"><i data-lucide="<?= $sr[1] ?>" style="width:12px; height:12px; color:<?= $sr[2] ?>; display:inline-block; vertical-align:middle; margin-right:4px;"></i> <?= $sr[0] ?></td>
                                        <td style="text-align:center;"><?= $sr[3] ?></td>
                                        <td style="text-align:center;"><?= $sr[4] ?></td>
                                        <td style="text-align:center;"><?= $sr[5] ?></td>
                                        <td>
                                            <div class="rsk-progress-bar"><div class="rsk-progress-fill" style="width:<?= $sr[6] ?>%;"></div></div>
                                            <span style="font-size:10px; color:#64748b; margin-left:4px;"><?= $sr[6] ?>%</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
HTML;
$newServices = <<<'HTML'
                                    <?php
                                    $serviceNames = [
                                        ['Layout', 'layout', '#3b82f6'],
                                        ['Building', 'home', '#3b82f6'],
                                        ['Single Plot', 'square', '#eab308'],
                                        ['UAL', 'maximize', '#10b981'],
                                        ['Land Survey', 'map', '#10b981']
                                    ];
                                    $totTotal=0; $totPend=0; $totComp=0;
                                    foreach($serviceNames as $sn):
                                        $c = $serviceCounts[$sn[0]] ?? ['total'=>0, 'comp'=>0, 'pend'=>0];
                                        $totTotal += $c['total']; $totPend += $c['pend']; $totComp += $c['comp'];
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
HTML;
$content = str_replace($oldServices, $newServices, $content);

// 4. Update the Layout menu to Approval Tasks
$startLayout = '<div id="view-layout" class="tab-view">';
$endLayout = '<!-- ==========================================================================';
$startPos = strpos($content, $startLayout);
$endPos = strpos($content, $endLayout, $startPos + 10);

$newLayoutView = <<<'HTML'
<div id="view-layout" class="tab-view">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #0f172a; margin: 0;">Approval Tasks (Pipeline Stages)</h2>
                    </div>
                    <div class="section-card" style="padding: 24px;">
                        <h3 style="margin-bottom: 20px; font-size: 16px; color: #1e293b; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px;">Pipeline Stages Definition</h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px;">
                            <?php foreach($pipelineStages as $idx => $stage): ?>
                                <div class="rsk-panel" style="padding: 16px; cursor: pointer; transition: all 0.2s;" onclick="openStageModal('<?= htmlspecialchars($stage[0], ENT_QUOTES) ?>')">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="rsk-step-circle" style="background: <?= $stage[1] ?>; margin: 0; width: 32px; height: 32px; font-size: 14px;"><?= $idx+1 ?></div>
                                        <div style="flex: 1;">
                                            <div style="font-size: 14px; font-weight: 700; color: #0f172a;"><?= htmlspecialchars($stage[0]) ?></div>
                                            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">Click to view projects</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ==========================================================================
HTML;
if ($startPos !== false && $endPos !== false) {
    $content = substr_replace($content, $newLayoutView, $startPos, $endPos - $startPos + 79);
}

// 5. Add Modal HTML and JS at the bottom of the body
$modalHtml = <<<'HTML'
    <!-- Stage Details Modal -->
    <div class="modal-overlay" id="stageModal">
        <div class="modal-content" style="max-width: 800px; width: 90%;">
            <div class="modal-header">
                <h3 id="stageModalTitle">Projects in Stage</h3>
                <button class="modal-close" onclick="closeStageModal()"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body">
                <div id="stageModalLoader" style="text-align: center; padding: 40px; color: #64748b;">
                    <i data-lucide="loader-2" class="spin" style="width: 32px; height: 32px;"></i>
                    <div style="margin-top: 10px;">Loading projects and files...</div>
                </div>
                <div id="stageModalContent" style="display: none;">
                    <div id="stageProjectsList" style="display: flex; flex-direction: column; gap: 16px;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function openStageModal(stageName) {
        document.getElementById('stageModal').style.display = 'flex';
        document.getElementById('stageModalTitle').innerText = 'Stage: ' + stageName;
        document.getElementById('stageModalLoader').style.display = 'block';
        document.getElementById('stageModalContent').style.display = 'none';
        
        fetch('api.php?action=get_pipeline_stage_details&stage=' + encodeURIComponent(stageName))
            .then(res => res.json())
            .then(data => {
                document.getElementById('stageModalLoader').style.display = 'none';
                document.getElementById('stageModalContent').style.display = 'block';
                
                const list = document.getElementById('stageProjectsList');
                list.innerHTML = '';
                
                if (data.projects.length === 0) {
                    list.innerHTML = '<div style="text-align:center; padding:30px; color:#64748b;">No projects in this stage.</div>';
                    return;
                }
                
                data.projects.forEach(p => {
                    let filesHtml = '';
                    if (p.files && p.files.length > 0) {
                        filesHtml = '<div style="margin-top:10px; padding:10px; background:#f8fafc; border-radius:6px; border:1px solid #e2e8f0;">' + 
                                    '<div style="font-size:11px; font-weight:700; color:#475569; margin-bottom:8px; text-transform:uppercase;">Uploaded Files ('+p.files.length+')</div>' +
                                    '<div style="display:flex; flex-direction:column; gap:6px;">';
                        p.files.forEach(f => {
                            filesHtml += '<div style="display:flex; align-items:center; gap:8px; font-size:12px; color:#2563eb;">' +
                                         '<i data-lucide="file-text" style="width:14px; height:14px;"></i> ' +
                                         '<a href="uploads/' + f.filepath + '" target="_blank" style="text-decoration:none; color:inherit;">' + f.name + '</a>' +
                                         '</div>';
                        });
                        filesHtml += '</div></div>';
                    } else {
                        filesHtml = '<div style="margin-top:10px; font-size:11px; color:#94a3b8; font-style:italic;">No files uploaded for this project yet.</div>';
                    }
                    
                    list.innerHTML += '<div style="border:1px solid #cbd5e1; border-radius:8px; padding:16px;">' +
                                      '<div style="display:flex; justify-content:space-between; align-items:flex-start;">' +
                                        '<div>' +
                                            '<div style="font-size:16px; font-weight:700; color:#0f172a;">' + p.name + '</div>' +
                                            '<div style="font-size:12px; color:#64748b; margin-top:4px;">Service: ' + p.service_type + '</div>' +
                                        '</div>' +
                                        '<span class="rsk-pill" style="background:#eff6ff; color:#2563eb;">' + p.status + '</span>' +
                                      '</div>' +
                                      filesHtml +
                                      '</div>';
                });
                
                if(typeof lucide !== 'undefined') lucide.createIcons();
            })
            .catch(err => {
                document.getElementById('stageModalLoader').innerHTML = 'Error loading data.';
            });
    }
    
    function closeStageModal() {
        document.getElementById('stageModal').style.display = 'none';
    }
    </script>

    <!-- External Script Load -->
HTML;
$content = str_replace('    <!-- External Script Load -->', $modalHtml, $content);

file_put_contents('c:/Users/acer/Desktop/dummy/dashboard.php', $content);
echo "Dashboard updated.";
