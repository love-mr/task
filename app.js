// app.js
document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize Icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // 2. Tab Navigation routing (SPA)
    setupSPARouting();

    // 3. Setup Modals
    setupModals();

    // 4. Setup Form AJAX Actions
    setupFormActions();

    // 5. Setup Header Widgets (Sidebar toggle, notifications dropdown, Sun toggle)
    setupHeaderWidgets();

    // 6. Initialize Charts
    if (typeof Chart !== 'undefined' && window.VYALA_TASKPAD_DASHBOARD_DATA) {
        initCharts();
    }

    // 7. Setup Table Search Filters
    setupSearchFilters();

    // 8. Setup Interactive Sub-Tabs and Accordion Bindings
    setupSubTabAndAccordionBindings();

    // 9. Extra Module Bindings
    populateNotesTagsDropdown();
    filterAndSortProjects();
    filterAndPaginateDocs();
    setupClientsAndDepartmentsCrud();
    setupDiscussionMembersManager();
    setupDirectChatManager();
    setupAttendanceFiltersAndExport();
    setupUsersManagement();
    setupLayoutModule();
    setupDashboardTriggers();
    setupTaskEditFeatures();
});

// ==========================================================================
// SPA TAB ROUTING
// ==========================================================================
function setupSPARouting() {
    const navItems = document.querySelectorAll('.sidebar-nav .nav-item');
    const views = document.querySelectorAll('.tab-view');
    const pageTitle = document.getElementById('current-view-title');

    function switchTab(targetHash) {
        let cleanHash = targetHash.replace('#', '');
        
        // Determine default tab based on user role
        const defaultTab = (window.VYALA_USER_ROLE === 'Admin') ? 'organizations' : 'dashboard';
        if (!cleanHash) {
            cleanHash = defaultTab;
        }

        // Force Admin to only access the 'organizations' tab
        if (window.VYALA_USER_ROLE === 'Admin' && cleanHash !== 'organizations') {
            window.location.hash = '#organizations';
            return;
        }
        
        // Hide all views, show active
        views.forEach(v => {
            if (v.id === `view-${cleanHash}`) {
                v.classList.add('active');
            } else {
                v.classList.remove('active');
            }
        });

        // Update sidebar active highlights
        navItems.forEach(item => {
            if (item.getAttribute('data-tab') === cleanHash) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        // Update page title
        const titles = {
            'dashboard': 'Dashboard',
            'organizations': 'Organizations',
            'tasks': 'Tasks',
            'projects': 'Projects',
            'discussion': 'Discussion',
            'documents': 'Documents',
            'notes': 'Notes',
            'attendance': 'Attendance',
            'reports': 'Reports',
            'users': 'Users',
            'departments': 'Departments',
            'settings': 'Settings',
            'clients': 'Clients',
            'layout': 'Layout Generator',
            'building': 'Building Module',
            'singleplot': 'Single Plot Module',
            'ual': 'UAL Module',
            'landsurvey': 'Land Survey Module'
        };
        if (pageTitle && titles[cleanHash]) {
            pageTitle.textContent = titles[cleanHash];
        }
    }

    // Hashchange listener
    window.addEventListener('hashchange', function() {
        switchTab(window.location.hash);
        // Lazy-init Reports charts when reports tab is first opened
        if (window.location.hash === '#reports') {
            setTimeout(initReportsCharts, 80);
        }
    });

    // Check initial hash
    if (window.VYALA_USER_ROLE === 'Admin') {
        if (window.location.hash !== '#organizations') {
            window.location.hash = '#organizations';
        } else {
            switchTab('#organizations');
        }
    } else {
        if (window.location.hash) {
            switchTab(window.location.hash);
            if (window.location.hash === '#reports') {
                setTimeout(initReportsCharts, 80);
            }
        }
    }

    // Internal tab trigger links (e.g. stat card links)
    document.querySelectorAll('.tab-trigger').forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            const target = trigger.getAttribute('data-target');
            if (target) {
                window.location.hash = `#${target}`;
            }
        });
    });
}

// ==========================================================================
// CHART INITIALIZATION
// ==========================================================================
function initCharts() {
    const data = window.VYALA_TASKPAD_DASHBOARD_DATA;

    // A. Statistics Completed vs Incomplete Line Chart
    const statsCtx = document.getElementById('statisticsChartCanvas');
    if (statsCtx) {
        new Chart(statsCtx, {
            type: 'line',
            data: {
                labels: data.monthlyCompleted.labels,
                datasets: [
                    {
                        label: 'Completed',
                        data: data.monthlyCompleted.completed,
                        borderColor: '#10b981', // Green line
                        borderWidth: 2.5,
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.45,
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 1.5,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Incomplete',
                        data: data.monthlyCompleted.incomplete,
                        borderColor: '#ef4444', // Red line
                        borderWidth: 2.5,
                        backgroundColor: 'transparent',
                        fill: false,
                        tension: 0.45,
                        pointBackgroundColor: '#ef4444',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 1.5,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false } // Custom legend printed in HTML
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 20,
                        ticks: { stepSize: 4, font: { family: 'Outfit', size: 10 } },
                        grid: { color: 'rgba(226, 232, 240, 0.4)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Outfit', size: 10 } }
                    }
                }
            }
        });
    }

    // B. Priority Task Summary Donut Chart
    const priorityCtx = document.getElementById('priorityChartCanvas');
    if (priorityCtx) {
        new Chart(priorityCtx, {
            type: 'doughnut',
            data: {
                labels: data.priorities.labels,
                datasets: [{
                    data: data.priorities.data,
                    backgroundColor: [
                        '#10b981', // Low (green)
                        '#f59e0b', // Medium (yellow)
                        '#ef4444'  // High (red)
                    ],
                    borderWidth: 1.5,
                    borderColor: '#ffffff',
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '80%',
                plugins: {
                    legend: { display: false } // Custom legend printed in HTML
                }
            }
        });
    }
}

// Reports charts (lazy-initialized when Reports tab is opened)
let _reportsChartsInit = false;
function initReportsCharts() {
    if (_reportsChartsInit) return;
    _reportsChartsInit = true;

    const data = window.VYALA_TASKPAD_DASHBOARD_DATA;
    if (!data || typeof Chart === 'undefined') return;

    // Bar chart — task completion per month
    const barCtx = document.getElementById('reportBarChart');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: data.monthlyCompleted.labels,
                datasets: [
                    {
                        label: 'Completed',
                        data: data.monthlyCompleted.completed,
                        backgroundColor: 'rgba(37,99,235,0.85)',
                        borderRadius: 4,
                        barThickness: 14
                    },
                    {
                        label: 'Incomplete',
                        data: data.monthlyCompleted.incomplete,
                        backgroundColor: 'rgba(239,68,68,0.75)',
                        borderRadius: 4,
                        barThickness: 14
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: { font: { family: 'Outfit', size: 11 }, boxWidth: 10, padding: 14 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 5, font: { family: 'Outfit', size: 10 } },
                        grid: { color: 'rgba(226,232,240,0.5)' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Outfit', size: 10 } }
                    }
                }
            }
        });
    }

    // Pie chart — priority distribution
    const pieCtx = document.getElementById('reportPieChart');
    if (pieCtx) {
        new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: ['Low', 'Medium', 'High'],
                datasets: [{
                    data: data.priorities.data,
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    }
}

// ==========================================================================
// MODAL MANAGEMENT
// ==========================================================================
function setupModals() {
    const triggers = {
        'btn-add-task-welcome': 'modal-task',
        'btn-tasks-add-task': 'modal-task',
        'btn-projects-add-project': 'modal-project',
        'btn-add-client': 'modal-client',
        'btn-clients-add': 'modal-client',
        'btn-log-time': 'modal-logtime',
        'btn-add-employee': 'modal-employee',
        'btn-notes-add-note': 'modal-add-note',
        'btn-new-discussion': 'modal-discussion',
        'btn-new-direct-message': 'modal-start-direct-chat',
        'btn-docs-add': 'modal-upload-doc',
        'btn-add-department': 'modal-department',
        'btn-add-building': 'modal-building',
        'btn-add-singleplot': 'modal-singleplot',
        'btn-add-ual': 'modal-ual',
        'btn-add-landsurvey': 'modal-landsurvey',
        'btn-view-all-notifications': 'modal-all-notifications'
    };

    Object.keys(triggers).forEach(btnId => {
        const btn = document.getElementById(btnId);
        const modalId = triggers[btnId];
        const modal = document.getElementById(modalId);
        
        if (btn && modal) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                modal.classList.add('active');
            });
        }
    });

    // Close button triggers
    document.querySelectorAll('.modal-close, .modal-close-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const modal = btn.closest('.modal-overlay');
            if (modal) {
                modal.classList.remove('active');
            }
        });
    });

    // Close clicking overlay
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.classList.remove('active');
            }
        });
    });
}

// ==========================================================================
// FORM AJAX ACTIONS
// ==========================================================================
function setupFormActions() {
    const forms = {
        'form-new-client': 'api.php?action=create_client',
        'form-edit-client': 'api.php?action=update_client',
        'form-new-project': 'api.php?action=create_project',
        'form-new-task': 'api.php?action=create_task',
        'form-edit-task': 'api.php?action=update_task_details',
        'form-new-timesheet': 'api.php?action=create_timesheet',
        'form-new-employee': 'api.php?action=create_employee',
        'form-update-settings': 'api.php?action=update_settings',
        'form-new-note': 'api.php?action=create_note',
        'form-edit-note': 'api.php?action=update_note',
        'form-new-discussion': 'api.php?action=create_discussion',
        'form-upload-document': 'api.php?action=upload_document',
        'form-new-department': 'api.php?action=create_department',
        'form-edit-department': 'api.php?action=update_department',
        'form-building': 'api.php',
        'form-singleplot': 'api.php',
        'form-ual': 'api.php',
        'form-landsurvey': 'api.php'
    };

    Object.keys(forms).forEach(formId => {
        const form = document.getElementById(formId);
        const actionUrl = forms[formId];

        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // Unique Employee Code validation before submitting new employee
                if (formId === 'form-new-employee') {
                    const codeVal = document.getElementById('emp-code')?.value.trim();
                    if (codeVal) {
                        const employees = window.VYALA_TASKPAD_DASHBOARD_DATA?.employees || [];
                        const exists = employees.some(emp => emp.emp_code && emp.emp_code.toLowerCase() === codeVal.toLowerCase());
                        if (exists) {
                            alert(`Error: Employee ID '${codeVal}' is already in use.`);
                            document.getElementById('emp-code').focus();
                            return;
                        }
                    }
                }
                
                // Unique Employee Code validation before submitting settings
                if (formId === 'form-update-settings') {
                    const codeVal = document.getElementById('set-code')?.value.trim();
                    if (codeVal) {
                        const employees = window.VYALA_TASKPAD_DASHBOARD_DATA?.employees || [];
                        const meId = window.VYALA_TASKPAD_DASHBOARD_DATA?.meId;
                        const exists = employees.some(emp => emp.id != meId && emp.emp_code && emp.emp_code.toLowerCase() === codeVal.toLowerCase());
                        if (exists) {
                            alert(`Error: Employee ID '${codeVal}' is already in use by another employee.`);
                            document.getElementById('set-code').focus();
                            return;
                        }
                    }
                }

                const formData = new FormData(form);
                
                // --- E2EE Intercept for New Discussion ---
                if (formId === 'form-new-discussion') {
                    // Generate a shared AES key and encrypt it for self
                    generateAESKey().then(aesKeyObj => {
                        exportAESKey(aesKeyObj).then(aesKeyBase64 => {
                            const selfPubKey = localStorage.getItem('e2ee_public_key');
                            encryptAESKeyWithRSA(aesKeyBase64, selfPubKey).then(encSelf => {
                                formData.append('encrypted_key_self', encSelf);
                                submitFormAjax(actionUrl, formData, form);
                            });
                        });
                    }).catch(err => {
                        console.error("Failed to generate group AES key:", err);
                        alert("Failed to setup secure chat. Please check console.");
                    });
                } else {
                    submitFormAjax(actionUrl, formData, form);
                }
            });
        }
    });

    function submitFormAjax(actionUrl, formData, form) {
        fetch(actionUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert(result.message);
                form.reset();
                // Hide modal
                const modal = form.closest('.modal-overlay');
                if (modal) {
                    modal.classList.remove('active');
                }
                // Reload page
                window.location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error submitting form:', error);
            alert('Submission failed. Please try again.');
        });
    }

    // Form Chat Send message AJAX
    const chatForm = document.getElementById('form-chat-send');
    if (chatForm) {
        chatForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const activeThread = document.querySelector('.chat-thread-item.active');
            if (!activeThread) {
                alert('Select a discussion thread first.');
                return;
            }
            const discId = activeThread.getAttribute('data-chat-id');
            const formData = new FormData(chatForm);
            formData.append('discussion_id', discId);

            // Simple plain-text send (no E2EE complexity)
            const msgInput = chatForm.querySelector('[name="message"]');
            const rawMsg = msgInput ? msgInput.value.trim() : '';
            if (!rawMsg && !formData.get('attachment')) {
                return; // nothing to send
            }

            fetch('api.php?action=send_message', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    chatForm.reset();
                    loadThreadMessages(discId);
                } else {
                    alert('Failed to send: ' + (res.message || 'Server error'));
                }
            })
            .catch(err => {
                console.error('Send message error:', err);
                alert('Network error. Please try again.');
            });
        });
    }
}

// ==========================================================================
// HEADER WIDGETS & THEME BINDINGS
// ==========================================================================
function setupHeaderWidgets() {
    // Mobile Sidebar toggle
    const toggleBtn = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('active');
        });

        document.addEventListener('click', function(e) {
            if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && e.target !== toggleBtn) {
                sidebar.classList.remove('active');
            }
        });
    }

    // Alerts Bell Toggle
    const notifBtn = document.getElementById('notif-toggle');
    const notifDrop = document.getElementById('notif-dropdown');
    
    if (notifBtn && notifDrop) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            // Close other dropdowns
            const profileDrop = document.getElementById('profile-dropdown');
            if (profileDrop) profileDrop.classList.remove('active');
            
            notifDrop.classList.toggle('active');
            
            // Clear badge count immediately when dropdown is opened
            if (notifDrop.classList.contains('active')) {
                const badge = notifBtn.querySelector('.badge');
                if (badge) badge.style.display = 'none';
                // Also send read request to server
                fetch('api.php?action=clear_notifications', { method: 'POST' }).catch(() => {});
            }
        });

        document.addEventListener('click', function(e) {
            if (notifDrop.classList.contains('active') && !notifDrop.contains(e.target) && !notifBtn.contains(e.target)) {
                notifDrop.classList.remove('active');
            }
        });

        // Dynamic clear notifications via clear-all button
        const clearBtn = notifDrop.querySelector('.clear-all');
        if (clearBtn) {
            clearBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                fetch('api.php?action=clear_notifications', { method: 'POST' })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const content = notifDrop.querySelector('.dropdown-content');
                        if (content) content.innerHTML = '<p class="empty-notif">No new alerts</p>';
                        const badge = notifBtn.querySelector('.badge');
                        if (badge) badge.style.display = 'none';
                    }
                });
            });
        }
    }

    // User Profile Dropdown Toggle
    const profileBtn = document.getElementById('profile-toggle');
    const profileDrop = document.getElementById('profile-dropdown');
    
    if (profileBtn && profileDrop) {
        profileBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (notifDrop) notifDrop.classList.remove('active');
            profileDrop.classList.toggle('active');
        });

        document.addEventListener('click', function(e) {
            if (profileDrop.classList.contains('active') && !profileDrop.contains(e.target) && !profileBtn.contains(e.target)) {
                profileDrop.classList.remove('active');
            }
        });
    }

    // Sun Mode Toggle Switch Handler
    const sunBtn = document.getElementById('sun-toggle');
    const circle = sunBtn?.querySelector('.sun-circle');
    
    // Apply saved theme immediately
    const savedTheme = localStorage.getItem('vyala_taskpad_theme') || 'light';
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
        if (circle) {
            circle.style.position = 'absolute';
            circle.style.left = '30px';
        }
    } else {
        document.body.classList.remove('dark-theme');
        if (circle) {
            circle.style.position = 'absolute';
            circle.style.left = '4px';
        }
    }
    
    if (sunBtn) {
        sunBtn.addEventListener('click', function() {
            if (document.body.classList.contains('dark-theme')) {
                document.body.classList.remove('dark-theme');
                localStorage.setItem('vyala_taskpad_theme', 'light');
                if (circle) circle.style.left = '4px';
            } else {
                document.body.classList.add('dark-theme');
                localStorage.setItem('vyala_taskpad_theme', 'dark');
                if (circle) circle.style.left = '30px';
            }
        });
    }

    // Discussions Tab Switcher Filter
    const discButtons = document.querySelectorAll('.disc-tab-btn');
    const discItems = document.querySelectorAll('.discussion-row-item');
    
    if (discButtons.length > 0 && discItems.length > 0) {
        const filterDiscussions = (type) => {
            discItems.forEach(item => {
                const itemType = item.getAttribute('data-type') || 'General';
                if (itemType === type) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        };

        // Initial filter
        filterDiscussions('General');

        discButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                discButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                const selectedType = btn.getAttribute('data-type') || 'General';
                filterDiscussions(selectedType);
            });
        });
    }
}

// ==========================================================================
// DATE HELPER FUNCTION
// ==========================================================================
function isDateInRange(dateStr, range) {
    if (!dateStr) return false;
    const parts = dateStr.split(' ')[0].split('-');
    if (parts.length !== 3) return false;
    const date = new Date(parts[0], parts[1] - 1, parts[2]);
    
    // Use server date if available to avoid client-server timezone mismatch
    let today;
    if (window.VYALA_TASKPAD_DASHBOARD_DATA && window.VYALA_TASKPAD_DASHBOARD_DATA.serverDate) {
        const sParts = window.VYALA_TASKPAD_DASHBOARD_DATA.serverDate.split('-');
        today = new Date(sParts[0], sParts[1] - 1, sParts[2]);
    } else {
        today = new Date();
        today.setHours(0,0,0,0);
    }
    
    const targetDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    
    if (range === 'All') return true;
    if (range === 'Today') {
        return targetDate.getTime() === today.getTime();
    }
    if (range === 'Yesterday') {
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        return targetDate.getTime() === yesterday.getTime();
    }
    if (range === 'ThisWeek') {
        const startOfWeek = new Date(today);
        startOfWeek.setDate(today.getDate() - today.getDay());
        const endOfWeek = new Date(startOfWeek);
        endOfWeek.setDate(startOfWeek.getDate() + 6);
        return targetDate >= startOfWeek && targetDate <= endOfWeek;
    }
    if (range === 'ThisMonth') {
        return targetDate.getFullYear() === today.getFullYear() && targetDate.getMonth() === today.getMonth();
    }
    return true;
}

// ==========================================================================
// SEARCH FILTERS
// ==========================================================================
function setupSearchFilters() {
    // Tasks Search
    const tasksSearch = document.getElementById('tasks-search');
    if (tasksSearch) {
        tasksSearch.addEventListener('input', filterAndSortTasks);
    }

    // Projects Search
    const projSearch = document.getElementById('projects-search');
    if (projSearch) {
        projSearch.addEventListener('input', filterAndSortProjects);
    }

    // Documents Search
    const docsSearch = document.getElementById('docs-search');
    if (docsSearch) {
        docsSearch.addEventListener('input', function() {
            currentDocsPage = 1;
            filterAndPaginateDocs();
        });
    }

    // Notes Search
    const notesSearch = document.getElementById('notes-search');
    if (notesSearch) {
        notesSearch.addEventListener('input', filterNotes);
    }
}

// ==========================================================================
// TASKS FILTERING, SORTING AND RENDERING
// ==========================================================================
let currentTasksFilterStatus = 'All';
let currentTasksFilterPriority = 'All';
let currentTasksSort = 'default';
let currentTasksFilterDateRange = 'All';

function filterAndSortTasks() {
    const query = (document.getElementById('tasks-search')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.task-row');

    rows.forEach(row => {
        const title = (row.querySelector('span')?.textContent || '').toLowerCase();
        const desc = (row.querySelector('small')?.textContent || '').toLowerCase();
        const status = row.getAttribute('data-status');
        const priority = row.getAttribute('data-priority');
        const dueDate = row.getAttribute('data-due-date');

        const matchesQuery = title.includes(query) || desc.includes(query);
        const matchesStatus = currentTasksFilterStatus === 'All' || status === currentTasksFilterStatus;
        const matchesPriority = currentTasksFilterPriority === 'All' || priority === currentTasksFilterPriority;
        const matchesDate = isDateInRange(dueDate, currentTasksFilterDateRange);

        if (matchesQuery && matchesStatus && matchesPriority && matchesDate) {
            row.style.display = 'flex';
        } else {
            row.style.display = 'none';
        }
    });

    // Update Accordion Counts
    updateAccordionCounts();

    // Perform sort within containers
    if (currentTasksSort !== 'default') {
        sortTasksInDOM();
    }
}

function updateAccordionCounts() {
    const groups = ['today', 'overdue', 'other'];
    groups.forEach(gid => {
        const container = document.getElementById(`group-body-${gid}`);
        if (container) {
            const visibleCount = container.querySelectorAll('.task-row[style*="display: flex"], .task-row:not([style*="display: none"])').length;
            const header = document.querySelector(`.task-group-header[data-group-id="${gid}"]`);
            if (header) {
                const countBadge = header.querySelector('.task-group-count');
                if (countBadge) countBadge.textContent = visibleCount;
            }
        }
    });
}

function sortTasksInDOM() {
    const groups = ['today', 'overdue', 'other'];
    groups.forEach(gid => {
        const container = document.getElementById(`group-body-${gid}`);
        if (!container) return;

        const rows = Array.from(container.querySelectorAll('.task-row'));
        rows.sort((a, b) => {
            if (currentTasksSort === 'due-asc') {
                const da = a.getAttribute('data-due-date') || '9999-99-99';
                const db = b.getAttribute('data-due-date') || '9999-99-99';
                return da.localeCompare(db);
            } else if (currentTasksSort === 'due-desc') {
                const da = a.getAttribute('data-due-date') || '0000-00-00';
                const db = b.getAttribute('data-due-date') || '0000-00-00';
                return db.localeCompare(da);
            } else if (currentTasksSort === 'priority-high') {
                const weight = { 'High': 3, 'Medium': 2, 'Low': 1 };
                const pa = weight[a.getAttribute('data-priority')] || 0;
                const pb = weight[b.getAttribute('data-priority')] || 0;
                return pb - pa;
            } else if (currentTasksSort === 'priority-low') {
                const weight = { 'High': 3, 'Medium': 2, 'Low': 1 };
                const pa = weight[a.getAttribute('data-priority')] || 0;
                const pb = weight[b.getAttribute('data-priority')] || 0;
                return pa - pb;
            }
            return 0;
        });

        // Re-append in sorted order
        rows.forEach(r => container.appendChild(r));
    });
}

// ==========================================================================
// INTERACTIVE SUB-TABS & ACCORDION BINDINGS
// ==========================================================================
function setupSubTabAndAccordionBindings() {
    // A. Tasks Accordion Groups Click Collapse
    const accordionHeaders = document.querySelectorAll('.task-group-header');
    accordionHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const gid = header.getAttribute('data-group-id');
            const body = document.getElementById(`group-body-${gid}`);
            const caret = header.querySelector('.accordion-caret');
            if (body) {
                if (body.style.display === 'none') {
                    body.style.display = '';
                    if (caret) caret.style.transform = 'none';
                } else {
                    body.style.display = 'none';
                    if (caret) caret.style.transform = 'rotate(-90deg)';
                }
            }
        });
    });

    // B. Tasks View Sub-Tabs Switch (List / Kanban / Calendar)
    const taskSubTabs = document.querySelectorAll('#tasks-view-sub-tabs .task-sub-tab');
    taskSubTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            taskSubTabs.forEach(t => {
                t.classList.remove('active');
                t.style.color = '#64748b';
                t.style.borderBottom = 'none';
            });
            tab.classList.add('active');
            tab.style.color = '#2563eb';
            tab.style.borderBottom = '2px solid #2563eb';

            const subView = tab.getAttribute('data-sub-view');
            document.querySelectorAll('.task-sub-view-container').forEach(c => {
                c.style.display = 'none';
            });

            if (subView === 'list') {
                document.getElementById('tasks-list-view').style.display = '';
            } else if (subView === 'kanban') {
                document.getElementById('tasks-kanban-view').style.display = '';
                renderKanban();
            } else if (subView === 'calendar') {
                document.getElementById('tasks-calendar-view').style.display = '';
                renderCalendar();
            }
        });
    });

    // Tasks Priority dropdown triggers
    setupDropdownToggle('tasks-priority-filter-toggle', 'tasks-priority-filter-dropdown');
    document.querySelectorAll('.filter-priority-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            currentTasksFilterPriority = item.getAttribute('data-priority');
            document.getElementById('tasks-priority-filter-toggle').innerHTML = `<i data-lucide="filter"></i> Priority: ${currentTasksFilterPriority} <i data-lucide="chevron-down"></i>`;
            lucide.createIcons();
            document.getElementById('tasks-priority-filter-dropdown').style.display = 'none';
            filterAndSortTasks();
        });
    });

    // Tasks Status dropdown triggers
    setupDropdownToggle('tasks-status-filter-toggle', 'tasks-status-filter-dropdown');
    document.querySelectorAll('.filter-status-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            currentTasksFilterStatus = item.getAttribute('data-status');
            document.getElementById('tasks-status-filter-toggle').innerHTML = `Status: ${currentTasksFilterStatus} <i data-lucide="chevron-down"></i>`;
            lucide.createIcons();
            document.getElementById('tasks-status-filter-dropdown').style.display = 'none';
            filterAndSortTasks();
        });
    });

    // Tasks Sort dropdown triggers
    setupDropdownToggle('tasks-sort-toggle', 'tasks-sort-dropdown');
    document.querySelectorAll('.sort-tasks-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            currentTasksSort = item.getAttribute('data-sort');
            const sortLabel = item.textContent;
            document.getElementById('tasks-sort-toggle').innerHTML = `<i data-lucide="arrow-up-down"></i> Sort: ${sortLabel} <i data-lucide="chevron-down"></i>`;
            lucide.createIcons();
            document.getElementById('tasks-sort-dropdown').style.display = 'none';
            filterAndSortTasks();
        });
    });

    // Tasks Date Type dropdown triggers
    setupDropdownToggle('tasks-date-filter-toggle', 'tasks-date-filter-dropdown');
    document.querySelectorAll('.filter-tasks-date-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            currentTasksFilterDateRange = item.getAttribute('data-date-range');
            document.getElementById('tasks-date-filter-toggle').innerHTML = `<i data-lucide="calendar"></i> Date: ${item.textContent} <i data-lucide="chevron-down"></i>`;
            lucide.createIcons();
            document.getElementById('tasks-date-filter-dropdown').style.display = 'none';
            filterAndSortTasks();
        });
    });

    // C. Projects View Sub-Tabs Filter
    const projSubTabs = document.querySelectorAll('.proj-tab-btn');
    if (projSubTabs.length > 0) {
        projSubTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                projSubTabs.forEach(t => {
                    t.classList.remove('active');
                    t.style.color = '#64748b';
                    t.style.borderBottom = 'none';
                });
                tab.classList.add('active');
                tab.style.color = '#2563eb';
                tab.style.borderBottom = '2px solid #2563eb';
                
                filterAndSortProjects();
            });
        });
    }

    // Projects dropdown triggers
    setupDropdownToggle('projects-priority-filter-toggle', 'projects-priority-filter-dropdown');
    document.querySelectorAll('.filter-project-priority-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            currentProjectsFilterPriority = item.getAttribute('data-priority');
            document.getElementById('projects-priority-filter-toggle').innerHTML = `<i data-lucide="filter"></i> Priority: ${currentProjectsFilterPriority} <i data-lucide="chevron-down"></i>`;
            lucide.createIcons();
            document.getElementById('projects-priority-filter-dropdown').style.display = 'none';
            filterAndSortProjects();
        });
    });

    setupDropdownToggle('projects-status-filter-toggle', 'projects-status-filter-dropdown');
    document.querySelectorAll('.filter-project-status-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            currentProjectsFilterStatus = item.getAttribute('data-status');
            document.getElementById('projects-status-filter-toggle').innerHTML = `Status: ${currentProjectsFilterStatus} <i data-lucide="chevron-down"></i>`;
            lucide.createIcons();
            document.getElementById('projects-status-filter-dropdown').style.display = 'none';
            filterAndSortProjects();
        });
    });

    setupDropdownToggle('projects-sort-toggle', 'projects-sort-dropdown');
    document.querySelectorAll('.sort-projects-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            currentProjectsSort = item.getAttribute('data-sort');
            document.getElementById('projects-sort-toggle').innerHTML = `<i data-lucide="arrow-up-down"></i> Sort: ${item.textContent} <i data-lucide="chevron-down"></i>`;
            lucide.createIcons();
            document.getElementById('projects-sort-dropdown').style.display = 'none';
            filterAndSortProjects();
        });
    });

    setupDropdownToggle('projects-date-filter-toggle', 'projects-date-filter-dropdown');
    document.querySelectorAll('.filter-projects-date-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            currentProjectsFilterDateRange = item.getAttribute('data-date-range');
            document.getElementById('projects-date-filter-toggle').innerHTML = `<i data-lucide="calendar"></i> Date: ${item.textContent} <i data-lucide="chevron-down"></i>`;
            lucide.createIcons();
            document.getElementById('projects-date-filter-dropdown').style.display = 'none';
            filterAndSortProjects();
        });
    });

    // D. Discussion View Sidebar Thread Click
    const threadItems = document.querySelectorAll('.chat-thread-item');
    const activeAvatar = document.getElementById('active-chat-avatar');
    const activeTitle = document.getElementById('active-chat-title');
    
    if (threadItems.length > 0) {
        threadItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // Don't fire if delete button was clicked
                if (e.target.closest('.thread-delete-btn')) return;
                threadItems.forEach(ti => ti.classList.remove('active'));
                item.classList.add('active');
                
                const id = item.getAttribute('data-chat-id');
                window.currentActiveDiscussionId = id; // Set global
                window.currentAESKey = null; // Reset key
                
                const title = item.getAttribute('data-chat-title') || '';
                const avatar = item.getAttribute('data-chat-avatar') || '';
                const color = item.getAttribute('data-chat-color') || '';
                
                if (activeTitle) activeTitle.textContent = title;
                if (activeAvatar) {
                    activeAvatar.textContent = avatar;
                    activeAvatar.className = 'chat-thread-avatar ' + color;
                }
                
                // Show chat window header and footer
                const header = document.querySelector('.chat-window-header');
                const footer = document.querySelector('.chat-window-footer');
                if (header) header.style.display = 'flex';
                if (footer) footer.style.display = 'block';

                loadThreadMessages(id);
                // Start auto-refresh polling for new messages
                startMessagePolling(id);
            });

            // Delete button on each thread item
            const delBtn = item.querySelector('.thread-delete-btn');
            if (delBtn) {
                delBtn.addEventListener('click', async function(e) {
                    e.stopPropagation();
                    const discId = item.getAttribute('data-chat-id');
                    const title = item.getAttribute('data-chat-title') || 'this discussion';
                    if (!confirm(`Delete "${title}" and all its messages? This cannot be undone.`)) return;
                    try {
                        const fd = new FormData();
                        fd.append('discussion_id', discId);
                        const r = await fetch('api.php?action=delete_discussion', { method: 'POST', body: fd });
                        const res = await r.json();
                        if (res.success) {
                            item.remove();
                            // If it was active, close chat window
                            if (window.currentActiveDiscussionId == discId) {
                                const chatCloseBtn = document.getElementById('chat-close-btn');
                                if (chatCloseBtn) chatCloseBtn.click();
                            }
                        } else {
                            alert('Failed to delete: ' + (res.message || 'Unknown error'));
                        }
                    } catch(e) {
                        alert('Error deleting discussion.');
                    }
                });
            }
        });

        // Load messages for the first discussion on load if active
        const initialDisc = document.querySelector('.chat-thread-item.active');
        if (initialDisc) {
            const initialId = initialDisc.getAttribute('data-chat-id');
            const header = document.querySelector('.chat-window-header');
            const footer = document.querySelector('.chat-window-footer');
            if (header) header.style.display = 'flex';
            if (footer) footer.style.display = 'block';
            loadThreadMessages(initialId);
        }
    }

    const chatCloseBtn = document.getElementById('chat-close-btn');
    if (chatCloseBtn) {
        chatCloseBtn.addEventListener('click', function() {
            document.querySelectorAll('.chat-thread-item').forEach(ti => ti.classList.remove('active'));
            const header = document.querySelector('.chat-window-header');
            const footer = document.querySelector('.chat-window-footer');
            const messagesContainer = document.querySelector('.chat-messages-container');
            
            if (header) header.style.display = 'none';
            if (footer) footer.style.display = 'none';
            if (messagesContainer) {
                messagesContainer.innerHTML = '<div style="text-align: center; color: var(--text-muted); margin-top: 50px;">Select a discussion to start messaging</div>';
            }
            window.currentActiveDiscussionId = null;
        });
    }

    const chatDeleteBtn = document.getElementById('chat-delete-btn');
    if (chatDeleteBtn) {
        chatDeleteBtn.addEventListener('click', async function() {
            if (!window.currentActiveDiscussionId) return;
            if (confirm('Are you sure you want to delete this discussion and all its messages? This cannot be undone.')) {
                const fd = new FormData();
                fd.append('discussion_id', window.currentActiveDiscussionId);
                try {
                    const r = await fetch('api.php?action=delete_discussion', { method: 'POST', body: fd });
                    const res = await r.json();
                    if (res.success) {
                        alert('Discussion deleted successfully.');
                        if (chatCloseBtn) chatCloseBtn.click();
                        loadDiscussionsList();
                    } else {
                        alert('Failed to delete: ' + res.message);
                    }
                } catch(e) {
                    alert('Error deleting discussion.');
                }
            }
        });
    }

    // Audio/Video call logic is now handled by inline onclick calling vyalaCalls from calls.js

    
    // Discussion Search & Group Filter
    function filterDiscussionThreads() {
        const query = (document.getElementById('chat-search')?.value || '').toLowerCase().trim();
        const activeGroupBtn = document.querySelector('.chat-group-buttons .chat-group-btn.active');
        const activeGroup = activeGroupBtn ? activeGroupBtn.getAttribute('data-chat-group') : 'All';

        threadItems.forEach(item => {
            const nameEl = item.querySelector('.chat-thread-name');
            const previewEl = item.querySelector('.chat-thread-preview');
            const name = nameEl ? nameEl.textContent.toLowerCase() : '';
            const preview = previewEl ? previewEl.textContent.toLowerCase() : '';
            const itemType = item.getAttribute('data-chat-type');

            const matchesSearch = name.includes(query) || preview.includes(query);
            const matchesGroup = (activeGroup === 'All' || itemType === activeGroup);

            if (matchesSearch && matchesGroup) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    const chatSearch = document.getElementById('chat-search');
    if (chatSearch) {
        chatSearch.addEventListener('input', filterDiscussionThreads);
    }

    const groupButtons = document.querySelectorAll('.chat-group-buttons .chat-group-btn');
    if (groupButtons.length > 0) {
        groupButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                groupButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                filterDiscussionThreads();
            });
        });
    }

    // Run initial filter on load to match the default active group ("General")
    if (threadItems.length > 0) {
        filterDiscussionThreads();
    }

    // Paperclip attachment click trigger
    const attachBtn = document.getElementById('btn-chat-attach');
    const fileInput = document.getElementById('chat-file-input');
    if (attachBtn && fileInput) {
        attachBtn.addEventListener('click', function() {
            fileInput.click();
        });
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                attachBtn.style.color = '#2563eb';
            } else {
                attachBtn.style.color = 'var(--text-muted)';
            }
        });
    }

    // E. Documents Menu Items Toggle
    const docMenuItems = document.querySelectorAll('.documents-menu-item');
    if (docMenuItems.length > 0) {
        docMenuItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                docMenuItems.forEach(mi => mi.classList.remove('active'));
                item.classList.add('active');
                currentDocsFilterCat = item.getAttribute('data-doc-cat') || 'all';
                currentDocsPage = 1;
                filterAndPaginateDocs();
            });
        });
    }

    // Documents Folder Card click simulation
    document.querySelectorAll('.folder-card').forEach(folder => {
        folder.addEventListener('click', function() {
            const folderName = folder.querySelector('.folder-name').textContent;
            const docSearch = document.getElementById('docs-search');
            if (docSearch) {
                docSearch.value = folderName;
                currentDocsPage = 1;
                filterAndPaginateDocs();
            }
        });
    });

    // Delete Document handler
    document.querySelectorAll('.btn-doc-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const docId = btn.getAttribute('data-doc-id');
            if (confirm('Are you sure you want to delete this document?')) {
                const formData = new FormData();
                formData.append('id', docId);
                fetch('api.php?action=delete_document', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert(res.message);
                        window.location.reload();
                    } else {
                        alert('Error: ' + res.message);
                    }
                });
            }
        });
    });

    // F. Notes Sub-tabs Filter
    const noteSubTabs = document.querySelectorAll('#notes-view-tabs .note-tab-btn');
    if (noteSubTabs.length > 0) {
        noteSubTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                noteSubTabs.forEach(t => {
                    t.classList.remove('active');
                    t.style.color = '#64748b';
                    t.style.borderBottom = 'none';
                });
                tab.classList.add('active');
                tab.style.color = '#2563eb';
                tab.style.borderBottom = '2px solid #2563eb';
                filterNotes();
            });
        });
    }

    // Notes sticky card CRUD button handlers
    const notesContainer = document.getElementById('notes-grid-container');
    if (notesContainer) {
        notesContainer.addEventListener('click', function(e) {
            const editBtn = e.target.closest('.note-action-icon[title="Edit Note"]');
            const delBtn = e.target.closest('.note-action-icon[title="Delete Note"]');
            const card = e.target.closest('.note-sticky-card');
            
            if (!card) return;
            const noteId = card.getAttribute('data-note-id');

            if (editBtn) {
                const title = card.querySelector('.note-sticky-title').textContent;
                // Get innerText of content (excluding footer)
                const content = card.querySelector('.note-sticky-content').innerText;
                const category = card.getAttribute('data-category');
                const tags = card.getAttribute('data-tags') || '';

                document.getElementById('edit-note-id').value = noteId;
                document.getElementById('edit-note-title').value = title;
                document.getElementById('edit-note-content').value = content;
                document.getElementById('edit-note-category').value = category.charAt(0).toUpperCase() + category.slice(1);
                document.getElementById('edit-note-tags').value = tags;
                
                document.getElementById('modal-edit-note').classList.add('active');
            }

            if (delBtn) {
                if (confirm('Are you sure you want to delete this note?')) {
                    const formData = new FormData();
                    formData.append('id', noteId);
                    fetch('api.php?action=delete_note', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            card.remove();
                        } else {
                            alert('Error: ' + res.message);
                        }
                    });
                }
            }
        });
    }

    // Note Pin button click toggle
    document.querySelectorAll('.note-pin-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const isPinned = btn.style.transform === 'rotate(45deg)';
            if (isPinned) {
                btn.style.transform = 'none';
                btn.style.opacity = '0.5';
            } else {
                btn.style.transform = 'rotate(45deg)';
                btn.style.opacity = '1';
            }
        });
    });

    // G. Attendance check-in/out button listeners
    const checkinBtn = document.getElementById('btn-attendance-checkin');
    const checkoutBtn = document.getElementById('btn-attendance-checkout');

    if (checkinBtn) {
        checkinBtn.addEventListener('click', function() {
            fetch('api.php?action=check_in', { method: 'POST' })
            .then(r => r.json())
            .then(res => {
                alert(res.message);
                if (res.success) {
                    window.location.reload();
                }
            });
        });
    }

    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function() {
            fetch('api.php?action=check_out', { method: 'POST' })
            .then(r => r.json())
            .then(res => {
                alert(res.message);
                if (res.success) {
                    window.location.reload();
                }
            });
        });
    }
}

// Helper: Setup dropdown popup toggling and closing outside
function setupDropdownToggle(btnId, dropId) {
    const btn = document.getElementById(btnId);
    const drop = document.getElementById(dropId);
    if (btn && drop) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            // Toggle
            const isOpened = drop.style.display === 'block';
            document.querySelectorAll('.dropdown-menu').forEach(d => d.style.display = 'none');
            document.querySelectorAll('.filter-dropdown-menu').forEach(d => d.style.display = 'none');
            drop.style.display = isOpened ? 'none' : 'block';
        });
        document.addEventListener('click', function(e) {
            if (!drop.contains(e.target) && e.target !== btn) {
                drop.style.display = 'none';
            }
        });
    }
}

// ==========================================================================
// CLIENTS & DEPARTMENTS CRUD ACTIONS
// ==========================================================================
function setupClientsAndDepartmentsCrud() {
    // CLIENTS CRUD
    // A. Edit Client Button Click (Delegation)
    document.querySelectorAll('.btn-client-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = btn.getAttribute('data-client-id');
            const name = btn.getAttribute('data-client-name');
            const email = btn.getAttribute('data-client-email');
            const phone = btn.getAttribute('data-client-phone');

            document.getElementById('edit-c-id').value = id;
            document.getElementById('edit-c-name').value = name;
            document.getElementById('edit-c-email').value = email;
            document.getElementById('edit-c-phone').value = phone;

            document.getElementById('modal-client-edit').classList.add('active');
        });
    });

    // B. Delete Client Button Click
    document.querySelectorAll('.btn-client-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = btn.getAttribute('data-client-id');
            if (confirm("Are you sure you want to delete this client? This will clear references in projects.")) {
                const formData = new FormData();
                formData.append('id', id);
                fetch('api.php?action=delete_client', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    alert(res.message);
                    if (res.success) {
                        window.location.reload();
                    }
                });
            }
        });
    });

    // DEPARTMENTS CRUD
    // A. Edit Department Button Click
    document.querySelectorAll('.btn-dept-edit').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = btn.getAttribute('data-dept-id');
            const name = btn.getAttribute('data-dept-name');
            const icon = btn.getAttribute('data-dept-icon');
            const color = btn.getAttribute('data-dept-color');
            const bg = btn.getAttribute('data-dept-bg');

            document.getElementById('edit-dept-id').value = id;
            document.getElementById('edit-dept-name').value = name;
            document.getElementById('edit-dept-icon').value = icon;
            document.getElementById('edit-dept-color').value = color;
            document.getElementById('edit-dept-bg').value = bg;

            document.getElementById('modal-department-edit').classList.add('active');
        });
    });

    // B. Delete Department Button Click
    document.querySelectorAll('.btn-dept-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = btn.getAttribute('data-dept-id');
            if (confirm("Are you sure you want to delete this department?")) {
                const formData = new FormData();
                formData.append('id', id);
                fetch('api.php?action=delete_department', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(res => {
                    alert(res.message);
                    if (res.success) {
                        window.location.reload();
                    }
                });
            }
        });
    });
}

// ==========================================================================
// DISCUSSION MEMBERS MANAGEMENT
// ==========================================================================
function setupDiscussionMembersManager() {
    const memBtn = document.getElementById('chat-header-members');
    if (memBtn) {
        memBtn.addEventListener('click', function() {
            const activeThread = document.querySelector('.chat-thread-item.active');
            if (!activeThread) {
                alert('Please select a discussion channel first.');
                return;
            }
            const discId = activeThread.getAttribute('data-chat-id');
            loadDiscussionMembers(discId);
            document.getElementById('modal-discussion-members').classList.add('active');
        });
    }
}

function loadDiscussionMembers(discussionId) {
    const activeList = document.getElementById('discussion-active-members-list');
    const nonList = document.getElementById('discussion-non-members-list');
    const meId = window.VYALA_TASKPAD_DASHBOARD_DATA?.meId;

    if (!activeList || !nonList) return;

    activeList.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:8px;">Loading...</div>';
    nonList.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:8px;">Loading...</div>';

    fetch(`api.php?action=get_discussion_members&discussion_id=${discussionId}`)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            // Render active members
            activeList.innerHTML = '';
            if (res.members.length === 0) {
                activeList.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:8px;">No active members.</div>';
            } else {
                res.members.forEach(m => {
                    const isAdmin = (window.VYALA_USER_ROLE === 'Admin');
                    const isMe = (m.id == meId);
                    const removeBtnHtml = isAdmin ? `<button class="btn btn-secondary btn-remove-member" data-emp-id="${m.id}" style="height:24px; padding:2px 6px; font-size:10px; color:#ef4444; border-color:#fca5a5;">Remove</button>` : '';
                    const actionBtnsHtml = isMe ? '' : `
                        <div style="display:flex; gap:8px; align-items:center;">
                            <i data-lucide="message-square" class="member-action-msg" data-emp-id="${m.id}" data-emp-name="${escapeHtml(m.name)}" style="width:14px; height:14px; cursor:pointer; color:#3b82f6;" title="Direct Message"></i>
                            <i data-lucide="phone" class="member-action-audio" data-emp-id="${m.id}" style="width:14px; height:14px; cursor:pointer; color:#10b981;" title="Audio Call"></i>
                            <i data-lucide="video" class="member-action-video" data-emp-id="${m.id}" style="width:14px; height:14px; cursor:pointer; color:#8b5cf6;" title="Video Call"></i>
                            ${removeBtnHtml}
                        </div>
                    `;
                    const itemHtml = `
                        <div style="display:flex; align-items:center; justify-content:space-between; padding:4px 0;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="background:#2563eb; color:#fff; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700;">${m.avatar || m.name.substring(0,2).toUpperCase()}</div>
                                <span style="font-size:12.5px; font-weight:600; color:var(--text-main);">${escapeHtml(m.name)}</span>
                            </div>
                            ${actionBtnsHtml}
                        </div>
                    `;
                    activeList.insertAdjacentHTML('beforeend', itemHtml);
                });
            }

            // Render non-members
            nonList.innerHTML = '';
            if (res.non_members.length === 0) {
                nonList.innerHTML = '<div style="font-size:12px; color:var(--text-muted); padding:8px;">All team members are already added.</div>';
            } else {
                res.non_members.forEach(m => {
                    const isAdmin = (window.VYALA_USER_ROLE === 'Admin');
                    const addBtnHtml = isAdmin ? `<button class="btn btn-primary btn-add-member" data-emp-id="${m.id}" style="height:24px; padding:2px 8px; font-size:10px; background:#10b981;">Add</button>` : '';
                    const actionBtnsHtml = `
                        <div style="display:flex; gap:8px; align-items:center;">
                            <i data-lucide="message-square" class="member-action-msg" data-emp-id="${m.id}" data-emp-name="${escapeHtml(m.name)}" style="width:14px; height:14px; cursor:pointer; color:#3b82f6;" title="Direct Message"></i>
                            <i data-lucide="phone" class="member-action-audio" data-emp-id="${m.id}" style="width:14px; height:14px; cursor:pointer; color:#10b981;" title="Audio Call"></i>
                            <i data-lucide="video" class="member-action-video" data-emp-id="${m.id}" style="width:14px; height:14px; cursor:pointer; color:#8b5cf6;" title="Video Call"></i>
                            ${addBtnHtml}
                        </div>
                    `;
                    const itemHtml = `
                        <div style="display:flex; align-items:center; justify-content:space-between; padding:4px 0;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="background:#64748b; color:#fff; width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700;">${m.avatar || m.name.substring(0,2).toUpperCase()}</div>
                                <span style="font-size:12.5px; font-weight:600; color:var(--text-main);">${escapeHtml(m.name)}</span>
                            </div>
                            ${actionBtnsHtml}
                        </div>
                    `;
                    nonList.insertAdjacentHTML('beforeend', itemHtml);
                });
            }

            // Bind member click actions
            bindDiscussionMembersActions(discussionId);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        } else {
            console.error('Error fetching members:', res.message);
        }
    })
    .catch(err => {
        console.error(err);
    });
}

function bindDiscussionMembersActions(discussionId) {
    document.querySelectorAll('.btn-add-member').forEach(btn => {
        btn.addEventListener('click', async function() {
            const empId = btn.getAttribute('data-emp-id');
            const formData = new FormData();
            formData.append('discussion_id', discussionId);
            formData.append('employee_id', empId);

            try {
                // First add the member
                const res = await fetch('api.php?action=add_discussion_member', { method: 'POST', body: formData }).then(r=>r.json());
                
                if (res.success) {
                    // Then encrypt and store the group AES key for them
                    if (window.currentAESKey) {
                        const pkRes = await fetch(`api.php?action=get_employee_public_key&employee_id=${empId}`).then(r=>r.json());
                        if (pkRes.success && pkRes.public_key) {
                            // Re-export AES key because currentAESKey is a CryptoKey or raw string?
                            // currentAESKey is raw string from decryptAESKeyWithRSA
                            const encTarget = await encryptAESKeyWithRSA(window.currentAESKey, pkRes.public_key);
                            
                            const keyForm = new FormData();
                            keyForm.append('discussion_id', discussionId);
                            keyForm.append('employee_id', empId);
                            keyForm.append('encrypted_key', encTarget);
                            
                            await fetch('api.php?action=store_discussion_key', { method: 'POST', body: keyForm });
                        }
                    }
                    loadDiscussionMembers(discussionId);
                } else {
                    alert('Error: ' + res.message);
                }
            } catch (err) {
                console.error(err);
                alert('Failed to add member completely.');
            }
        });
    });

    document.querySelectorAll('.btn-remove-member').forEach(btn => {
        btn.addEventListener('click', function() {
            const empId = btn.getAttribute('data-emp-id');
            const formData = new FormData();
            formData.append('discussion_id', discussionId);
            formData.append('employee_id', empId);

            fetch('api.php?action=remove_discussion_member', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    loadDiscussionMembers(discussionId);
                } else {
                    alert('Error: ' + res.message);
                }
            });
        });
    });

    document.querySelectorAll('.member-action-msg').forEach(btn => {
        btn.addEventListener('click', async function() {
            const empId = btn.getAttribute('data-emp-id');
            const empName = btn.getAttribute('data-emp-name');
            
            // Generate a shared AES key for the DM
            try {
                const aesKeyObj = await generateAESKey();
                const aesKeyBase64 = await exportAESKey(aesKeyObj);
                
                // Fetch target's public key
                const pkRes = await fetch(`api.php?action=get_employee_public_key&employee_id=${empId}`).then(r=>r.json());
                const targetPubKey = pkRes.public_key;
                
                // Encrypt AES key for self
                const selfPubKey = localStorage.getItem('e2ee_public_key');
                const encSelf = await encryptAESKeyWithRSA(aesKeyBase64, selfPubKey);
                
                // Encrypt AES key for target
                let encTarget = '';
                if (targetPubKey) {
                    encTarget = await encryptAESKeyWithRSA(aesKeyBase64, targetPubKey);
                } else {
                    // Fallback or warning if target has no key yet
                    alert("User has not set up encryption keys. Cannot create secure chat.");
                    return;
                }
                
                const formData = new FormData();
                formData.append('target_employee_id', empId);
                formData.append('encrypted_key_self', encSelf);
                formData.append('encrypted_key_target', encTarget);
                
                const res = await fetch('api.php?action=create_direct_message', { method: 'POST', body: formData }).then(r=>r.json());
                
                if (res.success) {
                    alert('Direct message thread created/opened!');
                    window.location.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            } catch(err) {
                console.error("DM creation failed:", err);
            }
        });
    });

    const meId = window.VYALA_TASKPAD_DASHBOARD_DATA?.meId;
    document.querySelectorAll('.member-action-audio').forEach(btn => {
        btn.addEventListener('click', function() {
            const empId = btn.getAttribute('data-emp-id');
            const roomId = "direct_" + Math.min(meId, empId) + "_" + Math.max(meId, empId);
            window.open(`video_call.php?discussion_id=${roomId}&type=audio`, '_blank', 'width=800,height=600');
        });
    });

    document.querySelectorAll('.member-action-video').forEach(btn => {
        btn.addEventListener('click', function() {
            const empId = btn.getAttribute('data-emp-id');
            const roomId = "direct_" + Math.min(meId, empId) + "_" + Math.max(meId, empId);
            window.open(`video_call.php?discussion_id=${roomId}&type=video`, '_blank', 'width=800,height=600');
        });
    });
}

// ==========================================================================
// DIRECT CHAT (ACTIVE MEMBERS) SELECTION & START
// ==========================================================================
let allActiveUsersForDirectChat = [];

function setupDirectChatManager() {
    const btnNewGroup = document.getElementById('btn-new-group');
    if (btnNewGroup) {
        btnNewGroup.addEventListener('click', function() {
            loadActiveUsersForDirectChat();
        });
    }

    const searchInput = document.getElementById('direct-chat-member-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterDirectChatUsers();
        });
    }

    // Auto-restore active thread after reload
    const activeDiscussionId = sessionStorage.getItem('active_discussion_id_after_reload');
    if (activeDiscussionId) {
        sessionStorage.removeItem('active_discussion_id_after_reload');
        
        // Switch tab to discussion
        window.location.hash = '#discussion';
        
        // Find direct tab button and click it to show direct threads
        const directBtn = document.querySelector('.chat-group-buttons .chat-group-btn[data-chat-group="Direct"]');
        if (directBtn) {
            directBtn.click();
        }

        setTimeout(() => {
            const threadItem = document.querySelector(`.chat-thread-item[data-chat-id="${activeDiscussionId}"]`);
            if (threadItem) {
                threadItem.click();
            }
        }, 300);
    }
}

function loadActiveUsersForDirectChat() {
    const listContainer = document.getElementById('direct-chat-users-list');
    if (!listContainer) return;

    listContainer.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 20px;">Loading users...</div>';

    fetch('api.php?action=get_active_users')
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            allActiveUsersForDirectChat = res.users || [];
            renderDirectChatUsers(allActiveUsersForDirectChat);
        } else {
            listContainer.innerHTML = `<div style="text-align: center; color: #dc2626; padding: 20px;">Error: ${res.message}</div>`;
        }
    })
    .catch(err => {
        console.error(err);
        listContainer.innerHTML = '<div style="text-align: center; color: #dc2626; padding: 20px;">Failed to load users.</div>';
    });
}

function renderDirectChatUsers(users) {
    const listContainer = document.getElementById('direct-chat-users-list');
    if (!listContainer) return;

    listContainer.innerHTML = '';
    if (users.length === 0) {
        listContainer.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 20px;">No members found.</div>';
        return;
    }

    users.forEach(user => {
        const initials = user.avatar || user.name.substring(0, 2).toUpperCase();
        let colorClass = 'aac-sj';
        if (initials === 'DR') colorClass = 'ta-dr';
        if (initials === 'DS') colorClass = 'ta-ds';
        if (initials === 'KG') colorClass = 'ta-kg';

        const userHtml = `
            <div class="direct-chat-user-item" data-user-id="${user.id}" data-user-name="${user.name}" style="display: flex; align-items: center; justify-content: space-between; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: background 0.2s; background: #ffffff;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div class="team-avatar-initials ${colorClass}" style="width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; color: #ffffff;">${initials}</div>
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-weight: 600; color: #0f172a; font-size: 13.5px;">${user.name}</span>
                        <span style="font-size: 11.5px; color: #64748b;">${user.role}</span>
                    </div>
                </div>
                <span style="font-size: 11px; color: #2563eb; font-weight: 600; background: #eff6ff; padding: 4px 8px; border-radius: 4px;">Message</span>
            </div>
        `;
        listContainer.insertAdjacentHTML('beforeend', userHtml);
    });

    listContainer.querySelectorAll('.direct-chat-user-item').forEach(item => {
        item.addEventListener('click', function() {
            const userId = item.getAttribute('data-user-id');
            startDirectChatWithUser(userId);
        });
        
        item.addEventListener('mouseenter', () => item.style.background = '#f8fafc');
        item.addEventListener('mouseleave', () => item.style.background = '#ffffff');
    });
}

function filterDirectChatUsers() {
    const searchVal = (document.getElementById('direct-chat-member-search')?.value || '').toLowerCase().trim();
    const usersList = allActiveUsersForDirectChat || [];
    const filtered = usersList.filter(u => {
        const name = (u.name || '').toLowerCase();
        const role = (u.role || '').toLowerCase();
        const email = (u.email || '').toLowerCase();
        return name.includes(searchVal) || role.includes(searchVal) || email.includes(searchVal);
    });
    renderDirectChatUsers(filtered);
}

function startDirectChatWithUser(userId) {
    const formData = new FormData();
    formData.append('user_id', userId);

    fetch('api.php?action=start_direct_conversation', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            document.getElementById('modal-start-direct-chat').classList.remove('active');
            sessionStorage.setItem('active_discussion_id_after_reload', res.discussion_id);
            window.location.reload();
        } else {
            alert('Error: ' + res.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Failed to start conversation.');
    });
}

// ==========================================================================
// DOCUMENTS SIDEBAR FILTER & PAGINATION & PREVIEW
// ==========================================================================
let currentDocsFilterCat = 'all';
let currentDocsPage = 1;
const docsPerPage = 5;

function filterAndPaginateDocs() {
    const query = (document.getElementById('docs-search')?.value || '').toLowerCase().trim();
    const rows = Array.from(document.querySelectorAll('.document-row'));
    
    // 1. Filter
    const filteredRows = rows.filter(row => {
        const name = (row.getAttribute('data-doc-name') || '').toLowerCase();
        const cat = row.getAttribute('data-doc-cat') || 'other';
        
        const matchesQuery = name.includes(query);
        const matchesCat = currentDocsFilterCat === 'all' || cat === currentDocsFilterCat;
        
        return matchesQuery && matchesCat;
    });
    
    // Hide all rows initially
    rows.forEach(r => r.style.display = 'none');
    const emptyRow = document.getElementById('doc-empty-row');
    if (emptyRow) emptyRow.style.display = 'none';

    // 2. Paginate
    const totalFiles = filteredRows.length;
    const totalPages = Math.ceil(totalFiles / docsPerPage) || 1;
    if (currentDocsPage > totalPages) currentDocsPage = totalPages;
    if (currentDocsPage < 1) currentDocsPage = 1;
    
    const startIdx = (currentDocsPage - 1) * docsPerPage;
    const endIdx = Math.min(startIdx + docsPerPage, totalFiles);
    
    for (let i = startIdx; i < endIdx; i++) {
        filteredRows[i].style.display = '';
    }
    
    // Show empty rows if zero records matching
    if (totalFiles === 0 && emptyRow) {
        emptyRow.style.display = '';
        const tableBody = emptyRow.closest('tbody');
        if (tableBody) tableBody.appendChild(emptyRow); // Ensure it's at the end
    }

    // 3. Update pagination UI
    const infoSpan = document.getElementById('doc-pagination-info');
    if (infoSpan) {
        if (totalFiles === 0) {
            infoSpan.textContent = 'Showing 0-0 of 0 files';
        } else {
            infoSpan.textContent = `Showing ${startIdx + 1}-${endIdx} of ${totalFiles} files`;
        }
    }
    
    const prevBtn = document.getElementById('btn-doc-prev');
    const nextBtn = document.getElementById('btn-doc-next');
    if (prevBtn) prevBtn.disabled = (currentDocsPage === 1);
    if (nextBtn) nextBtn.disabled = (currentDocsPage === totalPages);
}

function filterAndPaginateDocsInit() {
    const prevBtn = document.getElementById('btn-doc-prev');
    const nextBtn = document.getElementById('btn-doc-next');
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            if (currentDocsPage > 1) {
                currentDocsPage--;
                filterAndPaginateDocs();
            }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            currentDocsPage++;
            filterAndPaginateDocs();
        });
    }

    // Document Preview bindings
    document.querySelectorAll('.btn-doc-preview').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const name = btn.getAttribute('data-doc-name');
            const path = btn.getAttribute('data-doc-path');
            const ext = name.split('.').pop().toLowerCase();
            const titleElem = document.getElementById('doc-preview-title');
            const bodyElem = document.getElementById('doc-preview-body');
            
            if (titleElem) titleElem.textContent = 'Preview: ' + name;
            
            if (bodyElem) {
                bodyElem.innerHTML = '';
                if (['png', 'jpg', 'jpeg', 'webp', 'gif'].includes(ext)) {
                    bodyElem.innerHTML = `<img src="${path}" style="max-width: 100%; max-height: 400px; border-radius: 6px; box-shadow: var(--shadow-md);">`;
                } else if (ext === 'pdf') {
                    bodyElem.innerHTML = `<iframe src="${path}" style="width: 100%; height: 400px; border: none; border-radius: 6px;"></iframe>`;
                } else {
                    bodyElem.innerHTML = `
                        <div style="padding: 40px; color: var(--text-muted);">
                            <i data-lucide="file" style="width: 48px; height: 48px; margin-bottom: 12px; stroke-width: 1.5; margin:0 auto 12px auto; display:block;"></i>
                            <p style="font-size: 13px;">No direct preview available for this file type.</p>
                            <a href="${path}" download="${name}" class="btn btn-primary" style="margin-top: 14px;"><i data-lucide="download"></i> Download File</a>
                        </div>
                    `;
                    lucide.createIcons();
                }
            }
            
            const modal = document.getElementById('modal-doc-preview');
            if (modal) modal.classList.add('active');
        });
    });
}

function filterAndPaginateDocs() {
    const query = (document.getElementById('docs-search')?.value || '').toLowerCase().trim();
    const rows = Array.from(document.querySelectorAll('.document-row'));
    
    const filteredRows = rows.filter(row => {
        const name = (row.getAttribute('data-doc-name') || '').toLowerCase();
        const cat = row.getAttribute('data-doc-cat') || 'other';
        
        const matchesQuery = name.includes(query);
        const matchesCat = currentDocsFilterCat === 'all' || cat === currentDocsFilterCat;
        
        return matchesQuery && matchesCat;
    });
    
    rows.forEach(r => r.style.display = 'none');
    const emptyRow = document.getElementById('doc-empty-row');
    if (emptyRow) emptyRow.style.display = 'none';

    const totalFiles = filteredRows.length;
    const totalPages = Math.ceil(totalFiles / docsPerPage) || 1;
    if (currentDocsPage > totalPages) currentDocsPage = totalPages;
    if (currentDocsPage < 1) currentDocsPage = 1;
    
    const startIdx = (currentDocsPage - 1) * docsPerPage;
    const endIdx = Math.min(startIdx + docsPerPage, totalFiles);
    
    for (let i = startIdx; i < endIdx; i++) {
        filteredRows[i].style.display = '';
    }
    
    if (totalFiles === 0 && emptyRow) {
        emptyRow.style.display = '';
    }

    const infoSpan = document.getElementById('doc-pagination-info');
    if (infoSpan) {
        if (totalFiles === 0) {
            infoSpan.textContent = 'Showing 0-0 of 0 files';
        } else {
            infoSpan.textContent = `Showing ${startIdx + 1}-${endIdx} of ${totalFiles} files`;
        }
    }
    
    const prevBtn = document.getElementById('btn-doc-prev');
    const nextBtn = document.getElementById('btn-doc-next');
    if (prevBtn) prevBtn.disabled = (currentDocsPage === 1);
    if (nextBtn) nextBtn.disabled = (currentDocsPage === totalPages);
}

// Note: filterAndPaginateDocs is defined once above; duplicate removed.

// ==========================================================================
// PROJECTS FILTERING, SORTING AND RENDERING
// ==========================================================================
let currentProjectsFilterPriority = 'All';
let currentProjectsFilterStatus = 'All';
let currentProjectsSort = 'default';
let currentProjectsFilterDateRange = 'All';

function filterAndSortProjects() {
    const query = (document.getElementById('projects-search')?.value || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.project-grid-card');

    cards.forEach(card => {
        const name = (card.getAttribute('data-project-name') || '').toLowerCase();
        const desc = (card.querySelector('.project-card-description')?.textContent || '').toLowerCase();
        const priority = card.getAttribute('data-priority') || 'Medium';
        const status = card.getAttribute('data-status') || 'Active';
        const createdAt = card.getAttribute('data-created-at') || '';
        const createdBy = card.getAttribute('data-created-by') || '';
        const assignedTo = card.getAttribute('data-assigned-to') || '';

        const matchesQuery = name.includes(query) || desc.includes(query);
        const matchesPriority = currentProjectsFilterPriority === 'All' || priority === currentProjectsFilterPriority;
        const matchesStatus = currentProjectsFilterStatus === 'All' || status === currentProjectsFilterStatus;
        const matchesDate = isDateInRange(createdAt, currentProjectsFilterDateRange);

        // Sub-tabs filter logic (Created by me / Assignee to me / My Team)
        const activeTabBtn = document.querySelector('.proj-tab-btn.active');
        let matchesTab = true;
        if (activeTabBtn) {
            const filterText = activeTabBtn.textContent.trim();
            const meId = String(window.VYALA_TASKPAD_DASHBOARD_DATA?.meId || '');
            if (filterText === 'Created By Me') {
                matchesTab = (createdBy === meId);
            } else if (filterText === 'Assignee To Me') {
                const avatarStack = card.querySelector('.assignee-avatar-stack')?.textContent || '';
                const myInitials = window.VYALA_TASKPAD_DASHBOARD_DATA?.meAvatar || 'SJ';
                matchesTab = (assignedTo === meId || avatarStack.includes(myInitials));
            } else if (filterText === 'My Team Project') {
                    const memberIds = (card.getAttribute('data-team-member-ids') || '').split(',').map(s => s.trim()).filter(Boolean);
                    matchesTab = memberIds.includes(String(meId));
                }
        }

        if (matchesQuery && matchesPriority && matchesStatus && matchesDate && matchesTab) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });

    // Handle sort in DOM
    if (currentProjectsSort !== 'default') {
        const container = document.getElementById('projects-grid-container');
        if (container) {
            const cardsArray = Array.from(container.querySelectorAll('.project-grid-card'));
            cardsArray.sort((a, b) => {
                if (currentProjectsSort === 'name-asc') {
                    const na = (a.getAttribute('data-project-name') || '');
                    const nb = (b.getAttribute('data-project-name') || '');
                    return na.localeCompare(nb);
                } else if (currentProjectsSort === 'name-desc') {
                    const na = (a.getAttribute('data-project-name') || '');
                    const nb = (b.getAttribute('data-project-name') || '');
                    return nb.localeCompare(na);
                } else if (currentProjectsSort === 'created-newest') {
                    const ca = a.getAttribute('data-created-at') || '';
                    const cb = b.getAttribute('data-created-at') || '';
                    return cb.localeCompare(ca);
                } else if (currentProjectsSort === 'completion-high') {
                    const pa = parseFloat(a.getAttribute('data-completion-rate') || '0');
                    const pb = parseFloat(b.getAttribute('data-completion-rate') || '0');
                    return pb - pa;
                }
                return 0;
            });
            cardsArray.forEach(c => container.appendChild(c));
        }
    }
}

// ==========================================================================
// NOTES TAG FILTERS DYNAMIC POPULATION & FILTERING
// ==========================================================================
let currentNotesFilterTag = 'All';

function populateNotesTagsDropdown() {
    const list = document.getElementById('notes-tags-dropdown-list');
    if (!list) return;
    
    // Clear dynamic items, keep "All Tags"
    list.innerHTML = '<a href="#" class="filter-tag-item" data-tag="All">All Tags</a>';
    
    const uniqueTags = new Set();
    document.querySelectorAll('.note-sticky-card').forEach(card => {
        const tagsStr = card.getAttribute('data-tags') || '';
        tagsStr.split(',').forEach(t => {
            const trimmed = t.trim().toLowerCase();
            if (trimmed) uniqueTags.add(trimmed);
        });
    });
    
    uniqueTags.forEach(tag => {
        const item = document.createElement('a');
        item.href = '#';
        item.className = 'filter-tag-item';
        item.setAttribute('data-tag', tag);
        item.textContent = tag;
        list.appendChild(item);
    });
    
    // Wire up the dropdown toggle for notes tags
    setupDropdownToggle('notes-tags-filter-toggle', 'notes-tags-filter-dropdown');
    
    // Re-bind click events
    document.querySelectorAll('.filter-tag-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const tag = item.getAttribute('data-tag');
            const toggleBtn = document.getElementById('notes-tags-filter-toggle');
            if (toggleBtn) {
                toggleBtn.innerHTML = `Tag: ${tag} <i data-lucide="chevron-down"></i>`;
                lucide.createIcons();
            }
            const dropMenu = document.getElementById('notes-tags-filter-dropdown');
            if (dropMenu) dropMenu.style.display = 'none';
            currentNotesFilterTag = tag;
            filterNotes();
        });
    });
}

function filterNotes() {
    const activeTabBtn = document.querySelector('#notes-view-tabs .note-tab-btn.active');
    const filter = activeTabBtn ? activeTabBtn.getAttribute('data-note-tab') : 'all';
    const query = (document.getElementById('notes-search')?.value || '').toLowerCase().trim();

    const notes = document.querySelectorAll('.note-sticky-card');
    notes.forEach(note => {
        const cat = (note.getAttribute('data-category') || '').toLowerCase();
        const title = (note.getAttribute('data-title') || '').toLowerCase();
        const content = (note.getAttribute('data-content') || '').toLowerCase();
        const tags = (note.getAttribute('data-tags') || '').toLowerCase();
        
        const matchesTab = (filter === 'all' || cat === filter);
        const matchesQuery = (title.includes(query) || content.includes(query));
        const matchesTag = (currentNotesFilterTag === 'All' || tags.includes(currentNotesFilterTag.toLowerCase()));

        if (matchesTab && matchesQuery && matchesTag) {
            note.style.display = '';
        } else {
            note.style.display = 'none';
        }
    });
}

// ==========================================================================
// ATTENDANCE FILTERING & CSV EXPORT
// ==========================================================================
let currentAttendanceFilterEmp = 'All';
let currentAttendanceFilterStatus = 'All';
let currentAttendanceFilterDateRange = 'ThisMonth';

function setupAttendanceFiltersAndExport() {
    // Attendance search box
    const attSearch = document.getElementById('attendance-search');
    if (attSearch) {
        attSearch.addEventListener('input', filterAttendance);
    }

    // Attendance Employee dropdown triggers
    setupDropdownToggle('attendance-emp-filter-toggle', 'attendance-emp-filter-dropdown');
    document.querySelectorAll('.filter-attendance-emp-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            currentAttendanceFilterEmp = item.getAttribute('data-emp');
            document.getElementById('attendance-emp-filter-toggle').innerHTML = `<i data-lucide="users"></i> Employee: ${currentAttendanceFilterEmp} <i data-lucide="chevron-down"></i>`;
            lucide.createIcons();
            document.getElementById('attendance-emp-filter-dropdown').style.display = 'none';
            filterAttendance();
        });
    });

    // Attendance Status dropdown triggers
    setupDropdownToggle('attendance-status-filter-toggle', 'attendance-status-filter-dropdown');
    document.querySelectorAll('.filter-attendance-status-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            currentAttendanceFilterStatus = item.getAttribute('data-status');
            document.getElementById('attendance-status-filter-toggle').innerHTML = `Status: ${currentAttendanceFilterStatus} <i data-lucide="chevron-down"></i>`;
            lucide.createIcons();
            document.getElementById('attendance-status-filter-dropdown').style.display = 'none';
            filterAttendance();
        });
    });

    // Attendance Date range dropdown triggers
    setupDropdownToggle('attendance-date-filter-toggle', 'attendance-date-filter-dropdown');
    document.querySelectorAll('.filter-attendance-date-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            currentAttendanceFilterDateRange = item.getAttribute('data-range');
            document.getElementById('attendance-date-filter-toggle').innerHTML = `<i data-lucide="calendar"></i> Date: ${item.textContent} <i data-lucide="chevron-down"></i>`;
            lucide.createIcons();
            document.getElementById('attendance-date-filter-dropdown').style.display = 'none';
            filterAttendance();
        });
    });

    // Export button binding
    const exportBtn = document.getElementById('btn-attendance-export');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            exportAttendanceToCSV();
        });
    }

    // Initialize Document paginator helpers
    filterAndPaginateDocsInit();
    filterAttendance();
}

function filterAttendance() {
    const query = (document.getElementById('attendance-search')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.attendance-row');
    
    rows.forEach(row => {
        const empName = (row.getAttribute('data-emp-name') || '').toLowerCase();
        const dateStr = row.getAttribute('data-date') || '';
        const status = row.getAttribute('data-status') || '';
        
        const matchesSearch = empName.includes(query);
        const matchesEmp = currentAttendanceFilterEmp === 'All' || row.getAttribute('data-emp-name') === currentAttendanceFilterEmp;
        const matchesStatus = currentAttendanceFilterStatus === 'All' || status === currentAttendanceFilterStatus;
        const matchesDate = isDateInRange(dateStr, currentAttendanceFilterDateRange);
        
        if (matchesSearch && matchesEmp && matchesStatus && matchesDate) {
            row.style.display = 'flex';
        } else {
            row.style.display = 'none';
        }
    });
}

function exportAttendanceToCSV() {
    const visibleRows = document.querySelectorAll('.attendance-row[style*="display: flex"], .attendance-row:not([style*="display: none"])');
    if (visibleRows.length === 0) {
        alert('No records to export.');
        return;
    }
    
    let csvContent = "\uFEFF"; // Byte Order Mark for UTF-8 Excel support
    csvContent += "Employee,Date,Check In,Check Out,Total Hours,Status\n";
    
    visibleRows.forEach(row => {
        const name = row.getAttribute('data-emp-name');
        const date = row.getAttribute('data-date');
        const checkin = row.getAttribute('data-check-in') || '-';
        const checkout = row.getAttribute('data-check-out') || '-';
        const hours = row.getAttribute('data-hours') || '-';
        const status = row.getAttribute('data-status');
        
        csvContent += `"${name}","${date}","${checkin}","${checkout}","${hours}","${status}"\n`;
    });
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const encodedUri = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `attendance_report_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// ==========================================================================
// CHAT MESSAGES LOADING
// ==========================================================================
async function loadThreadMessages(discussionId) {
    const msgsContainer = document.querySelector('.chat-messages-container');
    if (!msgsContainer) return;

    msgsContainer.style.opacity = '0.5';

    // Safely try to get AES key — but NEVER let this block message loading
    try {
        const keyRes = await fetch(`api.php?action=get_discussion_key&discussion_id=${discussionId}`).then(r=>r.json());
        if (keyRes.success && keyRes.encrypted_key) {
            try {
                window.currentAESKey = await decryptAESKeyWithRSA(keyRes.encrypted_key);
            } catch(keyErr) {
                console.warn('Could not decrypt AES key, using plain text mode:', keyErr);
                window.currentAESKey = null;
            }
        } else {
            window.currentAESKey = null;
        }
    } catch(keyFetchErr) {
        console.warn('Key fetch failed, using plain text mode:', keyFetchErr);
        window.currentAESKey = null;
    }

    // Always load messages regardless of key status
    try {
        const msgRes = await fetch(`api.php?action=get_messages&discussion_id=${discussionId}`).then(r=>r.json());
        msgsContainer.style.opacity = '1';
        if (msgRes.success) {
            renderChatMessages(msgRes.messages, msgsContainer);
        } else {
            msgsContainer.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:40px 20px;">Could not load messages: ' + (msgRes.message || 'Unknown error') + '</div>';
        }
    } catch(err) {
        msgsContainer.style.opacity = '1';
        msgsContainer.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:40px 20px;">Error loading messages. Please try again.</div>';
        console.error('loadThreadMessages error:', err);
    }
}

async function renderChatMessages(messages, container) {
    const data = window.VYALA_TASKPAD_DASHBOARD_DATA;
    const meId = data.meId;

    container.innerHTML = '';
    if (!messages || messages.length === 0) {
        container.innerHTML = '<div style="text-align: center; color: var(--text-muted); padding: 40px; font-size: 13px;">No messages in this discussion yet. Say hello! 👋</div>';
        return;
    }

    for (const m of messages) {
        const isMe = (m.sender_id == meId);
        const initials = m.sender_avatar || (m.sender_name || 'U').substring(0, 2).toUpperCase();
        
        // Avatar color class
        let colorClass = isMe ? 'aac-sj' : 'da-pu';
        if (initials === 'DR') colorClass = 'ta-dr';
        if (initials === 'DS') colorClass = 'ta-ds';
        if (initials === 'KG') colorClass = 'ta-kg';

        let attachmentHtml = '';
        if (m.attachment_name) {
            const ext = (m.attachment_type || '').toLowerCase();
            const attachClass = ext === 'pdf' ? 'pdf-style' : 'dwg-style';
            const icon = ext === 'pdf' ? 'file-text' : 'file';
            const filePath = `uploads/${m.attachment_name}`;
            attachmentHtml = `
                <a href="${filePath}" download="${escapeHtml(m.attachment_name)}" class="chat-file-attachment ${attachClass}">
                    <i data-lucide="${icon}"></i>
                    <span>${escapeHtml(m.attachment_name)}</span>
                </a>
            `;
        }
        
        // Determine display message
        let displayMsg = m.message || '';
        if (window.currentAESKey && displayMsg.startsWith('{')) {
            // Has AES key - try to decrypt AES payload
            displayMsg = await decryptMessageAES(displayMsg, window.currentAESKey);
        } else if (!window.currentAESKey && displayMsg.startsWith('{')) {
            // No key, but JSON payload - try to extract as legacy or show indicator
            try {
                const parsed = JSON.parse(displayMsg);
                if (parsed && typeof parsed === 'object' && parsed[meId]) {
                    displayMsg = await decryptMessage(parsed[meId]);
                } else if (parsed && parsed.ciphertext) {
                    displayMsg = '[Encrypted Message - Key Required]';
                }
            } catch(e) {
                // Not valid JSON, just show as-is
            }
        }
        // Otherwise plain text - show as-is

        const senderNameHtml = !isMe 
            ? `<span style="font-size: 11px; font-weight: 600; color: var(--accent-color); margin-bottom: 3px; display: block;">${escapeHtml(m.sender_name || 'User')}</span>`
            : '';

        const msgHtml = `
            <div class="chat-bubble-wrapper ${isMe ? 'outgoing' : ''}">
                <div class="chat-bubble-avatar ${colorClass}">${initials}</div>
                <div style="display: flex; flex-direction: column; max-width: 75%;">
                    ${senderNameHtml}
                    <div class="chat-bubble-content">
                        ${escapeHtml(displayMsg)}
                        ${attachmentHtml}
                    </div>
                    <span class="chat-bubble-time">${m.time_text}${isMe ? ' ✓✓' : ''}</span>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', msgHtml);
    }

    lucide.createIcons();
    // Scroll to bottom
    container.scrollTop = container.scrollHeight;
}

// ==========================================================================
// MESSAGE AUTO-POLLING (WhatsApp-style real-time)
// ==========================================================================
let _msgPollTimer = null;
let _lastMsgCount = 0;

function startMessagePolling(discussionId) {
    stopMessagePolling();
    _msgPollTimer = setInterval(async () => {
        if (!window.currentActiveDiscussionId || window.currentActiveDiscussionId != discussionId) {
            stopMessagePolling();
            return;
        }
        try {
            const res = await fetch(`api.php?action=get_messages&discussion_id=${discussionId}`).then(r => r.json());
            if (res.success && res.messages) {
                // Only re-render if count changed (new messages arrived)
                if (res.messages.length !== _lastMsgCount) {
                    _lastMsgCount = res.messages.length;
                    const container = document.querySelector('.chat-messages-container');
                    if (container) {
                        const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 60;
                        await renderChatMessages(res.messages, container);
                        if (wasAtBottom) container.scrollTop = container.scrollHeight;
                    }
                }
            }
        } catch(e) { /* silent poll failure */ }
    }, 3000);
}

function stopMessagePolling() {
    if (_msgPollTimer) {
        clearInterval(_msgPollTimer);
        _msgPollTimer = null;
    }
    _lastMsgCount = 0;
}

// Reload discussion list (used after delete)
function loadDiscussionsList() {
    // Simple approach: reload the page to refresh the thread list
    window.location.reload();
}

// ==========================================================================
// KANBAN BOARD RENDERER
// ==========================================================================
function renderKanban() {
    const data = window.VYALA_TASKPAD_DASHBOARD_DATA;
    const tasks = data.tasks || [];
    const container = document.querySelector('.kanban-board-wrapper');
    if (!container) return;

    // Define columns
    const columns = [
        { id: 'Pending', title: 'Pending', color: '#8b5cf6' },
        { id: 'Todo', title: 'Todo', color: '#64748b' },
        { id: 'In Progress', title: 'In Progress', color: '#2563eb' },
        { id: 'In Review', title: 'In Review', color: '#f59e0b' },
        { id: 'Completed', title: 'Completed', color: '#10b981' }
    ];

    container.innerHTML = '';

    columns.forEach(col => {
        const colTasks = tasks.filter(t => t.status === col.id);
        const colHtml = `
            <div class="kanban-column" data-status="${col.id}">
                <div class="kanban-column-header">
                    <span style="display:flex; align-items:center; gap:6px;">
                        <span style="width:8px; height:8px; border-radius:50%; background:${col.color};"></span>
                        ${col.title}
                    </span>
                    <span class="kanban-column-count">${colTasks.length}</span>
                </div>
                <div class="kanban-cards-container" data-status="${col.id}">
                    ${colTasks.map(t => `
                        <div class="kanban-card" draggable="true" data-task-id="${t.id}">
                            <div class="kanban-card-title">${escapeHtml(t.title)}</div>
                            <div class="kanban-card-desc">${escapeHtml(t.description || '')}</div>
                            <div class="kanban-card-footer">
                                <span class="priority-tag pri-${(t.priority || 'Medium').toLowerCase()}">${t.priority}</span>
                                <span style="font-size:10px; color:var(--text-muted);">${t.due_date || 'No Date'}</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', colHtml);
    });

    setupKanbanDragAndDrop();
}

function setupKanbanDragAndDrop() {
    const cards = document.querySelectorAll('.kanban-card');
    const containers = document.querySelectorAll('.kanban-cards-container');

    cards.forEach(card => {
        card.addEventListener('dragstart', () => {
            card.classList.add('dragging');
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
        });
    });

    containers.forEach(container => {
        container.addEventListener('dragover', e => {
            e.preventDefault();
            container.classList.add('dragover');
        });
        container.addEventListener('dragleave', () => {
            container.classList.remove('dragover');
        });
        container.addEventListener('drop', e => {
            e.preventDefault();
            container.classList.remove('dragover');
            const draggingCard = document.querySelector('.kanban-card.dragging');
            if (draggingCard) {
                const taskId = draggingCard.getAttribute('data-task-id');
                const newStatus = container.getAttribute('data-status');
                
                container.appendChild(draggingCard);
                updateTaskStatus(taskId, newStatus);
            }
        });
    });
}

function updateTaskStatus(taskId, status) {
    const formData = new FormData();
    formData.append('task_id', taskId);
    formData.append('status', status);

    fetch('api.php?action=update_task_status', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            const data = window.VYALA_TASKPAD_DASHBOARD_DATA;
            const task = data.tasks.find(t => t.id == taskId);
            if (task) {
                task.status = status;
            }
            
            // Re-render kanban header counts
            document.querySelectorAll('.kanban-column').forEach(col => {
                const colStatus = col.getAttribute('data-status');
                const count = data.tasks.filter(t => t.status === colStatus).length;
                col.querySelector('.kanban-column-count').textContent = count;
            });
        } else {
            alert('Failed to update: ' + res.message);
        }
    });
}

// ==========================================================================
// CALENDAR MONTHLY VIEW RENDERER
// ==========================================================================
function renderCalendar() {
    const data = window.VYALA_TASKPAD_DASHBOARD_DATA;
    const tasks = data.tasks || [];
    const container = document.querySelector('.calendar-view-wrapper');
    if (!container) return;

    // June 2026 Monthly grid
    const year = 2026;
    const month = 5; // 0-indexed (June)
    const monthName = 'June 2026';

    const firstDay = new Date(year, month, 1).getDay(); // Sunday=0, Monday=1...
    const totalDays = new Date(year, month + 1, 0).getDate();
    const prevMonthDays = new Date(year, month, 0).getDate();

    let gridHtml = `
        <div class="calendar-grid-header">
            <h3>${monthName}</h3>
            <span style="font-size:12px; color:var(--text-muted);">Monthly grid view</span>
        </div>
        <div class="calendar-grid-days">
            <div class="calendar-day-header">Sun</div>
            <div class="calendar-day-header">Mon</div>
            <div class="calendar-day-header">Tue</div>
            <div class="calendar-day-header">Wed</div>
            <div class="calendar-day-header">Thu</div>
            <div class="calendar-day-header">Fri</div>
            <div class="calendar-day-header">Sat</div>
        </div>
        <div class="calendar-grid-cells">
    `;

    // Pad previous month dates
    for (let i = firstDay - 1; i >= 0; i--) {
        const d = prevMonthDays - i;
        gridHtml += `
            <div class="calendar-day-cell other-month">
                <span class="calendar-day-number">${d}</span>
            </div>
        `;
    }

    // June 2026 dates
    const today = new Date();
    const isCurrentMonthYear = today.getFullYear() === year && today.getMonth() === month;
    const todayDate = today.getDate();

    for (let day = 1; day <= totalDays; day++) {
        const isToday = isCurrentMonthYear && day === todayDate;
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayTasks = tasks.filter(t => t.due_date === dateStr);

        gridHtml += `
            <div class="calendar-day-cell ${isToday ? 'today' : ''}">
                <span class="calendar-day-number">${day}</span>
                <div style="display:flex; flex-direction:column; gap:4px; margin-top:4px;">
                    ${dayTasks.map(t => `
                        <div class="calendar-task-event pri-${(t.priority || 'Medium').toLowerCase()}" title="${escapeHtml(t.title)} (${t.status})">
                            ${escapeHtml(t.title)}
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    // Pad next month dates (make 42 cells total)
    const currentCellsCount = firstDay + totalDays;
    const paddingCells = 42 - currentCellsCount;
    for (let day = 1; day <= paddingCells; day++) {
        gridHtml += `
            <div class="calendar-day-cell other-month">
                <span class="calendar-day-number">${day}</span>
            </div>
        `;
    }

    gridHtml += `</div>`;
    container.innerHTML = gridHtml;
}

// Escape HTML utility helper
function escapeHtml(str) {
    if (!str) return '';
    return str
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

// Delegate click for Approve, Edit, and Delete buttons in employee list
document.addEventListener('click', function(e) {
    const approveBtn = e.target.closest('.btn-approve-emp');
    if (approveBtn) {
        const empId = approveBtn.getAttribute('data-emp-id');
        if (confirm("Are you sure you want to approve this employee?")) {
            const formData = new FormData();
            formData.append('employee_id', empId);
            fetch('api.php?action=approve_employee', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Approval failed');
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred during approval');
            });
        }
        return;
    }

    // Edit employee click
    const editBtn = e.target.closest('.btn-edit-emp');
    if (editBtn) {
        const empId = editBtn.getAttribute('data-emp-id');
        const empName = editBtn.getAttribute('data-emp-name');
        const empRole = editBtn.getAttribute('data-emp-role');
        const empEmail = editBtn.getAttribute('data-emp-email');
        const empCode = editBtn.getAttribute('data-emp-code');
        const empStatus = editBtn.getAttribute('data-emp-status');

        const editIdField = document.getElementById('edit-emp-id');
        const editNameField = document.getElementById('edit-emp-name');
        const editRoleField = document.getElementById('edit-emp-role');
        const editEmailField = document.getElementById('edit-emp-email');
        const editCodeField = document.getElementById('edit-emp-code');
        const editStatusField = document.getElementById('edit-emp-status');
        const editPassField = document.getElementById('edit-emp-password');

        if (editIdField) editIdField.value = empId;
        if (editNameField) editNameField.value = empName;
        if (editRoleField) editRoleField.value = empRole;
        if (editEmailField) editEmailField.value = empEmail;
        if (editCodeField) editCodeField.value = empCode;
        if (editStatusField) editStatusField.value = empStatus;
        if (editPassField) editPassField.value = '';

        const modal = document.getElementById('modal-edit-employee');
        if (modal) modal.classList.add('active');
        return;
    }

    // Direct Message click in Employees table
    const msgBtn = e.target.closest('.btn-direct-message-user');
    if (msgBtn) {
        const userId = msgBtn.getAttribute('data-user-id');
        startDirectChatWithUser(userId);
        return;
    }

    // Delete employee click
    const deleteBtn = e.target.closest('.btn-delete-emp');
    if (deleteBtn) {
        const empId = deleteBtn.getAttribute('data-emp-id');
        const empName = deleteBtn.getAttribute('data-emp-name');
        if (confirm(`Are you sure you want to delete the employee "${empName}"? This action cannot be undone.`)) {
            const formData = new FormData();
            formData.append('employee_id', empId);
            fetch('api.php?action=delete_employee', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert(data.message || 'Deletion failed');
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred during deletion');
            });
        }
        return;
    }
});

// Submit handler for Edit Employee form
const formEditEmp = document.getElementById('form-edit-employee');
if (formEditEmp) {
    formEditEmp.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(formEditEmp);
        fetch('api.php?action=update_employee', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                document.getElementById('modal-edit-employee')?.classList.remove('active');
                window.location.reload();
            } else {
                alert(data.message || 'Update failed');
            }
        })
        .catch(err => {
            console.error(err);
            alert('An error occurred during update');
        });
    });
}

// ==========================================================================
// CLIENT HISTORY TIMELINE & ACTIVITIES
// ==========================================================================
document.addEventListener('click', function(e) {
    const historyBtn = e.target.closest('.btn-client-history');
    if (historyBtn) {
        e.preventDefault();
        const clientId = historyBtn.getAttribute('data-client-id');
        loadClientHistory(clientId);
        document.getElementById('modal-client-history').classList.add('active');
    }
});

const historyTabs = document.querySelectorAll('.client-history-tab');
if (historyTabs.length > 0) {
    historyTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            historyTabs.forEach(t => {
                t.classList.remove('active');
                t.style.color = '#64748b';
                t.style.borderBottom = 'none';
            });
            tab.classList.add('active');
            tab.style.color = '#2563eb';
            tab.style.borderBottom = '2px solid #2563eb';

            const targetTab = tab.getAttribute('data-history-tab');
            document.querySelectorAll('.client-history-tab-content').forEach(c => {
                c.style.display = 'none';
            });
            const activeContent = document.getElementById(`client-history-content-${targetTab}`);
            if (activeContent) activeContent.style.display = 'block';
        });
    });
}

function loadClientHistory(clientId) {
    const detailPane = document.getElementById('client-history-content-details');
    const projectsPane = document.getElementById('client-history-content-projects');
    const activitiesPane = document.getElementById('client-history-content-activities');
    const transPane = document.getElementById('client-history-content-transactions');
    const timelinePane = document.getElementById('client-history-content-timeline');

    const loadingHtml = '<div style="text-align:center; padding:30px; color:var(--text-muted);">Loading client history...</div>';
    if (detailPane) detailPane.innerHTML = loadingHtml;
    if (projectsPane) projectsPane.innerHTML = loadingHtml;
    if (activitiesPane) activitiesPane.innerHTML = loadingHtml;
    if (transPane) transPane.innerHTML = loadingHtml;
    if (timelinePane) timelinePane.innerHTML = loadingHtml;

    // Reset tabs
    document.querySelectorAll('.client-history-tab').forEach((tab, index) => {
        if (index === 0) {
            tab.classList.add('active');
            tab.style.color = '#2563eb';
            tab.style.borderBottom = '2px solid #2563eb';
        } else {
            tab.classList.remove('active');
            tab.style.color = '#64748b';
            tab.style.borderBottom = 'none';
        }
    });
    document.querySelectorAll('.client-history-tab-content').forEach((pane, index) => {
        pane.style.display = index === 0 ? 'block' : 'none';
    });

    fetch(`api.php?action=get_client_history&client_id=${clientId}`)
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            // Render Details
            const details = res.client_details;
            if (detailPane) {
                detailPane.innerHTML = `
                    <div style="display:flex; flex-direction:column; gap:12px; font-size:13.5px; color:#334155; padding-top: 10px;">
                        <div><strong>Name:</strong> <span>${escapeHtml(details.name)}</span></div>
                        <div><strong>Email:</strong> <span>${escapeHtml(details.email || 'N/A')}</span></div>
                        <div><strong>Phone:</strong> <span>${escapeHtml(details.phone || 'N/A')}</span></div>
                        <div><strong>Registered Date:</strong> <span>${new Date(details.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })}</span></div>
                    </div>
                `;
            }

            // Render Projects
            if (projectsPane) {
                if (res.projects.length === 0) {
                    projectsPane.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;">No projects for this client.</div>';
                } else {
                    projectsPane.innerHTML = `
                        <div style="display:flex; flex-direction:column; gap:8px; padding-top: 10px;">
                            ${res.projects.map(p => `
                                <div style="border: 1px solid #e2e8f0; padding:10px; border-radius:6px; display:flex; justify-content:space-between; align-items:center; background:#ffffff;">
                                    <div>
                                        <div style="font-weight:600; color:#0f172a;">${escapeHtml(p.name)}</div>
                                        <small style="color:#64748b;">${escapeHtml(p.description || 'No description')}</small>
                                    </div>
                                    <span class="status-badge ${p.status === 'Completed' ? 'completed' : 'process'}" style="font-size:10.5px; padding:2px 8px;">${p.status}</span>
                                </div>
                            `).join('')}
                        </div>
                    `;
                }
            }

            // Render Activities
            if (activitiesPane) {
                if (res.activities.length === 0) {
                    activitiesPane.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;">No activities logged.</div>';
                } else {
                    activitiesPane.innerHTML = `
                        <div style="display:flex; flex-direction:column; gap:8px; padding-top: 10px;">
                            ${res.activities.map(act => `
                                <div style="border-bottom: 1px solid #f1f5f9; padding:8px 0; display:flex; flex-direction:column; gap:3px;">
                                    <div style="font-size:13px; font-weight:500; color:#1e293b;">${escapeHtml(act.action)}</div>
                                    <div style="display:flex; justify-content:space-between; font-size:11px; color:#64748b;">
                                        <span>Project: ${escapeHtml(act.project_name)}</span>
                                        <span>${escapeHtml(act.logged_date)}</span>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                }
            }

            // Render Transactions
            if (transPane) {
                if (res.transactions.length === 0) {
                    transPane.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;">No time logs / transactions recorded.</div>';
                } else {
                    transPane.innerHTML = `
                        <div style="display:flex; flex-direction:column; gap:8px; padding-top: 10px;">
                            ${res.transactions.map(t => `
                                <div style="border:1px solid #e2e8f0; padding:10px; border-radius:6px; background:#ffffff;">
                                    <div style="display:flex; justify-content:space-between; font-weight:600; font-size:13px; color:#0f172a;">
                                        <span>${escapeHtml(t.employee_name)}</span>
                                        <span style="color:#2563eb;">${t.hours} hrs</span>
                                    </div>
                                    <div style="font-size:12px; color:#334155; margin-top:4px;">Task: ${escapeHtml(t.task_title)}</div>
                                    <div style="display:flex; justify-content:space-between; font-size:11px; color:#64748b; margin-top:6px;">
                                        <span>Project: ${escapeHtml(t.project_name)}</span>
                                        <span>${new Date(t.date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })}</span>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    `;
                }
            }

            // Render Timeline
            if (timelinePane) {
                if (res.timeline.length === 0) {
                    timelinePane.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;">No timeline events.</div>';
                } else {
                    timelinePane.innerHTML = `
                        <div style="position:relative; padding-left:20px; border-left: 2px solid #e2e8f0; display:flex; flex-direction:column; gap:16px; margin-left:10px; padding-top:10px;">
                            ${res.timeline.map(t => {
                                let iconColor = '#2563eb';
                                if (t.type === 'project_created') iconColor = '#10b981';
                                return `
                                    <div style="position:relative;">
                                        <span style="position:absolute; left:-26px; top:4px; width:10px; height:10px; border-radius:50%; background:${iconColor}; border:2px solid #ffffff; box-shadow: 0 0 0 2px #e2e8f0;"></span>
                                        <div style="font-weight:600; font-size:13px; color:#0f172a;">${escapeHtml(t.title)} <small style="color:#64748b; font-weight:normal; margin-left:8px;">${new Date(t.date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' })}</small></div>
                                        <div style="font-size:12px; color:#334155; margin-top:2px;">${escapeHtml(t.description)}</div>
                                        ${t.meta ? `<div style="font-size:11px; color:#64748b; margin-top:2px; font-style:italic;">${escapeHtml(t.meta)}</div>` : ''}
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    `;
                }
            }
        } else {
            const errHtml = `<div style="text-align:center; color:#dc2626; padding:20px;">Error: ${res.message}</div>`;
            if (detailPane) detailPane.innerHTML = errHtml;
        }
    })
    .catch(err => {
        console.error(err);
        const failHtml = '<div style="text-align:center; color:#dc2626; padding:20px;">Failed to load history.</div>';
        if (detailPane) detailPane.innerHTML = failHtml;
    });
}

// ==========================================================================
// USERS MANAGEMENT (Approve / Activate / Deactivate / Delete)
// ==========================================================================
function setupUsersManagement() {
    function showUserToast(msg, isError) {
        let toast = document.getElementById('user-mgmt-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'user-mgmt-toast';
            toast.style.cssText = 'position:fixed; bottom:24px; right:24px; z-index:9999; padding:12px 20px; border-radius:10px; font-size:13px; font-weight:600; box-shadow:0 8px 24px rgba(0,0,0,0.15); transition:opacity 0.3s; color:#fff; font-family:inherit;';
            document.body.appendChild(toast);
        }
        toast.style.background = isError ? '#dc2626' : '#10b981';
        toast.textContent = msg;
        toast.style.opacity = '1';
        clearTimeout(toast._t);
        toast._t = setTimeout(() => { toast.style.opacity = '0'; }, 3000);
    }

    function updateRowUI(row, newStatus) {
        const empId = row.getAttribute('data-user-id');
        const isActive = (newStatus === 'Approved' || newStatus === 'Active');
        const isDeactivated = (newStatus === 'Deactivated');
        const isPending = (newStatus === 'Pending');

        const statusCell = row.querySelector('.user-status-cell');
        if (statusCell) {
            if (isActive) {
                statusCell.innerHTML = `
                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; color: #065f46; background: #dcfce7; padding: 3px 10px; border-radius: 20px; border: 1px solid #bbf7d0;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #10b981;"></span>Active
                    </span>
                `;
            } else if (isDeactivated) {
                statusCell.innerHTML = `
                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; color: #991b1b; background: #fee2e2; padding: 3px 10px; border-radius: 20px; border: 1px solid #fecaca;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></span>Deactivated
                    </span>
                `;
            } else {
                statusCell.innerHTML = `
                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; color: #92400e; background: #fef3c7; padding: 3px 10px; border-radius: 20px; border: 1px solid #fde68a;">
                        <span style="width: 6px; height: 6px; border-radius: 50%; background: #f59e0b;"></span>Pending
                    </span>
                `;
            }
        }

        const actionsCell = row.querySelector('.user-actions-cell');
        if (actionsCell) {
            let btnHtml = '<div style="display: flex; gap: 6px; justify-content: center;">';
            if (isPending) {
                btnHtml += `
                    <button class="btn-user-approve" data-emp-id="${empId}"
                        style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 600; background: #10b981; color: #fff; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <i data-lucide="check" style="width: 11px; height: 11px;"></i> Approve
                    </button>
                    <button class="btn-user-deactivate" data-emp-id="${empId}" data-current-status="Pending"
                        style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 600; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <i data-lucide="x" style="width: 11px; height: 11px;"></i> Reject
                    </button>
                `;
            } else if (isActive) {
                btnHtml += `
                    <button class="btn-user-deactivate" data-emp-id="${empId}" data-current-status="Approved"
                        style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 600; background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <i data-lucide="user-x" style="width: 11px; height: 11px;"></i> Deactivate
                    </button>
                `;
            } else {
                btnHtml += `
                    <button class="btn-user-activate" data-emp-id="${empId}" data-current-status="Deactivated"
                        style="height: 28px; padding: 0 10px; font-size: 11px; font-weight: 600; background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <i data-lucide="user-check" style="width: 11px; height: 11px;"></i> Activate
                    </button>
                `;
            }
            const empName = row.querySelector('.user-name-span')?.textContent?.trim() || 'User';
            btnHtml += `
                <button class="btn-delete-emp" data-emp-id="${empId}" data-emp-name="${empName}"
                    style="width: 28px; height: 28px; background: #f8fafc; color: #94a3b8; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center;" title="Delete User">
                    <i data-lucide="trash-2" style="width: 12px; height: 12px;"></i>
                </button>
            </div>`;
            actionsCell.innerHTML = btnHtml;
        }

        if (typeof lucide !== 'undefined') lucide.createIcons();
        bindRowButtons(row);
    }

    function bindRowButtons(row) {
        const approveBtn = row.querySelector('.btn-user-approve');
        if (approveBtn) {
            approveBtn.onclick = function() {
                const empId = this.getAttribute('data-emp-id');
                const fd = new FormData();
                fd.append('employee_id', empId);
                fetch('api.php?action=approve_employee', { method: 'POST', body: fd })
                    .then(r => r.json()).then(res => {
                        if (res.success) { updateRowUI(row, 'Approved'); showUserToast('User approved & activated!', false); }
                        else showUserToast(res.message || 'Failed.', true);
                    });
            };
        }

        const deactivateBtn = row.querySelector('.btn-user-deactivate');
        if (deactivateBtn) {
            deactivateBtn.onclick = function() {
                const empId = this.getAttribute('data-emp-id');
                const fd = new FormData();
                fd.append('employee_id', empId);
                fd.append('new_status', 'Deactivated');
                fetch('api.php?action=toggle_user_status', { method: 'POST', body: fd })
                    .then(r => r.json()).then(res => {
                        if (res.success) { updateRowUI(row, 'Deactivated'); showUserToast('User deactivated.', false); }
                        else showUserToast(res.message || 'Failed.', true);
                    });
            };
        }

        const activateBtn = row.querySelector('.btn-user-activate');
        if (activateBtn) {
            activateBtn.onclick = function() {
                const empId = this.getAttribute('data-emp-id');
                const fd = new FormData();
                fd.append('employee_id', empId);
                fd.append('new_status', 'Approved');
                fetch('api.php?action=toggle_user_status', { method: 'POST', body: fd })
                    .then(r => r.json()).then(res => {
                        if (res.success) { updateRowUI(row, 'Approved'); showUserToast('User activated!', false); }
                        else showUserToast(res.message || 'Failed.', true);
                    });
            };
        }

        const deleteBtn = row.querySelector('.btn-delete-emp');
        if (deleteBtn) {
            deleteBtn.onclick = function() {
                const empId = this.getAttribute('data-emp-id');
                const name = this.getAttribute('data-emp-name') || 'this user';
                if (!confirm(`Delete ${name}? This cannot be undone.`)) return;
                const fd = new FormData();
                fd.append('employee_id', empId);
                fetch('api.php?action=delete_employee', { method: 'POST', body: fd })
                    .then(r => r.json()).then(res => {
                        if (res.success) {
                            row.style.transition = 'opacity 0.3s, transform 0.3s';
                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 320);
                            showUserToast('User deleted.', false);
                        } else showUserToast(res.message || 'Failed.', true);
                    });
            };
        }
    }

    document.querySelectorAll('.user-row').forEach(row => {
        bindRowButtons(row);
    });
}

// ==========================================================================
// ORG APPROVAL LOGIC
// ==========================================================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-approve-org').forEach(btn => {
        btn.addEventListener('click', function() {
            const orgId = this.getAttribute('data-org-id');
            if (confirm('Approve this organization?')) {
                const formData = new FormData();
                formData.append('action', 'approve_org');
                formData.append('org_id', orgId);
                fetch('api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            }
        });
    });

    document.querySelectorAll('.btn-reject-org').forEach(btn => {
        btn.addEventListener('click', function() {
            const orgId = this.getAttribute('data-org-id');
            if (confirm('Reject this organization?')) {
                const formData = new FormData();
                formData.append('action', 'reject_org');
                formData.append('org_id', orgId);
                fetch('api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            }
        });
    });

    document.querySelectorAll('.btn-delete-org').forEach(btn => {
        btn.addEventListener('click', function() {
            const orgId = this.getAttribute('data-org-id');
            if (confirm('Are you absolutely sure you want to delete this organization and ALL its projects, tasks, employees, and data? This action CANNOT be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_org');
                formData.append('org_id', orgId);
                fetch('api.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            }
        });
    });
});

// ==========================================================================
// LAYOUT MODULE LOGIC
// ==========================================================================
function setupLayoutModule() {
    const btnGenerate = document.getElementById('btn-generate-sequence');
    const container = document.getElementById('layout-sequence-container');
    const wrapper = document.getElementById('layout-tasks-wrapper');
    const btnSaveLayout = document.getElementById('btn-save-layout');
    const btnLoadTimeline = document.getElementById('btn-load-timeline');

    if (!btnGenerate || !container || !wrapper || !btnSaveLayout) return;

    // ---- Generate Input Rows ----
    btnGenerate.addEventListener('click', function() {
        const numTasks = parseInt(document.getElementById('layout-num-tasks').value);
        if (isNaN(numTasks) || numTasks <= 0) {
            alert('Please enter a valid number of tasks.');
            return;
        }

        wrapper.innerHTML = '';
        for (let i = 1; i <= numTasks; i++) {
            const html = `
                <div class="layout-task-row" style="background: #f8fafc; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; gap: 12px; align-items: flex-end;">
                    <div style="font-weight: 700; color: #3b82f6; width: 24px; padding-bottom: 8px; font-size:13px;">${i}.</div>
                    <div class="form-group" style="flex: 2; margin-bottom: 0;">
                        <label class="form-label" style="font-size: 11px;">Task Title</label>
                        <input type="text" class="form-control lq-title" placeholder="e.g. Initial Survey" style="height:34px;">
                    </div>
                    <div class="form-group" style="flex: 1.5; margin-bottom: 0; position: relative;">
<<<<<<< HEAD
                        <label class="form-label" style="font-size: 11px;">Assignee</label>
                        <input type="text" class="form-control lq-assignee-input" placeholder="Type name..." autocomplete="off" style="height:34px;">
                        <input type="hidden" class="lq-assignee-val">
                        <div class="lq-assignee-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.12); max-height: 160px; overflow-y: auto; z-index: 9999;"></div>
=======
                        <label class="form-label" style="font-size: 11px;">Assignee (Emp ID / Name)</label>
                        <input type="text" class="form-control lq-assignee-input" placeholder="Type name..." autocomplete="off">
                        <input type="hidden" class="lq-assignee-val">
                        <div class="lq-assignee-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-height: 180px; overflow-y: auto; z-index: 9999;"></div>
>>>>>>> 5d6639863b2014e0b32a7d0fa18a20ed074cf549
                    </div>
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label class="form-label" style="font-size: 11px;">Priority</label>
                        <select class="form-control lq-priority" style="height:34px;">
                            <option value="Medium">Medium</option>
                            <option value="High">High</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 0 0 80px; margin-bottom: 0;">
                        <label class="form-label" style="font-size: 11px;">Est. Days</label>
                        <input type="number" class="form-control lq-days" value="1" min="1" style="height:34px;">
                    </div>
                </div>
            `;
            wrapper.insertAdjacentHTML('beforeend', html);
        }

<<<<<<< HEAD
        // Setup autocomplete dropdown events
=======
        // Setup dropdown events
>>>>>>> 5d6639863b2014e0b32a7d0fa18a20ed074cf549
        const rows = wrapper.querySelectorAll('.layout-task-row');
        const employeesList = window.VYALA_TASKPAD_DASHBOARD_DATA?.employees || [];

        rows.forEach(row => {
            const input = row.querySelector('.lq-assignee-input');
            const hidden = row.querySelector('.lq-assignee-val');
            const dropdown = row.querySelector('.lq-assignee-dropdown');

            function renderDropdown(filterText = '') {
                const query = filterText.toLowerCase().trim();
                const filtered = employeesList.filter(e => e.name.toLowerCase().includes(query));
<<<<<<< HEAD
=======

>>>>>>> 5d6639863b2014e0b32a7d0fa18a20ed074cf549
                dropdown.innerHTML = '';
                if (filtered.length === 0) {
                    dropdown.innerHTML = '<div style="padding: 8px 12px; color: #94a3b8; font-size: 12px;">No matches</div>';
                } else {
                    filtered.forEach(e => {
                        const item = document.createElement('div');
<<<<<<< HEAD
                        item.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:12px;color:#334155;border-bottom:1px solid #f1f5f9;';
                        item.innerText = e.name;
                        item.addEventListener('mouseenter', () => item.style.background = '#f1f5f9');
                        item.addEventListener('mouseleave', () => item.style.background = 'transparent');
=======
                        item.className = 'dropdown-item';
                        item.style.padding = '8px 12px';
                        item.style.cursor = 'pointer';
                        item.style.fontSize = '12px';
                        item.style.color = '#334155';
                        item.style.borderBottom = '1px solid #f1f5f9';
                        item.innerText = e.name;
                        
                        item.addEventListener('mouseenter', () => {
                            item.style.background = '#f1f5f9';
                        });
                        item.addEventListener('mouseleave', () => {
                            item.style.background = 'transparent';
                        });

>>>>>>> 5d6639863b2014e0b32a7d0fa18a20ed074cf549
                        item.addEventListener('mousedown', (evt) => {
                            evt.preventDefault();
                            input.value = e.name;
                            hidden.value = e.id;
                            dropdown.style.display = 'none';
                        });
                        dropdown.appendChild(item);
                    });
                }
            }

<<<<<<< HEAD
            input.addEventListener('focus', () => { renderDropdown(input.value); dropdown.style.display = 'block'; });
            input.addEventListener('input', () => { hidden.value = ''; renderDropdown(input.value); dropdown.style.display = 'block'; });
            input.addEventListener('blur', () => { setTimeout(() => { dropdown.style.display = 'none'; }, 150); });
=======
            input.addEventListener('focus', () => {
                renderDropdown(input.value);
                dropdown.style.display = 'block';
            });

            input.addEventListener('input', () => {
                hidden.value = '';
                renderDropdown(input.value);
                dropdown.style.display = 'block';
            });

            input.addEventListener('blur', () => {
                setTimeout(() => {
                    dropdown.style.display = 'none';
                }, 150);
            });
>>>>>>> 5d6639863b2014e0b32a7d0fa18a20ed074cf549
        });

        container.style.display = 'block';
    });

    // ---- Save & Generate Timeline ----
    btnSaveLayout.addEventListener('click', function() {
        const projectId = document.getElementById('layout-project').value;
        const startDate = document.getElementById('layout-start-date').value;
        const targetDate = document.getElementById('layout-target-date').value;

        if (!projectId) { alert('Please select a project.'); return; }
        if (!startDate) { alert('Please specify a start date.'); return; }

        const taskRows = wrapper.querySelectorAll('.layout-task-row');
        const sequenceData = [];
        const employeesList = window.VYALA_TASKPAD_DASHBOARD_DATA?.employees || [];

        taskRows.forEach((row, idx) => {
            const assigneeInput = row.querySelector('.lq-assignee-input');
            const assigneeVal = row.querySelector('.lq-assignee-val');
            let assignee = assigneeVal ? assigneeVal.value : '';
            if (!assignee && assigneeInput) {
                const name = assigneeInput.value.toLowerCase().trim();
                const matched = employeesList.find(e => e.name.toLowerCase() === name);
<<<<<<< HEAD
                assignee = matched ? matched.id : name;
=======
                if (matched) {
                    assignee = matched.id;
                } else {
                    assignee = name;
                }
>>>>>>> 5d6639863b2014e0b32a7d0fa18a20ed074cf549
            }

            sequenceData.push({
                title: row.querySelector('.lq-title').value,
                assignee: assignee,
                priority: row.querySelector('.lq-priority').value,
                days: parseInt(row.querySelector('.lq-days').value) || 1,
                order: idx + 1
            });
        });

        for (let t of sequenceData) {
            if (!t.title) { alert('Please provide titles for all tasks.'); return; }
        }

        const btnText = btnSaveLayout.innerHTML;
        btnSaveLayout.disabled = true;
        btnSaveLayout.innerHTML = '⏳ Saving...';

        const formData = new FormData();
        formData.append('action', 'save_layout');
        formData.append('project_id', projectId);
        formData.append('start_date', startDate);
        formData.append('target_date', targetDate);
        formData.append('sequence_data', JSON.stringify(sequenceData));

        fetch('api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            btnSaveLayout.disabled = false;
            btnSaveLayout.innerHTML = btnText;
            if (res.success) {
                // Collapse form
                container.style.display = 'none';
                wrapper.innerHTML = '';
                // Render the timeline
                renderGanttTimeline(res.project_name || 'Project', res.tasks, startDate, targetDate);
            } else {
                alert('Error: ' + res.message);
            }
        })
        .catch(err => {
            btnSaveLayout.disabled = false;
            btnSaveLayout.innerHTML = btnText;
            console.error(err);
            alert('A network error occurred.');
        });
    });

    // ---- Load Existing Timeline ----
    if (btnLoadTimeline) {
        btnLoadTimeline.addEventListener('click', function() {
            const projectId = document.getElementById('timeline-load-project').value;
            if (!projectId) { alert('Please select a project to view.'); return; }

            const hint = document.getElementById('timeline-load-hint');
            if (hint) hint.textContent = '⏳ Loading...';
            btnLoadTimeline.disabled = true;

            fetch(`api.php?action=get_timeline&project_id=${projectId}`)
            .then(r => r.json())
            .then(res => {
                btnLoadTimeline.disabled = false;
                if (hint) hint.textContent = 'Select a project and click Load Timeline to view its Gantt chart.';
                if (res.success) {
                    if (!res.tasks || res.tasks.length === 0) {
                        alert('No tasks found for this project. Generate a layout sequence first.');
                        return;
                    }
                    renderGanttTimeline(res.project.name, res.tasks, null, res.project.due_date);
                } else {
                    alert('Error: ' + res.message);
                }
            })
            .catch(err => {
                btnLoadTimeline.disabled = false;
                if (hint) hint.textContent = 'Select a project and click Load Timeline to view its Gantt chart.';
                console.error(err);
                alert('Network error loading timeline.');
            });
        });
    }
}

// ---- Gantt Timeline Renderer ----
function renderGanttTimeline(projectName, tasks, startDate, targetDate) {
    const area = document.getElementById('layout-timeline-area');
    const chart = document.getElementById('layout-gantt-chart');
    const titleEl = document.getElementById('tl-project-title');
    const metaEl = document.getElementById('tl-project-meta');

    if (!area || !chart) return;

    // Determine date range
    let minDate = null, maxDate = null;
    tasks.forEach(t => {
        const due = t.end_date || t.due_date;
        const start = t.start_date;
        if (start) { if (!minDate || start < minDate) minDate = start; }
        if (due)   { if (!maxDate || due > maxDate) maxDate = due; }
    });

    if (startDate && (!minDate || startDate < minDate)) minDate = startDate;
    if (!minDate) minDate = new Date().toISOString().slice(0,10);
    if (!maxDate) maxDate = targetDate || minDate;

    // Expand range by a few days on each side for padding
    const rangeStart = new Date(minDate);
    rangeStart.setDate(rangeStart.getDate() - 1);
    const rangeEnd = new Date(maxDate);
    rangeEnd.setDate(rangeEnd.getDate() + 2);

    const totalDays = Math.max(Math.ceil((rangeEnd - rangeStart) / 86400000), 7);
    const dayWidth = Math.max(38, Math.min(70, Math.floor(900 / totalDays)));

    // Build date headers
    let dateHeaders = '';
    let dayLabels = '';
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const days = ['Su','Mo','Tu','We','Th','Fr','Sa'];

    let prevMonth = -1;
    const allDates = [];
    for (let d = new Date(rangeStart); d <= rangeEnd; d.setDate(d.getDate() + 1)) {
        allDates.push(new Date(d));
    }

    // Month spans
    let monthGroups = [];
    allDates.forEach((d, i) => {
        const m = d.getMonth();
        if (m !== prevMonth) {
            monthGroups.push({ month: m, year: d.getFullYear(), count: 1, startIdx: i });
            prevMonth = m;
        } else {
            monthGroups[monthGroups.length - 1].count++;
        }
    });

    const LABEL_COL_W = 200;

    let monthRow = `<div style="display:flex;">`;
    monthRow += `<div style="width:${LABEL_COL_W}px; min-width:${LABEL_COL_W}px; flex-shrink:0;"></div>`;
    monthGroups.forEach(mg => {
        monthRow += `<div style="width:${mg.count * dayWidth}px; min-width:${mg.count * dayWidth}px; text-align:center; font-size:11px; font-weight:700; color:#0f172a; border-left:1px solid #e2e8f0; padding:4px 0; background:#f8fafc;">${months[mg.month]} ${mg.year}</div>`;
    });
    monthRow += `</div>`;

    let dayRow = `<div style="display:flex; border-bottom:2px solid #e2e8f0;">`;
    dayRow += `<div style="width:${LABEL_COL_W}px; min-width:${LABEL_COL_W}px; flex-shrink:0; font-size:10px; font-weight:700; color:#94a3b8; padding:4px 8px; background:#f1f5f9;">TASK</div>`;
    const today = new Date().toISOString().slice(0,10);
    allDates.forEach(d => {
        const iso = d.toISOString().slice(0,10);
        const isToday = iso === today;
        const isWeekend = (d.getDay() === 0 || d.getDay() === 6);
        const bg = isToday ? '#3b82f6' : (isWeekend ? '#f8fafc' : '#fff');
        const col = isToday ? '#fff' : (isWeekend ? '#94a3b8' : '#475569');
        dayRow += `<div style="width:${dayWidth}px; min-width:${dayWidth}px; text-align:center; font-size:9px; font-weight:${isToday?700:500}; color:${col}; background:${bg}; border-left:1px solid #f1f5f9; padding:3px 0;">${days[d.getDay()]}<br>${d.getDate()}</div>`;
    });
    dayRow += `</div>`;

    // Priority colors
    const priorityColor = { High: '#ef4444', Medium: '#f59e0b', Low: '#22c55e' };
    const statusColor   = { 'Todo': '#3b82f6', 'In Progress': '#f59e0b', 'Completed': '#10b981', 'In Review': '#8b5cf6' };

    // Task rows
    let taskRows = '';
    tasks.forEach((t, idx) => {
        const taskStart = new Date(t.start_date || rangeStart.toISOString().slice(0,10));
        const taskEnd   = new Date(t.end_date   || t.due_date || taskStart);

        const offsetDays = Math.max(0, Math.floor((taskStart - rangeStart) / 86400000));
        const spanDays   = Math.max(1, Math.ceil((taskEnd   - taskStart)  / 86400000));

        const barLeft   = offsetDays * dayWidth;
        const barWidth  = spanDays * dayWidth - 2;
        const pColor    = priorityColor[t.priority] || '#6366f1';
        const sColor    = statusColor[t.status]     || '#6366f1';
        const rowBg     = idx % 2 === 0 ? '#fff' : '#fafafa';
        const assignee  = t.assignee || t.assigned_name || '—';
        const status    = t.status || 'Todo';

        // Generate day cells for background grid
        let dayCells = '';
        allDates.forEach(d => {
            const isWeekend = (d.getDay() === 0 || d.getDay() === 6);
            const iso = d.toISOString().slice(0,10);
            const isToday = iso === today;
            const cellBg = isToday ? 'rgba(59,130,246,0.06)' : (isWeekend ? '#f8fafc' : 'transparent');
            dayCells += `<div style="width:${dayWidth}px; min-width:${dayWidth}px; height:44px; border-left:1px solid #f1f5f9; background:${cellBg}; flex-shrink:0;"></div>`;
        });

        taskRows += `
        <div style="display:flex; border-bottom:1px solid #f1f5f9; background:${rowBg}; position:relative; align-items:center;">
            <!-- Label -->
            <div style="width:${LABEL_COL_W}px; min-width:${LABEL_COL_W}px; flex-shrink:0; padding:6px 10px; z-index:2; background:${rowBg}; border-right:1px solid #e2e8f0;">
                <div style="font-size:12px; font-weight:600; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${t.title}">${(t.sequence_order || (idx+1))}. ${t.title}</div>
                <div style="display:flex; gap:5px; margin-top:3px; align-items:center; flex-wrap:wrap;">
                    <span style="font-size:9px; background:${pColor}22; color:${pColor}; padding:1px 5px; border-radius:3px; font-weight:600;">${t.priority || 'Med'}</span>
                    <span style="font-size:9px; background:${sColor}22; color:${sColor}; padding:1px 5px; border-radius:3px; font-weight:600;">${status}</span>
                    <span style="font-size:9px; color:#94a3b8; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${assignee}">👤 ${assignee}</span>
                </div>
            </div>
            <!-- Grid cells -->
            <div style="display:flex; position:relative; flex:1; height:44px; overflow:hidden;">
                ${dayCells}
                <!-- Gantt Bar -->
                <div style="position:absolute; top:8px; left:${barLeft}px; width:${barWidth}px; height:28px; background:${sColor}; border-radius:5px; display:flex; align-items:center; padding:0 8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.15); z-index:3;" title="${t.title} | ${t.start_date || ''} → ${t.end_date || t.due_date || ''} | ${status}">
                    <span style="font-size:10px; font-weight:600; color:#fff; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;">${t.title}</span>
                </div>
            </div>
        </div>`;
    });

    // Assemble chart
    const taskCount = tasks.length;
    const projDue = targetDate || (tasks[taskCount-1]?.end_date || tasks[taskCount-1]?.due_date || '');
    titleEl.textContent = '📋 ' + projectName;
    metaEl.textContent = `${taskCount} task${taskCount !== 1 ? 's' : ''} · Start: ${startDate || minDate || '—'} · Target: ${projDue || '—'}`;

    chart.innerHTML = `
    <div style="font-family: inherit; min-width: ${LABEL_COL_W + allDates.length * dayWidth}px;">
        ${monthRow}
        ${dayRow}
        ${taskRows}
        <div style="display:flex; margin-top:12px; gap:16px; padding:0 4px; flex-wrap:wrap; align-items:center;">
            <span style="font-size:10px; color:#94a3b8;">📅 Today: ${today}</span>
            <span style="font-size:10px; color:#94a3b8;">🏗️ ${taskCount} tasks in sequence</span>
        </div>
    </div>`;

    area.style.display = 'block';
    area.scrollIntoView({ behavior: 'smooth', block: 'start' });
}


// ==========================================================================
// REAL ESTATE MODULES LOGIC
// ==========================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Helper function for search filtering
    function setupModuleSearch(inputId, tbodyId) {
        const input = document.getElementById(inputId);
        const tbody = document.getElementById(tbodyId);
        if (!input || !tbody) return;
        
        input.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = tbody.querySelectorAll('tr');
            rows.forEach(row => {
                // If it's the "No records found" row, skip hiding logic based on term
                if (row.children.length === 1) return;
                
                const text = row.textContent.toLowerCase();
                if (text.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // Setup Searches
    setupModuleSearch('building-search', 'buildings-tbody');
    setupModuleSearch('singleplot-search', 'singleplot-tbody');
    setupModuleSearch('ual-search', 'ual-tbody');
    setupModuleSearch('landsurvey-search', 'landsurvey-tbody');

    // Edit Building
    document.querySelectorAll('.btn-edit-building').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.json);
            document.getElementById('modal-building-title').textContent = 'Edit Building';
            document.getElementById('building-action').value = 'update_building';
            document.getElementById('building-id').value = data.id;
            document.getElementById('building-name').value = data.name;
            document.getElementById('building-type').value = data.type;
            document.getElementById('building-address').value = data.address;
            document.getElementById('building-floors').value = data.total_floors;
            document.getElementById('building-units').value = data.total_units;
            document.getElementById('building-area').value = data.total_area;
            document.getElementById('building-owner').value = data.owner_name;
            document.getElementById('building-contact').value = data.contact_number;
            document.getElementById('building-status').value = data.status;
            document.getElementById('modal-building').classList.add('active');
        });
    });

    // Delete Building
    document.querySelectorAll('.btn-delete-building').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm("Are you sure you want to delete this building?")) {
                const id = this.dataset.id;
                const fd = new FormData();
                fd.append('action', 'delete_building');
                fd.append('id', id);
                fetch('api.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) location.reload();
                        else alert(res.message);
                    });
            }
        });
    });

    // Reset Building Form on Add
    const btnAddBuilding = document.getElementById('btn-add-building');
    if(btnAddBuilding) {
        btnAddBuilding.addEventListener('click', function() {
            document.getElementById('form-building').reset();
            document.getElementById('modal-building-title').textContent = 'Add Building';
            document.getElementById('building-action').value = 'create_building';
            document.getElementById('building-id').value = '';
        });
    }

    // Edit Single Plot
    document.querySelectorAll('.btn-edit-singleplot').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.json);
            document.getElementById('modal-singleplot-title').textContent = 'Edit Plot';
            document.getElementById('singleplot-action').value = 'update_single_plot';
            document.getElementById('singleplot-id').value = data.id;
            document.getElementById('sp-plot').value = data.plot_number;
            document.getElementById('sp-layout').value = data.layout_name;
            document.getElementById('sp-survey').value = data.survey_number;
            document.getElementById('sp-area').value = data.area;
            document.getElementById('sp-location').value = data.location;
            document.getElementById('sp-price').value = data.price;
            document.getElementById('sp-facing').value = data.facing_direction;
            document.getElementById('sp-status').value = data.status;
            document.getElementById('sp-owner').value = data.owner_name;
            document.getElementById('modal-singleplot').classList.add('active');
        });
    });

    // Delete Single Plot
    document.querySelectorAll('.btn-delete-singleplot').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm("Are you sure you want to delete this plot?")) {
                const id = this.dataset.id;
                const fd = new FormData();
                fd.append('action', 'delete_single_plot');
                fd.append('id', id);
                fetch('api.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) location.reload();
                        else alert(res.message);
                    });
            }
        });
    });

    // Reset Single Plot Form on Add
    const btnAddSinglePlot = document.getElementById('btn-add-singleplot');
    if(btnAddSinglePlot) {
        btnAddSinglePlot.addEventListener('click', function() {
            document.getElementById('form-singleplot').reset();
            document.getElementById('modal-singleplot-title').textContent = 'Add Plot';
            document.getElementById('singleplot-action').value = 'create_single_plot';
            document.getElementById('singleplot-id').value = '';
        });
    }

    // Edit UAL
    document.querySelectorAll('.btn-edit-ual').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.json);
            document.getElementById('modal-ual-title').textContent = 'Edit UAL Record';
            document.getElementById('ual-action').value = 'update_ual_record';
            document.getElementById('ual-id').value = data.id;
            document.getElementById('ual-case').value = data.case_number;
            document.getElementById('ual-owner').value = data.owner_name;
            document.getElementById('ual-address').value = data.address;
            document.getElementById('ual-total').value = data.total_land_area;
            document.getElementById('ual-limit').value = data.gov_ceiling_limit;
            document.getElementById('ual-excess').value = data.excess_land_area;
            document.getElementById('ual-order').value = data.gov_order_number;
            document.getElementById('ual-status').value = data.approval_status;
            document.getElementById('ual-remarks').value = data.remarks;
            document.getElementById('modal-ual').classList.add('active');
        });
    });

    // Delete UAL
    document.querySelectorAll('.btn-delete-ual').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm("Are you sure you want to delete this UAL record?")) {
                const id = this.dataset.id;
                const fd = new FormData();
                fd.append('action', 'delete_ual_record');
                fd.append('id', id);
                fetch('api.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) location.reload();
                        else alert(res.message);
                    });
            }
        });
    });

    // Reset UAL Form on Add
    const btnAddUal = document.getElementById('btn-add-ual');
    if(btnAddUal) {
        btnAddUal.addEventListener('click', function() {
            document.getElementById('form-ual').reset();
            document.getElementById('modal-ual-title').textContent = 'Add UAL Record';
            document.getElementById('ual-action').value = 'create_ual_record';
            document.getElementById('ual-id').value = '';
        });
    }

    // Edit Land Survey
    document.querySelectorAll('.btn-edit-landsurvey').forEach(btn => {
        btn.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.json);
            document.getElementById('modal-landsurvey-title').textContent = 'Edit Survey Record';
            document.getElementById('landsurvey-action').value = 'update_land_survey';
            document.getElementById('landsurvey-id').value = data.id;
            document.getElementById('ls-survey').value = data.survey_number;
            document.getElementById('ls-village').value = data.village_name;
            document.getElementById('ls-taluk').value = data.taluk;
            document.getElementById('ls-district').value = data.district;
            document.getElementById('ls-type').value = data.land_type;
            document.getElementById('ls-owner').value = data.owner_name;
            document.getElementById('ls-area').value = data.total_area;
            document.getElementById('ls-lat').value = data.latitude;
            document.getElementById('ls-long').value = data.longitude;
            document.getElementById('modal-landsurvey').classList.add('active');
        });
    });

    // Delete Land Survey
    document.querySelectorAll('.btn-delete-landsurvey').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm("Are you sure you want to delete this survey record?")) {
                const id = this.dataset.id;
                const fd = new FormData();
                fd.append('action', 'delete_land_survey');
                fd.append('id', id);
                fetch('api.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) location.reload();
                        else alert(res.message);
                    });
            }
        });
    });

    // Reset Land Survey Form on Add
    const btnAddLandSurvey = document.getElementById('btn-add-landsurvey');
    if(btnAddLandSurvey) {
        btnAddLandSurvey.addEventListener('click', function() {
            document.getElementById('form-landsurvey').reset();
            document.getElementById('modal-landsurvey-title').textContent = 'Add Survey Record';
            document.getElementById('landsurvey-action').value = 'create_land_survey';
            document.getElementById('landsurvey-id').value = '';
        });
    }

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
                    alert('Error: ' + (data.message || data.error));
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
                        alert('Error: ' + (data.message || data.error));
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
                        alert('Error: ' + (data.message || data.error));
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
                                    <div style="font-size: 11px; color: #64748b;">${h.created_at} by ${h.user_name || 'System'}</div>
                                    <div style="font-weight: 600; color: #0f172a;">${h.action}</div>
                                    <div style="font-size: 13px; color: #475569;">${h.details || ''}</div>
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

});

function setupDashboardTriggers() {
    // 1. Quick Action Buttons
    document.querySelectorAll('.rsk-qa-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const act = btn.getAttribute('data-action');
            if (act === 'new-project') {
                const m = document.getElementById('modal-project');
                if (m) m.classList.add('active');
            } else if (act === 'add-client') {
                const m = document.getElementById('modal-client');
                if (m) m.classList.add('active');
            } else if (act === 'new-survey') {
                const m = document.getElementById('modal-landsurvey');
                if (m) m.classList.add('active');
            } else if (act === 'upload-document') {
                const m = document.getElementById('modal-upload-doc');
                if (m) m.classList.add('active');
            } else if (act === 'noc-tracker') {
                window.location.hash = '#ual';
            } else if (act === 'payment-entry') {
                const m = document.getElementById('modal-logtime');
                if (m) m.classList.add('active');
            } else if (act === 'task-manager') {
                window.location.hash = '#tasks';
            } else if (act === 'reports') {
                window.location.hash = '#reports';
            }
        });
    });

    // 2. Tab Trigger Click handler modifications (Status filtering support)
    document.querySelectorAll('.tab-trigger').forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            const target = trigger.getAttribute('data-target');
            const statusFilter = trigger.getAttribute('data-filter-status');
            
            if (statusFilter && typeof currentProjectsFilterStatus !== 'undefined') {
                currentProjectsFilterStatus = statusFilter;
                const statusBtn = document.getElementById('projects-status-filter-toggle');
                if (statusBtn) {
                    statusBtn.innerHTML = `Status: ${statusFilter} <i data-lucide="chevron-down"></i>`;
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
                filterAndSortProjects();
            }

            if (target) {
                window.location.hash = `#${target}`;
            }
        });
    });

    // 3. Make metrics cards (.rsk-card-sm) clickable
    document.querySelectorAll('.rsk-card-sm').forEach(card => {
        card.addEventListener('click', function(e) {
            // Avoid recursion if the link itself was clicked directly
            if (e.target.closest('.rsk-card-link')) return;
            const link = card.querySelector('.rsk-card-link');
            if (link) {
                link.click();
            }
        });
    });
}

function setupTaskEditFeatures() {
    // Edit task button click handler (delegated)
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-edit-task');
        if (btn) {
            e.preventDefault();
            const taskId = btn.getAttribute('data-id');
            const title = btn.getAttribute('data-title');
            const due = btn.getAttribute('data-due');
            const days = btn.getAttribute('data-days');

            const modal = document.getElementById('modal-edit-task');
            if (modal) {
                document.getElementById('edit-tk-id').value = taskId;
                document.getElementById('edit-tk-title').value = title;
                document.getElementById('edit-tk-due').value = due || '';
                document.getElementById('edit-tk-days').value = days || '1';
                modal.classList.add('active');
            }
        }
    });

    // Edit task double-click handler on kanban card (delegated)
    document.addEventListener('dblclick', function(e) {
        const card = e.target.closest('.kanban-card');
        if (card) {
            const taskId = card.getAttribute('data-task-id');
            const tasks = window.VYALA_TASKPAD_DASHBOARD_DATA?.tasks || [];
            const t = tasks.find(item => item.id == taskId);
            if (t) {
                const modal = document.getElementById('modal-edit-task');
                if (modal) {
                    document.getElementById('edit-tk-id').value = t.id;
                    document.getElementById('edit-tk-title').value = t.title;
                    document.getElementById('edit-tk-due').value = t.due_date || '';
                    document.getElementById('edit-tk-days').value = t.estimated_duration || '1';
                    modal.classList.add('active');
                }
            }
        }
    });
}
