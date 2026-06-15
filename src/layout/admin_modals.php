<?php

declare(strict_types=1);

function render_admin_profile_settings_modal(array $admin): void
{
    $returnPage = (string) ($_GET['page'] ?? home_page_for_user($admin));
    $allowedPrefixes = ['admin_', 'vendor_', 'corporate_', 'member_'];
    $isValid = false;
    foreach ($allowedPrefixes as $prefix) {
        if (str_starts_with($returnPage, $prefix)) {
            $isValid = true;
            break;
        }
    }
    if (!$isValid || $returnPage === 'admin_profile_settings') {
        $returnPage = home_page_for_user($admin);
    }
    $memberSince = !empty($admin['created_at']) ? date('d M Y', strtotime((string) $admin['created_at'])) : 'Recently added';
    $isVendorProfile = ($admin['role'] ?? '') === 'external_vendor';
    $roleLabel = user_role_label((string) ($admin['role'] ?? 'admin'));
    $companyName = trim((string) ($admin['company_name'] ?? ''));
    if ($companyName === '') {
        $companyName = (string) ($admin['name'] ?? '');
    }
    $representativeName = trim((string) ($admin['representative_name'] ?? ''));
    if ($representativeName === '') {
        $representativeName = (string) ($admin['name'] ?? '');
    }
    $companyAddress = trim((string) ($admin['company_address'] ?? ''));
    $companyEmail = trim((string) ($admin['company_email'] ?? ''));
    $companyPhone = trim((string) ($admin['company_phone'] ?? ''));
    $designation = trim((string) ($admin['designation'] ?? ''));
    $personalEmail = trim((string) ($admin['personal_email'] ?? ''));
    if ($personalEmail === '') {
        $personalEmail = (string) ($admin['email'] ?? '');
    }
    $personalPhone = trim((string) ($admin['personal_phone'] ?? ''));
    if ($personalPhone === '') {
        $personalPhone = (string) ($admin['phone'] ?? '');
    }
    $vendorDocumentNames = [
        'bank_proof' => trim((string) ($admin['bank_proof_name'] ?? '')),
        'company_logo' => trim((string) ($admin['company_logo_name'] ?? '')),
        'profile_photo' => trim((string) ($admin['profile_photo_name'] ?? '')),
    ];
    $showBiometricIntegration = ($admin['role'] ?? '') === 'admin';
    $biometricIntegration = $showBiometricIntegration ? biometric_integration_for_admin((int) $admin['id']) : null;
    $biometricBaseUrl = '';
    $biometricCorporateId = '';
    $biometricUsername = '';
    $biometricEnabled = false;
    $biometricLastSync = !empty($biometricIntegration['last_sync_at'])
        ? date('d M Y, h:i A', strtotime((string) $biometricIntegration['last_sync_at']))
        : 'Not synced yet';
    $biometricLastTest = !empty($biometricIntegration['last_test_at'])
        ? date('d M Y, h:i A', strtotime((string) $biometricIntegration['last_test_at']))
        : 'Not tested yet';
    ?>
    <div class="modal" id="admin-profile-settings-modal">
        <div class="modal-card profile-settings-modal-card<?= $isVendorProfile ? ' vendor-profile-settings-card' : '' ?>">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            
            <?php if ($isVendorProfile): ?>
                <aside class="vendor-profile-settings-sidebar">
                    <span class="eyebrow">Profile Settings</span>
                    <h2><?= h($companyName) ?></h2>
                    <div class="profile-settings-grid">
                        <div class="list-item">
                            <strong>Your Name</strong>
                            <span><?= h($representativeName) ?></span>
                        </div>
                        <div class="list-item">
                            <strong>Designation</strong>
                            <span><?= h($designation !== '' ? $designation : 'Not added') ?></span>
                        </div>
                        <div class="list-item">
                            <strong>Company Mail</strong>
                            <span><?= h($companyEmail !== '' ? $companyEmail : (string) $admin['email']) ?></span>
                        </div>
                        <div class="list-item">
                            <strong>Company Phone</strong>
                            <span><?= h($companyPhone !== '' ? $companyPhone : 'Not added') ?></span>
                        </div>
                        <div class="list-item">
                            <strong>Member Since</strong>
                            <span><?= h($memberSince) ?></span>
                        </div>
                        <div class="list-item">
                            <strong>Role</strong>
                            <span><?= h($roleLabel) ?></span>
                        </div>
                    </div>
                    <p class="hint">Upload company proof, logo, and your photo from the form.</p>
                </aside>

                <main class="vendor-profile-settings-main">
                    <form method="post" enctype="multipart/form-data" class="vendor-profile-settings-form" data-validate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="admin_profile_update">
                        <input type="hidden" name="return_page" value="<?= h($returnPage) ?>">
                        
                        <div class="form-section-card">
                            <span class="eyebrow">Company Detail</span>
                            <div class="field">
                                <label>Company Name</label>
                                <div class="field-row"><input type="text" name="company_name" value="<?= h($companyName) ?>" required></div>
                                <small class="field-error"><span>!</span>Company name is required.</small>
                            </div>
                            <div class="field">
                                <label>Company Mail</label>
                                <div class="field-row"><input type="email" name="company_email" value="<?= h($companyEmail) ?>" required></div>
                                <small class="field-error"><span>!</span>Valid company mail is required.</small>
                            </div>
                            <div class="field">
                                <label>Company Phone Number</label>
                                <div class="field-row"><input type="text" name="company_phone" value="<?= h($companyPhone) ?>" required></div>
                                <small class="field-error"><span>!</span>Company phone number is required.</small>
                            </div>
                            <div class="field profile-settings-wide">
                                <label>Company Address</label>
                                <div class="field-row"><input type="text" name="company_address" value="<?= h($companyAddress) ?>" required></div>
                                <small class="field-error"><span>!</span>Company address is required.</small>
                            </div>
                        </div>

                        <div class="form-section-card">
                            <span class="eyebrow">Your Detail</span>
                            <div class="field">
                                <label>Your Name</label>
                                <div class="field-row"><input type="text" name="representative_name" value="<?= h($representativeName) ?>" required></div>
                                <small class="field-error"><span>!</span>Your name is required.</small>
                            </div>
                            <div class="field">
                                <label>Your Designation</label>
                                <div class="field-row"><input type="text" name="designation" value="<?= h($designation) ?>" required></div>
                                <small class="field-error"><span>!</span>Your designation is required.</small>
                            </div>
                            <div class="field">
                                <label>Personal Number</label>
                                <div class="field-row"><input type="text" name="personal_phone" value="<?= h($personalPhone) ?>" required></div>
                                <small class="field-error"><span>!</span>Personal number is required.</small>
                            </div>
                            <div class="field">
                                <label>Personal Mail</label>
                                <div class="field-row"><input type="email" name="personal_email" value="<?= h($personalEmail) ?>" required></div>
                                <small class="field-error"><span>!</span>Valid personal mail is required.</small>
                            </div>
                        </div>

                        <div class="form-section-card">
                            <span class="eyebrow">Tax Detail</span>
                            <div class="field">
                                <label>GST (if have)</label>
                                <div class="field-row"><input type="text" name="gst_no" value="<?= h((string) ($admin['gst_no'] ?? '')) ?>"></div>
                            </div>
                        </div>

                        <div class="form-section-card">
                            <span class="eyebrow">Upload</span>
                            <div class="field">
                                <label>Company Bank Proof</label>
                                <div class="field-row"><input type="file" name="bank_proof" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" <?= $vendorDocumentNames['bank_proof'] === '' ? 'required' : '' ?>></div>
                                <small class="hint"><?= h($vendorDocumentNames['bank_proof'] !== '' ? $vendorDocumentNames['bank_proof'] : 'PDF, image, DOC, or DOCX up to 5 MB.') ?></small>
                            </div>
                            <div class="field">
                                <label>Logo</label>
                                <div class="field-row"><input type="file" name="company_logo" accept="image/*" <?= $vendorDocumentNames['company_logo'] === '' ? 'required' : '' ?>></div>
                                <small class="hint"><?= h($vendorDocumentNames['company_logo'] !== '' ? $vendorDocumentNames['company_logo'] : 'JPG, PNG, or WEBP up to 5 MB.') ?></small>
                            </div>
                            <div class="field">
                                <label>Your Photo</label>
                                <div class="field-row"><input type="file" name="profile_photo" accept="image/*" data-profile-photo-input <?= $vendorDocumentNames['profile_photo'] === '' ? 'required' : '' ?>></div>
                                <small class="hint" data-profile-photo-status><?= h($vendorDocumentNames['profile_photo'] !== '' ? $vendorDocumentNames['profile_photo'] : 'JPG, PNG, or WEBP up to 5 MB.') ?></small>
                            </div>
                        </div>

                        <div class="profile-settings-actions">
                            <button class="button ghost" type="button" data-switch-modal-target="admin-password-modal">Change Password</button>
                            <button class="button solid" type="submit">Save Changes</button>
                        </div>
                    </form>
                </main>

            <?php else: ?>
                <span class="eyebrow">Profile Settings</span>
                <h2><?= h((string) $admin['name']) ?></h2>
                <div class="profile-settings-grid">
                    <div class="list-item">
                        <strong>Email</strong>
                        <span><?= h((string) $admin['email']) ?></span>
                    </div>
                    <div class="list-item">
                        <strong>Phone</strong>
                        <span><?= h((string) (($admin['phone'] ?? '') ?: 'Not added')) ?></span>
                    </div>
                    <div class="list-item">
                        <strong>Member Since</strong>
                        <span><?= h($memberSince) ?></span>
                    </div>
                    <div class="list-item">
                        <strong>Role</strong>
                        <span><?= h($roleLabel) ?></span>
                    </div>
                </div>
                <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="admin_profile_update">
                    <input type="hidden" name="return_page" value="<?= h($returnPage) ?>">
                    <label class="profile-settings-field">
                        <span>Choose Profile Photo</span>
                        <input type="file" name="profile_photo" accept="image/*" data-profile-photo-input>
                    </label>
                    <p class="hint" data-profile-photo-status><?= !empty($admin['profile_photo_name']) ? h((string) $admin['profile_photo_name']) : 'JPG, PNG, or WEBP up to 5 MB.' ?></p>
                    <div class="field">
                        <label>Name</label>
                        <div class="field-row"><input type="text" name="name" value="<?= h((string) $admin['name']) ?>" required></div>
                        <small class="field-error"><span>!</span>Name is required.</small>
                    </div>
                    <div class="field">
                        <label>Email</label>
                        <div class="field-row"><input type="email" name="email" value="<?= h((string) $admin['email']) ?>" required></div>
                        <small class="field-error"><span>!</span>Valid email required.</small>
                    </div>
                    <div class="field">
                        <label>Phone Number</label>
                        <div class="field-row"><input type="text" name="phone" value="<?= h((string) ($admin['phone'] ?? '')) ?>"></div>
                    </div>
                    <div class="profile-settings-actions">
                        <button class="button ghost" type="button" data-switch-modal-target="admin-password-modal">Change Password</button>
                        <button class="button solid" type="submit">Save Profile</button>
                    </div>
                </form>
                <?php if ($showBiometricIntegration): ?>
                    <hr class="soft-divider">
                    <button class="button outline profile-settings-toggle" type="button" data-switch-modal-target="admin-biometric-integration-modal">Biometric Integration</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($showBiometricIntegration): ?>
    <div class="modal" id="admin-biometric-integration-modal">
        <div class="modal-card" style="max-width:720px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Biometric Integration</span>
            <h2>eTime Office</h2>
            <p>Connect this admin account to eTime Office so Track Attendance can mark biometric IN/OUT records automatically.</p>
            <form method="post" class="stack-form" data-validate autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_biometric_integration_save">
                <input type="hidden" name="return_page" value="<?= h($returnPage) ?>">
                <label class="checkbox-line">
                    <input type="checkbox" name="is_enabled" value="1">
                    <span>Enable automatic eTime Office attendance sync</span>
                </label>
                <div class="reports-filter-grid">
                    <div class="field">
                        <label>API Base URL</label>
                        <div class="field-row"><input type="url" name="base_url" value="<?= h($biometricBaseUrl) ?>" autocomplete="off" required></div>
                        <small class="field-error"><span>!</span>Base URL is required.</small>
                    </div>
                    <div class="field">
                        <label>Corporate ID</label>
                        <div class="field-row"><input type="text" name="corporate_id" value="<?= h($biometricCorporateId) ?>" autocomplete="off" required></div>
                        <small class="field-error"><span>!</span>Corporate ID is required.</small>
                    </div>
                    <div class="field">
                        <label>Username</label>
                        <div class="field-row"><input type="text" name="username" value="<?= h($biometricUsername) ?>" autocomplete="off" required></div>
                        <small class="field-error"><span>!</span>Username is required.</small>
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <div class="field-row"><input type="password" name="password" autocomplete="new-password" placeholder="Enter eTime password" required></div>
                        <small class="field-error"><span>!</span>Password is required.</small>
                    </div>
                </div>
                <div class="profile-settings-grid">
                    <div class="list-item">
                        <strong>Last Sync</strong>
                        <span><?= h($biometricLastSync) ?></span>
                    </div>
                    <div class="list-item">
                        <strong>Last Test</strong>
                        <span><?= h($biometricLastTest) ?></span>
                    </div>
                </div>
                <div class="profile-settings-actions">
                    <button class="button ghost" type="button" data-switch-modal-target="admin-profile-settings-modal">Back to Profile</button>
                    <button class="button outline" type="submit" name="integration_mode" value="test">Test Connection</button>
                    <button class="button solid" type="submit" name="integration_mode" value="save">Save Integration</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php
}


function render_admin_password_modal(): void
{
    $returnPage = (string) ($_GET['page'] ?? 'admin_dashboard');
    if (!str_starts_with($returnPage, 'admin_') || $returnPage === 'admin_profile_settings') {
        $returnPage = 'admin_dashboard';
    }
    ?>
    <div class="modal" id="admin-password-modal">
        <div class="modal-card" style="max-width:520px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Admin Account</span>
            <h2>Change Password</h2>
            <p>Update your sign-in password directly from the sidebar.</p>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_change_password">
                <input type="hidden" name="return_page" value="<?= h($returnPage) ?>">
                <div class="field">
                    <label>Current Password</label>
                    <div class="field-row">
                        <input id="admin-current-password" type="password" name="current_password" placeholder="Enter current password" required>
                        <button class="password-toggle" type="button" data-password-toggle="admin-current-password">Show</button>
                    </div>
                    <small class="field-error"><span>!</span>Current password is required.</small>
                </div>
                <div class="field">
                    <label>New Password</label>
                    <div class="field-row">
                        <input id="admin-new-password" type="password" name="new_password" minlength="8" placeholder="Minimum 8 characters with letters and numbers" required>
                        <button class="password-toggle" type="button" data-password-toggle="admin-new-password">Show</button>
                    </div>
                    <small class="field-error"><span>!</span>Password must be at least 8 characters and include a letter and number.</small>
                </div>
                <div class="field">
                    <label>Confirm Password</label>
                    <div class="field-row">
                        <input id="admin-confirm-password" type="password" name="confirm_password" minlength="8" placeholder="Repeat new password" required>
                        <button class="password-toggle" type="button" data-password-toggle="admin-confirm-password">Show</button>
                    </div>
                    <small class="field-error"><span>!</span>Please confirm the new password.</small>
                </div>
                <div class="profile-settings-actions">
                    <button class="button ghost" type="button" data-switch-modal-target="admin-profile-settings-modal">Back to Profile</button>
                    <button class="button solid" type="submit">Update Password</button>
                </div>
            </form>
        </div>
    </div>
    <?php
}


