<?php

declare(strict_types=1);

$dashboardNotificationQuery = $pdo->prepare(
    'SELECT
        id,
        title,
        message,
        type,
        reference_id,
        is_read,
        created_at
     FROM notifications
     WHERE user_id = :user_id
     ORDER BY created_at DESC
     LIMIT 5'
);

$dashboardNotificationQuery->execute([
    'user_id' => $user['id'],
]);

$dashboardNotifications =
    $dashboardNotificationQuery->fetchAll();
?>

<article class="dashboard-panel dashboard-notification-panel">
    <header class="dashboard-panel-header">
        <div>
            <h3>Recent Notifications</h3>

            <p class="panel-subtitle">
                Your latest academic and account updates.
            </p>
        </div>

        <a
            class="button button-small button-secondary"
            href="<?= APP_URL ?>/notifications.php"
        >
            View All
        </a>
    </header>

    <?php if ($dashboardNotifications === []): ?>
        <div class="professional-empty-state compact-empty-state">
            <h3>No notifications</h3>

            <p>
                You are completely up to date.
            </p>
        </div>
    <?php else: ?>
        <div class="dashboard-notification-list">
            <?php foreach (
                $dashboardNotifications as $notification
            ): ?>
                <?php
                $notificationClass = match (
                    $notification['type']
                ) {
                    'assignment' => 'notification-assignment',
                    'announcement' => 'notification-announcement',
                    'submission' => 'notification-submission',
                    'grade' => 'notification-grade',
                    'attendance' => 'notification-attendance',
                    'course' => 'notification-course',
                    default => 'notification-general',
                };
                ?>

                <a
                    class="dashboard-notification-item <?= !(bool) $notification['is_read']
                        ? 'unread'
                        : '' ?>"
                    href="<?= APP_URL ?>/notifications.php"
                >
                    <span
                        class="dashboard-notification-indicator <?= $notificationClass ?>"
                    ></span>

                    <div class="dashboard-notification-content">
                        <div class="dashboard-notification-title">
                            <strong>
                                <?= escape(
                                    $notification['title']
                                ) ?>
                            </strong>

                            <?php if (
                                !(bool) $notification['is_read']
                            ): ?>
                                <span class="notification-new-label">
                                    New
                                </span>
                            <?php endif; ?>
                        </div>

                        <p>
                            <?= escape(
                                $notification['message']
                            ) ?>
                        </p>

                        <time>
                            <?= escape(
                                date(
                                    'd M Y, g:i A',
                                    strtotime(
                                        $notification[
                                            'created_at'
                                        ]
                                    )
                                )
                            ) ?>
                        </time>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</article>