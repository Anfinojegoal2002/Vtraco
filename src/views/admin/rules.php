<?php

declare(strict_types=1);

function render_admin_rules(): void 
{
    require_role('admin');
    $projectAssignableEmployees = project_assignable_employees();
    $adminEmployees = array_values(array_filter($projectAssignableEmployees, static function (array $employee): bool {
        $employeeType = (string) ($employee['employee_type'] ?? '');
        return ($employee['role'] ?? '') === 'employee' && ($employeeType === '' || $employeeType === 'regular');
    }));
    render_header('Rules', 'admin-rules-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Rules</span>
            <h1>Rules Workspace</h1>
            <p>Handle employee power access and project allocation from one page without switching screens.</p>
        </div>
    </section>
    <section class="rules-workspace-grid">
        <div class="rules-left-stack">
            <section class="section-block scroll-panel rules-assignment-panel">
                <div class="split rules-section-head">
                    <div>
                        <span class="eyebrow">Power Access</span>
                        <h2>Power Access</h2>
                    </div>
                    <span class="hint">Assign employee access to admin-side tabs and attendance people groups.</span>
                </div>
                <form method="post" class="stack-form" data-rule-form data-employee-form data-power-access-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply_rules">
                    <input type="hidden" name="allocation_type" value="power">
                    <?php render_employee_assignment_picker($projectAssignableEmployees, 'power-employee-options', 'power-selected-employee-tags'); ?>
                    <?php render_power_access_fields(); ?>
                    <div class="inline-actions">
                        <button class="button outline danger" type="submit" name="power_access_action" value="remove" data-rule-submit>Remove Power Access</button>
                        <button class="button solid" type="submit" name="power_access_action" value="save" data-rule-submit>Save Power Access</button>
                    </div>
                </form>
            </section>
        </div>
        <div class="rules-side-stack">
            <section class="section-block scroll-panel rules-assignment-panel">
                <div class="split rules-section-head">
                    <div>
                        <span class="eyebrow">Time Allocation</span>
                        <h2>Time Allocation</h2>
                    </div>
                    <span class="hint">Assign shift times and date windows for admin employees.</span>
                </div>
                <form method="post" class="stack-form" data-rule-form data-employee-form data-time-allocation-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply_rules">
                    <input type="hidden" name="allocation_type" value="time">
                    <?php render_employee_assignment_picker($adminEmployees, 'time-employee-options', 'time-selected-employee-tags', 'Select Admin Employees'); ?>
                    <?php render_rules_editor([], null, true, false, false, false, false, false, true); ?>
                    <div class="reports-filter-grid">
                        <div class="field">
                            <label>From Date</label>
                            <div class="field-row"><input type="date" name="shift_from" required></div>
                            <small class="field-error"><span>!</span>From date is required.</small>
                        </div>
                        <div class="field">
                            <label>To Date</label>
                            <div class="field-row"><input type="date" name="shift_to" required></div>
                            <small class="field-error"><span>!</span>To date is required.</small>
                        </div>
                    </div>
                    <div class="inline-actions">
                        <button class="button solid" type="submit" data-rule-submit>Save Time Allocation</button>
                    </div>
                </form>
            </section>
            <section class="section-block scroll-panel rules-assignment-panel">
                <div class="split rules-section-head">
                    <div>
                        <span class="eyebrow">Project Allocation</span>
                        <h2>Project Allocation</h2>
                    </div>
                    <span class="hint">Assign project access for selected employees.</span>
                </div>
                <form method="post" class="stack-form" data-rule-form data-employee-form data-project-allocation-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply_rules">
                    <input type="hidden" name="allocation_type" value="project">
                    <?php render_employee_assignment_picker($projectAssignableEmployees, 'project-employee-options', 'project-selected-employee-tags'); ?>
                    <?php render_project_assignment_picker([], 'project-allocation-options'); ?>
                    <div class="inline-actions">
                        <button class="button solid" type="submit" data-rule-submit>Save Project Allocation</button>
                    </div>
                </form>
            </section>
        </div>
    </section>
    <?php
    render_footer();
}


