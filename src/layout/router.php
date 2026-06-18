<?php

declare(strict_types=1);

function render_page(string $page): void
{
    $user = current_user();
    if ($user && in_array((string) ($user['role'] ?? ''), ['employee', 'corporate_employee'], true)) {
        if (employee_profile_requires_completion($user) && !in_array($page, ['employee_profile', 'employee_profile_completion'], true)) {
            redirect_to('employee_profile_completion');
        }
        if (!employee_profile_requires_completion($user) && !employee_profile_is_verified($user) && !in_array($page, ['employee_profile', 'employee_profile_completion'], true)) {
            redirect_to('employee_profile');
        }
    } elseif ($user && employee_profile_requires_completion($user) && !in_array($page, ['employee_profile', 'employee_profile_completion'], true)) {
        redirect_to('employee_profile_completion');
    }

    switch ($page) {
        case 'login':
            if (($_GET['role'] ?? '') === 'corporate_employee') {
                $popupParams = ['auth' => 'corporate_employee'];
                foreach (['reset', 'email'] as $key) {
                    if (isset($_GET[$key]) && (string) $_GET[$key] !== '') {
                        $popupParams[$key] = (string) $_GET[$key];
                    }
                }
                redirect_to('landing', $popupParams);
            }
            render_login();
            break;
        case 'register':
            render_register();
            break;
        case 'admin_dashboard':
            render_admin_dashboard();
            break;
        case 'corporate_dashboard':
            render_corporate_dashboard();
            break;
        case 'vendor_dashboard':
            render_vendor_dashboard();
            break;
        case 'vendor_payments':
            render_vendor_payments();
            break;
        case 'super_admin_dashboard':
            render_super_admin_dashboard();
            break;
        case 'admin_vendors':
            render_admin_vendors();
            break;
        case 'admin_employees':
            render_admin_employees();
            break;
        case 'admin_projects':
            render_admin_projects();
            break;
        case 'admin_rules':
            render_admin_rules();
            break;
        case 'admin_shift':
            render_admin_shift();
            break;
        case 'admin_attendance':
        case 'admin_employee_log':
            render_admin_attendance();
            break;
        case 'admin_profile_settings':
            render_admin_profile_settings();
            break;
        case 'admin_reports':
            render_admin_reports();
            break;
        case 'admin_accounts':
            render_admin_accounts();
            break;

        case 'notifications':
            render_notifications();
            break;

        case 'employee_attendance':
        case 'employee_log':
            render_employee_attendance();
            break;
        case 'employee_reimbursements':
            render_employee_reimbursements();
            break;
        case 'employee_projects':
            render_employee_projects();
            break;
        case 'employee_payments':
            render_employee_payments();
            break;
        case 'employee_profile':
            render_employee_profile();
            break;
        case 'employee_onboarding_reviews':
            render_employee_onboarding_reviews();
            break;
        case 'employee_profile_completion':
            render_employee_profile_completion();
            break;
        case 'member_dashboard':
            render_member_dashboard();
            break;
        default:
            render_landing();
            break;
    }
}


