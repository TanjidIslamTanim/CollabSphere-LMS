<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireLogin();

$user = currentUser();

$pageTitle = 'My Profile';
$pageSubtitle = 'View and manage your CollabSphere account.';

$profileQuery = $pdo->prepare(
    'SELECT
        id,
        full_name,
        email,
        role,
        membership_type,
        created_at
     FROM users
     WHERE id = :user_id
     LIMIT 1'
);

$profileQuery->execute([
    'user_id' => $user['id'],
]);

$profile = $profileQuery->fetch();

if (!$profile) {
    http_response_code(404);
    exit('Profile not found.');
}

$initials = '';

$nameParts = preg_split(
    '/\s+/',
    trim((string) $profile['full_name'])
);

if (is_array($nameParts) && $nameParts !== []) {
    $initials = strtoupper(
        substr($nameParts[0], 0, 1)
    );

    if (count($nameParts) > 1) {
        $initials .= strtoupper(
            substr(
                $nameParts[count($nameParts) - 1],
                0,
                1
            )
        );
    }
}

$roleLabel = ucfirst(
    (string) $profile['role']
);

$membershipLabel = ucwords(
    str_replace(
        '_',
        ' ',
        (string) (
            $profile['membership_type']
            ?? 'non_member'
        )
    )
);

$accountCreated = !empty($profile['created_at'])
    ? date(
        'd M Y',
        strtotime((string) $profile['created_at'])
    )
    : 'Not available';
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
        My Profile | <?= escape(APP_NAME) ?>
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
                <section class="page-heading-row">
                    <div>
                        <h2>My Profile</h2>

                        <p>
                            Review your personal details and account
                            information.
                        </p>
                    </div>
                </section>

                <section class="profile-layout">
                    <article class="profile-summary-card">
                        <div class="profile-avatar-large">
                            <?= escape($initials ?: 'U') ?>
                        </div>

                        <h2>
                            <?= escape($profile['full_name']) ?>
                        </h2>

                        <p class="profile-email">
                            <?= escape($profile['email']) ?>
                        </p>

                        <div class="profile-badges">
                            <span class="badge badge-info">
                                <?= escape($roleLabel) ?>
                            </span>

                            <span
                                class="badge <?= (
                                    $profile['membership_type']
                                    ?? 'non_member'
                                ) === 'member'
                                    ? 'badge-success'
                                    : 'badge-warning' ?>"
                            >
                                <?= escape($membershipLabel) ?>
                            </span>
                        </div>

                        <div class="profile-summary-actions">
                            <a
                                class="button button-full"
                                href="<?= APP_URL ?>/profile/edit-profile.php"
                            >
                                Edit Profile
                            </a>

                            <a
                                class="button button-full button-secondary"
                                href="<?= APP_URL ?>/profile/change-password.php"
                            >
                                Change Password
                            </a>
                        </div>
                    </article>

                    <div class="profile-content-column">
                        <article class="dashboard-panel">
                            <header class="dashboard-panel-header">
                                <div>
                                    <h3>Personal Information</h3>

                                    <p class="panel-subtitle">
                                        Basic information linked to your
                                        CollabSphere account.
                                    </p>
                                </div>
                            </header>

                            <div class="profile-information-grid">
                                <div class="profile-information-item">
                                    <span>Full Name</span>

                                    <strong>
                                        <?= escape(
                                            $profile['full_name']
                                        ) ?>
                                    </strong>
                                </div>

                                <div class="profile-information-item">
                                    <span>Email Address</span>

                                    <strong>
                                        <?= escape(
                                            $profile['email']
                                        ) ?>
                                    </strong>
                                </div>

                                <div class="profile-information-item">
                                    <span>Account Role</span>

                                    <strong>
                                        <?= escape($roleLabel) ?>
                                    </strong>
                                </div>

                                <div class="profile-information-item">
                                    <span>Membership</span>

                                    <strong>
                                        <?= escape($membershipLabel) ?>
                                    </strong>
                                </div>
                            </div>
                        </article>

                        <article class="dashboard-panel">
                            <header class="dashboard-panel-header">
                                <div>
                                    <h3>Account Information</h3>

                                    <p class="panel-subtitle">
                                        Account status and registration
                                        details.
                                    </p>
                                </div>
                            </header>

                            <div class="profile-information-grid">
                                <div class="profile-information-item">
                                    <span>Account ID</span>

                                    <strong>
                                        CS-<?= str_pad(
                                            (string) $profile['id'],
                                            5,
                                            '0',
                                            STR_PAD_LEFT
                                        ) ?>
                                    </strong>
                                </div>

                                <div class="profile-information-item">
                                    <span>Member Since</span>

                                    <strong>
                                        <?= escape($accountCreated) ?>
                                    </strong>
                                </div>

                                <div class="profile-information-item">
                                    <span>Account Status</span>

                                    <strong class="profile-active-status">
                                        Active
                                    </strong>
                                </div>

                                <div class="profile-information-item">
                                    <span>Access Level</span>

                                    <strong>
                                        <?= escape(
                                            $roleLabel
                                            . ' Workspace'
                                        ) ?>
                                    </strong>
                                </div>
                            </div>
                        </article>

                        <article class="profile-security-panel">
                            <div>
                                <h3>Account Security</h3>

                                <p>
                                    Keep your password private and update
                                    it regularly to protect your account.
                                </p>
                            </div>

                            <a
                                class="button button-secondary"
                                href="<?= APP_URL ?>/profile/change-password.php"
                            >
                                Manage Password
                            </a>
                        </article>
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