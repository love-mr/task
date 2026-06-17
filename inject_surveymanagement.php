<?php
$dashboardPath = __DIR__ . '/dashboard.php';
$content = file_get_contents($dashboardPath);

// The view HTML
$tabView = <<<HTML

                <!-- TAB: SURVEY MANAGEMENT -->
                <div id="view-surveymanagement" class="tab-view" style="display: none;">
                    <div class="view-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="font-size: 24px; font-weight: 700; color: #0f172a; margin: 0;">Survey Management</h2>
                        <div style="display: flex; gap: 10px;">
                            <button class="btn btn-primary" id="btn-add-survey" style="background-color: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">
                                <i data-lucide="plus"></i> Add Survey Record
                            </button>
                            <button class="btn btn-secondary" id="btn-export-survey" style="background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; cursor: pointer;">
                                <i data-lucide="download"></i> Generate Report (CSV)
                            </button>
                        </div>
                    </div>

                    <!-- Filter Bar -->
                    <div style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="text" id="filter-survey-number" placeholder="Search Survey Number..." style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; flex: 1; min-width: 150px;">
                        <input type="text" id="filter-survey-village" placeholder="Village" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 130px;">
                        <input type="text" id="filter-survey-taluk" placeholder="Taluk" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 130px;">
                        <input type="text" id="filter-survey-district" placeholder="District" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 130px;">
                        <select id="filter-survey-status" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Verified">Verified</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                        <button class="btn btn-secondary" id="btn-clear-survey-filters" style="padding: 8px 12px; border-radius: 6px; cursor: pointer;">Clear</button>
                    </div>

                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569;">
                                    <th style="padding: 12px 16px;">Survey / Sub Div</th>
                                    <th style="padding: 12px 16px;">Owner Name</th>
                                    <th style="padding: 12px 16px;">Location</th>
                                    <th style="padding: 12px 16px;">Total Area</th>
                                    <th style="padding: 12px 16px;">Status</th>
                                    <th style="padding: 12px 16px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="survey-management-tbody">
                                <?php if (!empty(\$surveyManagementList)): ?>
                                    <?php foreach (\$surveyManagementList as \$s): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 16px;">
                                            <div style="font-weight: 600; color: #0f172a;"><?= htmlspecialchars(\$s['survey_number']) ?></div>
                                            <div style="font-size: 12px; color: #64748b;">Sub: <?= htmlspecialchars(\$s['sub_division_number']) ?></div>
                                        </td>
                                        <td style="padding: 12px 16px;"><?= htmlspecialchars(\$s['owner_name']) ?></td>
                                        <td style="padding: 12px 16px;">
                                            <div><?= htmlspecialchars(\$s['village_name']) ?></div>
                                            <div style="font-size: 12px; color: #64748b;"><?= htmlspecialchars(\$s['taluk']) ?>, <?= htmlspecialchars(\$s['district']) ?></div>
                                        </td>
                                        <td style="padding: 12px 16px;"><?= htmlspecialchars(\$s['total_area']) ?></td>
                                        <td style="padding: 12px 16px;">
                                            <?php 
                                            \$color = '#64748b'; \$bg = '#f1f5f9';
                                            if (\$s['status'] === 'Verified') { \$color = '#15803d'; \$bg = '#dcfce7'; }
                                            if (\$s['status'] === 'Pending') { \$color = '#b45309'; \$bg = '#fef3c7'; }
                                            if (\$s['status'] === 'Rejected') { \$color = '#b91c1c'; \$bg = '#fee2e2'; }
                                            ?>
                                            <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; color: <?= \$color ?>; background: <?= \$bg ?>;">
                                                <?= htmlspecialchars(\$s['status']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px 16px; display: flex; gap: 8px;">
                                            <button class="btn-action edit-survey" data-id="<?= \$s['id'] ?>" data-json='<?= htmlspecialchars(json_encode(\$s), ENT_QUOTES, 'UTF-8') ?>' style="background:none; border:none; color:#3b82f6; cursor:pointer;" title="Edit">
                                                <i data-lucide="edit-3" style="width:16px;height:16px;"></i>
                                            </button>
                                            <button class="btn-action verify-survey" data-id="<?= \$s['id'] ?>" style="background:none; border:none; color:#10b981; cursor:pointer;" title="Verify">
                                                <i data-lucide="check-circle" style="width:16px;height:16px;"></i>
                                            </button>
                                            <button class="btn-action history-survey" data-id="<?= \$s['id'] ?>" style="background:none; border:none; color:#8b5cf6; cursor:pointer;" title="History">
                                                <i data-lucide="clock" style="width:16px;height:16px;"></i>
                                            </button>
                                            <button class="btn-action archive-survey" data-id="<?= \$s['id'] ?>" style="background:none; border:none; color:#ef4444; cursor:pointer;" title="Archive">
                                                <i data-lucide="archive" style="width:16px;height:16px;"></i>
                                            </button>
                                            <?php if (\$s['document_path']): ?>
                                            <a href="<?= htmlspecialchars(\$s['document_path']) ?>" target="_blank" style="color:#06b6d4;" title="View Document">
                                                <i data-lucide="file-text" style="width:16px;height:16px;"></i>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="padding: 30px; text-align: center; color: #94a3b8;">No active survey records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

HTML;

// Modals HTML
$modals = <<<HTML

    <!-- Modal: Survey Management -->
    <div class="modal-overlay" id="modal-surveymanagement">
        <div class="modal-container" style="max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3 id="modal-surveymanagement-title">Add Survey Record</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-surveymanagement" enctype="multipart/form-data">
                <input type="hidden" name="id" id="sm-id">
                <input type="hidden" name="action" id="sm-action" value="create_survey_record">
                <div class="modal-body" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Survey Number *</label>
                        <input type="text" name="survey_number" id="sm-survey-number" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Sub Division Number</label>
                        <input type="text" name="sub_division_number" id="sm-sub-division-number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Owner Name</label>
                        <input type="text" name="owner_name" id="sm-owner-name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Village Name</label>
                        <input type="text" name="village_name" id="sm-village-name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Taluk</label>
                        <input type="text" name="taluk" id="sm-taluk" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>District</label>
                        <input type="text" name="district" id="sm-district" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Land Type</label>
                        <input type="text" name="land_type" id="sm-land-type" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Total Area</label>
                        <input type="number" step="0.01" name="total_area" id="sm-total-area" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Patta Number</label>
                        <input type="text" name="patta_number" id="sm-patta-number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>FMB Number</label>
                        <input type="text" name="fmb_number" id="sm-fmb-number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Latitude</label>
                        <input type="text" name="latitude" id="sm-latitude" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Longitude</label>
                        <input type="text" name="longitude" id="sm-longitude" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Survey Date</label>
                        <input type="date" name="survey_date" id="sm-survey-date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="sm-status" class="form-control">
                            <option value="Pending">Pending</option>
                            <option value="Verified">Verified</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Remarks</label>
                        <textarea name="remarks" id="sm-remarks" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Upload Document (Optional)</label>
                        <input type="file" name="document" id="sm-document" class="form-control">
                    </div>
                </div>
                <div class="modal-footer" style="grid-column: span 2;">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background-color: #2563eb;">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Survey History -->
    <div class="modal-overlay" id="modal-survey-history">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3>Survey Record History</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body">
                <div id="survey-history-content" style="max-height: 400px; overflow-y: auto;">
                    <p style="text-align: center; color: #64748b;">Loading history...</p>
                </div>
            </div>
        </div>
    </div>

HTML;

// 1. Inject Tab View
// Find <!-- Footer --> and insert right before it (which is inside .content-area div end)
$content = preg_replace('/(<!-- Footer -->)/i', $tabView . "\n$1", $content);

// 2. Inject Modals
// Find <!-- Modal: Document Preview -->
$content = preg_replace('/(<!-- Modal: Document Preview -->)/i', $modals . "\n$1", $content);

file_put_contents($dashboardPath, $content);
echo "Injection successful.";
