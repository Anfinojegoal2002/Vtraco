<?php

declare(strict_types=1);

function render_member_dashboard(): void
{
    $user = require_roles(['external_vendor', 'freelancer']);
    $page = $_GET['tab'] ?? 'dashboard';
    
    // Get employees assigned to this vendor
    $vendorId = (int) $user['id'];
    $stmt = db()->prepare("SELECT * FROM users WHERE admin_id = :vendor_id AND role IN ('employee') ORDER BY name");
    $stmt->execute(['vendor_id' => $vendorId]);
    $allEmployees = $stmt->fetchAll();
    $employeeCount = count($allEmployees);
    
    render_header('Dashboard');
    ?>

    <?php if ($page === 'dashboard'): ?>
        <section class="section-block">
            <div class="split">
                <div>
                    <span class="eyebrow">Employee Summary</span>
                    <h2>Team Overview</h2>
                </div>
            </div>
            <div class="spacer"></div>
            <?php
            $totalSalary = 0;
            foreach ($allEmployees as $emp) {
                $totalSalary += (float) ($emp['salary'] ?? 0);
            }
            $avgSalary = $employeeCount > 0 ? $totalSalary / $employeeCount : 0;
            ?>
            <div class="dashboard-grid">
                <div class="metric-card">
                    <span class="eyebrow">Total Employees</span>
                    <strong><?= $employeeCount ?></strong>
                    <span>Active Team Members</span>
                </div>
            </div>
        </section>

        <div class="spacer"></div>
        <section class="section-block">
            <span class="eyebrow">Quick Actions</span>
            <h2>Manage Your Team</h2>
            <p>Use the Employees tab to add new team members or import employees in bulk. Use the Attendance tab to track your team's daily attendance and performance.</p>
        </section>

    <?php elseif ($page === 'employees'): ?>
        <section class="page-title">
            <div>
                <span class="eyebrow">Team Management</span>
                <h2>Your Employees</h2>
                <p>Add employees manually, import a CSV batch, and manage your team members.</p>
            </div>
            <div class="action-bar">
                <button class="button outline" type="button" data-modal-target="vendor-employee-csv-modal">Bulk Import</button>
                <button class="button solid" type="button" data-modal-target="vendor-add-employee-modal">Add Employee</button>
            </div>
        </section>

        <section class="section-block">
            <div class="split">
                <div>
                    <span class="eyebrow">Your Team</span>
                    <h2>Employees List</h2>
                </div>
                <span class="badge"><?= $employeeCount ?> total</span>
            </div>
            <div class="spacer"></div>
            <div class="list-item muted">Employee list management is available through the Add and Import options above.</div>
        </section>

        <div class="modal" id="vendor-add-employee-modal">
            <div class="modal-card">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <span class="eyebrow">Add Employee</span>
                <h2>Add New Employee</h2>
                <form method="post" class="stack-form" data-validate data-watch-required>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="vendor_add_employee">
                    <div class="reports-filter-grid">
                        <div class="field"><label>Emp ID</label><div class="field-row"><input type="text" name="emp_id" required></div><small class="field-error"><span>!</span>Emp ID is required.</small></div>
                        <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                        <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" required></div><small class="field-error"><span>!</span>Valid email required.</small></div>
                        <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" required></div><small class="field-error"><span>!</span>Phone number required.</small></div>
                        <div class="field"><label>Shift</label><div class="field-row"><input type="text" name="shift" placeholder="Enter shift (optional)"></div></div>
                        <div class="field"><label>Salary</label><div class="field-row"><input type="number" step="0.01" min="0" name="salary" required></div><small class="field-error"><span>!</span>Salary is required.</small></div>
                    </div>
                    <div class="inline-actions">
                        <button class="button outline" type="button" data-close-modal>Cancel</button>
                        <button class="button solid" type="submit">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal" id="vendor-employee-csv-modal">
            <div class="modal-card" style="max-width:720px;">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <span class="eyebrow">Bulk Import</span>
                <h2>Import Employees from CSV</h2>
                <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="vendor_upload_csv">
                    <label class="upload-drop">
                        <strong>Select employee CSV</strong>
                        <p>Upload a CSV with employee details. Supported columns include ID, Name, Email, Phone, Shift, and Salary. The CSV should have a header row with column names.</p>
                        <input type="file" name="csv_file" accept=".csv" required>
                    </label>
                    <button class="button solid" type="submit">Upload CSV</button>
                </form>
            </div>
        </div>

    <?php elseif ($page === 'attendance'): ?>
        <?php
        $selectedEmployeeId = (int) ($_GET['employee_id'] ?? 0);
        $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
        $selectedEmployee = null;
        
        if ($selectedEmployeeId && $allEmployees) {
            foreach ($allEmployees as $emp) {
                if ((int) $emp['id'] === $selectedEmployeeId) {
                    $selectedEmployee = $emp;
                    break;
                }
            }
        }
        
        $monthAttendance = $selectedEmployee ? month_attendance_for_user((int) $selectedEmployee['id'], $month) : [];
        ?>
        <section class="section-block scroll-panel">
            <form method="get" class="form-grid">
                <input type="hidden" name="page" value="member_dashboard">
                <input type="hidden" name="tab" value="attendance">
                <label>Employee
                    <select name="employee_id" onchange="this.form.submit()">
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= (int) $emp['id'] ?>" <?= $selectedEmployeeId === (int) $emp['id'] ? 'selected' : '' ?>><?= h($emp['name']) ?> (<?= h($emp['emp_id']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Month<input type="month" name="month" value="<?= h($month) ?>" onchange="this.form.submit()"></label>
            </form>
        </section>

        <div class="spacer"></div>
        
        <?php if ($selectedEmployee && $monthAttendance): ?>
            <section class="attendance-panel">
                <div class="attendance-header">
                    <h2><?= h($selectedEmployee['name']) ?> - <?= h(date('F Y', strtotime($month . '-01'))) ?></h2>
                </div>
                <div class="spacer"></div>
                <?php render_calendar('vendor', $selectedEmployee, $month, $monthAttendance); ?>
            </section>
        <?php elseif (!$selectedEmployee): ?>
            <section class="section-block">
                <div class="list-item muted">Select an employee from the dropdown above to view their attendance calendar.</div>
            </section>
        <?php else: ?>
            <section class="section-block">
                <div class="list-item muted">No attendance data available for the selected employee and month.</div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
    <?php
    render_footer();
}

