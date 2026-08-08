<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Helpers/auth.php';

requireLogin();

$user = currentUser();

$pageTitle = 'Notifications';
$pageSubtitle = 'Review your latest academic and account updates.';

$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_read') {
        $query = $pdo->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE user_id = :user_id'
        );

        $query->execute([
            'user_id' => $user['id'],
        ]);

        setFlash(
            'success',
            'All notifications marked as read.'
        );

        redirect('notifications.php');
    }

    if ($action === 'delete_all') {
        $query = $pdo->prepare(
            'DELETE FROM notifications
             WHERE user_id = :user_id'
        );

        $query->execute([
            'user_id' => $user['id'],
        ]);

        setFlash(
            'success',
            'All notifications deleted.'
        );

        redirect('notifications.php');
    }

    $notificationId = filter_input(
        INPUT_POST,
        'notification_id',
        FILTER_VALIDATE_INT
    );

    if (!$notificationId) {
        setFlash('error', 'Invalid notification selected.');
        redirect('notifications.php');
    }

    if ($action === 'mark_read') {
        $query = $pdo->prepare(
            'UPDATE notifications
             SET is_read = 1
             WHERE id = :notification_id
               AND user_id = :user_id'
        );

        $query->execute([
            'notification_id' => $notificationId,
            'user_id' => $user['id'],
        ]);

        setFlash(
            'success',
            'Notification marked as read.'
        );

        redirect('notifications.php');
    }

    if ($action === 'delete') {
        $query = $pdo->prepare(
            'DELETE FROM notifications
             WHERE id = :notification_id
               AND user_id = :user_id'
        );

        $query->execute([
            'notification_id' => $notificationId,
            'user_id' => $user['id'],
        ]);

        setFlash(
            'success',
            'Notification deleted.'
        );

        redirect('notifications.php');
    }
}

$query = $pdo->prepare(
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
     ORDER BY created_at DESC'
);

$query->execute([
    'user_id' => $user['id'],
]);

$notifications = $query->fetchAll();

$unreadCount = 0;

foreach ($notifications as $notification) {
    if (!(bool) $notification['is_read']) {
        $unreadCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Notifications | <?= escape(APP_NAME) ?></title>

    <link
        rel="stylesheet"
        href="<?= APP_URL ?>/public/assets/css/app.css"
    >
</head>

<body>
    <div class="application-layout">
        <?php require __DIR__ . '/includes/sidebar.php'; ?>

        <div class="application-main">
            <?php require __DIR__ . '/includes/topbar.php'; ?>

            <main class="application-content">
                <?php if ($flash !== null): ?>
                    <div
                        class="alert <?= $flash['type'] === 'success'
                            ? 'alert-success'
                            : 'alert-error' ?>"
                    >
                        <?= escape($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <section class="page-heading-row">
                    <div>
                        <h2>Notifications</h2>

                        <p>
                            <?= $unreadCount ?>
                            unread notification<?= $unreadCount === 1
                                ? ''
                                : 's' ?>.
                        </p>
                    </div>

                    <?php if ($notifications !== []): ?>
                        <div class="notification-page-actions">
                            <?php if ($unreadCount > 0): ?>
                                <form method="POST">
                                    <input
                                        type="hidden"
                                        name="action"
                                        value="mark_all_read"
                                    >

                                    <button
                                        class="button button-secondary"
                                        type="submit"
                                    >
                                        Mark All Read
                                    </button>
                                </form>
                            <?php endif; ?>

                            <form
                                method="POST"
                                onsubmit="return confirm(
                                    'Delete all notifications permanently?'
                                );"
                            >
                                <input
                                    type="hidden"
                                    name="action"
                                    value="delete_all"
                                >

                                <button
                                    class="button button-danger"
                                    type="submit"
                                >
                                    Delete All
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </section>

                <?php if ($notifications === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No notifications</h3>

                            <p>
                                You are currently up to date.
                            </p>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="notification-page-list">
                        <?php foreach ($notifications as $notification): ?>
                            <?php
                            $typeClass = match (
                                $notification['type']
                            ) {
                                'assignment' =>
                                    'notification-assignment',
                                'announcement' =>
                                    'notification-announcement',
                                'submission' =>
                                    'notification-submission',
                                'grade' =>
                                    'notification-grade',
                                'attendance' =>
                                    'notification-attendance',
                                'course' =>
                                    'notification-course',
                                default =>
                                    'notification-general',
                            };
                            ?>

                            <article
                                class="notification-page-card <?= !(bool) $notification['is_read']
                                    ? 'unread'
                                    : '' ?>"
                            >
                                <span
                                    class="notification-page-indicator <?= $typeClass ?>"
                                ></span>

                                <div class="notification-page-content">
                                    <div class="notification-page-header">
                                        <div>
                                            <h3>
                                                <?= escape(
                                                    $notification['title']
                                                ) ?>
                                            </h3>

                                            <?php if (
                                                !(bool) $notification['is_read']
                                            ): ?>
                                                <span class="notification-new-label">
                                                    New
                                                </span>
                                            <?php endif; ?>
                                        </div>

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

                                    <p>
                                        <?= escape(
                                            $notification['message']
                                        ) ?>
                                    </p>

                                    <div class="notification-card-actions">
                                        <?php if (
                                            !(bool) $notification['is_read']
                                        ): ?>
                                            <form method="POST">
                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="mark_read"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="notification_id"
                                                    value="<?= (int) $notification['id'] ?>"
                                                >

                                                <button
                                                    class="button button-small button-secondary"
                                                    type="submit"
                                                >
                                                    Mark Read
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm(
                                                'Delete this notification?'
                                            );"
                                        >
                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <input
                                                type="hidden"
                                                name="notification_id"
                                                value="<?= (int) $notification['id'] ?>"
                                            >

                                            <button
                                                class="button button-small button-danger"
                                                type="submit"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script
        src="<?= APP_URL ?>/public/assets/js/app.js"
    ></script>
</body>
</html>