<?php
$dashboardPath = __DIR__ . '/dashboard.php';
$content = file_get_contents($dashboardPath);

$html = <<<HTML

    <!-- ==========================================================================
         TAB: BUILDING MODULE
         ========================================================================== -->
    <div id="view-building" class="tab-view">
        <div class="view-header">
            <h2>Building Module</h2>
            <div class="header-actions">
                <div class="search-box">
                    <i data-lucide="search"></i>
                    <input type="text" id="building-search" placeholder="Search Buildings...">
                </div>
                <button class="btn btn-primary" id="btn-add-building"><i data-lucide="plus"></i> Add Building</button>
            </div>
        </div>
        <div class="card" style="margin-top: 20px;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Total Units</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="buildings-tbody">
                        <?php foreach (\$buildingsList as \$b): ?>
                        <tr>
                            <td>#<?= \$b['id'] ?></td>
                            <td><?= htmlspecialchars(\$b['name']) ?></td>
                            <td><?= htmlspecialchars(\$b['type']) ?></td>
                            <td><?= htmlspecialchars(\$b['total_units']) ?></td>
                            <td><?= htmlspecialchars(\$b['owner_name']) ?></td>
                            <td><span class="badge badge-<?= strtolower(\$b['status']) === 'available' ? 'success' : (strtolower(\$b['status']) === 'sold' ? 'danger' : 'warning') ?>"><?= htmlspecialchars(\$b['status']) ?></span></td>
                            <td>
                                <button class="btn-icon btn-edit-building" data-id="<?= \$b['id'] ?>" data-json='<?= json_encode(\$b, JSON_HEX_APOS) ?>'><i data-lucide="edit-2"></i></button>
                                <button class="btn-icon btn-delete-building" data-id="<?= \$b['id'] ?>"><i data-lucide="trash-2" style="color:#ef4444;"></i></button>
                                <?php if(\$b['document_path']): ?>
                                <a href="<?= htmlspecialchars(\$b['document_path']) ?>" target="_blank" class="btn-icon" title="View Document"><i data-lucide="file-text"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty(\$buildingsList)): ?>
                        <tr><td colspan="7" style="text-align:center;">No buildings found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Building -->
    <div class="modal-overlay" id="modal-building">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modal-building-title">Add Building</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-building" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="building-action" value="create_building">
                <input type="hidden" name="id" id="building-id">
                <div class="modal-body" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group"><label>Building Name</label><input type="text" name="name" id="building-name" class="form-control" required></div>
                    <div class="form-group"><label>Type</label><input type="text" name="type" id="building-type" class="form-control"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Address</label><textarea name="address" id="building-address" class="form-control" rows="2"></textarea></div>
                    <div class="form-group"><label>Total Floors</label><input type="number" name="total_floors" id="building-floors" class="form-control"></div>
                    <div class="form-group"><label>Total Units</label><input type="number" name="total_units" id="building-units" class="form-control"></div>
                    <div class="form-group"><label>Total Area (sq.ft)</label><input type="number" step="0.01" name="total_area" id="building-area" class="form-control"></div>
                    <div class="form-group"><label>Owner Name</label><input type="text" name="owner_name" id="building-owner" class="form-control"></div>
                    <div class="form-group"><label>Contact Number</label><input type="text" name="contact_number" id="building-contact" class="form-control"></div>
                    <div class="form-group"><label>Status</label><select name="status" id="building-status" class="form-control"><option value="Available">Available</option><option value="Sold">Sold</option><option value="Rented">Rented</option></select></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Document Upload</label><input type="file" name="document" id="building-doc" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Building</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         TAB: SINGLE PLOT MODULE
         ========================================================================== -->
    <div id="view-singleplot" class="tab-view">
        <div class="view-header">
            <h2>Single Plot Module</h2>
            <div class="header-actions">
                <div class="search-box">
                    <i data-lucide="search"></i>
                    <input type="text" id="singleplot-search" placeholder="Search Plots...">
                </div>
                <button class="btn btn-primary" id="btn-add-singleplot"><i data-lucide="plus"></i> Add Plot</button>
            </div>
        </div>
        <div class="card" style="margin-top: 20px;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Plot No.</th>
                            <th>Layout Name</th>
                            <th>Area</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="singleplot-tbody">
                        <?php foreach (\$singlePlotsList as \$p): ?>
                        <tr>
                            <td><?= htmlspecialchars(\$p['plot_number']) ?></td>
                            <td><?= htmlspecialchars(\$p['layout_name']) ?></td>
                            <td><?= htmlspecialchars(\$p['area']) ?></td>
                            <td>₹<?= htmlspecialchars(\$p['price']) ?></td>
                            <td><span class="badge badge-<?= strtolower(\$p['status']) === 'available' ? 'success' : 'warning' ?>"><?= htmlspecialchars(\$p['status']) ?></span></td>
                            <td>
                                <button class="btn-icon btn-edit-singleplot" data-id="<?= \$p['id'] ?>" data-json='<?= json_encode(\$p, JSON_HEX_APOS) ?>'><i data-lucide="edit-2"></i></button>
                                <button class="btn-icon btn-delete-singleplot" data-id="<?= \$p['id'] ?>"><i data-lucide="trash-2" style="color:#ef4444;"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty(\$singlePlotsList)): ?>
                        <tr><td colspan="6" style="text-align:center;">No plots found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Single Plot -->
    <div class="modal-overlay" id="modal-singleplot">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modal-singleplot-title">Add Plot</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-singleplot" method="POST">
                <input type="hidden" name="action" id="singleplot-action" value="create_single_plot">
                <input type="hidden" name="id" id="singleplot-id">
                <div class="modal-body" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group"><label>Plot Number</label><input type="text" name="plot_number" id="sp-plot" class="form-control" required></div>
                    <div class="form-group"><label>Layout Name</label><input type="text" name="layout_name" id="sp-layout" class="form-control"></div>
                    <div class="form-group"><label>Survey Number</label><input type="text" name="survey_number" id="sp-survey" class="form-control"></div>
                    <div class="form-group"><label>Area</label><input type="number" step="0.01" name="area" id="sp-area" class="form-control"></div>
                    <div class="form-group"><label>Location</label><input type="text" name="location" id="sp-location" class="form-control"></div>
                    <div class="form-group"><label>Price</label><input type="number" step="0.01" name="price" id="sp-price" class="form-control"></div>
                    <div class="form-group"><label>Facing Direction</label><input type="text" name="facing_direction" id="sp-facing" class="form-control"></div>
                    <div class="form-group"><label>Status</label><select name="status" id="sp-status" class="form-control"><option value="Available">Available</option><option value="Sold">Sold</option><option value="Reserved">Reserved</option></select></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Owner Name</label><input type="text" name="owner_name" id="sp-owner" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Plot</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         TAB: UAL MODULE
         ========================================================================== -->
    <div id="view-ual" class="tab-view">
        <div class="view-header">
            <h2>UAL Module</h2>
            <div class="header-actions">
                <div class="search-box">
                    <i data-lucide="search"></i>
                    <input type="text" id="ual-search" placeholder="Search UAL...">
                </div>
                <button class="btn btn-primary" id="btn-add-ual"><i data-lucide="plus"></i> Add UAL Record</button>
            </div>
        </div>
        <div class="card" style="margin-top: 20px;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Case No.</th>
                            <th>Owner Name</th>
                            <th>Total Land</th>
                            <th>Excess Land</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ual-tbody">
                        <?php foreach (\$ualRecordsList as \$u): ?>
                        <tr>
                            <td><?= htmlspecialchars(\$u['case_number']) ?></td>
                            <td><?= htmlspecialchars(\$u['owner_name']) ?></td>
                            <td><?= htmlspecialchars(\$u['total_land_area']) ?></td>
                            <td><?= htmlspecialchars(\$u['excess_land_area']) ?></td>
                            <td><span class="badge badge-<?= strtolower(\$u['approval_status']) === 'approved' ? 'success' : 'warning' ?>"><?= htmlspecialchars(\$u['approval_status']) ?></span></td>
                            <td>
                                <button class="btn-icon btn-edit-ual" data-id="<?= \$u['id'] ?>" data-json='<?= json_encode(\$u, JSON_HEX_APOS) ?>'><i data-lucide="edit-2"></i></button>
                                <button class="btn-icon btn-delete-ual" data-id="<?= \$u['id'] ?>"><i data-lucide="trash-2" style="color:#ef4444;"></i></button>
                                <?php if(\$u['document_path']): ?>
                                <a href="<?= htmlspecialchars(\$u['document_path']) ?>" target="_blank" class="btn-icon" title="View Document"><i data-lucide="file-text"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty(\$ualRecordsList)): ?>
                        <tr><td colspan="6" style="text-align:center;">No UAL records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: UAL -->
    <div class="modal-overlay" id="modal-ual">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modal-ual-title">Add UAL Record</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-ual" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="ual-action" value="create_ual_record">
                <input type="hidden" name="id" id="ual-id">
                <div class="modal-body" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group"><label>Case Number</label><input type="text" name="case_number" id="ual-case" class="form-control" required></div>
                    <div class="form-group"><label>Owner Name</label><input type="text" name="owner_name" id="ual-owner" class="form-control"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Address</label><textarea name="address" id="ual-address" class="form-control" rows="2"></textarea></div>
                    <div class="form-group"><label>Total Land Area</label><input type="number" step="0.01" name="total_land_area" id="ual-total" class="form-control"></div>
                    <div class="form-group"><label>Gov Ceiling Limit</label><input type="number" step="0.01" name="gov_ceiling_limit" id="ual-limit" class="form-control"></div>
                    <div class="form-group"><label>Excess Land Area</label><input type="number" step="0.01" name="excess_land_area" id="ual-excess" class="form-control"></div>
                    <div class="form-group"><label>Gov Order Number</label><input type="text" name="gov_order_number" id="ual-order" class="form-control"></div>
                    <div class="form-group"><label>Approval Status</label><select name="approval_status" id="ual-status" class="form-control"><option value="Pending">Pending</option><option value="Approved">Approved</option><option value="Rejected">Rejected</option></select></div>
                    <div class="form-group"><label>Document Upload</label><input type="file" name="document" id="ual-doc" class="form-control"></div>
                    <div class="form-group" style="grid-column: span 2;"><label>Remarks</label><input type="text" name="remarks" id="ual-remarks" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Record</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================================================
         TAB: LAND SURVEY MODULE
         ========================================================================== -->
    <div id="view-landsurvey" class="tab-view">
        <div class="view-header">
            <h2>Land Survey Module</h2>
            <div class="header-actions">
                <div class="search-box">
                    <i data-lucide="search"></i>
                    <input type="text" id="landsurvey-search" placeholder="Search Surveys...">
                </div>
                <button class="btn btn-primary" id="btn-add-landsurvey"><i data-lucide="plus"></i> Add Survey</button>
            </div>
        </div>
        <div class="card" style="margin-top: 20px;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Survey No.</th>
                            <th>Village</th>
                            <th>Owner Name</th>
                            <th>Total Area</th>
                            <th>Land Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="landsurvey-tbody">
                        <?php foreach (\$landSurveysList as \$s): ?>
                        <tr>
                            <td><?= htmlspecialchars(\$s['survey_number']) ?></td>
                            <td><?= htmlspecialchars(\$s['village_name']) ?></td>
                            <td><?= htmlspecialchars(\$s['owner_name']) ?></td>
                            <td><?= htmlspecialchars(\$s['total_area']) ?></td>
                            <td><?= htmlspecialchars(\$s['land_type']) ?></td>
                            <td>
                                <button class="btn-icon btn-edit-landsurvey" data-id="<?= \$s['id'] ?>" data-json='<?= json_encode(\$s, JSON_HEX_APOS) ?>'><i data-lucide="edit-2"></i></button>
                                <button class="btn-icon btn-delete-landsurvey" data-id="<?= \$s['id'] ?>"><i data-lucide="trash-2" style="color:#ef4444;"></i></button>
                                <?php if(\$s['document_path']): ?>
                                <a href="<?= htmlspecialchars(\$s['document_path']) ?>" target="_blank" class="btn-icon" title="View Document"><i data-lucide="file-text"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty(\$landSurveysList)): ?>
                        <tr><td colspan="6" style="text-align:center;">No land surveys found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Land Survey -->
    <div class="modal-overlay" id="modal-landsurvey">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modal-landsurvey-title">Add Survey Record</h3>
                <button class="modal-close"><i data-lucide="x"></i></button>
            </div>
            <form id="form-landsurvey" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="landsurvey-action" value="create_land_survey">
                <input type="hidden" name="id" id="landsurvey-id">
                <div class="modal-body" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div class="form-group"><label>Survey Number</label><input type="text" name="survey_number" id="ls-survey" class="form-control" required></div>
                    <div class="form-group"><label>Village Name</label><input type="text" name="village_name" id="ls-village" class="form-control"></div>
                    <div class="form-group"><label>Taluk</label><input type="text" name="taluk" id="ls-taluk" class="form-control"></div>
                    <div class="form-group"><label>District</label><input type="text" name="district" id="ls-district" class="form-control"></div>
                    <div class="form-group"><label>Land Type</label><input type="text" name="land_type" id="ls-type" class="form-control"></div>
                    <div class="form-group"><label>Owner Name</label><input type="text" name="owner_name" id="ls-owner" class="form-control"></div>
                    <div class="form-group"><label>Total Area</label><input type="number" step="0.01" name="total_area" id="ls-area" class="form-control"></div>
                    <div class="form-group"><label>Latitude</label><input type="text" name="latitude" id="ls-lat" class="form-control"></div>
                    <div class="form-group"><label>Longitude</label><input type="text" name="longitude" id="ls-long" class="form-control"></div>
                    <div class="form-group"><label>Document Upload</label><input type="file" name="document" id="ls-doc" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Survey</button>
                </div>
            </form>
        </div>
    </div>

HTML;

$search = "<!-- Export DB variables for JS rendering -->";
if (strpos($content, $html) === false) {
    $content = str_replace($search, $html . "\n" . $search, $content);
    file_put_contents($dashboardPath, $content);
    echo "Injected views.";
} else {
    echo "Already injected.";
}
?>
