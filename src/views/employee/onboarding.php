<?php

declare(strict_types=1);

function render_employee_onboarding_reviews(): void
{
    $reviewer = require_power_team_access(['admin', 'freelancer']);

    $stmt = db()->prepare("SELECT * FROM users WHERE admin_id = :admin_id AND role IN ('employee', 'corporate_employee') AND profile_status IN ('pending', 'rejected') ORDER BY FIELD(profile_status, 'pending', 'rejected'), name");
    $stmt->execute(['admin_id' => (int) $reviewer['id']]);
    $employees = $stmt->fetchAll();
    render_header('Profile Reviews', 'employee-onboarding-reviews-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">HR Verification</span>
            <h1>Profile Review Queue</h1>
            <p>Approve verified employee profiles or reject with a reason for resubmission.</p>
        </div>
    </section>
    <section class="section-block">
        <?php if (!$employees): ?>
            <div class="list-item muted">No employee profiles are waiting for review.</div>
        <?php else: ?>
            <div class="list">
                <?php foreach ($employees as $employee): ?>
                    <div class="list-item">
                        <div class="split">
                            <div>
                                <strong><?= h((string) $employee['name']) ?></strong>
                                <p class="hint"><?= h((string) ($employee['emp_id'] ?? '')) ?> | <?= h((string) ($employee['designation'] ?? '-')) ?> | <?= h(ucfirst((string) ($employee['profile_status'] ?? 'pending'))) ?></p>
                                <div class="inline-actions">
                                    <?php foreach ([
                                        'aadhaar_card' => 'Aadhaar',
                                        'pan_card' => 'PAN',
                                        'profile_photo' => 'Photo',
                                        'qualification_certificate' => 'Qualification',
                                        'bank_proof' => 'Bank Proof',
                                        'resume' => 'Resume',
                                    ] as $field => $label): ?>
                                        <?php if (!empty($employee[$field . '_path'])): ?>
                                            <a class="button ghost small" href="<?= h(public_file_path((string) $employee[$field . '_path'])) ?>" target="_blank" rel="noopener"><?= h($label) ?></a>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <form method="post" class="stack-form" style="min-width:280px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="admin_review_employee_profile">
                                <input type="hidden" name="user_id" value="<?= (int) $employee['id'] ?>">
                                <textarea name="rejection_reason" placeholder="Reason if rejecting"></textarea>
                                <div class="inline-actions">
                                    <button class="button solid small" type="submit" name="decision" value="approve">Approve</button>
                                    <button class="button outline small" type="submit" name="decision" value="reject">Reject</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
}   

