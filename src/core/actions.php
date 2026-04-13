<?php

declare(strict_types=1);

function handle_post_action(string $action): void
{
    switch ($action) {
        case 'login':
            $role = $_POST['role'] ?? 'admin';
            $email = trim((string) ($_POST['email'] ?? ''));
            $returnPage = (string) ($_POST['return_page'] ?? '');
            if ($role === 'employee' && !empty($_POST['forgot_password'])) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    flash('error', 'Enter your employee email first, then click Forgot your password.');
                    if ($returnPage === 'landing') {
                        redirect_to('landing', ['auth' => $role]);
                    }
                    redirect_to('login', ['role' => $role]);
                }

                try {
                    $reset = reset_employee_password_by_email($email);
                    if (!empty($reset['mail_result']['sent'])) {
                        flash('success', 'A new employee password was sent to ' . $reset['employee']['email'] . '.');
                    } else {
                        flash('success', 'A new employee password was generated. Email delivery is not configured yet, so a copy was saved in storage/emails/' . ($reset['mail_result']['log_file'] ?? '') . '.');
                    }
                } catch (Throwable $exception) {
                    flash('error', $exception->getMessage());
                }

                if ($returnPage === 'landing') {
                    redirect_to('landing', ['auth' => $role]);
                }
                redirect_to('login', ['role' => $role]);
            }

            $stmt = db()->prepare('SELECT * FROM users WHERE role = :role AND email = :email');
            $stmt->execute([
                'role' => $role,
                'email' => $email,
            ]);
            $user = $stmt->fetch();
            if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                redirect_to($user['role'] === 'admin' ? 'admin_dashboard' : 'employee_attendance');
            }
            flash('error', ucfirst((string) $role) . ' login failed.');
            if ($returnPage === 'landing') {
                redirect_to('landing', ['auth' => $role]);
            }
            redirect_to('login', ['role' => $role]);
            break;

        case 'register_admin':
            if (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
                flash('error', 'Passwords do not match.');
                redirect_to('register');
            }
            try {
                db()->prepare('INSERT INTO users (role, emp_id, name, email, phone, salary, password_hash, created_at) VALUES ("admin", NULL, :name, :email, :phone, 0, :password_hash, :created_at)')
                    ->execute([
                        'name' => trim((string) ($_POST['name'] ?? '')),
                        'email' => trim((string) ($_POST['email'] ?? '')),
                        'phone' => trim((string) ($_POST['phone'] ?? '')),
                        'password_hash' => password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
                        'created_at' => now(),
                    ]);
                flash('success', 'Admin account created.');
                redirect_to('login', ['role' => 'admin']);
            } catch (Throwable $exception) {
                flash('error', 'Admin registration failed. Email may already exist.');
                redirect_to('register');
            }
            break;

        case 'employee_manual_next':
            require_role('admin');
            $_SESSION['pending_employee'] = [
                'emp_id' => trim((string) ($_POST['emp_id'] ?? '')),
                'name' => trim((string) ($_POST['name'] ?? '')),
                'email' => trim((string) ($_POST['email'] ?? '')),
                'phone' => trim((string) ($_POST['phone'] ?? '')),
                'shift' => trim((string) ($_POST['shift'] ?? '')),
                'salary' => (float) ($_POST['salary'] ?? 0),
            ];
            redirect_to('admin_employees', ['stage' => 'manual_rules']);
            break;

        case 'employee_manual_submit':
            require_role('admin');
            $pending = $_SESSION['pending_employee'] ?? null;
            if (!$pending) {
                flash('error', 'No pending employee found.');
                redirect_to('admin_employees');
            }
            try {
                $rules = normalize_rules_from_input($_POST);
                $createdEmployee = insert_employee($pending, $rules);
                unset($_SESSION['pending_employee']);
                flash('success', 'Employee added successfully.');
            } catch (Throwable $exception) {
                flash('error', 'Unable to add employee. Email or Emp ID may already exist.');
            }
            redirect_to('admin_employees');
            break;

        case 'employee_csv_upload':
            require_role('admin');
            try {
                $_SESSION['pending_csv_import'] = parse_employee_csv((string) $_FILES['csv_file']['tmp_name']);
                flash('success', 'CSV uploaded. Assign rules to continue.');
                redirect_to('admin_employees', ['stage' => 'csv_rules']);
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
                redirect_to('admin_employees');
            }
            break;

        case 'employee_csv_submit':
            require_role('admin');
            $rows = $_SESSION['pending_csv_import'] ?? [];
            if (!$rows) {
                flash('error', 'No CSV import is pending.');
                redirect_to('admin_employees');
            }
            $rules = normalize_rules_from_input($_POST);
            if (!$rules['manual_punch_in'] && !$rules['manual_punch_out'] && !$rules['biometric_punch_in'] && !$rules['biometric_punch_out']) {
                flash('error', 'Select at least one rule before submitting the CSV import.');
                redirect_to('admin_employees', ['stage' => 'csv_rules']);
            }
            $created = 0;
            $skipped = 0;
            $emailsSent = 0;
            $emailsLogged = 0;
            foreach ($rows as $row) {
                try {
                    $createdEmployee = insert_employee($row, $rules);
                    $created++;
                    if (!empty($createdEmployee['mail_result']['sent'])) {
                        $emailsSent++;
                    } else {
                        $emailsLogged++;
                    }
                } catch (Throwable $exception) {
                    $skipped++;
                }
            }
            unset($_SESSION['pending_csv_import']);
            $message = 'CSV import completed. Created: ' . $created;
            if ($emailsSent) {
                $message .= ' | Emails sent: ' . $emailsSent;
            }
            if ($emailsLogged) {
                $message .= ' | Logged locally: ' . $emailsLogged;
            }
            if ($skipped) {
                $message .= ' | Skipped: ' . $skipped;
            }
            flash('success', $message);
            redirect_to('admin_employees');
            break;

        case 'employee_update':
            $admin = require_role('admin');
            $employeeId = (int) ($_POST['user_id'] ?? 0);
            if (!employee_by_id($employeeId)) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            try {
                db()->prepare('UPDATE users SET emp_id = :emp_id, name = :name, email = :email, phone = :phone, shift = :shift, salary = :salary WHERE id = :id AND role = "employee" AND admin_id = :admin_id')
                    ->execute([
                        'id' => $employeeId,
                        'admin_id' => (int) $admin['id'],
                        'emp_id' => trim((string) ($_POST['emp_id'] ?? '')),
                        'name' => trim((string) ($_POST['name'] ?? '')),
                        'email' => trim((string) ($_POST['email'] ?? '')),
                        'phone' => trim((string) ($_POST['phone'] ?? '')),
                        'shift' => trim((string) ($_POST['shift'] ?? '')),
                        'salary' => (float) ($_POST['salary'] ?? 0),
                    ]);
                flash('success', 'Employee updated.');
            } catch (Throwable $exception) {
                flash('error', 'Unable to update employee.');
            }
            redirect_to('admin_employees');
            break;

        case 'employee_reset_password':
            require_role('admin');
            $employeeId = (int) ($_POST['user_id'] ?? 0);
            if (!employee_by_id($employeeId)) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            try {
                $reset = reset_employee_password($employeeId);
                flash('success', employee_credentials_delivery_message($reset['employee'], $reset['mail_result'], $reset['password'], 'reset'));
            } catch (Throwable $exception) {
                flash('error', 'Unable to reset the employee password.');
            }
            redirect_to('admin_employees');
            break;
        case 'employee_delete':
            $admin = require_role('admin');
            $employeeId = (int) ($_POST['user_id'] ?? 0);
            if (!employee_by_id($employeeId)) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            db()->prepare('DELETE FROM users WHERE id = :id AND role = "employee" AND admin_id = :admin_id')
                ->execute([
                    'id' => $employeeId,
                    'admin_id' => (int) $admin['id'],
                ]);
            flash('success', 'Employee deleted successfully.');
            redirect_to('admin_employees');
            break;

        case 'admin_add_shift_timing':
            require_role('admin');
            try {
                $startTime = trim((string) ($_POST['start_time'] ?? ''));
                $endTime = trim((string) ($_POST['end_time'] ?? ''));

                if ($startTime === '' || $endTime === '') {
                    throw new RuntimeException('Start time and end time are required.');
                }

                if ($startTime === $endTime) {
                    throw new RuntimeException('Start time and end time must be different.');
                }

                add_shift_timing([
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ]);
                flash('success', 'Shift timing posted successfully.');
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
            }
            redirect_to('admin_shift');
            break;

        case 'admin_delete_shift_timing':
            require_role('admin');
            try {
                delete_shift_timing((int) ($_POST['shift_id'] ?? 0));
                flash('success', 'Shift timing deleted.');
            } catch (Throwable $exception) {
                flash('error', 'Unable to delete shift timing.');
            }
            redirect_to('admin_shift');
            break;

        case 'apply_rules':
            require_role('admin');
            $ids = array_map('intval', $_POST['employee_ids'] ?? []);
            $rules = normalize_rules_from_input($_POST);
            if (!$ids) {
                flash('error', 'Select at least one employee.');
                redirect_to('admin_rules');
            }
            $updated = 0;
            foreach ($ids as $id) {
                $employee = employee_by_id($id);
                if (!$employee) {
                    continue;
                }
                save_employee_rules((int) $employee['id'], $rules);
                send_rules_updated_email($employee, $rules);
                $updated++;
            }
            flash($updated > 0 ? 'success' : 'error', $updated > 0 ? 'Rules applied successfully.' : 'No employees were available for this administrator.');
            redirect_to('admin_rules');
            break;

        case 'admin_attendance_csv_upload':
            require_role('admin');
            try {
                $result = import_attendance_report_csv((string) ($_FILES['attendance_csv']['tmp_name'] ?? ''), trim((string) ($_POST['attendance_date'] ?? '')), (string) ($_FILES['attendance_csv']['name'] ?? ''));
                $message = 'Attendance import completed. Imported: ' . (int) $result['imported'];
                if (!empty($result['date'])) {
                    $message .= ' | Date: ' . date('d M Y', strtotime((string) $result['date']));
                }
                if (!empty($result['created'])) {
                    $message .= ' | Auto-added Employees: ' . (int) $result['created'];
                }
                if (!empty($result['skipped'])) {
                    $message .= ' | Skipped: ' . (int) $result['skipped'];
                }
                if (!empty($result['unmatched'])) {
                    $message .= ' | Unmatched Employees: ' . implode(', ', $result['unmatched']);
                }
                flash('success', $message);
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
            }
            redirect_to('admin_attendance');
            break;

        case 'admin_set_status':
            require_role('admin');
            $employeeId = (int) ($_POST['employee_id'] ?? 0);
            $employee = employee_by_id($employeeId);
            if (!$employee) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_attendance');
            }
            update_attendance_record((int) $employee['id'], (string) ($_POST['attend_date'] ?? ''), [
                'status' => (string) ($_POST['status'] ?? 'Absent'),
                'admin_override_status' => (string) ($_POST['status'] ?? 'Absent'),
            ]);
            flash('success', 'Attendance status updated.');
            redirect_to('admin_attendance', [
                'employee_id' => (int) $employee['id'],
                'month' => substr((string) ($_POST['attend_date'] ?? date('Y-m-d')), 0, 7),
            ]);
            break;

        case 'admin_profile_update':
            $admin = require_role('admin');
            $returnPage = (string) ($_POST['return_page'] ?? 'admin_dashboard');
            if (!str_starts_with($returnPage, 'admin_') || $returnPage === 'admin_profile_settings') {
                $returnPage = 'admin_dashboard';
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));

            if ($name === '') {
                flash('error', 'Name is required.');
                redirect_to($returnPage);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid email address.');
                redirect_to($returnPage);
            }

            try {
                db()->prepare('UPDATE users SET name = :name, email = :email, phone = :phone WHERE id = :id AND role = "admin"')
                    ->execute([
                        'id' => (int) $admin['id'],
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                    ]);
                flash('success', 'Profile updated successfully.');
            } catch (Throwable $exception) {
                flash('error', 'Unable to update profile.');
            }
            redirect_to($returnPage);
            break;

        case 'admin_change_password':
            $admin = require_role('admin');
            $returnPage = (string) ($_POST['return_page'] ?? 'admin_dashboard');
            if (!str_starts_with($returnPage, 'admin_') || $returnPage === 'admin_profile_settings') {
                $returnPage = 'admin_dashboard';
            }
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if (!password_verify($currentPassword, (string) $admin['password_hash'])) {
                flash('error', 'Current password is incorrect.');
                redirect_to($returnPage);
            }
            if (strlen($newPassword) < 6) {
                flash('error', 'New password must be at least 6 characters.');
                redirect_to($returnPage);
            }
            if ($newPassword !== $confirmPassword) {
                flash('error', 'New password and confirm password do not match.');
                redirect_to($returnPage);
            }

            db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id AND role = "admin"')
                ->execute([
                    'id' => (int) $admin['id'],
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                ]);
            flash('success', 'Password updated successfully. Please use the new password the next time you sign in.');
            redirect_to($returnPage);
            break;

        case 'employee_change_password':
            $employee = require_role('employee');
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if (!password_verify($currentPassword, (string) $employee['password_hash'])) {
                flash('error', 'Current password is incorrect.');
                redirect_to('employee_attendance');
            }
            if (strlen($newPassword) < 6) {
                flash('error', 'New password must be at least 6 characters.');
                redirect_to('employee_attendance');
            }
            if ($newPassword !== $confirmPassword) {
                flash('error', 'New password and confirm password do not match.');
                redirect_to('employee_attendance');
            }

            db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id AND role = "employee"')
                ->execute([
                    'id' => (int) $employee['id'],
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                ]);
            flash('success', 'Password updated successfully. Please use the new password the next time you sign in.');
            redirect_to('employee_attendance');
            break;
        case 'employee_manual_in':
        case 'employee_punch_in':
            $employee = require_role('employee');
            try {
                $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
                if (is_week_off_for_user_date((int) $employee['id'], $date)) {
                    throw new RuntimeException('Week Off dates do not require attendance.');
                }
                $rules = employee_rules((int) $employee['id']);
                $slotIndex = max(1, (int) ($_POST['slot_index'] ?? 1));
                $slotLimit = max(1, manual_slot_limit($rules));
                if (empty($rules['manual_punch_in'])) {
                    throw new RuntimeException('Manual Punch In is not enabled for this employee.');
                }
                if ($slotIndex > $slotLimit) {
                    throw new RuntimeException('Manual Punch In ' . $slotIndex . ' is not available for this date.');
                }
                if ((int) (($_FILES['punch_photo']['error'] ?? UPLOAD_ERR_NO_FILE)) === UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException('Manual Punch In ' . $slotIndex . ' requires a photo upload.');
                }

                $slotName = trim((string) ($_POST['slot_name'] ?? '')) ?: manual_slot_name($rules, $slotIndex);
                $record = ensure_attendance_record((int) $employee['id'], $date);
                $existingSession = attendance_session_by_slot((int) $record['id'], $slotName);
                if (!$existingSession && $slotIndex === 1 && !empty($record['punch_in_path'])) {
                    throw new RuntimeException('Manual Punch In 1 is already submitted for this date.');
                }
                if ($existingSession && !empty($existingSession['punch_in_path'])) {
                    throw new RuntimeException('Manual Punch In ' . $slotIndex . ' is already submitted for this date.');
                }

                $path = handle_upload($_FILES['punch_photo'] ?? []);
                $sessionPayload = [
                    'session_mode' => 'manual_pair',
                    'slot_name' => $slotName,
                    'punch_in_path' => $path,
                    'punch_in_lat' => trim((string) ($_POST['latitude'] ?? '')),
                    'punch_in_lng' => trim((string) ($_POST['longitude'] ?? '')),
                    'punch_in_time' => now(),
                ];

                if ($existingSession) {
                    update_attendance_session((int) $existingSession['id'], $sessionPayload);
                } else {
                    add_attendance_session((int) $record['id'], $sessionPayload);
                }
                $recordFields = ['status' => 'Pending'];
                if ($slotIndex === 1 || empty($record['punch_in_path'])) {
                    $recordFields['punch_in_path'] = $sessionPayload['punch_in_path'];
                    $recordFields['punch_in_lat'] = $sessionPayload['punch_in_lat'];
                    $recordFields['punch_in_lng'] = $sessionPayload['punch_in_lng'];
                    $recordFields['punch_in_time'] = $sessionPayload['punch_in_time'];
                }
                update_attendance_record((int) $employee['id'], $date, $recordFields);
                flash('success', 'Manual punch in ' . $slotIndex . ' submitted.');
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
            }
            redirect_to('employee_attendance', ['month' => substr((string) ($_POST['attend_date'] ?? date('Y-m-d')), 0, 7)]);
            break;
        case 'employee_manual_out':
            $employee = require_role('employee');
            $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
            if (is_week_off_for_user_date((int) $employee['id'], $date)) {
                flash('error', 'Week Off dates do not require attendance.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            $rules = employee_rules((int) $employee['id']);
            $slotIndex = max(1, (int) ($_POST['slot_index'] ?? 1));
            $slotLimit = max(1, manual_slot_limit($rules));
            $slotName = trim((string) ($_POST['slot_name'] ?? '')) ?: manual_slot_name($rules, $slotIndex);
            $record = ensure_attendance_record((int) $employee['id'], $date);
            $session = attendance_session_by_slot((int) $record['id'], $slotName);
            $collegeName = trim((string) ($_POST['college_name'] ?? ''));
            $sessionName = trim((string) ($_POST['session_name'] ?? ''));
            $dayPortion = trim((string) ($_POST['day_portion'] ?? 'Full Day'));
            $sessionDuration = (float) ($_POST['session_duration'] ?? 0);
            $location = trim((string) ($_POST['location'] ?? ''));

            if (empty($rules['manual_punch_out'])) {
                flash('error', 'Manual Punch Out is not enabled for this employee.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if ($slotIndex > $slotLimit) {
                flash('error', 'Manual Punch Out ' . $slotIndex . ' is not available for this date.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if (!$session && $slotIndex === 1 && !empty($record['punch_in_path'])) {
                add_attendance_session((int) $record['id'], [
                    'session_mode' => 'manual_pair',
                    'slot_name' => $slotName,
                    'punch_in_path' => $record['punch_in_path'],
                    'punch_in_lat' => $record['punch_in_lat'],
                    'punch_in_lng' => $record['punch_in_lng'],
                    'punch_in_time' => $record['punch_in_time'],
                ]);
                $session = attendance_session_by_slot((int) $record['id'], $slotName);
            }
            if (!$session || empty($session['punch_in_path'])) {
                flash('error', 'Submit Manual Punch In ' . $slotIndex . ' first.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if (session_has_manual_out($session)) {
                flash('error', 'Manual Punch Out ' . $slotIndex . ' is already submitted for this date.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            if ($collegeName === '' || $sessionName === '' || $location === '' || $sessionDuration <= 0) {
                flash('error', 'Manual Punch Out ' . $slotIndex . ' requires College Name, Session Name, Session Duration, and Location.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }

            update_attendance_session((int) $session['id'], [
                'session_mode' => 'manual_pair',
                'college_name' => $collegeName,
                'session_name' => $sessionName,
                'day_portion' => $dayPortion,
                'session_duration' => $sessionDuration,
                'location' => $location,
                'punch_out_time' => now(),
            ]);
            $updatedRecord = $record;
            $updatedRecord['status'] = ($dayPortion === 'Half Day') ? 'Half Day' : 'Present';
            $updatedSessions = attendance_sessions((int) $record['id']);
            update_attendance_record((int) $employee['id'], $date, [
                'status' => resolved_attendance_status($updatedRecord, $updatedSessions),
            ]);
            flash('success', 'Manual punch out ' . $slotIndex . ' of ' . $slotLimit . ' submitted.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;
        case 'employee_biometric':
            $employee = require_role('employee');
            $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
            if (is_week_off_for_user_date((int) $employee['id'], $date)) {
                flash('error', 'Week Off dates do not require attendance.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            $type = (string) ($_POST['stamp_type'] ?? 'in');
            update_attendance_record((int) $employee['id'], $date, [
                $type === 'out' ? 'biometric_out_time' : 'biometric_in_time' => now(),
                'status' => 'Present',
            ]);
            flash('success', 'Biometric ' . ($type === 'out' ? 'out' : 'in') . ' captured.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;

        case 'employee_leave':
            $employee = require_role('employee');
            $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
            if (is_week_off_for_user_date((int) $employee['id'], $date)) {
                flash('error', 'Week Off dates do not require attendance.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            update_attendance_record((int) $employee['id'], $date, [
                'status' => 'Leave',
                'leave_reason' => trim((string) ($_POST['leave_reason'] ?? '')),
            ]);
            flash('success', 'Leave request recorded.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;

        case 'logout':
            unset($_SESSION['user_id']);
            redirect_to('landing');
            break;
    }
}



