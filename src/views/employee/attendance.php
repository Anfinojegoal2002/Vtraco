<?php

declare(strict_types=1);

function render_employee_attendance(): void
{
    $employee = require_roles(['employee', 'corporate_employee']);
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $attendance = month_attendance_for_user((int) $employee['id'], $month);
    $employeeTypeKey = strtolower(trim((string) ($employee['employee_type'] ?? '')));
    $employeeRoleKey = strtolower(trim((string) ($employee['role'] ?? '')));
    $employeeDesignationKey = strtolower(trim((string) ($employee['designation'] ?? '')));
    $isContractualEmployee = $employeeRoleKey === 'corporate_employee'
        || $employeeTypeKey === 'corporate'
        || $employeeDesignationKey === 'contractual';
    $contractualAssignedProjects = $isContractualEmployee ? assigned_projects_for_employee((int) $employee['id']) : [];
    $contractualAdmin = $isContractualEmployee ? contractual_dashboard_admin_for_employee($employee) : [];
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
    <?php if ($isContractualEmployee): ?>
        <section class="section-block">
            <div class="split">
                <div>
                    <span class="eyebrow">Project Confirmation</span>
                    <h2>Confirmation Letters</h2>
                </div>
                <span class="hint">Project assignment letters from your employer.</span>
            </div>
            <div class="spacer"></div>
            <?php if ($contractualAssignedProjects): ?>
                <div class="dashboard-grid">
                    <?php foreach ($contractualAssignedProjects as $project): ?>
                        <?php
                            $projectId = (int) ($project['id'] ?? 0);
                            $modalId = 'contractual-project-confirmation-' . $projectId;
                            $dateParts = array_values(array_filter([
                                (string) ($project['project_from'] ?? ''),
                                (string) ($project['project_to'] ?? ''),
                            ]));
                        ?>
                        <article class="metric-card">
                            <span class="eyebrow">Assigned Project</span>
                            <strong><?= h((string) ($project['project_name'] ?? 'Project')) ?></strong>
                            <span><?= h(trim((string) ($project['college_name'] ?? '')) ?: 'Project confirmation') ?></span>
                            <?php if ($dateParts): ?>
                                <span><?= h(implode(' to ', $dateParts)) ?></span>
                            <?php endif; ?>
                            <div class="inline-actions">
                                <button class="button outline small" type="button" data-modal-target="<?= h($modalId) ?>">View Letter</button>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="employee_project_confirmation_download">
                                    <input type="hidden" name="project_id" value="<?= $projectId ?>">
                                    <button class="button solid small" type="submit">Download PDF</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($contractualAssignedProjects as $project): ?>
                    <?php
                        $projectId = (int) ($project['id'] ?? 0);
                        $modalId = 'contractual-project-confirmation-' . $projectId;
                    ?>
                    <div class="modal" id="<?= h($modalId) ?>">
                        <div class="modal-card project-confirmation-modal-card">
                            <button class="modal-close" type="button" data-close-modal>&times;</button>
                            <span class="eyebrow">Project Confirmation</span>
                            <h2><?= h((string) ($project['project_name'] ?? 'Project')) ?></h2>
                            <div class="inline-actions">
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="employee_project_confirmation_download">
                                    <input type="hidden" name="project_id" value="<?= $projectId ?>">
                                    <button class="button solid small" type="submit">Download PDF</button>
                                </form>
                            </div>
                            <div class="project-confirmation-letter">
                                <?= contractual_project_confirmation_letter_html($employee, $project, $project, $contractualAdmin) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="list-item muted">No project confirmation letters are available yet.</div>
            <?php endif; ?>
        </section>
        <div class="spacer"></div>
    <?php endif; ?>
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


