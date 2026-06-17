<?php
$appJsPath = __DIR__ . '/app.js';
$content = file_get_contents($appJsPath);

$jsCode = <<<JS

    // ==========================================
    // SURVEY MANAGEMENT MODULE
    // ==========================================

    const btnAddSurvey = document.getElementById('btn-add-survey');
    if (btnAddSurvey) {
        btnAddSurvey.addEventListener('click', function() {
            document.getElementById('form-surveymanagement').reset();
            document.getElementById('modal-surveymanagement-title').textContent = 'Add Survey Record';
            document.getElementById('sm-action').value = 'create_survey_record';
            document.getElementById('sm-id').value = '';
            document.getElementById('modal-surveymanagement').classList.add('active');
        });
    }

    const formSurvey = document.getElementById('form-surveymanagement');
    if (formSurvey) {
        formSurvey.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred.');
            });
        });
    }

    document.addEventListener('click', function(e) {
        // Edit Survey
        const editBtn = e.target.closest('.edit-survey');
        if (editBtn) {
            const data = JSON.parse(editBtn.getAttribute('data-json'));
            document.getElementById('modal-surveymanagement-title').textContent = 'Edit Survey Record';
            document.getElementById('sm-action').value = 'update_survey_record';
            document.getElementById('sm-id').value = data.id;
            
            document.getElementById('sm-survey-number').value = data.survey_number || '';
            document.getElementById('sm-sub-division-number').value = data.sub_division_number || '';
            document.getElementById('sm-owner-name').value = data.owner_name || '';
            document.getElementById('sm-village-name').value = data.village_name || '';
            document.getElementById('sm-taluk').value = data.taluk || '';
            document.getElementById('sm-district').value = data.district || '';
            document.getElementById('sm-land-type').value = data.land_type || '';
            document.getElementById('sm-total-area').value = data.total_area || '';
            document.getElementById('sm-patta-number').value = data.patta_number || '';
            document.getElementById('sm-fmb-number').value = data.fmb_number || '';
            document.getElementById('sm-latitude').value = data.latitude || '';
            document.getElementById('sm-longitude').value = data.longitude || '';
            document.getElementById('sm-survey-date').value = data.survey_date || '';
            document.getElementById('sm-status').value = data.status || 'Pending';
            document.getElementById('sm-remarks').value = data.remarks || '';
            
            document.getElementById('modal-surveymanagement').classList.add('active');
        }

        // Archive Survey
        const archiveBtn = e.target.closest('.archive-survey');
        if (archiveBtn) {
            if (confirm('Are you sure you want to archive this survey record?')) {
                const id = archiveBtn.getAttribute('data-id');
                const formData = new FormData();
                formData.append('action', 'archive_survey_record');
                formData.append('id', id);
                
                fetch('api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
        }

        // Verify Survey
        const verifyBtn = e.target.closest('.verify-survey');
        if (verifyBtn) {
            const id = verifyBtn.getAttribute('data-id');
            const newStatus = prompt("Enter new status (Pending, Verified, Rejected):", "Verified");
            if (newStatus && ['Pending', 'Verified', 'Rejected'].includes(newStatus)) {
                const formData = new FormData();
                formData.append('action', 'verify_survey_record');
                formData.append('id', id);
                formData.append('status', newStatus);
                
                fetch('api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            } else if (newStatus) {
                alert("Invalid status. Must be Pending, Verified, or Rejected.");
            }
        }

        // History Survey
        const historyBtn = e.target.closest('.history-survey');
        if (historyBtn) {
            const id = historyBtn.getAttribute('data-id');
            const contentDiv = document.getElementById('survey-history-content');
            contentDiv.innerHTML = '<p style="text-align:center;">Loading...</p>';
            document.getElementById('modal-survey-history').classList.add('active');
            
            fetch('api.php?action=get_survey_history&id=' + id)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.history.length === 0) {
                        contentDiv.innerHTML = '<p style="text-align:center; color:#64748b;">No history found.</p>';
                    } else {
                        let html = '<div style="display:flex; flex-direction:column; gap:12px;">';
                        data.history.forEach(h => {
                            html += `
                                <div style="border-left: 3px solid #3b82f6; padding-left: 12px; margin-bottom: 10px;">
                                    <div style="font-size: 11px; color: #64748b;">\${h.created_at} by \${h.user_name || 'System'}</div>
                                    <div style="font-weight: 600; color: #0f172a;">\${h.action}</div>
                                    <div style="font-size: 13px; color: #475569;">\${h.details || ''}</div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        contentDiv.innerHTML = html;
                    }
                } else {
                    contentDiv.innerHTML = '<p style="color:red;">Error loading history.</p>';
                }
            });
        }
    });

    // Export CSV
    const btnExportSurvey = document.getElementById('btn-export-survey');
    if (btnExportSurvey) {
        btnExportSurvey.addEventListener('click', function() {
            const number = document.getElementById('filter-survey-number').value;
            const village = document.getElementById('filter-survey-village').value;
            const taluk = document.getElementById('filter-survey-taluk').value;
            const district = document.getElementById('filter-survey-district').value;
            const status = document.getElementById('filter-survey-status').value;
            
            let url = 'api.php?action=export_survey_csv';
            if (number) url += '&survey_number=' + encodeURIComponent(number);
            if (village) url += '&village_name=' + encodeURIComponent(village);
            if (taluk) url += '&taluk=' + encodeURIComponent(taluk);
            if (district) url += '&district=' + encodeURIComponent(district);
            if (status) url += '&status=' + encodeURIComponent(status);
            
            window.location.href = url;
        });
    }

    // Frontend Filtering for Table
    const filterInputs = [
        document.getElementById('filter-survey-number'),
        document.getElementById('filter-survey-village'),
        document.getElementById('filter-survey-taluk'),
        document.getElementById('filter-survey-district'),
        document.getElementById('filter-survey-status')
    ];
    
    function applySurveyFilters() {
        const numberVal = filterInputs[0].value.toLowerCase();
        const villageVal = filterInputs[1].value.toLowerCase();
        const talukVal = filterInputs[2].value.toLowerCase();
        const districtVal = filterInputs[3].value.toLowerCase();
        const statusVal = filterInputs[4].value.toLowerCase();
        
        const tbody = document.getElementById('survey-management-tbody');
        if (!tbody) return;
        
        const rows = tbody.querySelectorAll('tr');
        rows.forEach(row => {
            if (row.cells.length < 5) return; // Skip empty row message
            
            const textNumber = row.cells[0].textContent.toLowerCase();
            const textVillage = row.cells[2].textContent.split(',')[0].toLowerCase(); // Hacky but works for village text
            const textTalukDistrict = row.cells[2].textContent.toLowerCase();
            const textStatus = row.cells[4].textContent.toLowerCase();
            
            let match = true;
            if (numberVal && !textNumber.includes(numberVal)) match = false;
            if (villageVal && !textTalukDistrict.includes(villageVal)) match = false;
            if (talukVal && !textTalukDistrict.includes(talukVal)) match = false;
            if (districtVal && !textTalukDistrict.includes(districtVal)) match = false;
            if (statusVal && !textStatus.includes(statusVal)) match = false;
            
            row.style.display = match ? '' : 'none';
        });
    }

    filterInputs.forEach(input => {
        if (input) {
            input.addEventListener('input', applySurveyFilters);
            input.addEventListener('change', applySurveyFilters);
        }
    });

    const btnClearFilters = document.getElementById('btn-clear-survey-filters');
    if (btnClearFilters) {
        btnClearFilters.addEventListener('click', function() {
            filterInputs.forEach(input => { if (input) input.value = ''; });
            applySurveyFilters();
        });
    }

JS;

$content = preg_replace('/(\}\);[\r\n]*)$/', $jsCode . "\n$1", $content);
file_put_contents($appJsPath, $content);
echo "JS Injection successful.";
