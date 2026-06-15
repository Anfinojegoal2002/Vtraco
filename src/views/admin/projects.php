<?php

declare(strict_types=1);

function render_admin_projects(): void
{
    $user = require_power_projects_access(['admin', 'external_vendor']);
    $isVendor = ($user['role'] ?? '') === 'external_vendor';

    if ($isVendor) {
        $projectAssignableEmployees = project_assignable_employees();

        render_header('Projects');
        ?>
        <section class="page-title">
            <div>
                <span class="eyebrow">Vendor - Projects</span>
                <h1>Projects</h1>
                <p>Assign verified active projects to your employees.</p>
            </div>
        </section>

        <section class="section-block scroll-panel rules-assignment-panel">
            <div class="split rules-section-head">
                <div>
                    <span class="eyebrow">Project Allocation</span>
                    <h2>Assign Project</h2>
                </div>
                <span class="hint">Choose employee names and verified active projects.</span>
            </div>
            <form method="post" class="stack-form" data-rule-form data-employee-form data-project-allocation-form>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="apply_rules">
                <input type="hidden" name="allocation_type" value="project">
                <?php render_employee_assignment_picker($projectAssignableEmployees, 'vendor-project-employee-options', 'vendor-project-selected-employee-tags', 'Select Names'); ?>
                <?php render_project_assignment_picker([], 'vendor-project-allocation-options'); ?>
                <div class="inline-actions">
                    <button class="button solid" type="submit" data-rule-submit>Save Project Allocation</button>
                </div>
            </form>
        </section>
        <?php
        render_footer();
        return;
    }

    $allProjects = projects();
    $stage = (string) ($_GET['stage'] ?? '');
    $editProject = isset($_GET['edit']) ? project_by_id((int) $_GET['edit']) : null;
    $projectDraft = $_SESSION['project_form'] ?? null;
    unset($_SESSION['project_form']);

    $formValues = project_form_defaults();
    if ($editProject) {
        $formValues = array_merge($formValues, $editProject);
    }
    if (is_array($projectDraft)) {
        $formValues = array_merge($formValues, $projectDraft);
    }

    $isEditing = $editProject !== null;
    $shouldOpenModal = $stage === 'create' || $isEditing;
    $shouldOpenContractualModal = $stage === 'contractual_create';
    $shouldOpenVendorModal = $stage === 'vendor_create';
    $modalTitle = $isEditing ? 'Edit Project' : 'Add Project';
    $submitLabel = $isEditing ? 'Save Project' : 'Add Project';
    $currentAdminId = (int) ($user['id'] ?? 0);
    $confirmationProjectId = $stage === 'contractual_confirm' ? (int) ($_GET['project_id'] ?? 0) : 0;
    $confirmationProject = $confirmationProjectId > 0 ? project_by_id($confirmationProjectId, $currentAdminId) : null;
    $confirmationAssignments = [];
    if ($confirmationProject) {
        $confirmationStmt = db()->prepare("SELECT u.*, a.project_from, a.project_to, a.project_daily_salary, COALESCE(a.project_pay_basis, 'daily') AS project_pay_basis
            FROM employee_project_assignments a
            INNER JOIN users u ON u.id = a.user_id
            WHERE a.project_id = :project_id
              AND u.admin_id = :admin_id
              AND (u.role = 'corporate_employee' OR u.employee_type = 'corporate')
            ORDER BY u.name");
        $confirmationStmt->execute([
            'project_id' => $confirmationProjectId,
            'admin_id' => $currentAdminId,
        ]);
        $confirmationAssignments = $confirmationStmt->fetchAll();
    }
    $shouldOpenContractualConfirmModal = $confirmationProject !== null;
    $projectAssignableEmployees = project_assignable_employees();
    $contractualEmployeesStmt = db()->prepare("SELECT * FROM users WHERE admin_id = :admin_id AND (role = 'corporate_employee' OR employee_type = 'corporate') ORDER BY name");
    $contractualEmployeesStmt->execute(['admin_id' => $currentAdminId]);
    $contractualEmployees = $contractualEmployeesStmt->fetchAll();
    $contractualSetup = $isEditing
        ? contractual_project_setup_for_project((int) ($formValues['id'] ?? 0), $currentAdminId)
        : [
            'employee_ids' => [],
            'from' => '',
            'to' => '',
            'daily_salary' => 0.0,
            'pay_basis' => 'daily',
        ];
    if (is_array($projectDraft)) {
        $contractualSetup = [
            'employee_ids' => array_map('intval', $projectDraft['contractual_employee_ids'] ?? []),
            'from' => (string) ($projectDraft['contractual_project_from'] ?? ''),
            'to' => (string) ($projectDraft['contractual_project_to'] ?? ''),
            'daily_salary' => (float) ($projectDraft['contractual_daily_salary'] ?? 0),
            'pay_basis' => (string) ($projectDraft['contractual_pay_basis'] ?? 'daily'),
        ];
    }
    $selectedContractualLookup = array_fill_keys(array_map('intval', $contractualSetup['employee_ids'] ?? []), true);
    $ongoingProjects = [];
    $completedProjects = [];
    foreach ($allProjects as $project) {
        $approvalStatus = (string) ($project['approval_status'] ?? 'verified');
        if ($approvalStatus !== 'pending' && empty($project['is_active'])) {
            $completedProjects[] = $project;
        } else {
            $ongoingProjects[] = $project;
        }
    }
    $projectAssignedTrainerLookup = [];
    $projectTrainerRecordLookup = [];
    $projectIds = array_values(array_filter(array_map(static fn(array $project): int => (int) ($project['id'] ?? 0), $allProjects)));
    if ($projectIds) {
        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $trainerStmt = db()->prepare("SELECT a.project_id, a.user_id AS assignment_user_id, a.project_from, a.project_to, u.*
            FROM employee_project_assignments a
            INNER JOIN users u ON u.id = a.user_id
            WHERE a.project_id IN ($placeholders)
              AND u.role IN ('employee', 'corporate_employee')
            ORDER BY u.name");
        $trainerStmt->execute($projectIds);
        foreach ($trainerStmt->fetchAll() as $trainer) {
            $projectAssignedTrainerLookup[(int) ($trainer['project_id'] ?? 0)][] = $trainer;
        }

        $recordStmt = db()->prepare("SELECT s.*, ar.user_id, ar.attend_date
            FROM attendance_sessions s
            INNER JOIN attendance_records ar ON ar.id = s.attendance_id
            WHERE s.project_id IN ($placeholders)
              AND s.session_mode = 'project_record'
            ORDER BY ar.attend_date DESC, s.id DESC");
        $recordStmt->execute($projectIds);
        foreach ($recordStmt->fetchAll() as $record) {
            $lookupKey = (int) ($record['project_id'] ?? 0) . ':' . (int) ($record['user_id'] ?? 0);
            $projectTrainerRecordLookup[$lookupKey][] = $record;
        }
    }

    render_header('Projects');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow"><?= $isVendor ? 'Vendor - Projects' : 'Admin - Projects' ?></span>
            <h1>Projects</h1>
            <p>Create and manage project colleges and locations from one place. Deactivated projects stay saved, become currently unavailable, and can be activated again later.</p>
        </div>
        <div class="action-bar">
            <button class="button solid" type="button" data-modal-target="project-type-modal">Add Project</button>
        </div>
    </section>

    <section class="project-status-columns">
        <?php foreach ([['title' => 'Ongoing', 'projects' => $ongoingProjects], ['title' => 'Completed', 'projects' => $completedProjects]] as $projectGroup): ?>
            <div class="table-wrap project-status-column">
                <div class="data-toolbar">
                    <div class="split">
                        <h2><?= h($projectGroup['title']) ?></h2>
                        <span class="badge"><?= count($projectGroup['projects']) ?> total</span>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Project ID</th>
                            <th>Project Name</th>
                            <th>Vendor</th>
                            <th>College</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projectGroup['projects'] as $project): ?>
                            <?php
                            $approvalStatus = (string) ($project['approval_status'] ?? 'verified');
                            $statusLabel = $approvalStatus === 'pending'
                                ? 'Pending Verification'
                                : (!empty($project['is_active']) ? 'Active' : 'Inactive');
                            $statusClass = $approvalStatus === 'pending'
                                ? 'Pending'
                                : (!empty($project['is_active']) ? 'Active' : 'Inactive');
                            $projectTrainerModalId = 'project-trainers-modal-' . (int) $project['id'];
                            ?>
                            <tr>
                                <td class="project-click-cell" data-modal-target="<?= h($projectTrainerModalId) ?>"><?= h((string) (($project['project_code'] ?? '') ?: 'After Verify')) ?></td>
                                <td class="project-click-cell" data-modal-target="<?= h($projectTrainerModalId) ?>">
                                    <button class="project-name-trigger" type="button" data-modal-target="<?= h($projectTrainerModalId) ?>"><?= h((string) $project['project_name']) ?></button>
                                    <p class="hint"><?= h((string) (($project['location'] ?? '') ?: '-')) ?> | <?= h((string) (($project['created_by_name'] ?? '') ?: 'Admin')) ?></p>
                                </td>
                                <td class="project-click-cell" data-modal-target="<?= h($projectTrainerModalId) ?>"><?= h((string) (($project['vendor_name'] ?? '') ?: '-')) ?></td>
                                <td class="project-click-cell" data-modal-target="<?= h($projectTrainerModalId) ?>"><?= h((string) $project['college_name']) ?></td>
                                <td class="project-click-cell" data-modal-target="<?= h($projectTrainerModalId) ?>"><span class="status-pill status-<?= h($statusClass) ?>"><?= h($statusLabel) ?></span></td>
                                <td>
                                    <div class="inline-actions">
                                        <a class="button ghost small" href="<?= h(BASE_URL) ?>?page=admin_projects&edit=<?= (int) $project['id'] ?>">Edit</a>
                                        <form method="post">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="project_delete">
                                            <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                            <button class="button secondary small" type="submit" onclick="return confirm('Delete this project?');">Delete</button>
                                        </form>
                                        <?php if ($approvalStatus === 'pending' && !$isVendor): ?>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="project_verify">
                                                <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                                <button class="button solid small" type="submit">Verify</button>
                                            </form>
                                        <?php elseif ($approvalStatus === 'pending'): ?>
                                            <span class="status-pill status-Pending">Waiting</span>
                                        <?php else: ?>
                                            <form method="post">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="project_toggle_active">
                                                <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                                                <button class="button outline small" type="submit"><?= !empty($project['is_active']) ? 'Inactive' : 'Activate' ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!$projectGroup['projects']): ?>
                    <div class="list-item muted table-empty-state">No <?= h(strtolower($projectGroup['title'])) ?> projects found.</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>

    <?php foreach ($allProjects as $project): ?>
        <?php
            $projectId = (int) ($project['id'] ?? 0);
            $assignedTrainers = $projectAssignedTrainerLookup[$projectId] ?? [];
        ?>
        <div class="modal" id="project-trainers-modal-<?= $projectId ?>">
            <div class="modal-card employee-rules-modal-card">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <span class="eyebrow">Assigned Trainers</span>
                <h2><?= h((string) ($project['project_name'] ?? 'Project')) ?></h2>
                <p class="hint"><?= h((string) (($project['college_name'] ?? '') ?: '-')) ?> | <?= h((string) (($project['location'] ?? '') ?: '-')) ?></p>
                <h3>Currently Assigned</h3>
                <?php if ($assignedTrainers): ?>
                    <div class="project-trainer-list">
                        <div class="project-trainer-list-head" aria-hidden="true">
                            <span>Emp ID</span>
                            <span>Name</span>
                            <span>Project</span>
                        </div>
                        <?php foreach ($assignedTrainers as $trainer): ?>
                            <?php
                            $trainerProjectRecords = $projectTrainerRecordLookup[$projectId . ':' . (int) ($trainer['id'] ?? 0)] ?? [];
                            ?>
                            <details class="project-trainer-detail">
                                <summary>
                                    <span><?= h((string) (($trainer['emp_id'] ?? '') ?: '-')) ?></span>
                                    <span><?= h((string) (($trainer['name'] ?? '') ?: '-')) ?></span>
                                    <span><?= h((string) ($project['project_name'] ?? 'Project')) ?></span>
                                </summary>
                                <div class="project-trainer-profile">
                                    <div class="rules-detail-grid project-trainer-grid">
                                        <section class="rules-detail-panel project-records-panel">
                                            <div class="project-records-section-head">
                                                <div>
                                                    <span class="eyebrow">Project Records</span>
                                                    <h3><?= h((string) (($trainer['name'] ?? '') ?: 'Trainer')) ?></h3>
                                                    <p class="hint"><?= h((string) (($trainer['emp_id'] ?? '') ?: '-')) ?> | <?= h((string) ($project['project_name'] ?? 'Project')) ?></p>
                                                </div>
                                                <span class="badge"><?= count($trainerProjectRecords) ?> submitted</span>
                                            </div>
                                            <?php if ($trainerProjectRecords): ?>
                                                <div class="project-record-table-wrap">
                                                    <table class="project-record-table">
                                                        <thead>
                                                            <tr>
                                                                <th>Sn</th>
                                                                <th>Date</th>
                                                                <th>College</th>
                                                                <th>Subject</th>
                                                                <th>Day Type</th>
                                                                <th>Topics Handled</th>
                                                                <th>Total</th>
                                                                <th>Present</th>
                                                                <th>Absent</th>
                                                                <th>Location</th>
                                                                <th>GPS Photo</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($trainerProjectRecords as $recordIndex => $record): ?>
                                                                <?php
                                                                $totalStudents = (int) ($record['total_students'] ?? 0);
                                                                $presentStudents = (int) ($record['present_students'] ?? 0);
                                                                $absentStudents = max(0, $totalStudents - $presentStudents);
                                                                $recordDate = !empty($record['attend_date']) ? date('d M Y', strtotime((string) $record['attend_date'])) : '-';
                                                                $gpsPhotoPath = trim((string) ($record['punch_in_path'] ?? ''));
                                                                $gpsPhotoUrl = $gpsPhotoPath !== '' ? public_file_path($gpsPhotoPath) : '';
                                                                ?>
                                                                <tr>
                                                                    <td data-label="#"><?= (int) ($recordIndex + 1) ?></td>
                                                                    <td data-label="Date"><?= h($recordDate) ?></td>
                                                                    <td data-label="College"><?= h((string) (($record['college_name'] ?? '') ?: '-')) ?></td>
                                                                    <td data-label="Subject"><?= h((string) (($record['session_name'] ?? '') ?: '-')) ?></td>
                                                                    <td data-label="Day Type"><?= h((string) (($record['day_portion'] ?? '') ?: 'Full Day')) ?></td>
                                                                    <td data-label="Topics Handled" class="project-record-topic-cell"><?= h((string) (($record['topics_handled'] ?? '') ?: '-')) ?></td>
                                                                    <td data-label="Total"><?= h((string) $totalStudents) ?></td>
                                                                    <td data-label="Present"><?= h((string) $presentStudents) ?></td>
                                                                    <td data-label="Absent"><?= h((string) $absentStudents) ?></td>
                                                                    <td data-label="Location"><?= h((string) (($record['location'] ?? '') ?: '-')) ?></td>
                                                                    <td data-label="GPS Photo"><?php if ($gpsPhotoUrl !== ''): ?><a class="project-record-photo-link" href="<?= h($gpsPhotoUrl) ?>" target="_blank" rel="noopener">View</a><?php else: ?>-<?php endif; ?></td>
                                                                    <td data-label="Status"><span class="status-pill status-Present">Present</span></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div class="list-item muted">No project records submitted for this trainer and project.</div>
                                            <?php endif; ?>
                                        </section>
                                    </div>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="list-item muted" style="display:block;">No trainers are assigned to this project.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($confirmationProject): ?>
        <div class="modal <?= $shouldOpenContractualConfirmModal ? 'open' : '' ?>" id="contractual-confirmation-modal" <?= $shouldOpenContractualConfirmModal ? 'data-open-on-load' : '' ?>>
            <div class="modal-card project-confirmation-modal-card">
                <a class="modal-close" href="<?= h(BASE_URL) ?>?page=admin_projects">&times;</a>
                <span class="eyebrow">Review Template</span>
                <h2>Confirm Project Letter</h2>
                <?php if ($confirmationAssignments): ?>
                    <p class="hint">Review the confirmation letter template below. It will be available in the selected contractual employee dashboard after you confirm.</p>
                    <form method="post" class="stack-form" data-project-confirmation-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="contractual_project_send_confirmations">
                        <input type="hidden" name="project_id" value="<?= (int) ($confirmationProject['id'] ?? 0) ?>">
                        <div class="project-confirmation-preview-list">
                        <?php foreach ($confirmationAssignments as $assignment): ?>
                            <?php $assignmentId = (int) ($assignment['id'] ?? 0); ?>
                            <section class="project-confirmation-preview">
                                <div class="split">
                                    <strong><?= h((string) ($assignment['name'] ?? 'Contractual Employee')) ?></strong>
                                    <span class="badge"><?= h((string) ($assignment['emp_id'] ?? '')) ?></span>
                                </div>
                                <div class="project-confirmation-letter">
                                    <?= contractual_confirmation_template_preview_html($assignment, $confirmationProject, $assignment, $user) ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                        </div>
                        <div class="inline-actions project-modal-actions">
                            <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_projects">Cancel</a>
                            <button class="button solid" type="submit">Publish to Contractual Dashboard</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p>No contractual employees are assigned to this project.</p>
                    <div class="inline-actions project-modal-actions">
                        <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_projects">Close</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal" id="project-type-modal">
        <div class="modal-card project-type-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Project Type</span>
            <h2>Add Project</h2>
            <p class="hint">Choose who this project is for, then fill the matching project details.</p>
            <div class="project-type-grid">
                <button class="project-type-option" type="button" data-switch-modal-target="project-modal">
                    <strong>Admin Employees</strong>
                    <span>Create a regular project for admin-side employees and trainers.</span>
                </button>
                <button class="project-type-option" type="button" data-switch-modal-target="contractual-project-modal">
                    <strong>Contractual Employees</strong>
                    <span>Assign contractual employees with hourly or daily rate setup.</span>
                </button>
                <button class="project-type-option" type="button" data-switch-modal-target="vendor-project-modal">
                    <strong>Vendor</strong>
                    <span>Create a vendor project with vendor, college, and location details.</span>
                </button>
            </div>
        </div>
    </div>

    <div class="modal <?= $shouldOpenModal ? 'open' : '' ?>" id="project-modal" <?= $shouldOpenModal ? 'data-open-on-load' : '' ?>>
        <div class="modal-card project-modal-card">
            <?php if ($isEditing): ?>
                <button class="modal-close" type="button" data-close-modal onclick="window.location='<?= h(BASE_URL) ?>?page=admin_projects'">&times;</button>
            <?php else: ?>
                <button class="modal-close" type="button" data-close-modal>&times;</button>
            <?php endif; ?>
            <span class="eyebrow">Project Setup</span>
            <h2><?= h($modalTitle) ?></h2>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="project_save">
                <input type="hidden" name="project_id" value="<?= (int) ($formValues['id'] ?? 0) ?>">
                <div class="reports-filter-grid">
                    <label>Project Name
                        <input type="text" name="project_name" value="<?= h((string) ($formValues['project_name'] ?? '')) ?>" placeholder="Summer Skill Development Program" required>
                    </label>
                    <label>Vendor
                        <input type="text" name="vendor_name" value="<?= h((string) ($formValues['vendor_name'] ?? '')) ?>" placeholder="Vendor name">
                    </label>
                    <label>College Name
                        <input type="text" name="college_name" value="<?= h((string) ($formValues['college_name'] ?? '')) ?>" placeholder="ABC Engineering College" required>
                    </label>
                    <label>Location
                        <input type="text" name="location" value="<?= h((string) ($formValues['location'] ?? '')) ?>" placeholder="Ahmedabad, Gujarat" required>
                    </label>
                    <label class="project-checkbox-field">Active
                        <input type="checkbox" name="is_active" value="1" <?= !empty($formValues['is_active']) ? 'checked' : '' ?>>
                    </label>
                </div>
                <div class="inline-actions project-modal-actions">
                    <?php if ($isEditing): ?>
                        <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_projects">Cancel</a>
                    <?php else: ?>
                        <button class="button outline" type="button" data-close-modal>Cancel</button>
                    <?php endif; ?>
                    <button class="button solid" type="submit"><?= h($submitLabel) ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal <?= $shouldOpenVendorModal ? 'open' : '' ?>" id="vendor-project-modal" <?= $shouldOpenVendorModal ? 'data-open-on-load' : '' ?>>
        <div class="modal-card project-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Vendor Project</span>
            <h2>Add Vendor Project</h2>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="project_save">
                <input type="hidden" name="project_kind" value="vendor">
                <input type="hidden" name="project_id" value="0">
                <div class="reports-filter-grid">
                    <label>Project Name
                        <input type="text" name="project_name" value="<?= h((string) ($formValues['project_name'] ?? '')) ?>" placeholder="Vendor training program" required>
                    </label>
                    <label>Vendor
                        <?php $vendorAccounts = db()->query("SELECT id, name, company_name FROM users WHERE role = 'external_vendor' AND status = 'ACTIVE' ORDER BY COALESCE(NULLIF(company_name, ''), name), name")->fetchAll(); ?>
                        <?php if ($vendorAccounts): ?>
                            <select name="vendor_id" required>
                                <option value="">-- Select Vendor --</option>
                                <?php foreach ($vendorAccounts as $vendorAccount): ?>
                                    <?php $vendorAccountName = (string) (($vendorAccount['company_name'] ?? '') ?: ($vendorAccount['name'] ?? '')); ?>
                                    <option value="<?= (int) $vendorAccount['id'] ?>" <?= (int) ($formValues['vendor_id'] ?? 0) === (int) $vendorAccount['id'] ? 'selected' : '' ?>><?= h($vendorAccountName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="vendor_name" value="<?= h((string) ($formValues['vendor_name'] ?? '')) ?>" placeholder="Vendor name" required>
                        <?php endif; ?>
                    </label>
                    <label>College Name
                        <input type="text" name="college_name" value="<?= h((string) ($formValues['college_name'] ?? '')) ?>" placeholder="ABC Engineering College" required>
                    </label>
                    <label>Location
                        <input type="text" name="location" value="<?= h((string) ($formValues['location'] ?? '')) ?>" placeholder="Ahmedabad, Gujarat" required>
                    </label>
                    <label class="project-checkbox-field">Active
                        <input type="checkbox" name="is_active" value="1" <?= !empty($formValues['is_active']) ? 'checked' : '' ?>>
                    </label>
                </div>
                <div class="inline-actions project-modal-actions">
                    <button class="button outline" type="button" data-close-modal>Cancel</button>
                    <button class="button solid" type="submit">Add Vendor Project</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal <?= $shouldOpenContractualModal ? 'open' : '' ?>" id="contractual-project-modal" <?= $shouldOpenContractualModal ? 'data-open-on-load' : '' ?>>
        <div class="modal-card project-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Contractual Project</span>
            <h2>Add Contractual Project</h2>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="project_save">
                <input type="hidden" name="project_kind" value="contractual">
                <input type="hidden" name="project_id" value="0">
                <div class="reports-filter-grid">
                    <label>Project Name
                        <input type="text" name="project_name" value="<?= h((string) ($formValues['project_name'] ?? '')) ?>" placeholder="Contractual training program" required>
                    </label>
                    <label>Vendor
                        <input type="text" name="vendor_name" value="<?= h((string) ($formValues['vendor_name'] ?? '')) ?>" placeholder="Vendor name">
                    </label>
                    <label>College Name
                        <input type="text" name="college_name" value="<?= h((string) ($formValues['college_name'] ?? '')) ?>" placeholder="ABC Engineering College" required>
                    </label>
                    <label>Location
                        <input type="text" name="location" value="<?= h((string) ($formValues['location'] ?? '')) ?>" placeholder="Ahmedabad, Gujarat" required>
                    </label>
                    <label class="project-checkbox-field">Active
                        <input type="checkbox" name="is_active" value="1" <?= !empty($formValues['is_active']) ? 'checked' : '' ?>>
                    </label>
                </div>
                <div class="employee-picker">
                    <div class="split">
                        <strong>Contractual Employees</strong>
                        <span class="hint">Assign this project and hours to selected contractual employees.</span>
                    </div>
                    <?php if ($contractualEmployees): ?>
                        <input type="text" placeholder="Search contractual employees..." data-employee-filter="contractual-project-employee-options">
                        <div class="tag-list" id="contractual-project-selected-tags"></div>
                        <div class="employee-options" id="contractual-project-employee-options" data-tag-source="contractual-project-selected-tags">
                            <?php foreach ($contractualEmployees as $contractualEmployee): ?>
                                <?php $contractualEmployeeId = (int) ($contractualEmployee['id'] ?? 0); ?>
                                <label class="employee-option">
                                    <input type="checkbox" name="contractual_employee_ids[]" value="<?= $contractualEmployeeId ?>" data-label="<?= h((string) ($contractualEmployee['name'] ?? '')) ?>" <?= isset($selectedContractualLookup[$contractualEmployeeId]) ? 'checked' : '' ?>>
                                    <span><?= h((string) ($contractualEmployee['name'] ?? '')) ?> (<?= h((string) ($contractualEmployee['emp_id'] ?? '')) ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="reports-filter-grid">
                            <label>Payment Basis
                                <select name="contractual_pay_basis" required>
                                    <?php foreach (project_pay_basis_options() as $basisValue => $basisLabel): ?>
                                        <option value="<?= h($basisValue) ?>" <?= normalize_project_pay_basis($contractualSetup['pay_basis'] ?? 'daily') === $basisValue ? 'selected' : '' ?>><?= h($basisLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>Project From
                                <input type="date" name="contractual_project_from" value="<?= h((string) ($contractualSetup['from'] ?? '')) ?>">
                            </label>
                            <label>Project To
                                <input type="date" name="contractual_project_to" value="<?= h((string) ($contractualSetup['to'] ?? '')) ?>">
                            </label>
                            <label>Rate (per hour/day)
                                <input type="number" name="contractual_daily_salary" min="0.01" step="0.01" value="<?= h(number_format((float) ($contractualSetup['daily_salary'] ?? 0), 2, '.', '')) ?>" placeholder="0.00" required>
                            </label>
                        </div>
                    <?php else: ?>
                        <div class="list-item muted">No contractual employees are available yet.</div>
                    <?php endif; ?>
                </div>
                <div class="inline-actions project-modal-actions">
                    <button class="button outline" type="button" data-close-modal>Cancel</button>
                    <button class="button solid" type="submit" <?= $contractualEmployees ? '' : 'disabled' ?>>Add Contractual Project</button>
                </div>
            </form>
        </div>
    </div>
    <?php
    render_footer();
}


