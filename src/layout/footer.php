<?php

declare(strict_types=1);

function render_footer(): void
{
    global $page;
    $user = current_user();
    $isAdminShell = $user && in_array($user['role'], ['admin', 'freelancer', 'external_vendor'], true);
    $isEmployeeShell = $user && in_array($user['role'], ['employee', 'corporate_employee'], true);
    $isSidebarShell = $isAdminShell || $isEmployeeShell;
    $currentPage = $page ?? ($_GET['page'] ?? 'landing');
    $showLandingLoginModal = !$user && in_array($currentPage, ['landing', 'register'], true);
    $landingAuthRole = in_array($_GET['auth'] ?? '', ['admin', 'employee', 'corporate_employee', 'external_vendor'], true) ? $_GET['auth'] : '';
    $employerName = '';
    if ($isEmployeeShell && !empty($user['admin_id'])) {
        $stmt = db()->prepare('SELECT name FROM users WHERE id = :id AND role = "admin"');
        $stmt->execute(['id' => (int) $user['admin_id']]);
        $employerName = (string) ($stmt->fetchColumn() ?: '');
    }
    ?>
        </main>
        <?php if ($isSidebarShell): ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($isAdminShell): ?>
        <?php render_admin_profile_settings_modal($user); ?>
        <?php render_admin_password_modal(); ?>
    <?php endif; ?>
    <?php if ($isEmployeeShell): ?>
        <?php render_employee_profile_settings_modal($user, $employerName); ?>
        <?php render_employee_password_modal(); ?>
    <?php endif; ?>
    <?php if ($showLandingLoginModal): ?>
        <div class="modal" id="landing-login-modal">
            <div class="modal-card landing-login-modal">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <div class="landing-login-head">
                    <span class="eyebrow">Choose Login</span>
                    <h2>Continue to your workspace</h2>
                    <p>Select the login flow that matches your role in V Traco.</p>
                </div>
                <div class="landing-login-grid">
                    <button class="landing-login-option" type="button" data-switch-modal-target="admin-login-modal">
                        <span class="landing-login-icon">A</span>
                        <strong>Admin Login</strong>
                        <span>Manage employees, attendance rules, payroll, and reports.</span>
                    </button>
                    <button class="landing-login-option" type="button" data-switch-modal-target="employee-login-modal">
                        <span class="landing-login-icon employee">E</span>
                        <strong>Employee Login</strong>
                        <span>Use the credentials sent to your email by your admin to sign in.</span>
                    </button>
                    <button class="landing-login-option" type="button" data-switch-modal-target="external_vendor-login-modal">
                        <span class="landing-login-icon">V</span>
                        <strong>External Vendor</strong>
                        <span>Use the credentials shared by your admin to sign in.</span>
                    </button>
                    <button class="landing-login-option" type="button" data-switch-modal-target="corporate_employee-login-modal">
                        <span class="landing-login-icon employee">C</span>
                        <strong>Contractual Employee</strong>
                        <span>Use your self-registered contractual employee account.</span>
                    </button>
                </div>
            </div>
        </div>
        <?php render_landing_auth_modal('admin', $landingAuthRole === 'admin'); ?>
        <?php render_landing_auth_modal('employee', $landingAuthRole === 'employee'); ?>
        <?php render_landing_auth_modal('external_vendor', $landingAuthRole === 'external_vendor'); ?>
        <?php render_landing_auth_modal('corporate_employee', $landingAuthRole === 'corporate_employee'); ?>
        <?php render_landing_register_form_modal('admin'); ?>
        <?php render_landing_register_form_modal('corporate_employee'); ?>
    <?php endif; ?>
    <div class="modal" id="attendance-modal">
        <div class="modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div class="modal-grid" id="modal-content"></div>
        </div>
    </div>
    <script>window.VTRACO_CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;</script>
    <script src="<?= h(asset_url('assets/js/app.js') . '?v=' . (string) filemtime(__DIR__ . '/../../assets/js/app.js')) ?>"></script>
    </body>
    </html>
    <?php
}

