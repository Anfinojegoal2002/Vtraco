<?php

declare(strict_types=1);

function render_admin_vendors(): void
{
    require_role('admin');
    $vendors = db()->query("SELECT * FROM users WHERE role = 'external_vendor' ORDER BY name")->fetchAll();
    
    render_header('Vendor Registrations', 'admin-vendors-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Admin - Vendors</span>
            <h1>Vendor Registrations</h1>
            <p>Create vendor accounts here and manage the list of external vendors added by admins.</p>
        </div>
    </section>
    <section class="section-block">
        <div class="split">
            <div>
                <span class="eyebrow">Create Vendor</span>
                <h2>Add Vendor Account</h2>
            </div>
        </div>
        <form method="post" class="stack-form" data-validate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_create_vendor">
            <div class="field">
                <label>Name of the Company</label>
                <div class="field-row"><input type="text" name="name" placeholder="Company name" required></div>
                <small class="field-error"><span>!</span>Company name is required.</small>
            </div>
            <div class="field">
                <label>Company Mail ID</label>
                <div class="field-row"><input type="email" name="email" placeholder="vendor@company.com" required></div>
                <small class="field-error"><span>!</span>Enter a valid company mail ID.</small>
            </div>
            <div class="field">
                <label>Company Phone Number</label>
                <div class="field-row"><input type="text" name="phone" placeholder="Phone number" required></div>
                <small class="field-error"><span>!</span>Company phone number is required.</small>
            </div>
            <p class="hint">The vendor password will be created the same way as employee passwords and sent to the vendor email automatically.</p>
            <button class="button solid" type="submit">Create Vendor Account</button>
        </form>
    </section>
    <div class="spacer"></div>
    <section class="table-wrap">
        <?php render_vendor_accounts_table($vendors, 'admin-vendors-table', 'admin-vendors-empty', true, 'admin_vendors'); ?>
    </section>
    <?php
    render_footer();
}

