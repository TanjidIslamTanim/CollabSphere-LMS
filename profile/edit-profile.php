<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireLogin();

$user = currentUser();

$pageTitle = 'Edit Profile';
$pageSubtitle = 'Update your personal account information.';

$errors = [];
$flash = getFlash();

$query = $pdo->prepare(
    'SELECT
        id,
        full_name,
        email,
        role,
        membership_type
     FROM users
     WHERE id = :user_id
     LIMIT 1'
);

$query->execute([
    'user_id' => $user['id'],
]);

$profile = $query->fetch();

if (!$profile) {
    http_response_code(404);
    exit('Profile not found.');
}

$fullName = (string) $profile['full_name'];
$email = (string) $profile['email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (strlen($fullName) < 3) {
        $errors[] = 'Full name must contain at least 3 characters.';
    }

    if (strlen($fullName) > 120) {
        $errors[] = 'Full name must not exceed 120 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (strlen($email) > 190) {
        $errors[] = 'Email address must not exceed 190 characters.';
    }

    if ($errors === []) {
        $emailQuery = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE email = :email
               AND id != :user_id
             LIMIT 1'
        );

        $emailQuery->execute([
            'email' => $email,
            'user_id' => $user['id'],
        ]);

        if ($emailQuery->fetch()) {
            $errors[] =
                'This email address is already used by another account.';
        }
    }

    if ($errors === []) {
        $updateQuery = $pdo->prepare(
            'UPDATE users
             SET
                full_name = :full_name,
                email = :email,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :user_id'
        );

        $updateQuery->execute([
            'full_name' => $fullName,
            'email' => $email,
            'user_id' => $user['id'],
        ]);

        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['email'] = $email;

        setFlash(
            'success',
            'Profile updated successfully.'
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

    <title>Edit Profile | <?= escape(APP_NAME) ?></title>

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
                                <h3>Edit Profile</h3>

                                <p class="panel-subtitle">
                                    Update your name and email address.
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
                                        <label for="full_name">
                                            Full Name
                                        </label>

                                        <input
                                            id="full_name"
                                            name="full_name"
                                            type="text"
                                            value="<?= escape($fullName) ?>"
                                            maxlength="120"
                                            required
                                        >
                                    </div>

                                    <div class="form-group full">
                                        <label for="email">
                                            Email Address
                                        </label>

                                        <input
                                            id="email"
                                            name="email"
                                            type="email"
                                            value="<?= escape($email) ?>"
                                            maxlength="190"
                                            required
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label>
                                            Account Role
                                        </label>

                                        <input
                                            type="text"
                                            value="<?= escape(
                                                ucfirst(
                                                    (string) $profile['role']
                                                )
                                            ) ?>"
                                            disabled
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label>
                                            Membership
                                        </label>

                                        <input
                                            type="text"
                                            value="<?= escape(
                                                ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        (string) (
                                                            $profile[
                                                                'membership_type'
                                                            ]
                                                            ?? 'non_member'
                                                        )
                                                    )
                                                )
                                            ) ?>"
                                            disabled
                                        >
                                    </div>

                                    <div class="form-group full form-actions">
                                        <a
                                            class="button button-secondary"
                                            href="<?= APP_URL ?>/profile/index.php"
                                        >
                                            Cancel
                                        </a>

                                        <button type="submit">
                                            Save Changes
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
</body>
</html>