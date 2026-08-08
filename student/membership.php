<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('student');

$user = currentUser();

$pageTitle = 'Membership';
$pageSubtitle = 'View your current plan and membership benefits.';

$flash = getFlash();

$query = $pdo->prepare(
    'SELECT
        id,
        full_name,
        email,
        membership_type,
        created_at
     FROM users
     WHERE id = :user_id
     LIMIT 1'
);

$query->execute([
    'user_id' => $user['id'],
]);

$account = $query->fetch();

if (!$account) {
    http_response_code(404);
    exit('Account not found.');
}

$isMember =
    ($account['membership_type'] ?? 'non_member')
    === 'member';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upgrade') {
        $updateQuery = $pdo->prepare(
            'UPDATE users
             SET
                membership_type = :membership_type,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :user_id'
        );

        $updateQuery->execute([
            'membership_type' => 'member',
            'user_id' => $user['id'],
        ]);

        $_SESSION['user']['membership_type'] = 'member';

        setFlash(
            'success',
            'Your membership has been upgraded successfully.'
        );

        redirect('student/membership.php');
    }

    if ($action === 'cancel') {
        $updateQuery = $pdo->prepare(
            'UPDATE users
             SET
                membership_type = :membership_type,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :user_id'
        );

        $updateQuery->execute([
            'membership_type' => 'non_member',
            'user_id' => $user['id'],
        ]);

        $_SESSION['user']['membership_type'] = 'non_member';

        setFlash(
            'success',
            'Your membership has been changed to the standard plan.'
        );

        redirect('student/membership.php');
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

    <title>Membership | <?= escape(APP_NAME) ?></title>

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
                        <h2>Membership</h2>

                        <p>
                            Compare plans and manage your CollabSphere
                            membership.
                        </p>
                    </div>
                </section>

                <section class="membership-status-panel">
                    <div>
                        <span>Current Plan</span>

                        <h3>
                            <?= $isMember
                                ? 'CollabSphere Member'
                                : 'Standard Student' ?>
                        </h3>

                        <p>
                            <?= $isMember
                                ? 'You currently have access to all premium learning and collaboration benefits.'
                                : 'You are currently using the standard student plan.' ?>
                        </p>
                    </div>

                    <span
                        class="badge <?= $isMember
                            ? 'badge-success'
                            : 'badge-warning' ?>"
                    >
                        <?= $isMember
                            ? 'Active Member'
                            : 'Standard Plan' ?>
                    </span>
                </section>

                <section class="membership-plan-grid">
                    <article class="membership-plan-card">
                        <div class="membership-plan-header">
                            <div>
                                <span class="membership-plan-label">
                                    Standard
                                </span>

                                <h3>Student Plan</h3>
                            </div>

                            <?php if (!$isMember): ?>
                                <span class="badge badge-info">
                                    Current Plan
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="membership-price">
                            Free
                        </div>

                        <p class="membership-plan-description">
                            Essential access for enrolled students.
                        </p>

                        <ul class="membership-benefit-list">
                            <li>Join published courses</li>
                            <li>Download course materials</li>
                            <li>Submit assignments</li>
                            <li>View published grades</li>
                            <li>View attendance records</li>
                            <li>Access course announcements</li>
                            <li>Up to three assignment attempts</li>
                        </ul>

                        <?php if ($isMember): ?>
                            <form method="POST">
                                <input
                                    type="hidden"
                                    name="action"
                                    value="cancel"
                                >

                                <button
                                    class="button button-full button-secondary"
                                    type="submit"
                                    onclick="return confirm(
                                        'Switch back to the standard plan?'
                                    );"
                                >
                                    Switch to Standard
                                </button>
                            </form>
                        <?php else: ?>
                            <button
                                class="button button-full button-secondary"
                                type="button"
                                disabled
                            >
                                Current Plan
                            </button>
                        <?php endif; ?>
                    </article>

                    <article class="membership-plan-card membership-plan-featured">
                        <div class="membership-plan-header">
                            <div>
                                <span class="membership-plan-label">
                                    Premium
                                </span>

                                <h3>CollabSphere Member</h3>
                            </div>

                            <?php if ($isMember): ?>
                                <span class="badge badge-success">
                                    Current Plan
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="membership-price">
                            Member
                        </div>

                        <p class="membership-plan-description">
                            Enhanced flexibility and collaboration
                            features.
                        </p>

                        <ul class="membership-benefit-list">
                            <li>Everything in the Student Plan</li>
                            <li>Unlimited assignment resubmissions before deadline</li>
                            <li>Priority collaboration access</li>
                            <li>Enhanced course participation tools</li>
                            <li>Member status displayed on profile</li>
                            <li>Priority academic notifications</li>
                            <li>Access to future premium features</li>
                        </ul>

                        <?php if (!$isMember): ?>
                            <form method="POST">
                                <input
                                    type="hidden"
                                    name="action"
                                    value="upgrade"
                                >

                                <button
                                    class="button button-full"
                                    type="submit"
                                    onclick="return confirm(
                                        'Upgrade your account to member status?'
                                    );"
                                >
                                    Upgrade Membership
                                </button>
                            </form>
                        <?php else: ?>
                            <button
                                class="button button-full"
                                type="button"
                                disabled
                            >
                                Membership Active
                            </button>
                        <?php endif; ?>
                    </article>
                </section>

                <section class="dashboard-panel">
                    <header class="dashboard-panel-header">
                        <div>
                            <h3>Membership Information</h3>

                            <p class="panel-subtitle">
                                Details about your current account plan.
                            </p>
                        </div>
                    </header>

                    <div class="profile-information-grid">
                        <div class="profile-information-item">
                            <span>Account Holder</span>

                            <strong>
                                <?= escape($account['full_name']) ?>
                            </strong>
                        </div>

                        <div class="profile-information-item">
                            <span>Email Address</span>

                            <strong>
                                <?= escape($account['email']) ?>
                            </strong>
                        </div>

                        <div class="profile-information-item">
                            <span>Plan Type</span>

                            <strong>
                                <?= $isMember
                                    ? 'Member'
                                    : 'Non-member' ?>
                            </strong>
                        </div>

                        <div class="profile-information-item">
                            <span>Account Since</span>

                            <strong>
                                <?= escape(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $account['created_at']
                                        )
                                    )
                                ) ?>
                            </strong>
                        </div>
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