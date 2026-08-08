<?php

declare(strict_types=1);

$user = currentUser();

$nameParts = preg_split('/\s+/', trim($user['full_name']));
$initials = '';

foreach (array_slice($nameParts, 0, 2) as $namePart) {
    $initials .= strtoupper(substr($namePart, 0, 1));
}

/*
|--------------------------------------------------------------------------
| Unread Notifications
|--------------------------------------------------------------------------
*/

$notificationQuery = $pdo->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE user_id = :user_id
       AND is_read = 0'
);

$notificationQuery->execute([
    'user_id' => $user['id'],
]);

$notificationCount = (int) $notificationQuery->fetchColumn();

?>

<header class="app-topbar">
    <div class="topbar-left">
        <button
            type="button"
            class="mobile-menu-button"
            id="mobile-menu-button"
            aria-label="Open navigation"
        >
            ☰
        </button>

        <div>
            <h1><?= escape($pageTitle ?? 'Dashboard') ?></h1>

            <p>
                <?= escape(
                    $pageSubtitle ??
                    'Manage your CollabSphere workspace.'
                ) ?>
            </p>
        </div>
    </div>

    <div class="topbar-right">

        <a
            class="notification-button"
            href="<?= APP_URL ?>/notifications.php"
            title="Notifications"
        >
            <span>Notifications</span>

            <?php if ($notificationCount > 0): ?>
                <span class="notification-badge">
                    <?= $notificationCount ?>
                </span>
            <?php endif; ?>
        </a>

        <div class="user-profile-chip">
            <div class="user-initials">
                <?= escape($initials) ?>
            </div>

            <div class="user-profile-details">
                <strong>
                    <?= escape($user['full_name']) ?>
                </strong>

                <span>
                    <?= escape(ucfirst($user['role'])) ?>
                </span>
            </div>
        </div>
    </div>
</header>