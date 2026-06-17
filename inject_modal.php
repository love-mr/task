<?php
$f = 'C:/xampp/htdocs/dummy/dashboard.php';
$c = file_get_contents($f);

$modalHtml = <<<'HTML'
    <!-- Stage Details Modal -->
    <div class="modal-overlay" id="stageModal">
        <div class="modal-container" style="max-width: 600px; width: 90%;">
            <div class="modal-header">
                <h3 id="stageModalTitle">Projects in Stage</h3>
                <button class="modal-close" onclick="closeStageModal()"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body" style="min-height: 200px;">
                <div id="stageModalLoader" style="text-align: center; padding: 40px; color: #64748b;">
                    <i data-lucide="loader-2" class="spin" style="width: 32px; height: 32px; animation: spin 1s linear infinite;"></i>
                    <p style="margin-top: 10px;">Loading projects...</p>
                </div>
                <div id="stageModalContent" style="display: none;">
                    <div id="stageProjectsList" style="display: flex; flex-direction: column; gap: 12px;"></div>
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
                
                if (!data.projects || data.projects.length === 0) {
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
                                        '<span class="rsk-pill" style="background:#eff6ff; color:#2563eb; padding: 4px 8px; border-radius: 99px; font-size: 11px;">' + p.status + '</span>' +
                                      '</div>' +
                                      filesHtml +
                                      '</div>';
                });
                
                if(typeof lucide !== 'undefined') {
                    try { lucide.createIcons(); } catch(e){}
                }
            })
            .catch(err => {
                document.getElementById('stageModalLoader').innerHTML = 'Error loading data.';
            });
    }
    
    function closeStageModal() {
        document.getElementById('stageModal').style.display = 'none';
    }
    </script>
    <style>
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
HTML;

if (strpos($c, 'stageModal') === false) {
    $c = str_replace('</body>', $modalHtml . "\n</body>", $c);
    file_put_contents($f, $c);
    
    $localFile = 'c:/Users/acer/Desktop/dummy/dashboard.php';
    if (file_exists($localFile)) {
        file_put_contents($localFile, $c);
    }
    echo "Modal injected successfully!\n";
} else {
    echo "Modal already injected!\n";
}
