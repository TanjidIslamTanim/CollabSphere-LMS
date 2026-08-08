<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$announcementId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$announcementId) {
    setFlash('error', 'Invalid announcement selected.');
    redirect('instructor/announcements.php');
}

$query = $pdo->prepare(
    'SELECT
        announcements.*,
        courses.course_code,
        courses.course_name
     FROM announcements
     INNER JOIN courses
        ON courses.id = announcements.course_id
     WHERE announcements.id = :announcement_id
       AND courses.instructor_id = :instructor_id
     LIMIT 1'
);

$query->execute([
    'announcement_id' => $announcementId,
    'instructor_id' => $user['id'],
]);

$announcement = $query->fetch();

if (!$announcement) {
    http_response_code(404);
    exit('Announcement not found or access denied.');
}

$pageTitle = 'Edit Announcement';
$pageSubtitle =
    $announcement['course_code']
    . ' — '
    . $announcement['course_name'];

$errors = [];

$title = $announcement['title'];
$content = $announcement['content'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (strlen($title) < 3) {
        $errors[] =
            'Announcement title must contain at least 3 characters.';
    }

    if (strlen($title) > 180) {
        $errors[] =
            'Announcement title must not exceed 180 characters.';
    }

    if (strlen($content) < 5) {
        $errors[] =
            'Announcement content must contain at least 5 characters.';
    }

    if ($errors === []) {
        $updateQuery = $pdo->prepare(
            'UPDATE announcements
             SET
                title = :title,
                content = :content,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :announcement_id
               AND posted_by = :posted_by'
        );

        $updateQuery->execute([
            'title' => $title,
            'content' => $content,
            'announcement_id' => $announcementId,
            'posted_by' => $user['id'],
        ]);

        setFlash(
            'success',
            'Announcement updated successfully.'
        );

        redirect(
            'instructor/announcements.php?course_id='
            . (int) $announcement['course_id']
        );
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

    <title>Edit Announcement | <?= escape(APP_NAME) ?></title>

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
                        href="<?= APP_URL ?>/instructor/announcements.php?course_id=<?= (int) $announcement['course_id'] ?>"
                    >
                        ← Back to Announcements
                    </a>

                    <section class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>Edit Announcement</h3>

                                <p class="panel-subtitle">
                                    Update the announcement title and
                                    content.
                                </p>
                            </div>
                        </header>

                        <div class="dashboard-panel-body">
                            <?php if ($errors !== []): ?>
                                <div class="alert alert-error">
                                    <ul class="error-list">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= escape($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form method="POST" novalidate>
                                <div class="form-grid">
                                    <div class="form-group full">
                                        <label>Course</label>

                                        <input
                                            type="text"
                                            value="<?= escape(
                                                $announcement['course_code']
                                                . ' — '
                                                . $announcement['course_name']
                                            ) ?>"
                                            disabled
                                        >
                                    </div>

                                    <div class="form-group full">
                                        <label for="title">
                                            Announcement Title
                                        </label>

                                        <input
                                            id="title"
                                            name="title"
                                            type="text"
                                            value="<?= escape($title) ?>"
                                            maxlength="180"
                                            required
                                        >
                                    </div>

                                    <div class="form-group full">
                                        <label for="content">
                                            Announcement Content
                                        </label>

                                        <textarea
                                            id="content"
                                            name="content"
                                            rows="9"
                                            required
                                        ><?= escape($content) ?></textarea>
                                    </div>

                                    <div class="form-group full form-actions">
                                        <a
                                            class="button button-secondary"
                                            href="<?= APP_URL ?>/instructor/announcements.php?course_id=<?= (int) $announcement['course_id'] ?>"
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