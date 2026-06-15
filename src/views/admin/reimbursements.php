<?php

declare(strict_types=1);

function render_admin_reimbursements(): void
{
    require_role('admin');

    $filters = [
        'employee_id' => max(0, (int) ($_GET['employee_id'] ?? 0)),
        'category' => strtoupper(trim((string) ($_GET['category'] ?? ''))),
    ];
    if (!in_array($filters['category'], reimbursement_categories(), true)) {
        $filters['category'] = '';
    }

    $allEmployees = employees();
    $items = admin_reimbursements($filters);
    $statusOptions = reimbursement_statuses();
    $bankNames = payment_bank_names();
    $transferModesMap = payment_transfer_modes_map();

    render_header('Reimbursement', 'admin-reimbursements-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Reimbursement</span>
            <h1>Reimbursement Requests</h1>
            <p>Review employee reimbursement claims, preview uploaded proof, and move each request through approval and payment.</p>
        </div>
    </section>

    <section class="section-block reimbursement-filters-panel">
        <form method="get" class="stack-form">
            <input type="hidden" name="page" value="admin_reimbursements">
            <div class="admin-reimbursement-filter-grid">
                <div class="field">
                    <label>Employee</label>
                    <select name="employee_id">
                        <option value="0">All Employees</option>
                        <?php foreach ($allEmployees as $employee): ?>
                            <option value="<?= (int) $employee['id'] ?>" <?= (int) $filters['employee_id'] === (int) $employee['id'] ? 'selected' : '' ?>>
                                <?= h((string) $employee['name']) ?> (<?= h((string) $employee['emp_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Category</label>
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach (reimbursement_categories() as $category): ?>
                            <option value="<?= h($category) ?>" <?= $filters['category'] === $category ? 'selected' : '' ?>><?= h($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="reimbursement-filter-actions">
                    <button class="button solid" type="submit">Apply Filters</button>
                    <a class="button outline" href="<?= h(BASE_URL) ?>?page=admin_reimbursements">Reset</a>
                </div>
            </div>
        </form>
    </section>

    <div class="spacer"></div>

    <?php if ($items): ?>
        <section class="admin-reimbursement-card-grid">
            <?php foreach ($items as $item):
                $badgeClass = reimbursement_status_badge_class((string) $item['status']);
                $previewPayload = [
                    'employee' => (string) $item['employee_name'] . ' (' . (string) $item['employee_emp_id'] . ')',
                    'category' => (string) $item['category'],
                    'status' => (string) $item['status'],
                    'amount' => number_format((float) $item['amount_requested'], 2),
                    'description' => (string) $item['expense_description'],
                    'attachmentName' => (string) ($item['attachment_name'] ?? ''),
                    'attachmentUrl' => reimbursement_attachment_url($item),
                    'attachmentMime' => (string) ($item['attachment_mime'] ?? ''),
                ];
                ?>
                <article class="reimbursement-admin-card">
                    <div class="split">
                        <div>
                            <span class="eyebrow">Employee</span>
                            <h2><?= h((string) $item['employee_name']) ?></h2>
                            <p class="hint"><?= h((string) $item['employee_emp_id']) ?></p>
                        </div>
                        <span class="status-pill reimbursement-status <?= h($badgeClass) ?>"><?= h((string) $item['status']) ?></span>
                    </div>

                    <div class="reimbursement-admin-meta">
                        <div class="reimbursement-meta-chip">
                            <strong>Category</strong>
                            <span><?= h((string) $item['category']) ?></span>
                        </div>
                        <div class="reimbursement-meta-chip">
                            <strong>Requested Amount</strong>
                            <span>Rs <?= h(number_format((float) $item['amount_requested'], 2)) ?></span>
                        </div>
                        <div class="reimbursement-meta-chip">
                            <strong>Paid Amount</strong>
                            <span>Rs <?= h(number_format((float) $item['amount_paid'], 2)) ?></span>
                        </div>
                        <div class="reimbursement-meta-chip">
                            <strong>Remaining Balance</strong>
                            <span>Rs <?= h(number_format((float) $item['remaining_balance'], 2)) ?></span>
                        </div>
                    </div>

                    <div class="spacer"></div>
                    <p class="reimbursement-description"><?= h((string) $item['expense_description']) ?></p>

                    <div class="spacer"></div>
                    <div class="split">
                        <span class="badge"><?= h(date('d M Y', strtotime((string) $item['expense_date']))) ?></span>
                        <button
                            class="button outline small"
                            type="button"
                            data-reimbursement-preview="<?= h(json_encode($previewPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>"
                        >
                            View Details
                        </button>
                    </div>

                    <div class="spacer"></div>
                    <form
                        method="post"
                        class="stack-form reimbursement-status-form"
                        data-reimbursement-status-form
                        data-reimbursement-id="<?= (int) $item['id'] ?>"
                        data-reimbursement-user-id="<?= (int) $item['user_id'] ?>"
                        data-reimbursement-remaining="<?= h(number_format((float) $item['remaining_balance'], 2, '.', '')) ?>"
                        data-filter-employee="<?= (int) $filters['employee_id'] ?>"
                        data-filter-category="<?= h($filters['category']) ?>"
                    >
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="admin_update_reimbursement_status">
                        <input type="hidden" name="reimbursement_id" value="<?= (int) $item['id'] ?>">
                        <input type="hidden" name="filter_employee_id" value="<?= (int) $filters['employee_id'] ?>">
                        <input type="hidden" name="filter_category" value="<?= h($filters['category']) ?>">
                        <div class="reimbursement-status-row">
                            <label class="reimbursement-status-label">
                                <span>Status</span>
                                <select name="status" data-reimbursement-status-select>
                                    <?php foreach ($statusOptions as $status): ?>
                                        <option value="<?= h($status) ?>" <?= (string) $item['status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button class="button solid small" type="submit">Apply</button>
                        </div>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>
    <?php else: ?>
        <section class="section-block">
            <div class="list-item muted">No reimbursement requests matched the selected filters.</div>
        </section>
    <?php endif; ?>

    <div class="modal" id="reimbursement-preview-modal">
        <div class="modal-card reimbursement-preview-modal-card">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <div id="reimbursement-preview-content"></div>
        </div>
    </div>

    <div class="modal" id="reimbursement-payment-modal">
        <div class="modal-card" style="max-width:920px;">
            <button class="modal-close" type="button" data-close-modal>&times;</button>
            <span class="eyebrow">Accounts Payment</span>
            <h2 id="reimbursement-payment-title">Reimbursement Payment</h2>
            <p id="reimbursement-payment-copy" class="hint">Capture the payment details to settle this reimbursement request.</p>
            <form method="post" enctype="multipart/form-data" class="stack-form" id="reimbursement-payment-form" data-validate>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="admin_record_reimbursement_payment">
                <input type="hidden" name="reimbursement_id" id="reimbursement-payment-reimbursement-id">
                <input type="hidden" name="filter_employee_id" id="reimbursement-payment-filter-employee">
                <input type="hidden" name="filter_category" id="reimbursement-payment-filter-category">

                <div class="accounts-payment-grid">
                    <div class="field">
                        <label>Amount</label>
                        <div class="field-row">
                            <input type="number" name="amount" id="reimbursement-payment-amount" min="0.01" step="0.01" required>
                        </div>
                        <small class="field-error"><span>!</span>Amount is required.</small>
                    </div>

                    <div class="field">
                        <label>Bank Name</label>
                        <div class="field-row">
                            <select name="bank_name" id="reimbursement-payment-bank" required>
                                <option value="" selected disabled>Select bank</option>
                                <?php foreach ($bankNames as $bankName): ?>
                                    <option value="<?= h($bankName) ?>"><?= h($bankName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small class="field-error"><span>!</span>Bank name is required.</small>
                    </div>

                    <div class="field" id="reimbursement-payment-transfer-mode-field">
                        <label>Transfer Mode</label>
                        <div class="field-row">
                            <select name="transfer_mode" id="reimbursement-payment-transfer-mode">
                                <option value="" selected disabled>Select transfer mode</option>
                            </select>
                        </div>
                        <small class="field-error"><span>!</span>Transfer mode is required for the selected bank.</small>
                    </div>

                    <div class="field" id="reimbursement-payment-transaction-id-field">
                        <label>Transaction ID</label>
                        <div class="field-row">
                            <input type="text" name="transaction_id" id="reimbursement-payment-transaction-id">
                        </div>
                        <small class="field-error"><span>!</span>Transaction ID is required unless the payment is cash.</small>
                    </div>

                    <div class="field">
                        <label>Payment Date</label>
                        <div class="field-row">
                            <input type="date" name="payment_date" id="reimbursement-payment-date" required>
                        </div>
                        <small class="field-error"><span>!</span>Payment date is required.</small>
                    </div>

                    <div class="field">
                        <label>Proof Upload</label>
                        <div class="field-row">
                            <input type="file" name="proof_upload" id="reimbursement-payment-proof" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
                        </div>
                        <small class="hint">Accepted formats: JPG, PNG, PDF.</small>
                    </div>
                </div>

                <div class="field">
                    <label>Remarks</label>
                    <div class="field-row">
                        <textarea name="remarks" id="reimbursement-payment-remarks" rows="3" placeholder="Optional notes for this payment"></textarea>
                    </div>
                </div>

                <button class="button solid" type="submit" id="reimbursement-payment-submit">Save Payment</button>
            </form>
        </div>
    </div>

    <style>
        .admin-reimbursement-filter-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; align-items: end; }
        .reimbursement-filter-actions { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
        .admin-reimbursement-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; }
        .reimbursement-admin-card { padding: 22px; border-radius: 28px; background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(243,246,255,0.96)); border: 1px solid rgba(36, 52, 109, 0.1); box-shadow: 0 16px 32px rgba(15, 23, 42, 0.08); }
        .reimbursement-admin-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
        .reimbursement-meta-chip { padding: 12px 14px; border-radius: 18px; background: rgba(236, 242, 255, 0.72); display: grid; gap: 4px; }
        .reimbursement-description { color: #1e293b; margin: 0; }
        .reimbursement-status.pending { background: #e5e7eb; color: #374151; }
        .reimbursement-status.approved { background: #fef3c7; color: #92400e; }
        .reimbursement-status.denied { background: #fee2e2; color: #b91c1c; }
        .reimbursement-status.partially-paid { background: #e0f2fe; color: #0369a1; }
        .reimbursement-status.paid { background: #dcfce7; color: #166534; }
        .reimbursement-status-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; align-items: end; }
        .reimbursement-status-label { display: grid; gap: 8px; }
        .reimbursement-preview-modal-card { max-width: 860px; }
        .reimbursement-preview-frame { width: 100%; min-height: 420px; border: 0; border-radius: 18px; background: #f8fafc; }
        .reimbursement-preview-image { max-width: 100%; border-radius: 18px; display: block; }
        @media (max-width: 900px) {
            .admin-reimbursement-filter-grid { grid-template-columns: 1fr; }
            .reimbursement-filter-actions { justify-content: stretch; }
            .reimbursement-admin-meta { grid-template-columns: 1fr; }
            .reimbursement-status-row { grid-template-columns: 1fr; }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const transferModesMap = <?= json_encode($transferModesMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
            const openPageModal = id => {
                const target = document.getElementById(id);
                if (target) {
                    target.classList.add('open');
                }
            };

            const paymentModal = document.getElementById('reimbursement-payment-modal');
            const paymentForm = document.getElementById('reimbursement-payment-form');
            const paymentTitle = document.getElementById('reimbursement-payment-title');
            const paymentCopy = document.getElementById('reimbursement-payment-copy');
            const paymentIdInput = document.getElementById('reimbursement-payment-reimbursement-id');
            const paymentFilterEmployee = document.getElementById('reimbursement-payment-filter-employee');
            const paymentFilterCategory = document.getElementById('reimbursement-payment-filter-category');
            const amountInput = document.getElementById('reimbursement-payment-amount');
            const bankSelect = document.getElementById('reimbursement-payment-bank');
            const transferField = document.getElementById('reimbursement-payment-transfer-mode-field');
            const transferSelect = document.getElementById('reimbursement-payment-transfer-mode');
            const txnField = document.getElementById('reimbursement-payment-transaction-id-field');
            const txnInput = document.getElementById('reimbursement-payment-transaction-id');
            const dateInput = document.getElementById('reimbursement-payment-date');

            const setSelectValue = (select, value) => {
                if (!select) return;
                const target = String(value ?? '');
                const option = Array.from(select.options).find(opt => String(opt.value) === target);
                if (option) {
                    select.value = target;
                } else {
                    select.value = '';
                }
            };

            const updateTransferModes = selectedMode => {
                if (!bankSelect || !transferField || !transferSelect || !txnInput || !txnField) return;
                const bank = String(bankSelect.value || '').toUpperCase();
                const modes = Array.isArray(transferModesMap[bank]) ? transferModesMap[bank] : [];
                const requiresTransfer = modes.length > 0;
                const requiresTxn = bank !== 'CASH';

                transferField.classList.toggle('hidden', !requiresTransfer);
                transferSelect.required = requiresTransfer;
                transferSelect.disabled = !requiresTransfer;

                txnInput.required = requiresTxn;
                txnInput.disabled = !requiresTxn;
                txnField.classList.toggle('hidden', !requiresTxn);
                if (!requiresTxn) {
                    txnInput.value = '';
                }

                if (!requiresTransfer) {
                    transferSelect.innerHTML = '<option value="" selected disabled>Select transfer mode</option>';
                    return;
                }

                transferSelect.innerHTML = '<option value=\"\" selected disabled>Select transfer mode</option>' +
                    modes.map(mode => `<option value=\"${mode}\">${mode}</option>`).join('');
                if (selectedMode) {
                    setSelectValue(transferSelect, selectedMode);
                }
            };

            if (bankSelect) {
                bankSelect.addEventListener('change', () => updateTransferModes(''));
            }

            document.querySelectorAll('[data-reimbursement-preview]').forEach(button => {
                button.addEventListener('click', () => {
                    const preview = JSON.parse(button.dataset.reimbursementPreview || '{}');
                    const content = document.getElementById('reimbursement-preview-content');
                    if (!content) {
                        return;
                    }

                    const isPdf = String(preview.attachmentMime || '').toLowerCase() === 'application/pdf';
                    const media = isPdf
                        ? `<iframe class="reimbursement-preview-frame" src="${preview.attachmentUrl}"></iframe>`
                        : `<img class="reimbursement-preview-image" src="${preview.attachmentUrl}" alt="Reimbursement proof preview">`;

                    content.innerHTML = `
                        <span class="eyebrow">Attachment Preview</span>
                        <h2>${preview.employee}</h2>
                        <p><strong>Category:</strong> ${preview.category} | <strong>Status:</strong> ${preview.status}</p>
                        <p><strong>Requested Amount:</strong> Rs ${preview.amount}</p>
                        <p>${preview.description}</p>
                        <div class="spacer"></div>
                        ${media}
                        <div class="spacer"></div>
                        <a class="button outline" href="${preview.attachmentUrl}" target="_blank" rel="noopener">Open File</a>
                    `;
                    openPageModal('reimbursement-preview-modal');
                });
            });

            document.querySelectorAll('[data-reimbursement-status-form]').forEach(form => {
                form.addEventListener('submit', event => {
                    const select = form.querySelector('[data-reimbursement-status-select]');
                    if (!select) {
                        return;
                    }

                    if (select.value === 'PARTIALLY PAID' || select.value === 'PAID') {
                        event.preventDefault();
                        const remaining = Number(form.dataset.reimbursementRemaining || 0);
                        const mode = String(select.value || '');

                        if (paymentIdInput) paymentIdInput.value = form.dataset.reimbursementId || '';
                        if (paymentFilterEmployee) paymentFilterEmployee.value = form.dataset.filterEmployee || '';
                        if (paymentFilterCategory) paymentFilterCategory.value = form.dataset.filterCategory || '';

                        if (paymentTitle) {
                            paymentTitle.textContent = mode === 'PAID' ? 'Mark Reimbursement as Paid' : 'Record Partial Reimbursement';
                        }
                        if (paymentCopy) {
                            paymentCopy.textContent = mode === 'PAID'
                                ? `Settlement amount for this payment: Rs ${remaining.toFixed(2)}.`
                                : `Enter the partial payment amount (must be less than or equal to remaining): Rs ${remaining.toFixed(2)}.`;
                        }

                        if (paymentForm) {
                            paymentForm.reset();
                        }
                        if (dateInput) {
                            const today = new Date();
                            const yyyy = today.getFullYear();
                            const mm = String(today.getMonth() + 1).padStart(2, '0');
                            const dd = String(today.getDate()).padStart(2, '0');
                            dateInput.value = `${yyyy}-${mm}-${dd}`;
                        }

                        if (amountInput) {
                            amountInput.max = remaining > 0 ? String(remaining.toFixed(2)) : '';
                            amountInput.readOnly = mode === 'PAID';
                            amountInput.value = mode === 'PAID' ? String(remaining.toFixed(2)) : '';
                        }

                        // Reset conditional banking fields.
                        if (bankSelect) {
                            bankSelect.value = '';
                        }
                        if (txnInput) {
                            txnInput.value = '';
                        }
                        updateTransferModes('');

                        openPageModal('reimbursement-payment-modal');
                    }
                });
            });
        });
    </script>
    <?php
    render_footer();
}


