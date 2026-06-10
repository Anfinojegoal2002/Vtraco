<?php

declare(strict_types=1);

function render_employee_attendance(): void
{
    $employee = require_roles(['employee', 'corporate_employee']);
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $attendance = month_attendance_for_user((int) $employee['id'], $month);
    $projectsPayload = array_map(static function (array $project): array {
        return [
            'id' => (int) ($project['id'] ?? 0),
            'project_name' => (string) ($project['project_name'] ?? ''),
            'college_name' => (string) ($project['college_name'] ?? ''),
            'location' => (string) ($project['location'] ?? ''),
            'session_type' => (string) ($project['session_type'] ?? ''),
            'project_from' => (string) ($project['project_from'] ?? ''),
            'project_to' => (string) ($project['project_to'] ?? ''),
            'project_incentive' => number_format((float) ($project['project_incentive'] ?? 0), 2, '.', ''),
        ];
    }, employee_available_projects($employee));

    render_header('My Attendance');
    ?>
    <section class="banner employee-banner">
        <div class="employee-topbar">
            <div>
                <span class="eyebrow employee-workspace-badge">Employee Workspace</span>
                <h1><?= h($employee['name']) ?></h1>
                <p>Employee ID: <strong><?= h($employee['emp_id']) ?></strong></p>
                <p>Shift: <strong><?= h(employee_shift_display($employee)) ?></strong></p>
            </div>

        </div>
    </section>

    <div class="spacer"></div>
    <section class="page-title">
        <div>
            <span class="eyebrow">Monthly Attendance</span>
            <h2>Attendance Calendar</h2>
        </div>
        <form method="get" class="inline-actions">
            <input type="hidden" name="page" value="employee_attendance">
            <input type="month" name="month" value="<?= h($month) ?>">
            <button class="button solid" type="submit">Change Month</button>
        </form>
    </section>
    <div class="attendance-panel employee-attendance-panel">
        <?php render_calendar('employee', $employee, $month, $attendance); ?>
    </div>
    <script>
        window.VTRACO_AVAILABLE_PROJECTS = <?= json_encode($projectsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    </script>
    <?php
    render_footer();
}

function render_employee_reimbursements(): void
{
    $employee = require_roles(['employee', 'corporate_employee']);
    if (employee_is_vendor_trainer($employee)) {
        flash('error', 'Reimbursement is not available for vendor trainers.');
        redirect_to('employee_attendance');
    }
    $month = reimbursement_current_month();
    $monthStart = new DateTimeImmutable($month . '-01');
    $monthEnd = $monthStart->modify('last day of this month');
    $today = date('Y-m-d');
    $claimsByDate = employee_reimbursements_by_date_map((int) $employee['id'], $month);
    $allClaims = employee_reimbursements_for_month((int) $employee['id'], $month);
    $startOffset = (int) $monthStart->format('w');
    $daysInMonth = (int) $monthEnd->format('j');
    $dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    $claimsPayload = [];
    foreach ($claimsByDate as $date => $meta) {
        $claimsPayload[$date] = array_map(static function (array $item): array {
            return [
                'category' => (string) $item['category'],
                'status' => (string) $item['status'],
                'amount_requested' => number_format((float) ($item['amount_requested'] ?? 0), 2, '.', ''),
                'amount_paid' => number_format((float) ($item['amount_paid'] ?? 0), 2, '.', ''),
                'remaining_balance' => number_format((float) ($item['remaining_balance'] ?? 0), 2, '.', ''),
                'expense_description' => (string) $item['expense_description'],
            ];
        }, $meta['items']);
    }

    render_header('My Reimbursements', 'employee-reimbursements-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Employee - Reimbursement</span>
            <h1>Monthly Reimbursement Calendar</h1>
            <p>Select any available date in the current month to submit a reimbursement. Future dates stay locked.</p>
            <p class="hint"><strong>Note:</strong> Once your employer marks a reimbursement as paid, you will receive your payslip by email from the employer.</p>
        </div>
        <span class="badge"><?= h($monthStart->format('F Y')) ?></span>
    </section>

    <section class="section-block reimbursement-calendar-block">
        <div class="split">
            <div>
                <span class="eyebrow">Current Month Only</span>
                <h2>Reimbursement Dates</h2>
            </div>
            <div class="hint">Previous months are disabled for employee reimbursement submissions.</div>
        </div>
        <div class="spacer"></div>
        <div class="reimbursement-calendar-scroll">
            <div class="calendar-grid reimbursement-calendar-grid">
                <?php foreach ($dayLabels as $label): ?>
                    <div class="weekday"><?= h($label) ?></div>
                <?php endforeach; ?>

                <?php for ($blank = 0; $blank < $startOffset; $blank++): ?>
                    <div class="day-card blank"></div>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $daysInMonth; $day++):
                    $date = $monthStart->setDate((int) $monthStart->format('Y'), (int) $monthStart->format('m'), $day)->format('Y-m-d');
                    $isFuture = $date > $today;
                    $summary = $claimsByDate[$date] ?? ['count' => 0, 'total' => 0];
                    $count = (int) ($summary['count'] ?? 0);
                    $total = (float) ($summary['total'] ?? 0);
                    $dayCopy = $isFuture ? 'Locked' : ($count > 0 ? ($count . ' claim' . ($count > 1 ? 's' : '')) : 'Add claim');
                    ?>
                    <?php if ($isFuture): ?>
                        <div class="day-card static">
                            <span class="day-dot dot-Pending" aria-hidden="true"></span>
                            <span class="day-number"><?= sprintf('%02d', $day) ?></span>
                            <span class="day-copy"><?= h($dayCopy) ?></span>
                        </div>
                    <?php else: ?>
                        <button
                            class="day-card"
                            type="button"
                            data-modal-target="employee-reimbursement-modal"
                            data-reimbursement-date="<?= h($date) ?>"
                            data-reimbursement-display="<?= h(date('d M Y', strtotime($date))) ?>"
                        >
                            <span class="day-dot <?= $count > 0 ? 'dot-Present' : 'dot-Pending' ?>" aria-hidden="true"></span>
                            <span class="day-number"><?= sprintf('%02d', $day) ?></span>
                            <span class="day-copy"><?= h($dayCopy) ?></span>
                            <?php if ($count > 0): ?>
                                <span class="day-copy">Rs <?= h(number_format($total, 2)) ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <div class="modal" id="employee-reimbursement-modal">
        <div class="modal-card reimbursement-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Employee Reimbursement</span>
            <h2 id="employee-reimbursement-date-label">Select Date</h2>
            <div class="list reimbursement-existing-list" id="employee-reimbursement-existing-list">
                <div class="list-item muted">No reimbursement requests recorded for this date yet.</div>
            </div>
            <div class="spacer"></div>
            <div class="list-item muted hidden" id="employee-reimbursement-locked-note">You can submit up to 3 reimbursement requests for this date.</div>
            <div class="spacer hidden" id="employee-reimbursement-locked-spacer"></div>
            <form method="post" enctype="multipart/form-data" class="stack-form" id="employee-reimbursement-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="employee_submit_reimbursement">
                <input type="hidden" name="expense_date" id="employee-reimbursement-date-input">

                <div class="field">
                    <label>Category</label>
                    <div class="reimbursement-radio-group">
                        <?php foreach (reimbursement_categories() as $category): ?>
                            <label class="reimbursement-radio">
                                <input type="radio" name="category" value="<?= h($category) ?>" required>
                                <span><?= h($category) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="field-error"><span>!</span>Please choose a category.</small>
                </div>

                <div class="field">
                    <label>Expense Description</label>
                    <div class="field-row">
                        <textarea name="expense_description" rows="4" placeholder="Explain the expense briefly." required></textarea>
                    </div>
                    <small class="field-error"><span>!</span>Description is required.</small>
                </div>

                <div class="field">
                    <label>Amount</label>
                    <div class="field-row">
                        <input type="number" name="amount_requested" min="0.01" step="0.01" placeholder="Enter amount" required>
                    </div>
                    <small class="field-error"><span>!</span>Enter a valid amount.</small>
                </div>

                <div class="field">
                    <label>Upload File (JPG/PDF, max 5MB)</label>
                    <div class="field-row">
                        <input type="file" name="attachment" accept=".jpg,.jpeg,.pdf,image/jpeg,application/pdf" required>
                    </div>
                    <small class="field-error"><span>!</span>Upload a JPG or PDF file up to 5MB.</small>
                </div>

                <button class="button solid" type="submit">Submit Reimbursement</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const claimsByDate = <?= json_encode($claimsPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const modalDateLabel = document.getElementById('employee-reimbursement-date-label');
            const modalDateInput = document.getElementById('employee-reimbursement-date-input');
            const claimsList = document.getElementById('employee-reimbursement-existing-list');
            const form = document.getElementById('employee-reimbursement-form');
            const lockedNote = document.getElementById('employee-reimbursement-locked-note');
            const lockedSpacer = document.getElementById('employee-reimbursement-locked-spacer');
            const formControls = form ? Array.from(form.querySelectorAll('input, textarea, select, button')) : [];
            const submitButton = form ? form.querySelector('button[type="submit"]') : null;
            const submitButtonText = submitButton ? submitButton.textContent : 'Submit Reimbursement';

            if (form) {
                form.addEventListener('submit', (event) => {
                    // Prevent double-submit (fast clicks / slow uploads).
                    if (form.dataset.submitting === '1') {
                        event.preventDefault();
                        return;
                    }
                    form.dataset.submitting = '1';
                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.textContent = 'Submitting...';
                    }
                });
            }

            document.querySelectorAll('[data-reimbursement-date]').forEach(button => {
                button.addEventListener('click', () => {
                    const date = button.dataset.reimbursementDate || '';
                    const displayDate = button.dataset.reimbursementDisplay || date;
                    const dayClaims = claimsByDate[date] || [];

                    if (form) {
                        form.reset();
                        form.dataset.submitting = '0';
                    }
                    if (modalDateInput) {
                        modalDateInput.value = date;
                    }
                    if (modalDateLabel) {
                        modalDateLabel.textContent = displayDate;
                    }
                    if (!claimsList) {
                        return;
                    }

                    const hasReachedLimit = dayClaims.length >= 3;

                    if (lockedNote && lockedSpacer) {
                        lockedNote.classList.toggle('hidden', !hasReachedLimit);
                        lockedSpacer.classList.toggle('hidden', !hasReachedLimit);
                    }
                    formControls.forEach(control => {
                        if (control && control.name === '_csrf') {
                            return;
                        }
                        if (control && control.type === 'hidden') {
                            return;
                        }
                        control.disabled = hasReachedLimit;
                    });
                    if (submitButton) {
                        submitButton.textContent = submitButtonText;
                        submitButton.disabled = hasReachedLimit;
                    }

                    if (!dayClaims.length) {
                        claimsList.innerHTML = '<div class="list-item muted">No reimbursement requests recorded for this date yet.</div>';
                        return;
                    }

                    claimsList.innerHTML = dayClaims.map(item => `
                        <div class="list-item">
                            <div class="split">
                                <div>
                                    <strong>${item.category}</strong><br>
                                    <span class="hint">Requested Rs ${item.amount_requested} | Paid Rs ${item.amount_paid}</span>
                                </div>
                                <span class="status-pill reimbursement-status ${String(item.status).toLowerCase().replace(/\s+/g, '-').replace('partially-paid', 'partially-paid')}">${item.status}</span>
                            </div>
                            <div class="spacer"></div>
                            <div class="hint">${item.expense_description}</div>
                            <div class="spacer"></div>
                            <div class="hint">Remaining Rs ${item.remaining_balance}</div>
                        </div>
                    `).join('');
                });
            });
        });
    </script>
    <?php
    render_footer();
}

function render_employee_projects(): void
{
    $employee = require_roles(['employee', 'corporate_employee']);
    $isContractual = (string) ($employee['role'] ?? '') === 'corporate_employee' || (string) ($employee['employee_type'] ?? '') === 'corporate';
    $isVendorTrainer = employee_is_vendor_trainer($employee);
    $isProjectCoordinator = employee_is_project_coordinator($employee);
    $canCreateProject = (employee_is_in_house_trainer($employee) || $isProjectCoordinator) && !$isContractual && !$isVendorTrainer;
    if (!$canCreateProject && !$isContractual && !$isVendorTrainer && !$isProjectCoordinator) {
        redirect_to('employee_attendance');
    }

    $assignedProjects = employee_project_workspace_projects($employee);
    $assignableEmployees = $isProjectCoordinator ? project_coordinator_assignable_employees($employee) : [];
    $today = date('Y-m-d');
    $ongoingProjects = [];
    $completedProjects = [];
    foreach ($assignedProjects as $project) {
        $projectTo = substr(trim((string) ($project['project_to'] ?? '')), 0, 10);
        if ($projectTo !== '' && $projectTo < $today) {
            $completedProjects[] = $project;
        } else {
            $ongoingProjects[] = $project;
        }
    }
    $renderProjectRows = static function (array $projects, string $statusLabel): void {
        foreach ($projects as $project):
            $approvalStatus = (string) ($project['approval_status'] ?? 'verified');
            $displayStatus = $approvalStatus === 'pending' ? 'Pending Verification' : $statusLabel;
            $statusClass = $approvalStatus === 'pending' ? 'Pending' : ($statusLabel === 'Ongoing' ? 'Active' : 'Inactive');
            $searchText = strtolower(trim(implode(' ', [
                (string) ($project['project_name'] ?? ''),
                (string) ($project['vendor_name'] ?? ''),
                (string) ($project['college_name'] ?? ''),
                (string) ($project['location'] ?? ''),
                (string) ($project['session_type'] ?? ''),
                (string) ($project['project_from'] ?? ''),
                (string) ($project['project_to'] ?? ''),
                (string) ($project['project_code'] ?? ''),
                $displayStatus,
            ])));
            ?>
            <tr data-search="<?= h($searchText) ?>">
                <td><?= h((string) (($project['project_code'] ?? '') ?: 'After Verify')) ?></td>
                <td><strong><?= h((string) ($project['project_name'] ?? 'Project')) ?></strong></td>
                <td><?= h((string) (($project['vendor_name'] ?? '') ?: '-')) ?></td>
                <td><?= h((string) (($project['college_name'] ?? '') ?: '-')) ?></td>
                <td><?= h((string) (($project['location'] ?? '') ?: '-')) ?></td>
                <td><?= h(project_session_label((string) ($project['session_type'] ?? 'FULL_DAY'))) ?></td>
                <td><?= !empty($project['project_from']) ? h(date('d M Y', strtotime((string) $project['project_from']))) : '-' ?></td>
                <td><?= !empty($project['project_to']) ? h(date('d M Y', strtotime((string) $project['project_to']))) : '-' ?></td>
                <td>Rs <?= h(number_format((float) ($project['project_incentive'] ?? 0), 2)) ?></td>
                <td><span class="status-pill status-<?= h($statusClass) ?>"><?= h($displayStatus) ?></span></td>
            </tr>
            <?php
        endforeach;
    };

    render_header('My Projects', 'employee-projects-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow"><?= $isContractual ? 'Contractual Employee' : ($isVendorTrainer ? 'Vendor Trainer' : ($isProjectCoordinator ? 'Project Coordinator' : 'In-house Trainer')) ?></span>
            <h1>Projects</h1>
            <p>Review your assigned project colleges, session type, dates, and incentive details.</p>
        </div>
        <div class="inline-actions employee-project-title-actions">
            <span class="badge"><?= count($assignedProjects) ?> total</span>
            <?php if ($canCreateProject): ?>
                <button class="button solid" type="button" data-modal-target="employee-project-create-modal">Create Project</button>
            <?php endif; ?>
        </div>
    </section>

    <section class="cards-3">
        <div class="metric-card">
            <span>Assigned Projects</span>
            <strong><?= count($assignedProjects) ?></strong>
        </div>
        <div class="metric-card">
            <span>Ongoing Projects</span>
            <strong><?= count($ongoingProjects) ?></strong>
        </div>
        <div class="metric-card">
            <span>Completed Projects</span>
            <strong><?= count($completedProjects) ?></strong>
        </div>
    </section>

    <?php if ($canCreateProject): ?>
        <div class="modal" id="employee-project-create-modal">
            <div class="modal-card project-modal-card employee-project-create-modal-card">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <div class="employee-project-create-head">
                    <span class="eyebrow"><?= $isProjectCoordinator ? 'Project Coordinator' : 'In-house Trainer' ?></span>
                    <h2>Create Project</h2>
                    <p class="hint"><?= $isProjectCoordinator ? 'Create a project and assign it to employees, contractual employees, or vendor trainers.' : 'This project will be created under your employer and assigned to you automatically.' ?></p>
                </div>
                <form method="post" class="stack-form employee-project-create-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="employee_project_create">
                    <div class="form-grid employee-project-create-grid">
                        <div class="field"><label>Project Name</label><div class="field-row"><input type="text" name="project_name" required></div><small class="field-error"><span>!</span>Project name is required.</small></div>
                        <div class="field"><label>Vendor Company</label><div class="field-row"><input type="text" name="vendor_name"></div></div>
                        <div class="field"><label>College Name</label><div class="field-row"><input type="text" name="college_name" required></div><small class="field-error"><span>!</span>College name is required.</small></div>
                        <div class="field"><label>Location</label><div class="field-row"><input type="text" name="location" required></div><small class="field-error"><span>!</span>Location is required.</small></div>
                        <div class="field">
                            <label>Session Type</label>
                            <div class="field-row">
                                <select name="session_type" required>
                                    <?php foreach (project_session_types() as $sessionType): ?>
                                        <option value="<?= h($sessionType) ?>"><?= h(project_session_label($sessionType)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small class="field-error"><span>!</span>Session type is required.</small>
                        </div>
                        <div class="field"><label>Total Days</label><div class="field-row"><input type="number" name="total_days" min="1" step="1" value="1" required></div><small class="field-error"><span>!</span>Total days is required.</small></div>
                        <div class="field"><label>Project From</label><div class="field-row"><input type="date" name="project_from"></div></div>
                        <div class="field"><label>Project To</label><div class="field-row"><input type="date" name="project_to"></div></div>
                        <div class="field"><label>Incentive Per Session</label><div class="field-row"><input type="number" name="project_incentive" min="0" step="0.01" value="0.00"></div></div>
                        <?php if ($isProjectCoordinator): ?>
                            <div class="field"><label>Contractual Daily Salary / Hours</label><div class="field-row"><input type="number" name="project_daily_salary" min="0" step="0.01" value="0.00"></div></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($isProjectCoordinator): ?>
                        <div class="employee-picker">
                            <div class="split">
                                <strong>Assign Employees</strong>
                                <span class="hint">Select regular employees, contractual employees, and vendor trainers.</span>
                            </div>
                            <?php if ($assignableEmployees): ?>
                                <input type="text" placeholder="Search employees..." data-employee-filter="coordinator-project-employee-options">
                                <div class="tag-list" id="coordinator-project-selected-tags"></div>
                                <div class="employee-options" id="coordinator-project-employee-options" data-tag-source="coordinator-project-selected-tags">
                                    <?php foreach ($assignableEmployees as $assignableEmployee): ?>
                                        <?php
                                            $assignableId = (int) ($assignableEmployee['id'] ?? 0);
                                            $typeLabel = (string) ($assignableEmployee['role'] ?? '') === 'corporate_employee' || (string) ($assignableEmployee['employee_type'] ?? '') === 'corporate'
                                                ? 'Contractual'
                                                : (employee_is_vendor_trainer($assignableEmployee) ? 'Vendor' : 'Employee');
                                        ?>
                                        <label class="employee-option">
                                            <input type="checkbox" name="project_employee_ids[]" value="<?= $assignableId ?>" data-label="<?= h((string) ($assignableEmployee['name'] ?? '')) ?>">
                                            <span><?= h((string) ($assignableEmployee['name'] ?? '')) ?> (<?= h((string) ($assignableEmployee['emp_id'] ?? '')) ?>) - <?= h($typeLabel) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="list-item muted">No employees are available for assignment.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="inline-actions project-modal-actions">
                        <button class="button solid" type="submit">Create Project</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="spacer"></div>
    <div class="employee-project-flex">
        <section class="section-block employee-project-flex-item">
            <div class="split">
                <div>
                    <span class="eyebrow">Current Work</span>
                    <h2>Ongoing Projects</h2>
                </div>
                <input type="text" placeholder="Search ongoing projects..." data-table-filter="employee-ongoing-projects-table" data-empty-target="employee-ongoing-projects-empty">
            </div>
            <div class="spacer"></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Project ID</th>
                            <th>Project</th>
                            <th>Vendor Company</th>
                            <th>College</th>
                            <th>Location</th>
                            <th>Session</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Incentive</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="employee-ongoing-projects-table">
                        <?php $renderProjectRows($ongoingProjects, 'Ongoing'); ?>
                    </tbody>
                </table>
            </div>
            <?php if (!$ongoingProjects): ?>
                <div class="list-item muted table-empty-state" style="display: block;">No ongoing projects assigned.</div>
            <?php endif; ?>
            <div class="list-item muted hidden table-empty-state" id="employee-ongoing-projects-empty">No ongoing projects match your search.</div>
        </section>

        <section class="section-block employee-project-flex-item">
            <div class="split">
                <div>
                    <span class="eyebrow">Finished Work</span>
                    <h2>Completed Projects</h2>
                </div>
                <input type="text" placeholder="Search completed projects..." data-table-filter="employee-completed-projects-table" data-empty-target="employee-completed-projects-empty">
            </div>
            <div class="spacer"></div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Project ID</th>
                            <th>Project</th>
                            <th>Vendor Company</th>
                            <th>College</th>
                            <th>Location</th>
                            <th>Session</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Incentive</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="employee-completed-projects-table">
                        <?php $renderProjectRows($completedProjects, 'Completed'); ?>
                    </tbody>
                </table>
            </div>
            <?php if (!$completedProjects): ?>
                <div class="list-item muted table-empty-state" style="display: block;">No completed projects yet.</div>
            <?php endif; ?>
            <div class="list-item muted hidden table-empty-state" id="employee-completed-projects-empty">No completed projects match your search.</div>
        </section>
    </div>

    <?php
    render_footer();
}

function render_employee_payments(): void
{
    $employee = require_role('corporate_employee');
    if (employee_is_vendor_trainer($employee)) {
        flash('error', 'Payment is not available for vendor trainers.');
        redirect_to('employee_attendance');
    }
    $requestDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['payment_date'] ?? ''))
        ? (string) $_GET['payment_date']
        : date('Y-m-d');
    $requestAmount = function_exists('contractual_payment_amount_due_for_date')
        ? contractual_payment_amount_due_for_date($employee, $requestDate)
        : 0.0;
    $currentRequest = function_exists('contractual_payment_request_for_employee_date')
        ? contractual_payment_request_for_employee_date((int) $employee['id'], $requestDate)
        : null;

    $stmt = db()->prepare('SELECT * FROM payments WHERE user_id = :user_id ORDER BY payment_date DESC, id DESC');
    $stmt->execute(['user_id' => (int) $employee['id']]);
    $payments = $stmt->fetchAll();
    $totalPaid = array_reduce($payments, static fn(float $sum, array $payment): float => $sum + (float) ($payment['amount'] ?? 0), 0.0);

    render_header('My Payment', 'employee-payments-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Contractual Employee - Payment</span>
            <h1>Payment</h1>
            <p>View payment records processed for your contractual work.</p>
        </div>
    </section>

    <section class="dashboard-grid">
        <div class="metric-card">
            <span class="eyebrow">Total Paid</span>
            <strong>Rs <?= h(number_format($totalPaid, 2)) ?></strong>
            <span>Across all payment records</span>
        </div>
        <div class="metric-card">
            <span class="eyebrow">Payments</span>
            <strong><?= count($payments) ?></strong>
            <span>Total records</span>
        </div>
    </section>

    <div class="spacer"></div>

    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Payment Request</span>
                <h2>Request Payment</h2>
                <p class="hint">Send a request to admin for completed project-record payment.</p>
            </div>
        </div>
        <div class="spacer"></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Selected Date</th>
                        <th>Available Amount</th>
                        <th>Status</th>
                        <th>Note</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <form method="get" class="inline-actions">
                                <input type="hidden" name="page" value="employee_payments">
                                <input type="date" name="payment_date" value="<?= h($requestDate) ?>">
                                <button class="button outline small" type="submit">Show</button>
                            </form>
                        </td>
                        <td>Rs <?= h(number_format($requestAmount, 2)) ?></td>
                        <td>
                            <?php if ($currentRequest): ?>
                                <span class="status-pill status-<?= h((string) ($currentRequest['status'] ?? 'PENDING')) ?>"><?= h(ucfirst(strtolower((string) ($currentRequest['status'] ?? 'PENDING')))) ?></span>
                            <?php else: ?>
                                <span class="status-pill status-Pending">Not Requested</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" id="contractual-payment-request-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="employee_contractual_payment_request">
                                <input type="hidden" name="request_date" value="<?= h($requestDate) ?>">
                                <textarea name="note" rows="2" placeholder="Optional note for admin"></textarea>
                            </form>
                        </td>
                        <td>
                            <button class="button solid small" form="contractual-payment-request-form" type="submit" <?= $requestAmount <= 0 || ($currentRequest && in_array((string) ($currentRequest['status'] ?? ''), ['PENDING', 'APPROVED'], true)) ? 'disabled' : '' ?>>Request</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <div class="spacer"></div>

    <section class="table-wrap">
        <div class="data-toolbar">
            <div class="split">
                <h2>Payment History</h2>
                <span class="badge"><?= count($payments) ?> payment(s)</span>
            </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Transfer</th>
                    <th>Transaction ID</th>
                    <th>Remarks</th>
                    <th>Proof</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($payments): ?>
                    <?php foreach ($payments as $payment): ?>
                        <?php
                        $methods = function_exists('payment_methods_for_record') ? payment_methods_for_record($payment) : [];
                        $proofUrl = !empty($payment['proof_path']) ? asset_url((string) $payment['proof_path']) : '';
                        ?>
                        <tr>
                            <td><?= h(date('d M Y', strtotime((string) ($payment['payment_date'] ?? date('Y-m-d'))))) ?></td>
                            <td><?= h((string) ($payment['payment_type'] ?? '-')) ?></td>
                            <td>Rs <?= h(number_format((float) ($payment['amount'] ?? 0), 2)) ?></td>
                            <td><?= h($methods !== [] && function_exists('payment_methods_label') ? payment_methods_label($methods) : (string) (($payment['bank_name'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($payment['transfer_mode'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($payment['transaction_id'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($payment['remarks'] ?? '') ?: '-')) ?></td>
                            <td>
                                <?php if ($proofUrl !== ''): ?>
                                    <a class="button ghost small" href="<?= h($proofUrl) ?>" target="_blank" rel="noopener">Open</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="muted center">No payment records are available yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
    <?php
    render_footer();
}

function render_employee_profile(): void
{
    $employee = require_roles(['employee', 'corporate_employee']);
    $status = (string) ($employee['profile_status'] ?? 'incomplete');
    $statusCopy = [
        'verified' => 'Verified',
        'pending' => 'Under Review',
        'rejected' => 'Needs Update',
        'incomplete' => 'Incomplete',
    ];
    $documents = [
        'aadhaar_card' => 'Aadhaar Card',
        'pan_card' => 'PAN Card',
        'profile_photo' => 'Profile Photo',
        'qualification_certificate' => 'Qualification Certificate',
        'bank_proof' => 'Bank Proof',
        'resume' => 'Resume',
    ];

    render_header('My Profile', 'employee-profile-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Employee Profile</span>
            <h1>Profile Settings</h1>
            <p>Keep your personal, bank, and onboarding documents up to date.</p>
        </div>
        <span class="status-pill status-<?= h($status) ?>"><?= h($statusCopy[$status] ?? ucfirst($status)) ?></span>
    </section>

    <?php if ($status === 'rejected'): ?>
        <section class="section-block">
            <span class="eyebrow">Action Required</span>
            <h2>Verification Rejected</h2>
            <p><?= nl2br(h((string) ($employee['profile_rejection_reason'] ?? 'Please review your details and resubmit.'))) ?></p>
        </section>
    <?php elseif ($status === 'pending'): ?>
        <section class="section-block">
            <span class="eyebrow">Submitted</span>
            <h2>Verification Pending</h2>
            <p>Your profile details have been submitted. You can still update details if something needs correction.</p>
        </section>
    <?php endif; ?>

    <section class="section-block employee-profile-settings-panel">
        <form method="post" enctype="multipart/form-data" class="employee-profile-form" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="employee_profile_update">

            <div class="profile-verification-section">
                <div class="profile-verification-section-head">
                    <strong>Account Details</strong>
                </div>
                <div class="profile-verification-grid">
                    <div class="field"><label>Employee ID</label><div class="field-row"><input type="text" name="emp_id" value="<?= h((string) ($employee['emp_id'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Employee ID is required.</small></div>
                    <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" value="<?= h((string) ($employee['name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                    <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" value="<?= h((string) ($employee['email'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Valid email is required.</small></div>
                    <div class="field"><label>Date of Joining</label><div class="field-row"><input type="date" name="date_of_joining" value="<?= h((string) ($employee['date_of_joining'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of joining is required.</small></div>
                    <div class="field"><label>Designation</label><div class="field-row"><select name="designation" required><option value="">Select designation</option><?php foreach (employee_designation_options() as $value => $label): ?><option value="<?= h($value) ?>" <?= ((string) ($employee['designation'] ?? '')) === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Designation is required.</small></div>
                    <div class="field"><label>Shift</label><div class="field-row"><input type="text" name="shift" value="<?= h(normalize_shift_selection((string) ($employee['shift'] ?? ''))) ?>" required></div><small class="field-error"><span>!</span>Shift is required.</small></div>
                </div>
            </div>

            <div class="profile-verification-section">
                <div class="profile-verification-section-head">
                    <strong>Personal Details</strong>
                </div>
                <div class="profile-verification-grid">
                    <div class="field"><label>Date of Birth</label><div class="field-row"><input type="date" name="date_of_birth" value="<?= h((string) ($employee['date_of_birth'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of birth is required.</small></div>
                    <div class="field"><label>Gender</label><div class="field-row"><select name="gender" required><option value="">Select gender</option><?php foreach (['Female', 'Male', 'Non-binary', 'Prefer not to say'] as $gender): ?><option value="<?= h($gender) ?>" <?= ((string) ($employee['gender'] ?? '')) === $gender ? 'selected' : '' ?>><?= h($gender) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Gender is required.</small></div>
                    <div class="field"><label>Highest Qualification</label><div class="field-row"><input type="text" name="highest_qualification" value="<?= h((string) ($employee['highest_qualification'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Qualification is required.</small></div>
                    <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" value="<?= h((string) ($employee['phone'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Phone number is required.</small></div>
                </div>
                <label class="profile-verification-wide">Address<textarea name="address" required><?= h((string) ($employee['address'] ?? '')) ?></textarea></label>
            </div>

            <div class="profile-verification-section">
                <div class="profile-verification-section-head">
                    <strong>Bank Details</strong>
                </div>
                <div class="profile-verification-grid">
                    <div class="field"><label>Bank Name</label><div class="field-row"><input type="text" name="bank_name" value="<?= h((string) ($employee['bank_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Bank name is required.</small></div>
                    <div class="field"><label>Account Number</label><div class="field-row"><input type="text" name="bank_account_no" value="<?= h((string) ($employee['bank_account_no'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account number is required.</small></div>
                    <div class="field"><label>IFSC Code</label><div class="field-row"><input type="text" name="bank_ifsc_code" value="<?= h((string) ($employee['bank_ifsc_code'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>IFSC code is required.</small></div>
                    <div class="field"><label>Account Holder Name</label><div class="field-row"><input type="text" name="account_holder_name" value="<?= h((string) ($employee['account_holder_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account holder name is required.</small></div>
                </div>
            </div>

            <div class="profile-verification-section">
                <div class="profile-verification-section-head">
                    <strong>Documents</strong>
                </div>
                <div class="profile-document-grid">
                    <?php foreach ($documents as $field => $label): ?>
                        <?php $hasFile = !empty($employee[$field . '_path']); ?>
                        <label class="profile-document-upload<?= $hasFile ? ' has-file' : '' ?>">
                            <span class="profile-document-icon"><?= $hasFile ? 'OK' : '+' ?></span>
                            <span class="profile-document-copy">
                                <strong><?= h($label) ?></strong>
                                <small><?= $hasFile ? h((string) $employee[$field . '_name']) : 'JPG, PNG, PDF, DOC, DOCX' ?></small>
                            </span>
                            <input type="file" name="<?= h($field) ?>" <?= !$hasFile ? 'required' : '' ?> accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="profile-verification-actions">
                <span>Updates are verified immediately after saving required details.</span>
                <button class="button solid" type="submit">Save Profile</button>
            </div>
        </form>
    </section>
    <?php
    render_footer();
}

function render_employee_profile_completion(): void
{
    $employee = require_roles(['employee', 'corporate_employee']);
    $status = (string) ($employee['profile_status'] ?? 'incomplete');
    $isContractual = (string) ($employee['role'] ?? '') === 'corporate_employee';
    $documents = $isContractual
        ? ['pan_card' => 'PAN Card', 'bank_proof' => 'Bank Proof', 'profile_photo' => 'Profile Photo', 'resume' => 'Resume']
        : [
            'aadhaar_card' => 'Aadhaar Card',
            'pan_card' => 'PAN Card',
            'profile_photo' => 'Profile Photo',
            'qualification_certificate' => 'Qualification Certificate',
            'bank_proof' => 'Bank Proof',
            'resume' => 'Resume',
        ];
    render_header('Profile Verification', 'employee-profile-verification-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Employee Onboarding</span>
            <h1>Profile Verification</h1>
            <p>Complete verification to activate full dashboard access.</p>
        </div>
    </section>
    <?php if ($status === 'pending'): ?>
        <section class="section-block">
            <span class="eyebrow">Submitted</span>
            <h2>Verification Pending</h2>
            <p>Your profile details and documents have been submitted for admin verification.</p>
        </section>
    <?php else: ?>
        <?php if ($status === 'rejected'): ?>
            <section class="section-block">
                <span class="eyebrow">Action Required</span>
                <h2>Verification Rejected</h2>
                <p><?= nl2br(h((string) ($employee['profile_rejection_reason'] ?? 'Please review your details and resubmit.'))) ?></p>
            </section>
        <?php endif; ?>
        <section class="section-block">
            <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="employee_profile_submit">
                <div class="reports-filter-grid">
                    <div class="field"><label>Employee ID</label><div class="field-row"><input type="text" name="emp_id" value="<?= h((string) ($employee['emp_id'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Employee ID is required.</small></div>
                    <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" value="<?= h((string) ($employee['name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                    <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" value="<?= h((string) ($employee['email'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Valid email is required.</small></div>
                    <div class="field"><label>Date of Joining</label><div class="field-row"><input type="date" name="date_of_joining" value="<?= h((string) ($employee['date_of_joining'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of joining is required.</small></div>
                    <div class="field"><label>Designation</label><div class="field-row"><select name="designation" required><option value="">Select designation</option><?php foreach (employee_designation_options() as $value => $label): ?><option value="<?= h($value) ?>" <?= ((string) ($employee['designation'] ?? '')) === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Designation is required.</small></div>
                    <div class="field"><label>Shift</label><div class="field-row"><input type="text" name="shift" value="<?= h(normalize_shift_selection((string) ($employee['shift'] ?? ''))) ?>" required></div><small class="field-error"><span>!</span>Shift is required.</small></div>
                    <div class="field"><label>Date of Birth</label><div class="field-row"><input type="date" name="date_of_birth" value="<?= h((string) ($employee['date_of_birth'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of birth is required.</small></div>
                    <div class="field"><label>Gender</label><div class="field-row"><select name="gender" required><option value="">Select gender</option><?php foreach (['Female', 'Male', 'Non-binary', 'Prefer not to say'] as $gender): ?><option value="<?= h($gender) ?>" <?= ((string) ($employee['gender'] ?? '')) === $gender ? 'selected' : '' ?>><?= h($gender) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Gender is required.</small></div>
                    <?php if ($isContractual): ?>
                        <div class="field"><label>Training Experience</label><div class="field-row"><input type="text" name="training_experience_years" value="<?= h((string) ($employee['training_experience_years'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Training experience is required.</small></div>
                        <div class="field"><label>Languages Known</label><div class="field-row"><input type="text" name="languages_known" value="<?= h((string) ($employee['languages_known'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Languages known is required.</small></div>
                        <div class="field"><label>Technical Skills</label><div class="field-row"><input type="text" name="technical_skills" value="<?= h((string) ($employee['technical_skills'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Technical skills are required.</small></div>
                    <?php else: ?>
                        <div class="field"><label>Highest Qualification</label><div class="field-row"><input type="text" name="highest_qualification" value="<?= h((string) ($employee['highest_qualification'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Qualification is required.</small></div>
                    <?php endif; ?>
                    <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" value="<?= h((string) ($employee['phone'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Phone number is required.</small></div>
                    <div class="field"><label>Bank Name</label><div class="field-row"><input type="text" name="bank_name" value="<?= h((string) ($employee['bank_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Bank name is required.</small></div>
                    <div class="field"><label>Account Number</label><div class="field-row"><input type="text" name="bank_account_no" value="<?= h((string) ($employee['bank_account_no'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account number is required.</small></div>
                    <div class="field"><label>IFSC Code</label><div class="field-row"><input type="text" name="bank_ifsc_code" value="<?= h((string) ($employee['bank_ifsc_code'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>IFSC code is required.</small></div>
                    <div class="field"><label>Account Holder Name</label><div class="field-row"><input type="text" name="account_holder_name" value="<?= h((string) ($employee['account_holder_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account holder name is required.</small></div>
                </div>
                <?php if (!$isContractual): ?>
                    <label>Address<textarea name="address" required><?= h((string) ($employee['address'] ?? '')) ?></textarea></label>
                <?php endif; ?>
                <div class="reports-filter-grid">
                    <?php foreach ($documents as $field => $label): ?>
                        <label class="upload-drop">
                            <strong><?= h($label) ?></strong>
                            <p><?= !empty($employee[$field . '_name']) ? 'Current file: ' . h((string) $employee[$field . '_name']) : 'Upload JPG, PNG, or PDF. Resume also accepts DOC/DOCX.' ?></p>
                            <input type="file" name="<?= h($field) ?>" <?= empty($employee[$field . '_path']) ? 'required' : '' ?> accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                        </label>
                    <?php endforeach; ?>
                </div>
                <button class="button solid" type="submit">Submit for Review</button>
            </form>
        </section>
    <?php endif; ?>
    <?php
    render_footer();
}

function render_employee_profile_completion_modal(array $employee): void
{
    $status = (string) ($employee['profile_status'] ?? 'incomplete');
    $statusLabel = $status === 'rejected' ? 'Needs Update' : ($status === 'pending' ? 'Under Review' : 'Required');
    $isContractual = (string) ($employee['role'] ?? '') === 'corporate_employee';
    $documents = $isContractual
        ? ['pan_card' => 'PAN Card', 'bank_proof' => 'Bank Proof', 'profile_photo' => 'Profile Photo', 'resume' => 'Resume']
        : [
            'aadhaar_card' => 'Aadhaar Card',
            'pan_card' => 'PAN Card',
            'profile_photo' => 'Profile Photo',
            'qualification_certificate' => 'Qualification Certificate',
            'bank_proof' => 'Bank Proof',
            'resume' => 'Resume',
        ];
    ?>
    <div class="modal open employee-profile-gate-modal" id="employee-profile-verification-modal" data-profile-gate>
        <div class="modal-card profile-verification-modal-card">
            <div class="profile-verification-head">
                <div>
                    <span class="eyebrow">Employee Onboarding</span>
                    <h2>Profile Verification</h2>
                </div>
                <span class="profile-verification-status status-<?= h($status) ?>"><?= h($statusLabel) ?></span>
            </div>
            <?php if ($status === 'pending'): ?>
                <div class="profile-verification-pending">
                    <strong>Profile submitted</strong>
                    <span>Your profile details have been submitted for admin verification.</span>
                </div>
            <?php else: ?>
                <?php if ($status === 'rejected'): ?>
                    <div class="profile-verification-alert">
                        <strong>Verification Rejected</strong>
                        <span><?= nl2br(h((string) ($employee['profile_rejection_reason'] ?? 'Please review your details and resubmit.'))) ?></span>
                    </div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="stack-form profile-verification-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="employee_profile_submit">
                    <section class="profile-verification-section">
                        <div class="profile-verification-section-head">
                            <strong>Personal Details</strong>
                        </div>
                        <div class="profile-verification-grid">
                            <div class="field"><label>Employee ID</label><div class="field-row"><input type="text" name="emp_id" value="<?= h((string) ($employee['emp_id'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Employee ID is required.</small></div>
                            <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" value="<?= h((string) ($employee['name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                            <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" value="<?= h((string) ($employee['email'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Valid email is required.</small></div>
                            <div class="field"><label>Date of Joining</label><div class="field-row"><input type="date" name="date_of_joining" value="<?= h((string) ($employee['date_of_joining'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of joining is required.</small></div>
                            <div class="field"><label>Designation</label><div class="field-row"><select name="designation" required><option value="">Select designation</option><?php foreach (employee_designation_options() as $value => $label): ?><option value="<?= h($value) ?>" <?= ((string) ($employee['designation'] ?? '')) === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Designation is required.</small></div>
                            <div class="field"><label>Shift</label><div class="field-row"><input type="text" name="shift" value="<?= h(normalize_shift_selection((string) ($employee['shift'] ?? ''))) ?>" required></div><small class="field-error"><span>!</span>Shift is required.</small></div>
                            <div class="field"><label>Date of Birth</label><div class="field-row"><input type="date" name="date_of_birth" value="<?= h((string) ($employee['date_of_birth'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of birth is required.</small></div>
                            <div class="field"><label>Gender</label><div class="field-row"><select name="gender" required><option value="">Select gender</option><?php foreach (['Female', 'Male', 'Non-binary', 'Prefer not to say'] as $gender): ?><option value="<?= h($gender) ?>" <?= ((string) ($employee['gender'] ?? '')) === $gender ? 'selected' : '' ?>><?= h($gender) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Gender is required.</small></div>
                            <?php if ($isContractual): ?>
                                <div class="field"><label>Training Experience</label><div class="field-row"><input type="text" name="training_experience_years" value="<?= h((string) ($employee['training_experience_years'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Training experience is required.</small></div>
                                <div class="field"><label>Languages Known</label><div class="field-row"><input type="text" name="languages_known" value="<?= h((string) ($employee['languages_known'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Languages known is required.</small></div>
                                <div class="field"><label>Technical Skills</label><div class="field-row"><input type="text" name="technical_skills" value="<?= h((string) ($employee['technical_skills'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Technical skills are required.</small></div>
                            <?php else: ?>
                                <div class="field"><label>Highest Qualification</label><div class="field-row"><input type="text" name="highest_qualification" value="<?= h((string) ($employee['highest_qualification'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Qualification is required.</small></div>
                            <?php endif; ?>
                            <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" value="<?= h((string) ($employee['phone'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Phone number is required.</small></div>
                        </div>
                        <?php if (!$isContractual): ?>
                            <label class="profile-verification-wide">Address<textarea name="address" required><?= h((string) ($employee['address'] ?? '')) ?></textarea></label>
                        <?php endif; ?>
                    </section>
                    <section class="profile-verification-section">
                        <div class="profile-verification-section-head">
                            <strong>Bank Details</strong>
                        </div>
                        <div class="profile-verification-grid">
                            <div class="field"><label>Bank Name</label><div class="field-row"><input type="text" name="bank_name" value="<?= h((string) ($employee['bank_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Bank name is required.</small></div>
                            <div class="field"><label>Account Number</label><div class="field-row"><input type="text" name="bank_account_no" value="<?= h((string) ($employee['bank_account_no'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account number is required.</small></div>
                            <div class="field"><label>IFSC Code</label><div class="field-row"><input type="text" name="bank_ifsc_code" value="<?= h((string) ($employee['bank_ifsc_code'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>IFSC code is required.</small></div>
                            <div class="field"><label>Account Holder Name</label><div class="field-row"><input type="text" name="account_holder_name" value="<?= h((string) ($employee['account_holder_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account holder name is required.</small></div>
                        </div>
                    </section>
                    <section class="profile-verification-section">
                        <div class="profile-verification-section-head">
                            <strong>Documents</strong>
                        </div>
                        <div class="profile-document-grid">
                            <?php foreach ($documents as $field => $label): ?>
                                <?php $hasFile = !empty($employee[$field . '_path']); ?>
                                <label class="profile-document-upload<?= $hasFile ? ' has-file' : '' ?>">
                                    <span class="profile-document-icon"><?= $hasFile ? 'OK' : '+' ?></span>
                                    <span class="profile-document-copy">
                                        <strong><?= h($label) ?></strong>
                                        <small><?= $hasFile ? h((string) $employee[$field . '_name']) : 'JPG, PNG, PDF, DOC, DOCX' ?></small>
                                    </span>
                                    <input type="file" name="<?= h($field) ?>" <?= !$hasFile ? 'required' : '' ?> accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <div class="profile-verification-actions">
                        <span>Profile access unlocks after admin verification.</span>
                        <button class="button solid" type="submit">Submit for Review</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function render_employee_onboarding_reviews(): void
{
    $reviewer = require_roles(['employee', 'corporate_employee']);
    flash('error', 'Employee profile verification is handled by admin.');
    redirect_to(home_page_for_user($reviewer));

    $stmt = db()->prepare("SELECT * FROM users WHERE admin_id = :admin_id AND role IN ('employee', 'corporate_employee') AND profile_status IN ('pending', 'rejected') ORDER BY FIELD(profile_status, 'pending', 'rejected'), name");
    $stmt->execute(['admin_id' => (int) ($reviewer['admin_id'] ?? 0)]);
    $employees = $stmt->fetchAll();
    render_header('Profile Reviews', 'employee-onboarding-reviews-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">HR Verification</span>
            <h1>Profile Review Queue</h1>
            <p>Approve verified employee profiles or reject with a reason for resubmission.</p>
        </div>
    </section>
    <section class="section-block">
        <?php if (!$employees): ?>
            <div class="list-item muted">No employee profiles are waiting for review.</div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($employees as $employee): ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h((string) $employee['name']) ?></strong>
                                <p class="hint"><?= h((string) ($employee['emp_id'] ?? '')) ?> | <?= h((string) ($employee['designation'] ?? '-')) ?> | <?= h(ucfirst((string) ($employee['profile_status'] ?? 'pending'))) ?></p>
                                <div class="inline-actions">
                                    <?php foreach ([
                                        'aadhaar_card' => 'Aadhaar',
                                        'pan_card' => 'PAN',
                                        'profile_photo' => 'Photo',
                                        'qualification_certificate' => 'Qualification',
                                        'bank_proof' => 'Bank Proof',
                                        'resume' => 'Resume',
                                    ] as $field => $label): ?>
                                        <?php if (!empty($employee[$field . '_path'])): ?>
                                            <a class="button ghost small" href="<?= h(public_file_path((string) $employee[$field . '_path'])) ?>" target="_blank" rel="noopener"><?= h($label) ?></a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <form method="post" class="stack-form" style="min-width:280px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="admin_review_employee_profile">
                                <input type="hidden" name="user_id" value="<?= (int) $employee['id'] ?>">
                                <textarea name="rejection_reason" placeholder="Reason if rejecting"></textarea>
                                <div class="inline-actions">
                                    <button class="button solid small" type="submit" name="decision" value="approve">Approve</button>
                                    <button class="button outline small" type="submit" name="decision" value="reject">Reject</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
}
