<?php

declare(strict_types=1);

function render_notifications(): void
{
    $user = require_roles(['admin', 'employee', 'corporate_employee', 'external_vendor', 'freelancer']);
    $notifications = notifications_for_user((int) $user['id'], 50);
    $unreadCount = unread_notification_count((int) $user['id']);

    render_header('Notifications', 'notifications-page');
    ?>
    <section class="page-title">
        <div>
            <span class="eyebrow">Updates</span>
            <h1>Notifications</h1>
            <p>Track reimbursement and payment updates in one place.</p>
        </div>
        <div class="inline-actions">
            <span class="badge"><?= (int) $unreadCount ?> unread</span>
            <?php if ($unreadCount > 0): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="mark_notifications_read">
                    <input type="hidden" name="return_page" value="notifications">
                    <button class="button outline small" type="submit">Mark all as read</button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <section class="grid-2">
        <div class="metric-card">
            <span class="eyebrow">Unread</span>
            <strong><?= (int) $unreadCount ?></strong>
            <span>Needs your attention</span>
        </div>
        <div class="metric-card">
            <span class="eyebrow">Recent</span>
            <strong><?= count($notifications) ?></strong>
            <span>Latest notifications shown</span>
        </div>
    </section>

    <div class="spacer"></div>

    <section class="section-block notifications-shell">
        <div class="split">
            <div>
                <span class="eyebrow">Recent Activity</span>
                <h2>Notification Feed</h2>
            </div>
            <span class="hint">Newest updates appear first.</span>
        </div>
        <div class="spacer"></div>

        <?php if ($notifications): ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notification):
                    $type = strtolower((string) ($notification['type'] ?? 'info'));
                    $typeLabel = $type === 'payment' ? 'Payment' : ($type === 'reimbursement' ? 'Reimbursement' : 'Update');
                    ?>
                    <article class="notification-card <?= empty($notification['is_read']) ? 'unread' : 'read' ?> notification-card-<?= h($type) ?>">
                        <div class="notification-card-head">
                            <div>
                                <strong><?= h((string) $notification['title']) ?></strong>
                                <span class="notification-meta"><?= h($typeLabel) ?></span>
                            </div>
                            <span class="notification-time"><?= h(date('d M Y h:i A', strtotime((string) $notification['created_at']))) ?></span>
                        </div>
                        <p><?= h((string) $notification['message']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="notification-empty">
                <strong>No notifications yet</strong>
                <p>Payment and reimbursement updates will appear here.</p>
            </div>
        <?php endif; ?>
    </section>
    <?php
    render_footer();
}
