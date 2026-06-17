                <div id="view-dashboard" class="tab-view active">
                    <!-- EXACT REPLICA OF RSK DASHBOARD -->
                    <style>
                        /* Custom RSK Dashboard Styles */
                        .rsk-grid-8 { display: grid; grid-template-columns: repeat(8, 1fr); gap: 12px; margin-bottom: 20px; }
                        .rsk-card-sm { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 10px; display: flex; flex-direction: column; align-items: center; justify-content: space-between; text-align: center; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
                        .rsk-icon-sm { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; }
                        .rsk-card-title { font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 4px; line-height: 1.2; height: 26px; display: flex; align-items: center; text-transform: capitalize; }
                        .rsk-card-value { font-size: 20px; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
                        .rsk-card-link { font-size: 10px; color: #3b82f6; text-decoration: none; font-weight: 600; }
                        .rsk-card-link:hover { text-decoration: underline; }

                        .rsk-row-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
                        .rsk-row-3 { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 20px; margin-bottom: 20px; }
                        .rsk-row-4 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px; }

                        .rsk-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; flex-direction: column; }
                        .rsk-panel-title { font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; }
                        .rsk-panel-link { font-size: 11px; color: #3b82f6; text-decoration: none; font-weight: 600; }

                        /* Pipeline Stepper */
                        .rsk-stepper { display: flex; justify-content: space-between; align-items: flex-start; position: relative; margin-top: 10px; padding: 0 10px; }
                        .rsk-stepper::before { content: ''; position: absolute; top: 12px; left: 20px; right: 20px; height: 2px; background: #e2e8f0; z-index: 1; }
                        .rsk-step { display: flex; flex-direction: column; align-items: center; z-index: 2; position: relative; width: 60px; }
                        .rsk-step-circle { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: #fff; margin-bottom: 8px; border: 2px solid #fff; box-shadow: 0 0 0 1px #e2e8f0; }
                        .rsk-step-title { font-size: 9px; font-weight: 600; color: #475569; text-align: center; line-height: 1.2; height: 22px; }
                        .rsk-step-count { background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; color: #0f172a; margin-top: 8px; }

                        /* Custom Tables */
                        .rsk-table { width: 100%; border-collapse: collapse; font-size: 11px; }
                        .rsk-table th { text-align: left; padding: 8px; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
                        .rsk-table td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; color: #0f172a; font-weight: 500; }
                        .rsk-progress-bar { height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden; width: 60px; display: inline-block; vertical-align: middle; }
                        .rsk-progress-fill { height: 100%; background: #10b981; border-radius: 3px; }

                        /* Quick Actions */
                        .rsk-qa-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
                        .rsk-qa-btn { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 6px; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: all 0.2s; }
                        .rsk-qa-btn:hover { border-color: #3b82f6; box-shadow: 0 2px 8px rgba(37,99,235,0.1); }
                        .rsk-qa-icon { width: 24px; height: 24px; margin-bottom: 6px; display: flex; align-items: center; justify-content: center; }
                        .rsk-qa-text { font-size: 10px; font-weight: 600; color: #475569; text-align: center; }

                        /* Pills */
                        .rsk-pill { padding: 4px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; display: inline-block; }
                    </style>

                    <!-- Top 8 Cards -->
                    <div class="rsk-grid-8">
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#eff6ff; color:#3b82f6;"><i data-lucide="folder"></i></div>
                            <div class="rsk-card-title">Total<br>Projects</div>
                            <div class="rsk-card-value"><?= $rsk_totalProjects ?></div>
                            <a href="#projects" class="rsk-card-link tab-trigger" data-target="projects">View all projects &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#fef3c7; color:#f59e0b;"><i data-lucide="hourglass"></i></div>
                            <div class="rsk-card-title">Pending<br>Projects</div>
                            <div class="rsk-card-value"><?= $rsk_pendingProjects ?></div>
                            <a href="#projects" class="rsk-card-link tab-trigger" data-target="projects">View pending &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#dcfce7; color:#10b981;"><i data-lucide="check-circle-2"></i></div>
                            <div class="rsk-card-title">Approved<br>Projects</div>
                            <div class="rsk-card-value"><?= $rsk_approvedProjects ?></div>
                            <a href="#projects" class="rsk-card-link tab-trigger" data-target="projects">View approved &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#fee2e2; color:#ef4444;"><i data-lucide="x-circle"></i></div>
                            <div class="rsk-card-title">Rejected /<br>Query</div>
                            <div class="rsk-card-value"><?= $rsk_rejectedProjects ?></div>
                            <a href="#projects" class="rsk-card-link tab-trigger" data-target="projects">View details &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#fce7f3; color:#ec4899;"><i data-lucide="users"></i></div>
                            <div class="rsk-card-title">Total<br>Employees</div>
                            <div class="rsk-card-value"><?= $rsk_totalEmployees ?></div>
                            <a href="#settings" class="rsk-card-link">View employees &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#e0e7ff; color:#6366f1;"><i data-lucide="check-square"></i></div>
                            <div class="rsk-card-title">Total<br>Tasks</div>
                            <div class="rsk-card-value"><?= $rsk_totalTasks ?></div>
                            <a href="#dashboard" class="rsk-card-link">View tasks &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#f3e8ff; color:#8b5cf6;"><i data-lucide="users"></i></div>
                            <div class="rsk-card-title">Active<br>Clients</div>
                            <div class="rsk-card-value"><?= $rsk_activeClients ?></div>
                            <a href="#dashboard" class="rsk-card-link">View clients &rarr;</a>
                        </div>
                        <div class="rsk-card-sm">
                            <div class="rsk-icon-sm" style="background:#cffafe; color:#06b6d4;"><i data-lucide="map"></i></div>
                            <div class="rsk-card-title">Survey Works<br>In Progress</div>
                            <div class="rsk-card-value"><?= $rsk_surveyWorks ?></div>
                            <a href="#dashboard" class="rsk-card-link">View surveys &rarr;</a>
                        </div>
                    </div>

                    <!-- Row 2: Pipeline & Service-wise -->
                    <div class="rsk-row-2">
                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span><i data-lucide="bar-chart-2" style="width:16px; height:16px; display:inline-block; vertical-align:middle; margin-right:4px; color:#3b82f6;"></i> Project Pipeline</span>
                                <a href="#" class="rsk-panel-link">View All &rarr;</a>
                            </div>
                            <div class="rsk-stepper">
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
                            </div>
                        </div>

                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Service-wise Projects</span>
                                <a href="#" class="rsk-panel-link">View All &rarr;</a>
                            </div>
                            <table class="rsk-table">
                                <thead>
                                    <tr><th>Service Type</th><th>Total</th><th>Pending</th><th>Completed</th><th>Completion %</th></tr>
                                </thead>
                                <tbody>
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
                                    <tr style="border-top: 2px solid #e2e8f0;">
                                        <td style="font-weight:700; color:#0f172a;">Total</td>
                                        <td style="text-align:center; font-weight:700; color:#0f172a;"><?= $totTotal ?></td>
                                        <td style="text-align:center; font-weight:700; color:#0f172a;"><?= $totPend ?></td>
                                        <td style="text-align:center; font-weight:700; color:#0f172a;"><?= $totComp ?></td>
                                        <td style="font-weight:700; color:#0f172a;"><?= round(($totComp/$totTotal)*100) ?>%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Row 3: Recent, NOC, Notifications -->
                    <div class="rsk-row-3">
                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Recent Projects</span>
                                <a href="#projects" class="rsk-panel-link tab-trigger" data-target="projects">View All &rarr;</a>
                            </div>
                            <table class="rsk-table">
                                <thead>
                                    <tr><th>Project ID</th><th>Client Name</th><th>Service Type</th><th>Location</th><th>Current Status</th><th>Target Date</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>RSK-78</td><td>Suresh Babu</td><td>Layout</td><td>Anantapur</td>
                                        <td><span class="rsk-pill" style="background:#ffedd5; color:#ea580c;">NOC Process</span></td>
                                        <td><i data-lucide="calendar" style="width:10px; height:10px; display:inline-block;"></i> 28 May 2025</td>
                                    </tr>
                                    <tr>
                                        <td>RSK-77</td><td>Mahesh Reddy</td><td>Building</td><td>Dharmavaram</td>
                                        <td><span class="rsk-pill" style="background:#fef3c7; color:#d97706;">Draft Preparation</span></td>
                                        <td><i data-lucide="calendar" style="width:10px; height:10px; display:inline-block;"></i> 25 May 2025</td>
                                    </tr>
                                    <tr>
                                        <td>RSK-76</td><td>Ravi Kumar</td><td>Single Plot</td><td>Tadipatri</td>
                                        <td><span class="rsk-pill" style="background:#eff6ff; color:#2563eb;">Document Collection</span></td>
                                        <td><i data-lucide="calendar" style="width:10px; height:10px; display:inline-block;"></i> 22 May 2025</td>
                                    </tr>
                                    <tr>
                                        <td>RSK-75</td><td>Lakshmi Prasad</td><td>UAL</td><td>Penukonda</td>
                                        <td><span class="rsk-pill" style="background:#f3e8ff; color:#9333ea;">Application Submitted</span></td>
                                        <td><i data-lucide="calendar" style="width:10px; height:10px; display:inline-block;"></i> 30 May 2025</td>
                                    </tr>
                                    <tr>
                                        <td>RSK-74</td><td>Kiran Kumar</td><td>Land Survey</td><td>Guntakal</td>
                                        <td><span class="rsk-pill" style="background:#dcfce7; color:#16a34a;">Survey In Progress</span></td>
                                        <td><i data-lucide="calendar" style="width:10px; height:10px; display:inline-block;"></i> 24 May 2025</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>NOC Tracking</span>
                                <a href="#" class="rsk-panel-link">View All &rarr;</a>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: center; height: 180px;">
                                <div style="position: relative; width: 140px; height: 140px;">
                                    <canvas id="nocChart"></canvas>
                                    <div style="position: absolute; top:0; left:0; right:0; bottom:0; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                        <span style="font-size: 20px; font-weight: 700; color: #0f172a;">78</span>
                                        <span style="font-size: 8px; color: #64748b;">Total Projects</span>
                                    </div>
                                </div>
                                <div style="margin-left: 20px; font-size: 10px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; width: 140px;"><span style="color:#475569;"><span style="color:#10b981;">■</span> Agriculture NOC</span> <span style="font-weight:600; color:#0f172a;">38 (49%)</span></div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; width: 140px;"><span style="color:#475569;"><span style="color:#ec4899;">■</span> Revenue NOC</span> <span style="font-weight:600; color:#0f172a;">41 (53%)</span></div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; width: 140px;"><span style="color:#475569;"><span style="color:#eab308;">■</span> WRD NOC</span> <span style="font-weight:600; color:#0f172a;">30 (38%)</span></div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; width: 140px;"><span style="color:#475569;"><span style="color:#3b82f6;">■</span> Highway NOC</span> <span style="font-weight:600; color:#0f172a;">25 (32%)</span></div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px; width: 140px;"><span style="color:#475569;"><span style="color:#a855f7;">■</span> Forest NOC</span> <span style="font-weight:600; color:#0f172a;">12 (15%)</span></div>
                                    <div style="display: flex; justify-content: space-between; width: 140px;"><span style="color:#475569;"><span style="color:#ef4444;">■</span> Railway NOC</span> <span style="font-weight:600; color:#0f172a;">9 (12%)</span></div>
                                </div>
                            </div>
                            <div style="font-size: 9px; color: #94a3b8; text-align: center; margin-top: 10px;">* Multiple NOCs may be applicable for a project</div>
                        </div>

                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Notifications</span>
                                <a href="#" class="rsk-panel-link">View All &rarr;</a>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:16px;">
                                <div style="display:flex; align-items:flex-start; gap:10px;">
                                    <div style="width:24px; height:24px; background:#fff1f2; color:#ef4444; border-radius:50%; display:flex; align-items:center; justify-content:center;"><i data-lucide="file-x" style="width:12px; height:12px;"></i></div>
                                    <div style="flex:1;">
                                        <div style="font-size:11px; font-weight:700; color:#0f172a; margin-bottom:2px;">Missing Documents</div>
                                        <div style="font-size:10px; color:#64748b;">3 projects have missing documents</div>
                                    </div>
                                    <div style="font-size:9px; color:#94a3b8;">10 min ago</div>
                                </div>
                                <div style="display:flex; align-items:flex-start; gap:10px;">
                                    <div style="width:24px; height:24px; background:#fef2f2; color:#ef4444; border-radius:50%; display:flex; align-items:center; justify-content:center;"><i data-lucide="help-circle" style="width:12px; height:12px;"></i></div>
                                    <div style="flex:1;">
                                        <div style="font-size:11px; font-weight:700; color:#0f172a; margin-bottom:2px;">Approval Queries Raised</div>
                                        <div style="font-size:10px; color:#64748b;">5 projects have queries from department</div>
                                    </div>
                                    <div style="font-size:9px; color:#94a3b8;">1 hour ago</div>
                                </div>
                                <div style="display:flex; align-items:flex-start; gap:10px;">
                                    <div style="width:24px; height:24px; background:#fef3c7; color:#f59e0b; border-radius:50%; display:flex; align-items:center; justify-content:center;"><i data-lucide="indian-rupee" style="width:12px; height:12px;"></i></div>
                                    <div style="flex:1;">
                                        <div style="font-size:11px; font-weight:700; color:#0f172a; margin-bottom:2px;">Payment Due</div>
                                        <div style="font-size:10px; color:#64748b;">8 payments are overdue</div>
                                    </div>
                                    <div style="font-size:9px; color:#94a3b8;">2 hours ago</div>
                                </div>
                                <div style="display:flex; align-items:flex-start; gap:10px;">
                                    <div style="width:24px; height:24px; background:#dcfce7; color:#10b981; border-radius:50%; display:flex; align-items:center; justify-content:center;"><i data-lucide="check" style="width:12px; height:12px;"></i></div>
                                    <div style="flex:1;">
                                        <div style="font-size:11px; font-weight:700; color:#0f172a; margin-bottom:2px;">Approval Received</div>
                                        <div style="font-size:10px; color:#64748b;">2 projects have been approved</div>
                                    </div>
                                    <div style="font-size:9px; color:#94a3b8;">3 hours ago</div>
                                </div>
                                <div style="display:flex; align-items:flex-start; gap:10px;">
                                    <div style="width:24px; height:24px; background:#eff6ff; color:#3b82f6; border-radius:50%; display:flex; align-items:center; justify-content:center;"><i data-lucide="calendar" style="width:12px; height:12px;"></i></div>
                                    <div style="flex:1;">
                                        <div style="font-size:11px; font-weight:700; color:#0f172a; margin-bottom:2px;">Site Visit Scheduled</div>
                                        <div style="font-size:10px; color:#64748b;">4 survey site visits scheduled this week</div>
                                    </div>
                                    <div style="font-size:9px; color:#94a3b8;">5 hours ago</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Status, Top Services, Quick Actions -->
                    <div class="rsk-row-4">
                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Projects by Status</span>
                                <a href="#" class="rsk-panel-link">View Report &rarr;</a>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: center; height: 180px;">
                                <div style="position: relative; width: 140px; height: 140px;">
                                    <canvas id="statusChart"></canvas>
                                    <div style="position: absolute; top:0; left:0; right:0; bottom:0; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                        <span style="font-size: 20px; font-weight: 700; color: #0f172a;">78</span>
                                    </div>
                                </div>
                                <div style="margin-left: 20px; font-size: 11px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; width: 120px;"><span style="color:#475569;"><span style="color:#10b981;">■</span> Completed</span> <span style="font-weight:600; color:#0f172a;">56 (72%)</span></div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; width: 120px;"><span style="color:#475569;"><span style="color:#3b82f6;">■</span> In Progress</span> <span style="font-weight:600; color:#0f172a;">22 (28%)</span></div>
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px; width: 120px;"><span style="color:#475569;"><span style="color:#f59e0b;">■</span> On Hold</span> <span style="font-weight:600; color:#0f172a;">4 (5%)</span></div>
                                    <div style="display: flex; justify-content: space-between; width: 120px;"><span style="color:#475569;"><span style="color:#ef4444;">■</span> Rejected</span> <span style="font-weight:600; color:#0f172a;">6 (8%)</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Top Services by Task Volume</span>
                                <a href="#" class="rsk-panel-link">View Report &rarr;</a>
                            </div>
                            <div style="height: 180px;">
                                <canvas id="servicesChart"></canvas>
                            </div>
                        </div>

                        <div class="rsk-panel">
                            <div class="rsk-panel-title">
                                <span>Quick Actions</span>
                            </div>
                            <div class="rsk-qa-grid" style="height: 180px; align-content: center;">
                                <div class="rsk-qa-btn"><div class="rsk-qa-icon" style="color:#3b82f6;"><i data-lucide="plus-circle"></i></div><div class="rsk-qa-text">New Project</div></div>
                                <div class="rsk-qa-btn"><div class="rsk-qa-icon" style="color:#10b981;"><i data-lucide="user-plus"></i></div><div class="rsk-qa-text">Add Client</div></div>
                                <div class="rsk-qa-btn"><div class="rsk-qa-icon" style="color:#f59e0b;"><i data-lucide="map"></i></div><div class="rsk-qa-text">New Survey</div></div>
                                <div class="rsk-qa-btn"><div class="rsk-qa-icon" style="color:#a855f7;"><i data-lucide="upload-cloud"></i></div><div class="rsk-qa-text">Upload Document</div></div>
                                <div class="rsk-qa-btn"><div class="rsk-qa-icon" style="color:#22c55e;"><i data-lucide="check-shield"></i></div><div class="rsk-qa-text">NOC Tracker</div></div>
                                <div class="rsk-qa-btn"><div class="rsk-qa-icon" style="color:#ec4899;"><i data-lucide="credit-card"></i></div><div class="rsk-qa-text">Payment Entry</div></div>
                                <div class="rsk-qa-btn"><div class="rsk-qa-icon" style="color:#3b82f6;"><i data-lucide="clipboard-list"></i></div><div class="rsk-qa-text">Task Manager</div></div>
                                <div class="rsk-qa-btn"><div class="rsk-qa-icon" style="color:#f97316;"><i data-lucide="bar-chart-2"></i></div><div class="rsk-qa-text">Reports</div></div>
                            </div>
                        </div>
                    </div>

                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Wait slightly for Chart.js to load if via CDN
                        setTimeout(() => {
                            // NOC Chart
                            new Chart(document.getElementById('nocChart').getContext('2d'), {
                                type: 'doughnut',
                                data: {
                                    labels: ['Agriculture', 'Revenue', 'WRD', 'Highway', 'Forest', 'Railway'],
                                    datasets: [{
                                        data: [38, 41, 30, 25, 12, 9],
                                        backgroundColor: ['#10b981', '#ec4899', '#eab308', '#3b82f6', '#a855f7', '#ef4444'],
                                        borderWidth: 0,
                                        cutout: '75%'
                                    }]
                                },
                                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: true } } }
                            });

                            // Status Chart
                            new Chart(document.getElementById('statusChart').getContext('2d'), {
                                type: 'doughnut',
                                data: {
                                    labels: ['Completed', 'In Progress', 'On Hold', 'Rejected'],
                                    datasets: [{
                                        data: [56, 22, 4, 6],
                                        backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                                        borderWidth: 0,
                                        cutout: '75%'
                                    }]
                                },
                                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: true } } }
                            });

                            // Services Chart (Horizontal Bar)
                            new Chart(document.getElementById('servicesChart').getContext('2d'), {
                                type: 'bar',
                                data: {
                                    labels: ['Layout', 'Building', 'Land Survey', 'Single Plot', 'UAL'],
                                    datasets: [{
                                        data: [85, 65, 45, 30, 15],
                                        backgroundColor: ['#10b981', '#3b82f6', '#eab308', '#a855f7', '#ec4899'],
                                        borderRadius: 4,
                                        barThickness: 8
                                    }]
                                },
                                options: {
                                    indexAxis: 'y',
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        x: { display: false, grid: { display: false } },
                                        y: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10, family: "'Outfit', sans-serif" }, color: '#475569' } }
                                    },
                                    plugins: { legend: { display: false } }
                                }
                            });
                            
                            // Initialize icons in new block
                            if(typeof lucide !== 'undefined') lucide.createIcons();
                        }, 500);
                    });
                    </script>
                </div>
