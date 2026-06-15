<?php

declare(strict_types=1);

function render_employee_profile(): void
{
    $employee = require_roles(['employee', 'corporate_employee']);
    $status = (string) ($employee['profile_status'] ?? 'incomplete');
    $statusCopy = [
        'verified' => 'Verified',
        'pending' => 'Under Review',
        'rejected' => 'Needs Update',
        'incomplete' => 'Incomplete',
    ];
    $documents = [
        'aadhaar_card' => 'Aadhaar Card',
        'pan_card' => 'PAN Card',
        'profile_photo' => 'Profile Photo',
        'qualification_certificate' => 'Qualification Certificate',
        'bank_proof' => 'Bank Proof',
        'resume' => 'Resume',
    ];

    render_header('My Profile', 'employee-profile-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Employee Profile</span>
            <h1>Profile Settings</h1>
            <p>Keep your personal, bank, and onboarding documents up to date.</p>
        </div>
        <span class="status-pill status-<?= h($status) ?>"><?= h($statusCopy[$status] ?? ucfirst($status)) ?></span>
    </section>

    <?php if ($status === 'rejected'): ?>
        <section class="section-block">
            <span class="eyebrow">Action Required</span>
            <h2>Verification Rejected</h2>
            <p><?= nl2br(h((string) ($employee['profile_rejection_reason'] ?? 'Please review your details and resubmit.'))) ?></p>
        </section>
    <?php elseif ($status === 'pending'): ?>
        <section class="section-block">
            <span class="eyebrow">Submitted</span>
            <h2>Verification Pending</h2>
            <p>Your profile details have been submitted. You can still update details if something needs correction.</p>
        </section>
    <?php endif; ?>

    <section class="section-block employee-profile-settings-panel">
        <form method="post" enctype="multipart/form-data" class="employee-profile-form" data-validate>
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
                    <div class="field"><label>Designation</label><div class="field-row"><input type="text" name="designation" value="<?= h((string) ($employee['designation'] ?? '')) ?>" placeholder="Enter designation" required></div><small class="field-error"><span>!</span>Designation is required.</small></div>
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

            <div class="profile-verification-actions">
                <span>Updates are verified immediately after saving required details.</span>
                <button class="button solid" type="submit">Save Profile</button>
            </div>
        </form>
    </section>
    <?php
    render_footer();
}


function render_employee_profile_completion(): void
{
    $employee = require_roles(['employee', 'corporate_employee']);
    $status = (string) ($employee['profile_status'] ?? 'incomplete');
    $isContractual = (string) ($employee['role'] ?? '') === 'corporate_employee';
    $documents = $isContractual
        ? ['pan_card' => 'PAN Card', 'bank_proof' => 'Bank Proof', 'profile_photo' => 'Profile Photo', 'resume' => 'Resume']
        : [
            'aadhaar_card' => 'Aadhaar Card',
            'pan_card' => 'PAN Card',
            'profile_photo' => 'Profile Photo',
            'qualification_certificate' => 'Qualification Certificate',
            'bank_proof' => 'Bank Proof',
            'resume' => 'Resume',
        ];
    render_header('Profile Verification', 'employee-profile-verification-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Employee Onboarding</span>
            <h1>Profile Verification</h1>
            <p>Complete verification to activate full dashboard access.</p>
        </div>
    </section>
    <?php if ($status === 'pending'): ?>
        <section class="section-block">
            <span class="eyebrow">Submitted</span>
            <h2>Verification Pending</h2>
            <p>Your profile details and documents have been submitted for admin verification.</p>
        </section>
    <?php else: ?>
        <?php if ($status === 'rejected'): ?>
            <section class="section-block">
                <span class="eyebrow">Action Required</span>
                <h2>Verification Rejected</h2>
                <p><?= nl2br(h((string) ($employee['profile_rejection_reason'] ?? 'Please review your details and resubmit.'))) ?></p>
            </section>
        <?php endif; ?>
        <section class="section-block">
            <form method="post" enctype="multipart/form-data" class="stack-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="employee_profile_submit">
                <div class="reports-filter-grid">
                    <div class="field"><label>Employee ID</label><div class="field-row"><input type="text" name="emp_id" value="<?= h((string) ($employee['emp_id'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Employee ID is required.</small></div>
                    <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" value="<?= h((string) ($employee['name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                    <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" value="<?= h((string) ($employee['email'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Valid email is required.</small></div>
                    <div class="field"><label>Date of Joining</label><div class="field-row"><input type="date" name="date_of_joining" value="<?= h((string) ($employee['date_of_joining'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of joining is required.</small></div>
                    <div class="field"><label>Designation</label><div class="field-row"><input type="text" name="designation" value="<?= h((string) ($employee['designation'] ?? '')) ?>" placeholder="Enter designation" required></div><small class="field-error"><span>!</span>Designation is required.</small></div>
                    <div class="field"><label>Shift</label><div class="field-row"><input type="text" name="shift" value="<?= h(normalize_shift_selection((string) ($employee['shift'] ?? ''))) ?>" required></div><small class="field-error"><span>!</span>Shift is required.</small></div>
                    <div class="field"><label>Date of Birth</label><div class="field-row"><input type="date" name="date_of_birth" value="<?= h((string) ($employee['date_of_birth'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of birth is required.</small></div>
                    <div class="field"><label>Gender</label><div class="field-row"><select name="gender" required><option value="">Select gender</option><?php foreach (['Female', 'Male', 'Non-binary', 'Prefer not to say'] as $gender): ?><option value="<?= h($gender) ?>" <?= ((string) ($employee['gender'] ?? '')) === $gender ? 'selected' : '' ?>><?= h($gender) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Gender is required.</small></div>
                    <?php if ($isContractual): ?>
                        <div class="field"><label>Training Experience</label><div class="field-row"><input type="text" name="training_experience_years" value="<?= h((string) ($employee['training_experience_years'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Training experience is required.</small></div>
                        <div class="field"><label>Languages Known</label><div class="field-row"><input type="text" name="languages_known" value="<?= h((string) ($employee['languages_known'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Languages known is required.</small></div>
                        <div class="field"><label>Technical Skills</label><div class="field-row"><input type="text" name="technical_skills" value="<?= h((string) ($employee['technical_skills'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Technical skills are required.</small></div>
                    <?php else: ?>
                        <div class="field"><label>Highest Qualification</label><div class="field-row"><input type="text" name="highest_qualification" value="<?= h((string) ($employee['highest_qualification'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Qualification is required.</small></div>
                    <?php endif; ?>
                    <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" value="<?= h((string) ($employee['phone'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Phone number is required.</small></div>
                    <div class="field"><label>Bank Name</label><div class="field-row"><input type="text" name="bank_name" value="<?= h((string) ($employee['bank_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Bank name is required.</small></div>
                    <div class="field"><label>Account Number</label><div class="field-row"><input type="text" name="bank_account_no" value="<?= h((string) ($employee['bank_account_no'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account number is required.</small></div>
                    <div class="field"><label>IFSC Code</label><div class="field-row"><input type="text" name="bank_ifsc_code" value="<?= h((string) ($employee['bank_ifsc_code'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>IFSC code is required.</small></div>
                    <div class="field"><label>Account Holder Name</label><div class="field-row"><input type="text" name="account_holder_name" value="<?= h((string) ($employee['account_holder_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account holder name is required.</small></div>
                </div>
                <?php if (!$isContractual): ?>
                    <label>Address<textarea name="address" required><?= h((string) ($employee['address'] ?? '')) ?></textarea></label>
                <?php endif; ?>
                <div class="reports-filter-grid">
                    <?php foreach ($documents as $field => $label): ?>
                        <label class="upload-drop">
                            <strong><?= h($label) ?></strong>
                            <p><?= !empty($employee[$field . '_name']) ? 'Current file: ' . h((string) $employee[$field . '_name']) : 'Upload JPG, PNG, or PDF. Resume also accepts DOC/DOCX.' ?></p>
                            <input type="file" name="<?= h($field) ?>" <?= empty($employee[$field . '_path']) ? 'required' : '' ?> accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                        </label>
                    <?php endforeach; ?>
                </div>
                <button class="button solid" type="submit">Submit for Review</button>
            </form>
        </section>
    <?php endif; ?>
    <?php
    render_footer();
}


function render_employee_profile_completion_modal(array $employee): void
{
    $status = (string) ($employee['profile_status'] ?? 'incomplete');
    $statusLabel = $status === 'rejected' ? 'Needs Update' : ($status === 'pending' ? 'Under Review' : 'Required');
    $isContractual = (string) ($employee['role'] ?? '') === 'corporate_employee';
    $documents = $isContractual
        ? ['pan_card' => 'PAN Card', 'bank_proof' => 'Bank Proof', 'profile_photo' => 'Profile Photo', 'resume' => 'Resume']
        : [
            'aadhaar_card' => 'Aadhaar Card',
            'pan_card' => 'PAN Card',
            'profile_photo' => 'Profile Photo',
            'qualification_certificate' => 'Qualification Certificate',
            'bank_proof' => 'Bank Proof',
            'resume' => 'Resume',
        ];
    ?>
    <div class="modal open employee-profile-gate-modal" id="employee-profile-verification-modal" data-profile-gate>
        <div class="modal-card profile-verification-modal-card">
            <div class="profile-verification-head">
                <div>
                    <span class="eyebrow">Employee Onboarding</span>
                    <h2>Profile Verification</h2>
                </div>
                <span class="profile-verification-status status-<?= h($status) ?>"><?= h($statusLabel) ?></span>
            </div>
            <?php if ($status === 'pending'): ?>
                <div class="profile-verification-pending">
                    <strong>Profile submitted</strong>
                    <span>Your profile details have been submitted for admin verification.</span>
                </div>
            <?php else: ?>
                <?php if ($status === 'rejected'): ?>
                    <div class="profile-verification-alert">
                        <strong>Verification Rejected</strong>
                        <span><?= nl2br(h((string) ($employee['profile_rejection_reason'] ?? 'Please review your details and resubmit.'))) ?></span>
                    </div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="stack-form profile-verification-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="employee_profile_submit">
                    <section class="profile-verification-section">
                        <div class="profile-verification-section-head">
                            <strong>Personal Details</strong>
                        </div>
                        <div class="profile-verification-grid">
                            <div class="field"><label>Employee ID</label><div class="field-row"><input type="text" name="emp_id" value="<?= h((string) ($employee['emp_id'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Employee ID is required.</small></div>
                            <div class="field"><label>Name</label><div class="field-row"><input type="text" name="name" value="<?= h((string) ($employee['name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Name is required.</small></div>
                            <div class="field"><label>Email</label><div class="field-row"><input type="email" name="email" value="<?= h((string) ($employee['email'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Valid email is required.</small></div>
                            <div class="field"><label>Date of Joining</label><div class="field-row"><input type="date" name="date_of_joining" value="<?= h((string) ($employee['date_of_joining'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of joining is required.</small></div>
                            <div class="field"><label>Designation</label><div class="field-row"><input type="text" name="designation" value="<?= h((string) ($employee['designation'] ?? '')) ?>" placeholder="Enter designation" required></div><small class="field-error"><span>!</span>Designation is required.</small></div>
                            <div class="field"><label>Shift</label><div class="field-row"><input type="text" name="shift" value="<?= h(normalize_shift_selection((string) ($employee['shift'] ?? ''))) ?>" required></div><small class="field-error"><span>!</span>Shift is required.</small></div>
                            <div class="field"><label>Date of Birth</label><div class="field-row"><input type="date" name="date_of_birth" value="<?= h((string) ($employee['date_of_birth'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Date of birth is required.</small></div>
                            <div class="field"><label>Gender</label><div class="field-row"><select name="gender" required><option value="">Select gender</option><?php foreach (['Female', 'Male', 'Non-binary', 'Prefer not to say'] as $gender): ?><option value="<?= h($gender) ?>" <?= ((string) ($employee['gender'] ?? '')) === $gender ? 'selected' : '' ?>><?= h($gender) ?></option><?php endforeach; ?></select></div><small class="field-error"><span>!</span>Gender is required.</small></div>
                            <?php if ($isContractual): ?>
                                <div class="field"><label>Training Experience</label><div class="field-row"><input type="text" name="training_experience_years" value="<?= h((string) ($employee['training_experience_years'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Training experience is required.</small></div>
                                <div class="field"><label>Languages Known</label><div class="field-row"><input type="text" name="languages_known" value="<?= h((string) ($employee['languages_known'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Languages known is required.</small></div>
                                <div class="field"><label>Technical Skills</label><div class="field-row"><input type="text" name="technical_skills" value="<?= h((string) ($employee['technical_skills'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Technical skills are required.</small></div>
                            <?php else: ?>
                                <div class="field"><label>Highest Qualification</label><div class="field-row"><input type="text" name="highest_qualification" value="<?= h((string) ($employee['highest_qualification'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Qualification is required.</small></div>
                            <?php endif; ?>
                            <div class="field"><label>Phone Number</label><div class="field-row"><input type="text" name="phone" value="<?= h((string) ($employee['phone'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Phone number is required.</small></div>
                        </div>
                        <?php if (!$isContractual): ?>
                            <label class="profile-verification-wide">Address<textarea name="address" required><?= h((string) ($employee['address'] ?? '')) ?></textarea></label>
                        <?php endif; ?>
                    </section>
                    <section class="profile-verification-section">
                        <div class="profile-verification-section-head">
                            <strong>Bank Details</strong>
                        </div>
                        <div class="profile-verification-grid">
                            <div class="field"><label>Bank Name</label><div class="field-row"><input type="text" name="bank_name" value="<?= h((string) ($employee['bank_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Bank name is required.</small></div>
                            <div class="field"><label>Account Number</label><div class="field-row"><input type="text" name="bank_account_no" value="<?= h((string) ($employee['bank_account_no'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account number is required.</small></div>
                            <div class="field"><label>IFSC Code</label><div class="field-row"><input type="text" name="bank_ifsc_code" value="<?= h((string) ($employee['bank_ifsc_code'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>IFSC code is required.</small></div>
                            <div class="field"><label>Account Holder Name</label><div class="field-row"><input type="text" name="account_holder_name" value="<?= h((string) ($employee['account_holder_name'] ?? '')) ?>" required></div><small class="field-error"><span>!</span>Account holder name is required.</small></div>
                        </div>
                    </section>
                    <section class="profile-verification-section">
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
                    </section>
                    <div class="profile-verification-actions">
                        <span>Profile access unlocks after admin verification.</span>
                        <button class="button solid" type="submit">Submit for Review</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}


