<?php

declare(strict_types=1);

function calendar_payload(string $context, array $employee, string $date, array $recordBlock, array $reimbursementMeta = [], ?string $month = null, string $viewMode = 'attendance'): string
{
    $rules = employee_rules((int) $employee['id']);
    $month = $month && preg_match('/^\d{4}-\d{2}$/', $month) ? $month : substr($date, 0, 7);
    $canUseReimbursement = !employee_is_vendor_trainer($employee);
    $sessions = array_map(static function (array $session): array {
        $sessionPhotoData = report_photo_data_uri_from_row([
            'manual_punch_in_photo_data' => $session['punch_in_photo'] ?? null,
            'manual_punch_in_photo_mime' => $session['punch_in_photo_mime'] ?? '',
            'manual_punch_in_photo' => $session['punch_in_path'] ?? '',
        ]);
        $session['punch_in_path'] = $sessionPhotoData !== '' ? $sessionPhotoData : public_file_path((string) ($session['punch_in_path'] ?? ''));
        unset($session['punch_in_photo'], $session['punch_in_photo_mime']);
        return $session;
    }, $recordBlock['sessions'] ?? []);
    $reimbursementItems = array_map(static function (array $item): array {
        return [
            'category' => (string) ($item['category'] ?? ''),
            'status' => (string) ($item['status'] ?? ''),
            'amount_requested' => number_format((float) ($item['amount_requested'] ?? 0), 2, '.', ''),
            'amount_paid' => number_format((float) ($item['amount_paid'] ?? 0), 2, '.', ''),
            'remaining_balance' => number_format((float) ($item['remaining_balance'] ?? 0), 2, '.', ''),
            'expense_description' => (string) ($item['expense_description'] ?? ''),
        ];
    }, $reimbursementMeta['items'] ?? []);

    $recordPhotoData = report_photo_data_uri_from_row([
        'manual_punch_in_photo_data' => $recordBlock['record']['punch_in_photo'] ?? null,
        'manual_punch_in_photo_mime' => $recordBlock['record']['punch_in_photo_mime'] ?? '',
        'manual_punch_in_photo' => $recordBlock['record']['punch_in_path'] ?? '',
    ]);

    return h(json_encode([
        'context' => $context,
        'employee_id' => (int) $employee['id'],
        'date' => $date,
        'display_date' => date('d M Y', strtotime($date)),
        'status' => $recordBlock['record']['status'],
        'sessions' => array_values($sessions),
        'punch_in_time' => $recordBlock['record']['punch_in_time'],
        'punch_in_path' => $recordPhotoData !== '' ? $recordPhotoData : public_file_path((string) ($recordBlock['record']['punch_in_path'] ?? '')),
        'punch_in_lat' => $recordBlock['record']['punch_in_lat'],
        'punch_in_lng' => $recordBlock['record']['punch_in_lng'],
        'punch_out_time' => $recordBlock['record']['punch_out_time'] ?? '',
        'leave_reason' => $recordBlock['record']['leave_reason'],
        'biometric_in_time' => $recordBlock['record']['biometric_in_time'],
        'biometric_out_time' => $recordBlock['record']['biometric_out_time'],
        'rule_manual_in' => $rules['manual_punch_in'],
        'rule_manual_out' => $rules['manual_punch_out'],
        'manual_out_count' => $rules['manual_out_count'],
        'manual_out_slots' => $rules['manual_out_slots'],
        'view_mode' => $viewMode,
        'rule_bio_in' => $rules['biometric_punch_in'],
        'rule_bio_out' => $rules['biometric_punch_out'],
        'reimbursement' => [
            'available' => $canUseReimbursement,
            'count' => (int) ($reimbursementMeta['count'] ?? 0),
            'total' => number_format((float) ($reimbursementMeta['total'] ?? 0), 2, '.', ''),
            'current_month' => $month === date('Y-m'),
            'future' => $date > date('Y-m-d'),
            'locked' => (int) ($reimbursementMeta['count'] ?? 0) >= 3,
            'items' => $reimbursementItems,
        ],
    ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));
}


function calendar_sheet_time(?string $time): string
{
    $time = trim((string) $time);
    if ($time === '') {
        return '00:00';
    }

    $timestamp = strtotime($time);
    return $timestamp !== false ? date('H:i', $timestamp) : $time;
}


function calendar_sheet_project_label(array $entry): string
{
    $names = [];
    foreach (($entry['sessions'] ?? []) as $session) {
        $name = trim((string) (($session['project_name'] ?? '') ?: ($session['session_name'] ?? '') ?: ($session['slot_name'] ?? '')));
        if ($name !== '' && !in_array($name, $names, true)) {
            $names[] = $name;
        }
    }

    return $names ? implode(', ', array_slice($names, 0, 2)) : 'NIL';
}


function calendar_sheet_shift_label(array $employee, array $monthAttendance): string
{
    $window = shift_window_for_employee($employee);
    if ($window !== null) {
        return calendar_sheet_time($window['start_time'] ?? '') . ' TO ' . calendar_sheet_time($window['end_time'] ?? '');
    }

    return '09:00 TO 17:00';
}


function calendar_sheet_display_status(string $date, array $entry, string $status): string
{
    return attendance_status_for_counts($date, $entry);
}


function render_calendar(string $context, array $employee, string $month, array $monthAttendance, array $calendarMeta = []): void
{
    [$start] = month_bounds($month);
    $offset = (int) $start->format('w');
    $trailingBlankDays = (7 - (($offset + count($monthAttendance)) % 7)) % 7;
    $weekRows = (int) max(4, min(6, (int) ceil(($offset + count($monthAttendance)) / 7)));
    $showSummary = !array_key_exists('show_summary', $calendarMeta) || !empty($calendarMeta['show_summary']);
    $compact = !empty($calendarMeta['compact']);
    $reimbursementsByDate = $calendarMeta['reimbursements_by_date'] ?? [];
    $viewMode = $calendarMeta['view_mode'] ?? 'attendance';
    $calendarActionsHtml = (string) ($calendarMeta['calendar_actions_html'] ?? '');
    $employeeTypeKey = strtolower(trim((string) ($employee['employee_type'] ?? '')));
    $employeeRoleKey = strtolower(trim((string) ($employee['role'] ?? '')));
    $isVendorTrainer = employee_is_vendor_trainer($employee);
    $usesSessionAttendance = in_array($employeeTypeKey, ['vendor', 'corporate'], true) || $employeeRoleKey === 'corporate_employee';
    $salaryBreakdown = employee_salary_breakdown_for_month($employee, $monthAttendance);
    $halfDayCount = (float) ($salaryBreakdown['half_day_count'] ?? 0);
    $halfDaySalary = (float) ($salaryBreakdown['half_day_salary'] ?? 0);
    $halfDayLabel = $usesSessionAttendance ? 'Half Sessions' : 'Half Days';
    $halfDaySalaryLabel = $usesSessionAttendance ? 'Half Session Salary' : 'Half Day Salary';
    $showHalfDaySalaryCard = false;
    $canUseReimbursement = !$isVendorTrainer;
    $reimbursementSummary = $canUseReimbursement
        ? employee_reimbursement_month_summary((int) ($employee['id'] ?? 0), $month)
        : ['count' => 0, 'requested_total' => 0, 'approved_total' => 0, 'paid_total' => 0];
    $usesVendorHourlySalary = $employeeTypeKey === 'vendor';
    $usesHourlySalary = employee_is_freelancer_managed($employee);
    $usesPaymentSummary = $usesHourlySalary || $employeeTypeKey === 'corporate' || $employeeRoleKey === 'corporate_employee';
    $salaryPaidAmount = $usesPaymentSummary
        ? paid_amount_for_employee_month_by_admin((int) ($employee['id'] ?? 0), 'SALARY', $month, !empty($employee['admin_id']) ? (int) $employee['admin_id'] : null)
        : 0.0;
    $salaryActualAmount = (float) ($salaryBreakdown['calculated_salary'] ?? 0);
    if ($usesPaymentSummary && !$usesHourlySalary) {
        $salaryActualAmount = max($salaryActualAmount, assigned_project_payment_total_for_month((int) ($employee['id'] ?? 0), $month));
    }
    $salaryPendingAmount = round(max($salaryActualAmount - $salaryPaidAmount, 0), 2);
    $attendanceSummaryCounts = attendance_counts($monthAttendance);
    $sheetFullDayCount = $usesSessionAttendance
        ? (float) ($salaryBreakdown['full_sessions'] ?? 0)
        : (float) ($attendanceSummaryCounts['present'] ?? 0);
    $sheetHalfDayCount = (float) ($salaryBreakdown['half_day_count'] ?? ($attendanceSummaryCounts['half_day'] ?? 0));
    $sheetPresentDays = (float) ($salaryBreakdown['payable_days'] ?? 0);
    $sheetWorkingDays = (float) ($salaryBreakdown['working_days'] ?? ($attendanceSummaryCounts['working_days'] ?? 0));
    $sheetShiftLabel = calendar_sheet_shift_label($employee, $monthAttendance);
    $monthShortLabel = strtoupper($start->format('M'));
    ?>
    <div class="calendar-shell calendar-shell-sheet<?= $compact ? ' calendar-shell-compact' : '' ?>">
        <?php if ($calendarActionsHtml !== ''): ?>
            <div class="calendar-top-actions">
                <?= $calendarActionsHtml ?>
            </div>
        <?php endif; ?>
        <div class="calendar-legend" aria-label="Attendance legend">
            <?php if ($viewMode === 'reimbursement'): ?>
                <span class="legend-chip"><span class="legend-swatch legend-reimbursement" style="background-color: #6366f1;"></span>Reimbursement Claim</span>
            <?php elseif ($usesSessionAttendance): ?>
                <span class="legend-chip"><span class="legend-swatch legend-present"></span>Completed Session</span>
                <span class="legend-chip"><span class="legend-swatch legend-half-day"></span>Half Session</span>
            <?php else: ?>
                <span class="legend-chip"><span class="legend-swatch legend-present"></span>Present</span>
                <span class="legend-chip"><span class="legend-swatch legend-absent"></span>Absent</span>
                <span class="legend-chip"><span class="legend-swatch legend-half-day"></span>Half Day</span>
                <span class="legend-chip"><span class="legend-swatch legend-leave"></span>Leave</span>
                <span class="legend-chip"><span class="legend-swatch legend-week-off"></span>Week Off</span>
            <?php endif; ?>
        </div>
        <div class="calendar-grid calendar-grid-sheet<?= $compact ? ' calendar-grid-compact' : '' ?>"<?= $compact ? ' style="--calendar-week-rows: ' . $weekRows . ';"' : '' ?>>
            <div class="calendar-sheet-title">ACTUAL WORK TIME - <?= h($sheetShiftLabel) ?></div>
            <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
                <div class="weekday"><?= h($weekday) ?></div>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $offset; $i++): ?>
                <div class="day-card blank"></div>
            <?php endfor; ?>
            <?php foreach ($monthAttendance as $date => $entry): ?>
                <?php $status = (string) ($entry['record']['status'] ?? ''); ?>
                <?php if (!empty($entry['record']['sandwich_week_off_absent'])) {
                    $status = 'Absent';
                } ?>
                <?php if ($status === 'Pending') { $status = 'Half Day'; } ?>
                <?php if ($viewMode !== 'reimbursement') { $status = calendar_sheet_display_status($date, $entry, $status); } ?>
                <?php $statusClass = str_replace(' ', '-', $status); ?>
                <?php $dayCopy = in_array($status, ['Week Off', 'Absent'], true) ? $status : ''; ?>
                <?php $isAdminChanged = trim((string) ($entry['record']['admin_override_status'] ?? '')) !== '' && trim((string) ($entry['record']['admin_override_status'] ?? '')) !== 'Pending'; ?>
                <?php
                    $manualMarkedBy = trim((string) ($entry['record']['admin_override_by_name'] ?? ''));
                    if ($manualMarkedBy === '') {
                        $manualMarkedBy = 'Admin/HR';
                    }
                    $manualMarkedAtRaw = trim((string) (($entry['record']['admin_override_at'] ?? '') ?: ($entry['record']['updated_at'] ?? '')));
                    $manualMarkedAt = $manualMarkedAtRaw !== '' ? date('d M Y, h:i A', strtotime($manualMarkedAtRaw)) : 'time not recorded';
                    $manualMarkedTitle = 'Manually marked by ' . $manualMarkedBy . ' on ' . $manualMarkedAt;
                ?>
                <?php if ($usesSessionAttendance && $viewMode !== 'reimbursement'): ?>
                    <?php $vendorSessionDisplay = vendor_session_display_for_entry($entry); ?>
                    <?php $statusClass = (string) ($vendorSessionDisplay['status_class'] ?? ''); ?>
                    <?php $dayCopy = (string) ($vendorSessionDisplay['copy'] ?? ''); ?>
                <?php endif; ?>
                <?php 
                    $dayCardClass = 'day-card';
                    if ($viewMode !== 'reimbursement' && $statusClass !== '') {
                        $dayCardClass .= ' day-card-' . $statusClass;
                    }
                ?>
                <?php $isEmployeeWeekOff = $context === 'employee' && ($status === 'Week Off') && empty($entry['record']['sandwich_week_off_absent']); ?>
                <?php $reimbursementMeta = $reimbursementsByDate[$date] ?? ['count' => 0, 'total' => 0.0, 'items' => []]; ?>
                <?php
                    $dayProjectPayment = 0.0;
                    $workTimes = attendance_resolved_work_times($entry['record'] ?? [], $entry['sessions'] ?? []);
                    $sheetLogin = calendar_sheet_time($workTimes['in_time'] ?? null);
                    $sheetLogout = calendar_sheet_time($workTimes['out_time'] ?? null);
                    $sheetProject = calendar_sheet_project_label($entry);
                    if ($usesPaymentSummary && $viewMode !== 'reimbursement') {
                        foreach (employee_available_projects_for_date($employee, $date) as $availableProject) {
                            if ($usesVendorHourlySalary) {
                                $sessionPay = project_session_salary_for_date($employee, (int) ($availableProject['id'] ?? 0), $date);
                                $sessionAmount = (float) ($sessionPay['amount'] ?? 0);
                                $dayProjectPayment += $sessionAmount > 0 ? $sessionAmount : project_assignment_payment_amount($availableProject, $sessionPay);
                            } else {
                                $sessionPay = project_session_salary_for_date($employee, (int) ($availableProject['id'] ?? 0), $date);
                                if ((int) ($sessionPay['completed_sessions'] ?? 0) <= 0) {
                                    continue;
                                }
                                $dayProjectPayment += project_assignment_payment_amount($availableProject, $sessionPay);
                            }
                        }
                        $dayProjectPayment = round($dayProjectPayment, 2);
                    }
                ?>
                <?php if ($isEmployeeWeekOff): ?>
                    <div class="<?= h($dayCardClass) ?> static<?= $compact ? ' compact' : '' ?>"<?= $isAdminChanged ? ' title="' . h($manualMarkedTitle) . '"' : '' ?>>
                        <?php if ($viewMode === 'reimbursement'): ?>
                            <?php if ($reimbursementMeta['count'] > 0): ?>
                                <span class="day-dot" aria-hidden="true" style="background-color: #6366f1;"></span>
                                <span class="day-number"><?= date('d', strtotime($date)) ?></span>
                            <?php else: ?>
                                <span class="day-number"><?= date('d', strtotime($date)) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($statusClass !== ''): ?>
                                <span class="day-dot dot-<?= h($statusClass) ?>" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="day-number<?= ($viewMode !== 'reimbursement' && $statusClass !== '') ? ' day-number-' . h($statusClass) : '' ?>"><?= h($monthShortLabel . '-' . (int) date('j', strtotime($date))) ?></span>
                            <span class="day-copy">log in: <?= h($sheetLogin) ?></span>
                            <span class="day-copy">logout: <?= h($sheetLogout) ?></span>
                            <span class="day-copy">project: <?= h($sheetProject) ?></span>
                            <?php if ($dayProjectPayment > 0): ?>
                                <span class="day-copy">Rs <?= h(number_format($dayProjectPayment, 2)) ?></span>
                            <?php endif; ?>
                            <?php if ($canUseReimbursement && !empty($reimbursementMeta['count'])): ?>
                                <span class="day-badge reimbursement">R <?= (int) $reimbursementMeta['count'] ?></span>
                            <?php endif; ?>
                            <?php if ($isAdminChanged): ?>
                                <span class="day-badge admin-change" title="<?= h($manualMarkedTitle) ?>">M</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <button class="<?= h($dayCardClass) ?><?= $compact ? ' compact' : '' ?>" type="button"<?= $isAdminChanged ? ' title="' . h($manualMarkedTitle) . '"' : '' ?> data-attendance="<?= calendar_payload($context, $employee, $date, $entry, $reimbursementMeta, $month, $viewMode) ?>">
                        <?php if ($viewMode === 'reimbursement'): ?>
                            <?php if ($reimbursementMeta['count'] > 0): ?>
                                <span class="day-dot" aria-hidden="true" style="background-color: #6366f1;"></span>
                                <span class="day-number"><?= date('d', strtotime($date)) ?></span>
                            <?php else: ?>
                                <span class="day-number"><?= date('d', strtotime($date)) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($statusClass !== ''): ?>
                                <span class="day-dot dot-<?= h($statusClass) ?>" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span class="day-number<?= ($viewMode !== 'reimbursement' && $statusClass !== '') ? ' day-number-' . h($statusClass) : '' ?>"><?= h($monthShortLabel . '-' . (int) date('j', strtotime($date))) ?></span>
                            <span class="day-copy">log in: <?= h($sheetLogin) ?></span>
                            <span class="day-copy">logout: <?= h($sheetLogout) ?></span>
                            <span class="day-copy">project: <?= h($sheetProject) ?></span>
                            <?php if ($dayProjectPayment > 0): ?>
                                <span class="day-copy">Rs <?= h(number_format($dayProjectPayment, 2)) ?></span>
                            <?php endif; ?>
                            <?php if ($canUseReimbursement && !empty($reimbursementMeta['count'])): ?>
                                <span class="day-badge reimbursement">R <?= (int) $reimbursementMeta['count'] ?></span>
                            <?php endif; ?>
                            <?php if ($isAdminChanged): ?>
                                <span class="day-badge admin-change" title="<?= h($manualMarkedTitle) ?>">M</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
            <?php for ($i = 0; $i < $trailingBlankDays; $i++): ?>
                <div class="day-card blank"></div>
            <?php endfor; ?>
        </div>
        <?php if ($showSummary && !$isVendorTrainer): ?>
            <?php if ($usesPaymentSummary): ?>
                <div class="freelancer-attendance-summary">
                    <div class="split">
                        <div>
                            <span class="eyebrow">Payment</span>
                            <h3>Payment</h3>
                        </div>
                    </div>
                    <div class="calendar-summary">
                        <div class="summary-card"><strong>Rs <?= number_format($salaryPendingAmount, 2) ?></strong><span>Pending</span></div>
                        <div class="summary-card"><strong>Rs <?= number_format($salaryActualAmount, 2) ?></strong><span>Actual</span></div>
                        <?php if ($showHalfDaySalaryCard): ?>
                            <div class="summary-card"><strong>Rs <?= number_format($halfDaySalary, 2) ?></strong><span><?= h($halfDaySalaryLabel) ?></span></div>
                        <?php endif; ?>
                        <div class="summary-card summary-highlight"><strong>Rs <?= number_format($salaryPaidAmount, 2) ?></strong><span>Paid</span></div>
                    </div>
                    <?php if ($canUseReimbursement): ?>
                        <div class="split freelancer-summary-subhead">
                            <div>
                                <span class="eyebrow">Reimbursement</span>
                                <h3>Reimbursement</h3>
                            </div>
                        </div>
                        <div class="calendar-summary">
                            <div class="summary-card"><strong>Rs <?= number_format((float) ($reimbursementSummary['requested_total'] ?? 0), 2) ?></strong><span>Reimbursement Requested</span></div>
                            <div class="summary-card"><strong>Rs <?= number_format((float) ($reimbursementSummary['approved_total'] ?? 0), 2) ?></strong><span>Approval</span></div>
                            <div class="summary-card summary-highlight"><strong>Rs <?= number_format((float) ($reimbursementSummary['paid_total'] ?? 0), 2) ?></strong><span>Paid</span></div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="calendar-summary">
                    <?php if ($showHalfDaySalaryCard): ?>
                        <div class="summary-card"><strong>Rs <?= number_format($halfDaySalary, 2) ?></strong><span><?= h($halfDaySalaryLabel) ?></span></div>
                    <?php endif; ?>
                    <div class="summary-card summary-highlight"><strong>Rs <?= number_format((float) ($salaryBreakdown['calculated_salary'] ?? 0), 2) ?></strong><span>Salary</span></div>
                    <?php if ($canUseReimbursement): ?>
                        <div class="summary-card"><strong>Rs <?= number_format((float) ($reimbursementSummary['requested_total'] ?? 0), 2) ?></strong><span>Reimbursement Requested</span></div>
                        <div class="summary-card"><strong>Rs <?= number_format((float) ($reimbursementSummary['approved_total'] ?? 0), 2) ?></strong><span>Approved</span></div>
                        <div class="summary-card"><strong>Rs <?= number_format((float) ($reimbursementSummary['paid_total'] ?? 0), 2) ?></strong><span>Paid</span></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($viewMode !== 'reimbursement' && $employeeTypeKey !== 'corporate' && $employeeRoleKey !== 'corporate_employee'): ?>
                <div class="calendar-summary calendar-summary-attendance-extra">
                    <div class="summary-card"><strong><?= number_format($sheetFullDayCount, 2) ?></strong><span>Full day</span></div>
                    <div class="summary-card"><strong><?= number_format($sheetHalfDayCount, 2) ?></strong><span>Half day</span></div>
                    <div class="summary-card"><strong><?= number_format($sheetPresentDays, 2) ?></strong><span>Total present days</span></div>
                    <div class="summary-card"><strong><?= number_format($sheetWorkingDays, 2) ?></strong><span>Total working days</span></div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}


