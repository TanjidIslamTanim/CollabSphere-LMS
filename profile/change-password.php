<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireLogin();

$user = currentUser();

$pageTitle = 'Change Password';
$pageSubtitle = 'Update your account password securely.';

$errors = [];

$currentPassword = '';
$newPassword = '';
$confirmPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($currentPassword === '') {
        $errors[] = 'Please enter your current password.';
    }

    if (strlen($newPassword) < 8) {
        $errors[] =
            'New password must contain at least 8 characters.';
    }

    if (!preg_match('/[A-Z]/', $newPassword)) {
        $errors[] =
            'New password must include at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $newPassword)) {
        $errors[] =
            'New password must include at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $newPassword)) {
        $errors[] =
            'New password must include at least one number.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] =
            'New password and confirmation do not match.';
    }

    if ($currentPassword === $newPassword) {
        $errors[] =
            'New password must be different from your current password.';
    }

    if ($errors === []) {
        $passwordQuery = $pdo->prepare(
            'SELECT password_hash
             FROM users
             WHERE id = :user_id
             LIMIT 1'
        );

        $passwordQuery->execute([
            'user_id' => $user['id'],
        ]);

        $account = $passwordQuery->fetch();

        if (
            !$account
            || !password_verify(
                $currentPassword,
                (string) $account['password_hash']
            )
        ) {
            $errors[] = 'Your current password is incorrect.';
        }
    }

    if ($errors === []) {
        $newPasswordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        $updateQuery = $pdo->prepare(
            'UPDATE users
             SET
                password_hash = :password_hash,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :user_id'
        );

        $updateQuery->execute([
            'password_hash' => $newPasswordHash,
            'user_id' => $user['id'],
        ]);

        setFlash(
            'success',
            'Password changed successfully.'
        );

        redirect('profile/index.php');
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

    <title>
        Change Password | <?= escape(APP_NAME) ?>
    </title>

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
                <div class="form-page-container">
                    <a
                        class="back-link"
                        href="<?= APP_URL ?>/profile/index.php"
                    >
                        ← Back to Profile
                    </a>

                    <section class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>Change Password</h3>

                                <p class="panel-subtitle">
                                    Enter your current password and
                                    choose a new secure password.
                                </p>
                            </div>
                        </header>

                        <div class="dashboard-panel-body">
                            <?php if ($errors !== []): ?>
                                <div class="alert alert-error">
                                    <ul class="error-list">
                                        <?php foreach ($errors as $error): ?>
                                            <li>
                                                <?= escape($error) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" novalidate>
                                <div class="form-grid">
                                    <div class="form-group full">
                                        <label for="current_password">
                                            Current Password
                                        </label>

                                        <div class="password-input-wrapper">
                                            <input
                                                id="current_password"
                                                name="current_password"
                                                type="password"
                                                autocomplete="current-password"
                                                required
                                            >

                                            <button
                                                class="password-toggle"
                                                type="button"
                                                data-password-toggle="current_password"
                                            >
                                                Show
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-group full">
                                        <label for="new_password">
                                            New Password
                                        </label>

                                        <div class="password-input-wrapper">
                                            <input
                                                id="new_password"
                                                name="new_password"
                                                type="password"
                                                autocomplete="new-password"
                                                minlength="8"
                                                required
                                            >

                                            <button
                                                class="password-toggle"
                                                type="button"
                                                data-password-toggle="new_password"
                                            >
                                                Show
                                            </button>
                                        </div>

                                        <small class="field-help">
                                            Use at least 8 characters with
                                            uppercase, lowercase and a number.
                                        </small>
                                    </div>

                                    <div class="form-group full">
                                        <label for="confirm_password">
                                            Confirm New Password
                                        </label>

                                        <div class="password-input-wrapper">
                                            <input
                                                id="confirm_password"
                                                name="confirm_password"
                                                type="password"
                                                autocomplete="new-password"
                                                minlength="8"
                                                required
                                            >

                                            <button
                                                class="password-toggle"
                                                type="button"
                                                data-password-toggle="confirm_password"
                                            >
                                                Show
                                            </button>
                                        </div>
                                    </div>

                                    <div class="form-group full form-actions">
                                        <a
                                            class="button button-secondary"
                                            href="<?= APP_URL ?>/profile/index.php"
                                        >
                                            Cancel
                                        </a>

                                        <button type="submit">
                                            Update Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script
        src="<?= APP_URL ?>/public/assets/js/app.js"
    ></script>

    <script>
        document
            .querySelectorAll('[data-password-toggle]')
            .forEach((button) => {
                button.addEventListener('click', () => {
                    const inputId = button.dataset.passwordToggle;
                    const input = document.getElementById(inputId);

                    if (!input) {
                        return;
                    }

                    const showing = input.type === 'text';

                    input.type = showing ? 'password' : 'text';
                    button.textContent = showing ? 'Show' : 'Hide';
                });
            });
    </script>
</body>
</html>