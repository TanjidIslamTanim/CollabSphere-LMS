<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireLogin();

$user = currentUser();

$pageTitle = 'Settings';
$pageSubtitle = 'Manage your account preferences.';

$flash = getFlash();

$settingsQuery = $pdo->prepare(
    'SELECT
        email_notifications,
        assignment_notifications,
        announcement_notifications
     FROM users
     WHERE id = :user_id
     LIMIT 1'
);

$settingsQuery->execute([
    'user_id' => $user['id'],
]);

$settings = $settingsQuery->fetch();

if (!$settings) {
    http_response_code(404);
    exit('User not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailNotifications =
        isset($_POST['email_notifications']) ? 1 : 0;

    $assignmentNotifications =
        isset($_POST['assignment_notifications']) ? 1 : 0;

    $announcementNotifications =
        isset($_POST['announcement_notifications']) ? 1 : 0;

    $updateQuery = $pdo->prepare(
        'UPDATE users
         SET
            email_notifications = :email_notifications,
            assignment_notifications = :assignment_notifications,
            announcement_notifications = :announcement_notifications,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :user_id'
    );

    $updateQuery->execute([
        'email_notifications' => $emailNotifications,
        'assignment_notifications' => $assignmentNotifications,
        'announcement_notifications' => $announcementNotifications,
        'user_id' => $user['id'],
    ]);

    setFlash(
        'success',
        'Settings updated successfully.'
    );

    redirect('profile/settings.php');
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

    <title>Settings | <?= escape(APP_NAME) ?></title>

    <link
        rel="stylesheet"
        href="<?= APP_URL ?>/public/assets/css/app.css"
    >
</head>

<body>
    <div class="application-layout">
        <?php require __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="application-main">
            <?php require __DIR__ . '/../includes/topbar.php'; ?>

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
                        <h2>Settings</h2>

                        <p>
                            Manage your notification preferences.
                        </p>
                    </div>
                </section>

                <section class="dashboard-panel settings-panel">
                    <header class="dashboard-panel-header">
                        <div>
                            <h3>Notification Preferences</h3>

                            <p class="panel-subtitle">
                                Choose which alerts you want to receive.
                            </p>
                        </div>
                    </header>

                    <div class="dashboard-panel-body">
                        <form method="POST">
                            <div class="settings-group">
                                <label class="switch-row">
                                    <div>
                                        <strong>
                                            Email Notifications
                                        </strong>

                                        <span>
                                            Receive general account
                                            notifications by email.
                                        </span>
                                    </div>

                                    <input
                                        type="checkbox"
                                        name="email_notifications"
                                        value="1"
                                        <?= (bool) $settings[
                                            'email_notifications'
                                        ]
                                            ? 'checked'
                                            : '' ?>
                                    >
                                </label>

                                <label class="switch-row">
                                    <div>
                                        <strong>
                                            Assignment Reminders
                                        </strong>

                                        <span>
                                            Receive reminders about
                                            assignments and deadlines.
                                        </span>
                                    </div>

                                    <input
                                        type="checkbox"
                                        name="assignment_notifications"
                                        value="1"
                                        <?= (bool) $settings[
                                            'assignment_notifications'
                                        ]
                                            ? 'checked'
                                            : '' ?>
                                    >
                                </label>

                                <label class="switch-row">
                                    <div>
                                        <strong>
                                            Announcement Alerts
                                        </strong>

                                        <span>
                                            Receive alerts when a new course
                                            announcement is posted.
                                        </span>
                                    </div>

                                    <input
                                        type="checkbox"
                                        name="announcement_notifications"
                                        value="1"
                                        <?= (bool) $settings[
                                            'announcement_notifications'
                                        ]
                                            ? 'checked'
                                            : '' ?>
                                    >
                                </label>
                            </div>

                            <div class="settings-actions">
                                <a
                                    class="button button-secondary"
                                    href="<?= APP_URL ?>/profile/index.php"
                                >
                                    Back to Profile
                                </a>

                                <button type="submit">
                                    Save Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <script
        src="<?= APP_URL ?>/public/assets/js/app.js"
    ></script>
</body>
</html>