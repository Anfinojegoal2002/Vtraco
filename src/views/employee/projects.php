<?php

declare(strict_types=1);

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


