<?php

declare(strict_types=1);

function handle_post_action(string $action): void
{
    verify_csrf_request();

    switch ($action) {
        case 'login':
            $role = can_login_role((string) ($_POST['role'] ?? 'admin')) ? (string) $_POST['role'] : 'admin';
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
                    if (!empty($reset['handled']) && empty($reset['rate_limited']) && !empty($reset['employee'])) {
                        audit_log('employee_password_reset_requested', [
                            'email' => $email,
                            'delivery' => !empty($reset['mail_result']['sent']) ? 'email' : 'mail_log',
                        ], (int) $reset['employee']['id'], ['role' => 'guest']);
                    } elseif (!empty($reset['rate_limited']) && !empty($reset['employee'])) {
                        audit_log('employee_password_reset_rate_limited', [
                            'email' => $email,
                        ], (int) $reset['employee']['id'], ['role' => 'guest']);
                    } else {
                        audit_log('employee_password_reset_unknown_email', [
                            'email' => $email,
                        ], null, ['role' => 'guest']);
                    }
                    flash('success', 'If that employee email exists, a temporary password has been sent or logged locally. For security, reset requests are rate-limited.');
                } catch (Throwable $exception) {
                    report_exception($exception, 'Employee self-service password reset failed.', ['email' => $email]);
                    flash('error', 'Unable to process the password reset request right now.');
                }

                if ($returnPage === 'landing') {
                    redirect_to('landing', ['auth' => $role]);
                }
                redirect_to('login', ['role' => $role]);
            }

            $stmt = db()->prepare('SELECT * FROM users WHERE role = :role AND email = :email ORDER BY id DESC');
            $stmt->execute([
                'role' => $role,
                'email' => $email,
            ]);
            $user = null;
            $password = (string) ($_POST['password'] ?? '');
            foreach ($stmt->fetchAll() as $candidate) {
                if (!password_verify($password, (string) ($candidate['password_hash'] ?? ''))) {
                    continue;
                }

                $user = $candidate;
                break;
            }

            if ($user) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                audit_log('login_success', [
                    'email' => $email,
                ], (int) $user['id'], $user);
                if (in_array(($user['role'] ?? ''), ['employee', 'corporate_employee'], true) && password_change_required($user)) {
                    flash('success', 'A temporary password is active on this account. Please change it from Profile Settings after signing in.');
                }
                redirect_to(home_page_for_user($user));
            }
            audit_log('login_failed', [
                'role' => $role,
                'email' => $email,
            ], null, ['role' => 'guest']);
            flash('error', ucfirst((string) $role) . ' login failed.');
            if ($returnPage === 'landing') {
                redirect_to('landing', ['auth' => $role]);
            }
            redirect_to('login', ['role' => $role]);
            break;

        case 'register_user':
        case 'register_admin':
            $role = $action === 'register_admin'
                ? 'admin'
                : trim((string) ($_POST['role'] ?? 'admin'));
            if (!can_self_register_role($role)) {
                flash('error', 'Choose a valid registration type.');
                redirect_to('register');
            }
            if (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '')) {
                flash('error', 'Passwords do not match.');
                redirect_to('register');
            }
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if ($name === '') {
                flash('error', 'Name is required.');
                redirect_to('register');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid email address.');
                redirect_to('register');
            }
            if (!password_meets_policy($password)) {
                flash('error', password_policy_message());
                redirect_to('register');
            }
            try {
                db()->prepare('INSERT INTO users (role, emp_id, name, email, phone, salary, password_hash, password_changed_at, created_at) VALUES (:role, NULL, :name, :email, :phone, 0, :password_hash, :password_changed_at, :created_at)')
                    ->execute([
                        'role' => $role,
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'password_changed_at' => now(),
                        'created_at' => now(),
                    ]);
                audit_log('user_registered', [
                    'email' => $email,
                    'role' => $role,
                ], (int) db()->lastInsertId(), ['role' => 'guest']);
                flash('success', user_role_label($role) . ' account created.');
                redirect_to('login', ['role' => $role]);
            } catch (Throwable $exception) {
                report_exception($exception, 'User registration failed.', ['email' => $email, 'role' => $role]);
                flash('error', user_role_label($role) . ' registration failed. Please try again.');
                redirect_to('register');
            }
            break;

        case 'employee_manual_next':
            require_roles(['admin', 'freelancer']);
            if (!filter_var((string) ($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Enter a valid employee email address.');
                redirect_to('admin_employees');
            }
            if (trim((string) ($_POST['name'] ?? '')) === '') {
                flash('error', 'Employee name is required.');
                redirect_to('admin_employees');
            }
            if (trim((string) ($_POST['phone'] ?? '')) === '') {
                flash('error', 'Employee phone number is required.');
                redirect_to('admin_employees');
            }
            if (!is_numeric((string) ($_POST['salary'] ?? '')) || (float) ($_POST['salary'] ?? 0) < 0) {
                flash('error', 'Employee salary must be zero or greater.');
                redirect_to('admin_employees');
            }
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
            require_roles(['admin', 'freelancer']);

            $pending = $_SESSION['pending_employee'] ?? null;
            if (!$pending) {
                flash('error', 'No pending employee found.');
                redirect_to('admin_employees');
            }
            try {
                $rules = normalize_rules_from_input($_POST);
                $createdEmployee = insert_employee($pending, $rules);
                unset($_SESSION['pending_employee']);
                audit_log('employee_created', [
                    'email' => (string) $createdEmployee['employee']['email'],
                    'delivery' => !empty($createdEmployee['mail_result']['sent']) ? 'email' : 'mail_log',
                ], (int) $createdEmployee['employee']['id']);
                flash('success', employee_credentials_delivery_message($createdEmployee['employee'], $createdEmployee['mail_result'], $createdEmployee['password']));
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee creation failed.', ['email' => $pending['email'] ?? '']);
                flash('error', $exception->getMessage() ?: 'Unable to add employee. Email or Emp ID may already exist.');
            }
            redirect_to('admin_employees');
            break;

        case 'employee_csv_upload':
            require_roles(['admin', 'freelancer']);
            try {
                validate_employee_csv_upload($_FILES['csv_file'] ?? []);
                $_SESSION['pending_csv_import'] = parse_employee_csv((string) $_FILES['csv_file']['tmp_name']);
                flash('success', 'CSV uploaded. Assign rules to continue.');
                redirect_to('admin_employees', ['stage' => 'csv_rules']);
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee CSV upload failed.', [
                    'filename' => (string) (($_FILES['csv_file']['name'] ?? '') ?: ''),
                ]);
                flash('error', $exception->getMessage());
                redirect_to('admin_employees');
            }
            break;

        case 'employee_csv_submit':
            require_roles(['admin', 'freelancer']);

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
            audit_log('employee_csv_import_completed', [
                'created' => $created,
                'skipped' => $skipped,
                'emails_sent' => $emailsSent,
                'emails_logged' => $emailsLogged,
            ]);
            flash('success', $message);
            redirect_to('admin_employees');
            break;

        case 'employee_update':
            $admin = require_roles(['admin', 'freelancer']);

            $employeeId = (int) ($_POST['user_id'] ?? 0);
            if (!employee_by_id($employeeId)) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            try {
                $email = trim((string) ($_POST['email'] ?? ''));
                $name = trim((string) ($_POST['name'] ?? ''));
                $phone = trim((string) ($_POST['phone'] ?? ''));
                $salary = (float) ($_POST['salary'] ?? 0);
                if ($name === '') {
                    throw new RuntimeException('Employee name is required.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a valid employee email address.');
                }
                if ($phone === '') {
                    throw new RuntimeException('Employee phone number is required.');
                }
                if (!is_numeric((string) ($_POST['salary'] ?? '')) || $salary < 0) {
                    throw new RuntimeException('Employee salary must be zero or greater.');
                }
                $role = current_manager_target_role();
                if (role_requires_unique_email($role) && role_email_exists($role, $email, $employeeId)) {
                    throw new RuntimeException('This employee email is already assigned.');
                }
                db()->prepare('UPDATE users SET emp_id = :emp_id, name = :name, email = :email, phone = :phone, shift = :shift, salary = :salary WHERE id = :id AND role = :role AND admin_id = :admin_id')
                    ->execute([
                        'id' => $employeeId,
                        'role' => $role,
                        'admin_id' => (int) $admin['id'],
                        'emp_id' => trim((string) ($_POST['emp_id'] ?? '')),
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'shift' => trim((string) ($_POST['shift'] ?? '')),
                        'salary' => $salary,
                    ]);
                audit_log('employee_updated', [
                    'email' => $email,
                ], $employeeId);
                flash('success', 'Employee updated.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee update failed.', ['employee_id' => $employeeId]);
                flash('error', $exception->getMessage() ?: 'Unable to update employee.');
            }
            redirect_to('admin_employees');
            break;

        case 'employee_reset_password':
            require_roles(['admin', 'freelancer']);

            $employeeId = (int) ($_POST['user_id'] ?? 0);
            if (!employee_by_id($employeeId)) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            try {
                $reset = reset_employee_password($employeeId);
                audit_log('employee_password_reset_admin', [
                    'delivery' => !empty($reset['mail_result']['sent']) ? 'email' : 'mail_log',
                ], $employeeId);
                flash('success', employee_credentials_delivery_message($reset['employee'], $reset['mail_result'], $reset['password'], 'reset'));
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin employee password reset failed.', ['employee_id' => $employeeId]);
                flash('error', 'Unable to reset the employee password.');
            }
            redirect_to('admin_employees');
            break;
        case 'employee_delete':
            $admin = require_roles(['admin', 'freelancer']);

            $employeeId = (int) ($_POST['user_id'] ?? 0);
            if (!employee_by_id($employeeId)) {
                flash('error', 'Employee not found for this administrator.');
                redirect_to('admin_employees');
            }
            $role = current_manager_target_role();
            db()->prepare('DELETE FROM users WHERE id = :id AND role = :role AND admin_id = :admin_id')
                ->execute([
                    'id' => $employeeId,
                    'role' => $role,
                    'admin_id' => (int) $admin['id'],
                ]);
            audit_log('employee_deleted', [], $employeeId);
            flash('success', 'Employee deleted successfully.');
            redirect_to('admin_employees');
            break;

        case 'project_save':
            require_role('admin');

            $projectId = (int) ($_POST['project_id'] ?? 0);
            $_SESSION['project_form'] = array_merge(project_form_defaults(), [
                'id' => $projectId,
                'project_name' => trim((string) ($_POST['project_name'] ?? '')),
                'college_name' => trim((string) ($_POST['college_name'] ?? '')),
                'location' => trim((string) ($_POST['location'] ?? '')),
                'total_days' => trim((string) ($_POST['total_days'] ?? '')),
                'session_type' => trim((string) ($_POST['session_type'] ?? 'FULL_DAY')),
                'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            ]);

            try {
                $savedProjectId = save_project($_POST, $projectId > 0 ? $projectId : null);
                $savedProject = project_by_id($savedProjectId);
                unset($_SESSION['project_form']);
                audit_log($projectId > 0 ? 'project_updated' : 'project_created', [
                    'project_name' => (string) ($savedProject['project_name'] ?? ''),
                    'college_name' => (string) ($savedProject['college_name'] ?? ''),
                    'is_active' => (int) ($savedProject['is_active'] ?? 0),
                ], null);
                flash('success', $projectId > 0 ? 'Project updated successfully.' : 'Project added successfully.');
                redirect_to('admin_projects');
            } catch (Throwable $exception) {
                report_exception($exception, 'Project save failed.', ['project_id' => $projectId]);
                flash('error', $exception->getMessage() ?: 'Unable to save the project.');
                if ($projectId > 0) {
                    redirect_to('admin_projects', ['edit' => $projectId]);
                }
                redirect_to('admin_projects', ['stage' => 'create']);
            }
            break;

        case 'project_delete':
            require_role('admin');

            $projectId = (int) ($_POST['project_id'] ?? 0);
            try {
                $project = project_by_id($projectId);
                delete_project($projectId);
                audit_log('project_deleted', [
                    'project_name' => (string) ($project['project_name'] ?? ''),
                ], null);
                flash('success', 'Project deleted successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Project delete failed.', ['project_id' => $projectId]);
                flash('error', $exception->getMessage() ?: 'Unable to delete the project.');
            }
            redirect_to('admin_projects');
            break;

        case 'project_toggle_active':
            require_role('admin');

            $projectId = (int) ($_POST['project_id'] ?? 0);
            try {
                $project = toggle_project_active($projectId);
                audit_log('project_toggled', [
                    'project_name' => (string) ($project['project_name'] ?? ''),
                    'is_active' => (int) ($project['is_active'] ?? 0),
                ], null);
                flash('success', !empty($project['is_active']) ? 'Project activated successfully.' : 'Project deactivated successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Project status toggle failed.', ['project_id' => $projectId]);
                flash('error', $exception->getMessage() ?: 'Unable to update the project status.');
            }
            redirect_to('admin_projects');
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
            audit_log('employee_rules_updated_bulk', [
                'employee_count' => $updated,
                'rules' => $rules,
            ]);
            flash($updated > 0 ? 'success' : 'error', $updated > 0 ? 'Rules applied successfully.' : 'No employees were available for this administrator.');
            redirect_to('admin_rules');
            break;

        case 'admin_attendance_csv_upload':
            require_role('admin');
            try {
                validate_attendance_report_upload($_FILES['attendance_csv'] ?? []);
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
                audit_log('attendance_import_completed', [
                    'filename' => (string) ($_FILES['attendance_csv']['name'] ?? ''),
                    'date' => $result['date'] ?? null,
                    'imported' => (int) $result['imported'],
                    'created' => (int) ($result['created'] ?? 0),
                    'skipped' => (int) ($result['skipped'] ?? 0),
                    'unmatched' => $result['unmatched'] ?? [],
                ]);
                flash('success', $message);
            } catch (Throwable $exception) {
                report_exception($exception, 'Attendance import failed.', [
                    'filename' => (string) ($_FILES['attendance_csv']['name'] ?? ''),
                ]);
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
            $status = (string) ($_POST['status'] ?? 'Absent');
            update_attendance_record((int) $employee['id'], (string) ($_POST['attend_date'] ?? ''), [
                'status' => $status,
                'admin_override_status' => $status,
            ]);
            audit_log('attendance_status_overridden', [
                'attend_date' => (string) ($_POST['attend_date'] ?? ''),
                'status' => $status,
            ], (int) $employee['id']);
            flash('success', 'Attendance status updated.');
            redirect_to('admin_attendance', [
                'employee_id' => (int) $employee['id'],
                'month' => substr((string) ($_POST['attend_date'] ?? date('Y-m-d')), 0, 7),
            ]);
            break;

        case 'admin_profile_update':
            $admin = require_roles(['admin', 'freelancer']);

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
                db()->prepare('UPDATE users SET name = :name, email = :email, phone = :phone WHERE id = :id AND role = :role')
                    ->execute([
                        'id' => (int) $admin['id'],
                        'role' => $admin['role'],
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                    ]);
                audit_log('admin_profile_updated', [
                    'email' => $email,
                ], (int) $admin['id']);
                flash('success', 'Profile updated successfully.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Admin profile update failed.', ['admin_id' => (int) $admin['id']]);
                flash('error', 'Unable to update profile.');
            }
            redirect_to($returnPage);
            break;

        case 'admin_change_password':
            $admin = require_roles(['admin', 'freelancer']);

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
            if (!password_meets_policy($newPassword)) {
                flash('error', password_policy_message());
                redirect_to($returnPage);
            }
            if ($newPassword !== $confirmPassword) {
                flash('error', 'New password and confirm password do not match.');
                redirect_to($returnPage);
            }

            db()->prepare('UPDATE users SET password_hash = :password_hash, password_changed_at = :password_changed_at WHERE id = :id AND role = :role')
                ->execute([
                    'id' => (int) $admin['id'],
                    'role' => $admin['role'],
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'password_changed_at' => now(),
                ]);
            audit_log('admin_password_changed', [], (int) $admin['id']);
            flash('success', 'Password updated successfully. Please use the new password the next time you sign in.');
            redirect_to($returnPage);
            break;

        case 'employee_change_password':
            $employee = require_roles(['employee', 'corporate_employee']);

            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if (!password_verify($currentPassword, (string) $employee['password_hash'])) {
                flash('error', 'Current password is incorrect.');
                redirect_to('employee_attendance');
            }
            if (!password_meets_policy($newPassword)) {
                flash('error', password_policy_message());
                redirect_to('employee_attendance');
            }
            if ($newPassword !== $confirmPassword) {
                flash('error', 'New password and confirm password do not match.');
                redirect_to('employee_attendance');
            }

            db()->prepare('UPDATE users SET password_hash = :password_hash, force_password_change = 0, password_changed_at = :password_changed_at WHERE id = :id AND role = :role')
                ->execute([
                    'id' => (int) $employee['id'],
                    'role' => $employee['role'],
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                    'password_changed_at' => now(),
                ]);
            audit_log('employee_password_changed', [], (int) $employee['id']);
            flash('success', 'Password updated successfully. Please use the new password the next time you sign in.');
            redirect_to('employee_attendance');
            break;
        case 'employee_manual_in':
        case 'employee_punch_in':
            $employee = require_roles(['employee', 'corporate_employee']);

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
                audit_log('employee_manual_punch_in_submitted', [
                    'attend_date' => $date,
                    'slot_index' => $slotIndex,
                ], (int) $employee['id']);
                flash('success', 'Manual punch in ' . $slotIndex . ' submitted.');
            } catch (Throwable $exception) {
                report_exception($exception, 'Employee manual punch in failed.', [
                    'employee_id' => (int) $employee['id'],
                    'attend_date' => (string) ($_POST['attend_date'] ?? ''),
                ]);
                flash('error', $exception->getMessage());
            }
            redirect_to('employee_attendance', ['month' => substr((string) ($_POST['attend_date'] ?? date('Y-m-d')), 0, 7)]);
            break;
        case 'employee_manual_out':
            $employee = require_roles(['employee', 'corporate_employee']);

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
            audit_log('employee_manual_punch_out_submitted', [
                'attend_date' => $date,
                'slot_index' => $slotIndex,
                'day_portion' => $dayPortion,
            ], (int) $employee['id']);
            flash('success', 'Manual punch out ' . $slotIndex . ' of ' . $slotLimit . ' submitted.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;
        case 'employee_biometric':
            $employee = require_roles(['employee', 'corporate_employee']);

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
            audit_log('employee_biometric_marked', [
                'attend_date' => $date,
                'stamp_type' => $type,
            ], (int) $employee['id']);
            flash('success', 'Biometric ' . ($type === 'out' ? 'out' : 'in') . ' captured.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;

        case 'employee_leave':
            $employee = require_roles(['employee', 'corporate_employee']);

            $date = (string) ($_POST['attend_date'] ?? date('Y-m-d'));
            if (is_week_off_for_user_date((int) $employee['id'], $date)) {
                flash('error', 'Week Off dates do not require attendance.');
                redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            }
            update_attendance_record((int) $employee['id'], $date, [
                'status' => 'Leave',
                'leave_reason' => trim((string) ($_POST['leave_reason'] ?? '')),
            ]);
            audit_log('employee_leave_requested', [
                'attend_date' => $date,
            ], (int) $employee['id']);
            flash('success', 'Leave request recorded.');
            redirect_to('employee_attendance', ['month' => substr($date, 0, 7)]);
            break;

        case 'filter_reports':
            require_role('admin');
            // This case doesn't redirect, it just lets the page render with POST data
            break;

        case 'export_reports_csv':
            require_role('admin');
            $filters = [
                'employee_ids' => $_POST['employee_ids'] ?? [],
                'project_ids' => $_POST['project_ids'] ?? [],
                'from_date' => $_POST['from_date'] ?? '',
                'to_date' => $_POST['to_date'] ?? '',
            ];
            $data = get_attendance_report_data($filters);
            export_report_csv($data);
            break;

        case 'export_reports_pdf':
            require_role('admin');
            $filters = [
                'employee_ids' => $_POST['employee_ids'] ?? [],
                'project_ids' => $_POST['project_ids'] ?? [],
                'from_date' => $_POST['from_date'] ?? '',
                'to_date' => $_POST['to_date'] ?? '',
            ];
            $data = get_attendance_report_data($filters);
            export_report_pdf($data);
            break;

        case 'logout':
            $user = current_user();
            if ($user) {
                audit_log('logout', [], (int) $user['id'], $user);
            }
            unset($_SESSION['user_id']);
            session_regenerate_id(true);
            redirect_to('landing');
            break;
    }
}



