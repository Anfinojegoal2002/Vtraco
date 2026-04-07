<?php

declare(strict_types=1);

function render_employee_attendance(): void
{
    $employee = require_role('employee');
    $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $attendance = month_attendance_for_user((int) $employee['id'], $month);

    render_header('My Attendance');
    ?>
    <section class="banner">
        <div class="employee-topbar">
            <div>
                <span class="eyebrow" style="background:rgba(255,255,255,0.14);color:#fff;">Employee Workspace</span>
                <h1><?= h($employee['name']) ?></h1>
                <p>Employee ID: <strong><?= h($employee['emp_id']) ?></strong></p>
            </div>
            <form method="get" class="inline-actions">
                <input type="hidden" name="page" value="employee_attendance">
                <input type="month" name="month" value="<?= h($month) ?>">
                <button class="button outline" style="background:rgba(255,255,255,0.12);color:#fff;border-color:rgba(255,255,255,0.28);" type="submit">Change Month</button>
            </form>
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
    <?php render_calendar('employee', $employee, $month, $attendance); ?>
    <?php
    render_footer();
}
