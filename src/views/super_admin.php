<?php

declare(strict_types=1);

function render_super_admin_dashboard(): void
{
    $user = current_user();
    if (!$user || ($user['role'] ?? '') !== 'super_admin') {
        render_super_admin_login_modal();
        return;
    }
    
    render_header('Super Admin Dashboard', 'super-admin-page');
    ?>
    <div class="super-admin-layout" x-data="superAdminDashboard()">
        <!-- Sidebar -->
        <aside class="super-admin-sidebar">
            <div class="brand">
                <img src="<?= h(asset_url('assets/images/vtraco-logo.svg')) ?>" alt="V Traco">
                <strong>V Traco</strong>
                <span>Super Admin</span>
            </div>
            <nav>
                <button :class="{ active: tab !== 'logout' }" @click="tab = 'approved'">
                    <span class="icon">▤</span>
                    Dashboard
                </button>
            </nav>
            <div class="sidebar-footer">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="super-admin-main">
            <header>
                <h1>Dashboard</h1>
                <p>Manage and monitor all company registrations and active statuses.</p>
            </header>

            <div class="dashboard-tabs">
                <button :class="{ active: tab === 'approved' }" @click="tab = 'approved'">
                    Approved Companies
                </button>
                <button :class="{ active: tab === 'pending' }" @click="tab = 'pending'">
                    Approval Pending
                    <span class="badge" x-show="pendingCount > 0" x-text="pendingCount"></span>
                </button>
            </div>

            <div class="content-card">
                <!-- Approved Companies Table -->
                <template x-if="tab === 'approved'">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Company Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th class="actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="company in approvedCompanies" :key="company.id">
                                    <tr>
                                        <td>
                                            <div class="company-name" x-text="company.company_name || company.name"></div>
                                        </td>
                                        <td x-text="company.email"></td>
                                        <td>
                                            <span :class="'status-badge ' + company.status.toLowerCase()" x-text="company.status"></span>
                                        </td>
                                        <td class="actions">
                                            <button 
                                                class="btn-toggle" 
                                                :class="company.status === 'ACTIVE' ? 'btn-block' : 'btn-unblock'"
                                                @click="toggleBlock(company)"
                                                :disabled="loading"
                                            >
                                                <span x-text="company.status === 'ACTIVE' ? 'Block' : 'Unblock'"></span>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="approvedCompanies.length === 0">
                                    <td colspan="4" class="empty-state">No approved companies found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <!-- Pending Approvals Table -->
                <template x-if="tab === 'pending'">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Company Name</th>
                                    <th>Email</th>
                                    <th>Requested At</th>
                                    <th class="actions">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="company in pendingCompanies" :key="company.id">
                                    <tr>
                                        <td>
                                            <div class="company-name" x-text="company.company_name || company.name"></div>
                                        </td>
                                        <td x-text="company.email"></td>
                                        <td x-text="formatDate(company.created_at)"></td>
                                        <td class="actions">
                                            <button class="btn-approve" @click="approve(company)" :disabled="loading">Approve</button>
                                            <button class="btn-deny" @click="deny(company)" :disabled="loading">Deny</button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="pendingCompanies.length === 0">
                                    <td colspan="4" class="empty-state">No pending requests found.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </main>
    </div>

    <script>
        function superAdminDashboard() {
            return {
                tab: 'approved',
                approvedCompanies: [],
                pendingCompanies: [],
                pendingCount: 0,
                loading: false,
                csrfToken: '<?= csrf_token() ?>',

                init() {
                    this.fetchData();
                },

                async fetchData() {
                    this.loading = true;
                    try {
                        const url = '<?= BASE_URL ?>?action=super_admin_get_data';
                        console.log('Fetching Super Admin data from:', url);
                        const response = await fetch(url);
                        const data = await response.json();
                        this.approvedCompanies = data.approved || [];
                        this.pendingCompanies = data.pending || [];
                        this.pendingCount = this.pendingCompanies.length;
                    } catch (e) {
                        console.error('Fetch error:', e);
                    } finally {
                        this.loading = false;
                    }
                },

                async toggleBlock(company) {
                    const newStatus = company.status === 'ACTIVE' ? 'BLOCKED' : 'ACTIVE';
                    await this.performAction('super_admin_toggle_status', { id: company.id, status: newStatus });
                },

                async approve(company) {
                    await this.performAction('super_admin_approve', { id: company.id });
                },

                async deny(company) {
                    if (confirm('Are you sure you want to deny this request? This will permanently remove it.')) {
                        await this.performAction('super_admin_deny', { id: company.id });
                    }
                },

                async performAction(action, params = {}) {
                    this.loading = true;
                    try {
                        const formData = new FormData();
                        formData.append('action', action);
                        formData.append('_csrf', this.csrfToken);
                        for (const key in params) {
                            formData.append(key, params[key]);
                        }

                        const response = await fetch('<?= BASE_URL ?>', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            await this.fetchData();
                        } else {
                            alert(result.message || 'Action failed');
                        }
                    } catch (e) {
                        console.error('Action error:', e);
                    } finally {
                        this.loading = false;
                    }
                },

                formatDate(dateStr) {
                    if (!dateStr) return '-';
                    const d = new Date(dateStr);
                    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                }
            }
        }
    </script>
    <?php
    render_footer();
}
function render_super_admin_login_modal(): void
{
    render_header('Super Admin Login', 'auth-page-wrap super-admin-login-page');
    ?>
    <div class="super-admin-auth-bg">
        <div class="auth-header">
            <div class="brand">
                <img src="<?= h(asset_url('assets/images/vtraco-logo.svg')) ?>" alt="V Traco">
                <div class="brand-info">
                    <strong>V Traco</strong>
                    <span>Attendance & Payroll</span>
                </div>
            </div>
        </div>
        
        <div class="super-admin-login-modal">
            <div class="modal-card">
                <span class="eyebrow">System Control</span>
                <h2>Super Admin Login</h2>
                <p>Sign in to manage companies, approvals, and system-wide settings.</p>
                
                <form method="post" class="stack-form" data-validate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="role" value="super_admin">
                    
                    <div class="field">
                        <label>Email</label>
                        <div class="field-row">
                            <input type="email" name="email" value="anfinojegoa@gmail.com" required>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label>Password</label>
                        <div class="field-row">
                            <input id="sa-pass" type="password" name="password" required>
                            <button class="password-toggle" type="button" data-password-toggle="sa-pass">Show</button>
                        </div>
                    </div>
                    
                    <button class="button solid large full-width" type="submit">Super Admin Login</button>
                </form>
            </div>
        </div>
    </div>
    <?php
    render_footer();
}
