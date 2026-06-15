<?php

declare(strict_types=1);

function render_admin_reports(): void
{
    require_role('admin');

    $filters = [
        'employee_ids' => $_POST['employee_ids'] ?? [],
        'project_ids' => $_POST['project_ids'] ?? [],
        'from_date' => $_POST['from_date'] ?? date('Y-m-01'),
        'to_date' => $_POST['to_date'] ?? date('Y-m-d'),
    ];

    $reportData = get_attendance_report_data($filters);
    $allEmployees = employees();
    $allProjects = active_projects();

    render_header('Reports', 'admin-reports-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Reports</span>
            <h1>Track Attendance Reports</h1>
            <p>Filter by employees, projects, and date range to review full attendance logs, including manual project entries and biometric punches.</p>
        </div>
    </section>

    <section class="section-block reports-filters">
        <form method="post" id="reports-filter-form" class="stack-form reports-filter-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="filter_reports">
            
            <div class="reports-filter-grid">
                <!-- Employee Multi-Select -->
                <div class="field">
                    <label>Employee Multi-Select</label>
                    <div class="multi-select-picker">
                        <input type="text" placeholder="Search employees..." data-multi-filter="employee-report-options">
                        <div class="multi-options scroll-panel" id="employee-report-options">
                            <?php foreach ($allEmployees as $emp): ?>
                                <label class="multi-option">
                                    <input type="checkbox" name="employee_ids[]" value="<?= (int)$emp['id'] ?>" <?= in_array((int)$emp['id'], array_map('intval', $filters['employee_ids'])) ? 'checked' : '' ?>>
                                    <span><?= h($emp['name']) ?> (<?= h($emp['emp_id']) ?>)</span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Project Multi-Select -->
                <div class="field">
                    <label>Project Multi-Select <span class="hint">(optional)</span></label>
                    <div class="multi-select-picker">
                        <input type="text" placeholder="Search projects..." data-multi-filter="project-report-options">
                        <div class="multi-options scroll-panel" id="project-report-options">
                            <?php foreach ($allProjects as $proj): ?>
                                <label class="multi-option">
                                    <input type="checkbox" name="project_ids[]" value="<?= (int)$proj['id'] ?>" <?= in_array((int)$proj['id'], array_map('intval', $filters['project_ids'])) ? 'checked' : '' ?>>
                                    <span><?= h($proj['project_name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="reports-date-grid">
                <div class="field">
                    <label>From Date</label>
                    <input type="date" name="from_date" value="<?= h($filters['from_date']) ?>" required>
                </div>
                <div class="field">
                    <label>To Date</label>
                    <input type="date" name="to_date" value="<?= h($filters['to_date']) ?>" required>
                </div>
                <div class="field align-end">
                    <button class="button solid" type="submit">Apply Filters</button>
                </div>
            </div>
        </form>
    </section>

    <div class="spacer"></div>

    <section class="table-wrap">
        <div class="data-toolbar">
            <div class="split">
                <h2>Report Results</h2>
                <div class="action-bar report-actions">
                    <form method="post" style="display:inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="export_reports_csv">
                        <?php foreach($filters['employee_ids'] as $id): ?><input type="hidden" name="employee_ids[]" value="<?= (int)$id ?>"><?php endforeach; ?>
                        <?php foreach($filters['project_ids'] as $id): ?><input type="hidden" name="project_ids[]" value="<?= (int)$id ?>"><?php endforeach; ?>
                        <input type="hidden" name="from_date" value="<?= h($filters['from_date']) ?>">
                        <input type="hidden" name="to_date" value="<?= h($filters['to_date']) ?>">
                        <button class="button outline small" type="submit">Download Calendar CSV</button>
                    </form>
                    <form method="post" style="display:inline-block;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="export_reports_pdf">
                        <?php foreach($filters['employee_ids'] as $id): ?><input type="hidden" name="employee_ids[]" value="<?= (int)$id ?>"><?php endforeach; ?>
                        <?php foreach($filters['project_ids'] as $id): ?><input type="hidden" name="project_ids[]" value="<?= (int)$id ?>"><?php endforeach; ?>
                        <input type="hidden" name="from_date" value="<?= h($filters['from_date']) ?>">
                        <input type="hidden" name="to_date" value="<?= h($filters['to_date']) ?>">
                        <button class="button outline small" type="submit">Download Calendar PDF</button>
                    </form>
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee Name</th>
                    <th>Source</th>
                    <th>Project Name</th>
                    <th>Slot</th>
                    <th>Session Type</th>
                    <th>Attendance Status</th>
                    <th>Manual Punch In</th>
                    <th>Manual Punch In Photo</th>
                    <th>Manual Punch Out</th>
                    <th>Biometric Punch In</th>
                    <th>Biometric Punch Out</th>
                    <th>Total Students</th>
                    <th>Present Students</th>
                    <th>Topics Handled</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reportData): ?>
                    <?php foreach ($reportData as $row): ?>
                        <tr>
                            <td><?= h(date('d M Y', strtotime((string)$row['date']))) ?></td>
                            <td><?= h((string)$row['employee_name']) ?></td>
                            <td><?= h((string)(($row['attendance_source'] ?? '') ?: 'Attendance')) ?></td>
                            <td><?= h((string)($row['project_name'] ?: '-')) ?></td>
                            <td><?= h((string)($row['slot_name'] ?: '-')) ?></td>
                            <td><?= h(project_session_label((string)$row['session_type'])) ?></td>
                            <td>
                                <?php $statusClass = str_replace(' ', '-', (string)$row['attendance_status']); ?>
                                <span class="status-pill status-<?= h($statusClass) ?>"><?= h((string)$row['attendance_status']) ?></span>
                            </td>
                            <td><?= h((string) (($row['manual_punch_in'] ?? '') ?: '-')) ?></td>
                            <td>
                                <?php $manualPunchPhoto = report_photo_url($row['manual_punch_in_photo'] ?? ''); ?>
                                <?php $manualPunchPhotoData = report_photo_data_uri_from_row($row); ?>
                                <?php if ($manualPunchPhotoData !== ''): ?>
                                    <img class="report-punch-photo" src="<?= h($manualPunchPhotoData) ?>" alt="Manual punch in photo">
                                <?php elseif ($manualPunchPhoto !== ''): ?>
                                    <a href="<?= h($manualPunchPhoto) ?>" target="_blank" rel="noopener">
                                        <img class="report-punch-photo" src="<?= h($manualPunchPhoto) ?>" alt="Manual punch in photo">
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= h((string) (($row['manual_punch_out'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($row['biometric_punch_in'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($row['biometric_punch_out'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($row['total_students'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($row['present_students'] ?? '') ?: '-')) ?></td>
                            <td><?= h((string) (($row['topics_handled'] ?? '') ?: '-')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="15" class="muted center">No records found for the selected filters.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
    <style>
        .multi-select-picker { border: 1px solid #ddd; padding: 10px; border-radius: 8px; background: #fff; }
        .multi-options { max-height: 150px; margin-top: 10px; border-top: 1px solid #eee; padding-top: 5px; }
        .multi-option { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 10px 12px; cursor: pointer; border-radius: 12px; }
        .multi-option span { min-width: 0; }
        .multi-option input { order: 2; width: auto; min-height: auto; margin: 0; }
        .multi-option:hover { background: #f9f9f9; }
        .reports-filter-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px; align-items: start; }
        .reports-date-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-top: 1rem; }
        .reports-actions { display: flex; justify-content: flex-end; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 1rem; }
        .report-punch-photo { width: 54px; height: 54px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(30,41,59,0.12); background: #eef2ff; display: block; }
        @media print {
            .admin-sidebar, .reports-actions, .eyebrow, .spacer, .flash-stack { display: none !important; }
            .admin-main { margin: 0 !important; padding: 0 !important; width: 100% !important; }
            table { font-size: 10px !important; }
            .status-pill { border: none !important; padding: 0 !important; }
        }
        @media (max-width: 900px) {
            .reports-filter-grid, .reports-date-grid { grid-template-columns: 1fr; }
            .reports-actions { justify-content: stretch; }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-multi-filter]').forEach(filterInput => {
                filterInput.addEventListener('input', () => {
                    const list = document.getElementById(filterInput.dataset.multiFilter);
                    if (!list) return;
                    const term = filterInput.value.toLowerCase();
                    list.querySelectorAll('.multi-option').forEach(option => {
                        const label = option.textContent.toLowerCase();
                        option.style.display = label.includes(term) ? '' : 'none';
                    });
                });
            });
        });
    </script>
    <?php
    render_footer();
}


