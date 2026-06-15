<?php

declare(strict_types=1);

function render_employee_profile_settings_modal(array $employee, string $employerName): void
{
    $employerDisplay = $employerName !== '' ? $employerName : 'Not assigned yet';
    if ($employerName !== '' && strcasecmp($employerName, (string) $employee['name']) === 0) {
        $employerDisplay .= ' (Admin)';
    }
    $status = (string) ($employee['profile_status'] ?? 'incomplete');
    $isContractualEmployee = (string) ($employee['role'] ?? '') === 'corporate_employee'
        || (string) ($employee['employee_type'] ?? '') === 'corporate';
    $showOfferLetterForm = false;
    $statusCopy = [
        'verified' => 'Verified',
        'pending' => 'Under Review',
        'rejected' => 'Needs Update',
        'incomplete' => 'Incomplete',
    ];
    $profilePhoto = !empty($employee['profile_photo_path'])
        ? public_file_path((string) $employee['profile_photo_path'])
        : '';
    $documents = [
        'aadhaar_card' => 'Aadhaar Card',
        'pan_card' => 'PAN Card',
        'profile_photo' => 'Profile Photo',
        'qualification_certificate' => 'Qualification Certificate',
        'bank_proof' => 'Bank Proof',
        'resume' => 'Resume',
    ];
    ?>
    <div class="modal" id="employee-profile-settings-modal">
        <div class="modal-card profile-settings-modal-card employee-profile-settings-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div class="employee-profile-card employee-profile-settings-summary">
                <div class="employee-profile-media">
                    <?php if ($profilePhoto !== ''): ?>
                        <img class="employee-profile-photo" src="<?= h($profilePhoto) ?>" alt="<?= h((string) $employee['name']) ?> profile photo" onerror="this.classList.add('hidden');this.nextElementSibling.classList.remove('hidden');">
                        <div class="employee-profile-fallback hidden"><?= h(user_initials((string) $employee['name'])) ?></div>
                    <?php else: ?>
                        <div class="employee-profile-fallback"><?= h(user_initials((string) $employee['name'])) ?></div>
                    <?php endif; ?>
                </div>
                <div class="employee-profile-copy">
                    <div>
                        <span class="eyebrow">Profile Settings</span>
                        <h2><?= h((string) $employee['name']) ?></h2>
                    </div>
                    <div class="employee-profile-meta">
                        <div class="list-item"><strong>Employee ID</strong><span><?= h((string) ($employee['emp_id'] ?: 'Employee')) ?></span></div>
                        <div class="list-item"><strong>Employer Name</strong><span><?= h($employerDisplay) ?></span></div>
                        <div class="list-item"><strong>Email</strong><span><?= h((string) $employee['email']) ?></span></div>
                        <div class="list-item"><strong>Phone</strong><span><?= h((string) (($employee['phone'] ?? '') ?: '-')) ?></span></div>
                        <div class="list-item"><strong>Designation</strong><span><?= h((string) (($employee['designation'] ?? '') ?: '-')) ?></span></div>
                        <div class="list-item"><strong>Shift</strong><span><?= h(employee_shift_display($employee)) ?></span></div>
                    </div>
                </div>
                <div class="employee-profile-meta">
                    <div class="list-item"><strong>Status</strong><span><?= h($statusCopy[$status] ?? ucfirst($status)) ?></span></div>
                    <div class="list-item"><strong>Date of Birth</strong><span><?= !empty($employee['date_of_birth']) ? h(date('d M Y', strtotime((string) $employee['date_of_birth']))) : '-' ?></span></div>
                    <div class="list-item"><strong>Gender</strong><span><?= h((string) (($employee['gender'] ?? '') ?: '-')) ?></span></div>
                    <div class="list-item"><strong>Qualification</strong><span><?= h((string) (($employee['highest_qualification'] ?? '') ?: '-')) ?></span></div>
                </div>
            </div>
            <?php if ($status === 'rejected'): ?>
                <div class="profile-verification-alert employee-profile-settings-alert">
                    <strong>Verification Rejected</strong>
                    <span><?= nl2br(h((string) ($employee['profile_rejection_reason'] ?? 'Please review your details and resubmit.'))) ?></span>
                </div>
            <?php elseif ($status === 'pending'): ?>
                <div class="profile-verification-pending employee-profile-settings-alert">
                    <strong>Verification Pending</strong>
                    <span>Your profile details have been submitted. You can still update details if needed.</span>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" class="employee-profile-form employee-profile-settings-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="employee_profile_update">

                <div class="profile-verification-section">
                    <div class="profile-verification-section-head">
                        <strong>Account Details</strong>
                    </div>
                    <div class="profile-verification-grid">
                        <div class="field"><label>Employee ID</label><div class="field-row"><input type="text" name="emp_id" value="<?= h((string) ($employee['emp_id'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Employee ID is required.</small></div>
                        <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" value="<?= h((string) ($employee['name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                        <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" value="<?= h((string) ($employee['email'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Valid email is required.</small></div>
                        <div class="field"><label>Date of Joining</label><div class="field-row"><input type="date" name="date_of_joining" value="<?= h((string) ($employee['date_of_joining'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of joining is required.</small></div>
                        <div class="field"><label>Designation</label><div class="field-row"><select name="designation" required><option value="">Select designation</option><?php foreach (employee_designation_options() as $value => $label): ?><option value="<?= h($value) ?>" <?= ((string) ($employee['designation'] ?? '')) === $value ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Designation is required.</small></div>
                        <div class="field"><label>Shift</label><div class="field-row"><input type="text" name="shift" value="<?= h(normalize_shift_selection((string) ($employee['shift'] ?? ''))) ?>" required></div><small class="field-error"><span>!</span>Shift is required.</small></div>
                    </div>
                </div>

                <div class="profile-verification-section">
                    <div class="profile-verification-section-head">
                        <strong>Personal Details</strong>
                    </div>
                    <div class="profile-verification-grid">
                        <div class="field"><label>Date of Birth</label><div class="field-row"><input type="date" name="date_of_birth" value="<?= h((string) ($employee['date_of_birth'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of birth is required.</small></div>
                        <div class="field"><label>Gender</label><div class="field-row"><select name="gender" required><option value="">Select gender</option><?php foreach (['Female', 'Male', 'Non-binary', 'Prefer not to say'] as $gender): ?><option value="<?= h($gender) ?>" <?= ((string) ($employee['gender'] ?? '')) === $gender ? 'selected' : '' ?>><?= h($gender) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Gender is required.</small></div>
                        <div class="field"><label>Highest Qualification</label><div class="field-row"><input type="text" name="highest_qualification" value="<?= h((string) ($employee['highest_qualification'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Qualification is required.</small></div>
                        <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" value="<?= h((string) ($employee['phone'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Phone number is required.</small></div>
                    </div>
                    <label class="profile-verification-wide">Address<textarea name="address" required><?= h((string) ($employee['address'] ?? '')) ?></textarea></label>
                </div>

                <div class="profile-verification-section">
                    <div class="profile-verification-section-head">
                        <strong>Bank Details</strong>
                    </div>
                    <div class="profile-verification-grid">
                        <div class="field"><label>Bank Name</label><div class="field-row"><input type="text" name="bank_name" value="<?= h((string) ($employee['bank_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Bank name is required.</small></div>
                        <div class="field"><label>Account Number</label><div class="field-row"><input type="text" name="bank_account_no" value="<?= h((string) ($employee['bank_account_no'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account number is required.</small></div>
                        <div class="field"><label>IFSC Code</label><div class="field-row"><input type="text" name="bank_ifsc_code" value="<?= h((string) ($employee['bank_ifsc_code'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>IFSC code is required.</small></div>
                        <div class="field"><label>Account Holder Name</label><div class="field-row"><input type="text" name="account_holder_name" value="<?= h((string) ($employee['account_holder_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account holder name is required.</small></div>
                    </div>
                </div>

                <div class="profile-verification-section">
                    <div class="profile-verification-section-head">
                        <strong>Documents</strong>
                    </div>
                    <div class="profile-document-grid">
                        <?php foreach ($documents as $field => $label): ?>
                            <?php $hasFile = !empty($employee[$field . '_path']); ?>
                            <label class="profile-document-upload<?= $hasFile ? ' has-file' : '' ?>">
                                <span class="profile-document-icon"><?= $hasFile ? 'OK' : '+' ?></span>
                                <span class="profile-document-copy">
                                    <strong><?= h($label) ?></strong>
                                    <small><?= $hasFile ? h((string) $employee[$field . '_name']) : 'JPG, PNG, PDF, DOC, DOCX' ?></small>
                                </span>
                                <input type="file" name="<?= h($field) ?>" <?= !$hasFile ? 'required' : '' ?> accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="profile-verification-actions employee-profile-settings-actions">
                    <button class="button ghost" type="button" data-switch-modal-target="employee-password-modal">Change Password</button>
                    <button class="button solid" type="submit">Save Profile</button>
                </div>
            </form>

            <?php if ($showOfferLetterForm): ?>
                <?php
                    $offerName = trim((string) ($employee['offer_letter_name'] ?? '')) ?: (string) ($employee['name'] ?? '');
                    $offerAddress = trim((string) ($employee['offer_letter_address'] ?? '')) ?: (string) ($employee['address'] ?? '');
                    $offerDesignation = trim((string) ($employee['offer_letter_designation'] ?? '')) ?: (string) ($employee['designation'] ?? '');
                    $offerSignature = !empty($employee['offer_letter_signature_path'])
                        ? public_file_path((string) $employee['offer_letter_signature_path'])
                        : '';
                ?>
                <section class="profile-verification-section offer-letter-section offer-letter-launch-section">
                    <div class="profile-verification-section-head">
                        <strong>Offer Letter</strong>
                        <button class="button solid small" type="button" data-modal-target="employee-offer-letter-modal">Open Offer Letter Form</button>
                    </div>
                    <p class="hint">Open the offer letter as a separate form to update details and upload your signature.</p>
                </section>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($showOfferLetterForm): ?>
        <div class="modal" id="employee-offer-letter-modal">
            <div class="modal-card offer-letter-modal-card">
                <button class="modal-close" type="button" data-close-modal>&times;</button>
                <span class="eyebrow">Verified Employee</span>
                <h2>Offer Letter Form</h2>
                <p class="hint">Update the offer letter fields and upload your signature image.</p>
                <section class="profile-verification-section offer-letter-section">
                    <div class="offer-letter-preview">
                        <div class="offer-letter-head">
                            <strong>V Traco</strong>
                            <span>Offer Letter</span>
                        </div>
                        <p>Date: <?= h(date('d M Y')) ?></p>
                        <p>To,<br><strong><?= h($offerName) ?></strong><br><?= nl2br(h($offerAddress !== '' ? $offerAddress : '-')) ?></p>
                        <div class="offer-letter-details">
                            <div><strong>Employee ID</strong><span><?= h((string) (($employee['emp_id'] ?? '') ?: '-')) ?></span></div>
                            <div><strong>Name</strong><span><?= h($offerName !== '' ? $offerName : '-') ?></span></div>
                            <div><strong>Designation</strong><span><?= h($offerDesignation !== '' ? $offerDesignation : '-') ?></span></div>
                            <div><strong>Employer</strong><span><?= h($employerDisplay) ?></span></div>
                            <div><strong>Email</strong><span><?= h((string) (($employee['email'] ?? '') ?: '-')) ?></span></div>
                            <div><strong>Phone</strong><span><?= h((string) (($employee['phone'] ?? '') ?: '-')) ?></span></div>
                            <div><strong>Date of Joining</strong><span><?= !empty($employee['date_of_joining']) ? h(date('d M Y', strtotime((string) $employee['date_of_joining']))) : '-' ?></span></div>
                            <div><strong>Shift</strong><span><?= h(employee_shift_display($employee)) ?></span></div>
                            <div><strong>Salary</strong><span>Rs <?= h(number_format((float) ($employee['salary'] ?? 0), 2)) ?></span></div>
                            <div class="offer-letter-detail-wide"><strong>Address</strong><span><?= nl2br(h($offerAddress !== '' ? $offerAddress : '-')) ?></span></div>
                        </div>
                        <p>Dear <?= h($offerName !== '' ? $offerName : 'Employee') ?>,</p>
                        <p>We are pleased to offer you the position of <strong><?= h($offerDesignation !== '' ? $offerDesignation : 'Employee') ?></strong> with <?= h($employerDisplay) ?>. Your joining and work details will follow the rules assigned in V Traco.</p>
                        <p>Please confirm your acceptance by updating the details below and uploading your signature image.</p>
                        <div class="offer-letter-sign-row">
                            <div>
                                <span>Employee Signature</span>
                                <?php if ($offerSignature !== ''): ?>
                                    <img class="offer-letter-signature" src="<?= h($offerSignature) ?>" alt="Employee signature">
                                <?php else: ?>
                                    <strong class="offer-letter-sign-placeholder">Signature pending</strong>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span>For <?= h($employerDisplay) ?></span>
                                <strong>Authorized Signatory</strong>
                            </div>
                        </div>
                    </div>
                    <form method="post" enctype="multipart/form-data" class="stack-form offer-letter-form" data-validate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="employee_offer_letter_update">
                        <div class="profile-verification-grid">
                            <div class="field"><label>Name</label><div class="field-row"><input type="text" name="offer_letter_name" value="<?= h($offerName) ?>" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                            <div class="field"><label>Designation</label><div class="field-row"><input type="text" name="offer_letter_designation" value="<?= h($offerDesignation) ?>" required></div><small class="field-error"><span>!</span>Designation is required.</small></div>
                        </div>
                        <label class="profile-verification-wide">Address<textarea name="offer_letter_address" required><?= h($offerAddress) ?></textarea></label>
                        <label class="profile-document-upload<?= $offerSignature !== '' ? ' has-file' : '' ?>">
                            <span class="profile-document-icon"><?= $offerSignature !== '' ? 'OK' : '+' ?></span>
                            <span class="profile-document-copy">
                                <strong>Signature Image</strong>
                                <small><?= $offerSignature !== '' ? h((string) ($employee['offer_letter_signature_name'] ?? 'Uploaded')) : 'JPG, PNG, or WEBP' ?></small>
                            </span>
                            <input type="file" name="offer_letter_signature" <?= $offerSignature === '' ? 'required' : '' ?> accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                        </label>
                        <div class="profile-verification-actions employee-profile-settings-actions">
                            <button class="button solid" type="submit">Save Offer Letter</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    <?php endif; ?>
    <?php
}

function render_employee_password_modal(): void
{
    ?>
    <div class="modal" id="employee-password-modal">
        <div class="modal-card" style="max-width:520px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Employee Account</span>
            <h2>Change Password</h2>
            <p>Update your sign-in password directly from the sidebar.</p>
            <form method="post" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="employee_change_password">
                <div class="field">
                    <label>Current Password</label>
                    <div class="field-row">
                        <input id="employee-current-password" type="password" name="current_password" placeholder="Enter current password" required>
                        <button class="password-toggle" type="button" data-password-toggle="employee-current-password">Show</button>
                    </div>
                    <small class="field-error"><span>!</span>Current password is required.</small>
                </div>
                <div class="field">
                    <label>New Password</label>
                    <div class="field-row">
                        <input id="employee-new-password" type="password" name="new_password" minlength="8" placeholder="Minimum 8 characters with letters and numbers" required>
                        <button class="password-toggle" type="button" data-password-toggle="employee-new-password">Show</button>
                    </div>
                    <small class="field-error"><span>!</span>Password must be at least 8 characters and include a letter and number.</small>
                </div>
                <div class="field">
                    <label>Confirm Password</label>
                    <div class="field-row">
                        <input id="employee-confirm-password" type="password" name="confirm_password" minlength="8" placeholder="Repeat new password" required>
                        <button class="password-toggle" type="button" data-password-toggle="employee-confirm-password">Show</button>
                    </div>
                    <small class="field-error"><span>!</span>Please confirm the new password.</small>
                </div>
                <button class="button solid" type="submit">Update Password</button>
            </form>
        </div>
    </div>
    <?php
}


