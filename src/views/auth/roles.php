<?php

declare(strict_types=1);

function auth_login_roles(): array
{
    return [
        'admin' => [
            'title' => 'Admin Login',
            'eyebrow' => 'Admin Access',
            'description' => 'Sign in to manage attendance, employees, rules, and reporting.',
        ],
        'employee' => [
            'label' => 'Employee',
            'title' => 'Employee Login',
            'eyebrow' => 'Employee Access',
            'description' => 'Use the credentials shared by your admin to access your attendance workspace.',
        ],
        'corporate_employee' => [
            'label' => 'Contractual Employee',
            'title' => 'Contractual Employee Login',
            'eyebrow' => 'Contractual Employee Access',
            'description' => 'Use your contractual employee registration details to access the employee workspace.',
        ],
        'external_vendor' => [
            'label' => 'External Vendor',
            'title' => 'External Vendor Login',
            'eyebrow' => 'Vendor Access',
            'description' => 'Use the credentials shared by your admin to access your vendor account.',
        ],
        'freelancer' => [
            'label' => 'Contractual Admin',
            'title' => 'Contractual Admin Login',
            'eyebrow' => 'Admin Access',
            'description' => 'Sign in to manage your contractual employees and attendance.',
        ],
        'super_admin' => [
            'label' => 'Super Admin',
            'title' => 'Super Admin Login',
            'eyebrow' => 'System Control',
            'description' => 'Sign in to manage companies, approvals, and system-wide settings.',
        ],
    ];
}


function auth_registration_roles(): array
{
    return [
        'admin' => [
            'label' => 'Admin',
            'eyebrow' => 'Admin Setup',
            'title' => 'Register Your Company',
            'description' => 'Register a management account for payroll, rules, attendance review, and employee operations.',
            'button' => 'Register Admin',
        ],
        'corporate_employee' => [
            'label' => 'Contractual Employee',
            'eyebrow' => 'Employee Setup',
            'title' => 'Register as Contractual',
            'description' => 'Self-register as a contractual employee to track your own attendance and view your payroll.',
            'button' => 'Register Employee',
        ],
    ];
}


