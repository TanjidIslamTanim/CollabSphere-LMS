<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';
require_once __DIR__ . '/../app/Helpers/notification.php';

requireRole('instructor');

$user = currentUser();

$pageTitle = 'Create Announcement';
$pageSubtitle = 'Publish an update for enrolled students.';

$errors = [];

$courseId = '';

$requestedCourseId = filter_input(
    INPUT_GET,
    'course_id',
    FILTER_VALIDATE_INT
);

if ($requestedCourseId) {
    $courseId = (string) $requestedCourseId;
}

$title = '';
$content = '';

$courseQuery = $pdo->prepare(
    'SELECT
        id,
        course_code,
        course_name
     FROM courses
     WHERE instructor_id = :instructor_id
     ORDER BY course_name ASC'
);

$courseQuery->execute([
    'instructor_id' => $user['id'],
]);

$courses = $courseQuery->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = trim($_POST['course_id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!ctype_digit($courseId)) {
        $errors[] = 'Please select a valid course.';
    }

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
        $ownershipQuery = $pdo->prepare(
            'SELECT id
             FROM courses
             WHERE id = :course_id
               AND instructor_id = :instructor_id
             LIMIT 1'
        );

        $ownershipQuery->execute([
            'course_id' => (int) $courseId,
            'instructor_id' => $user['id'],
        ]);

        if (!$ownershipQuery->fetch()) {
            $errors[] =
                'The selected course does not belong to you.';
        }
    }

    if ($errors === []) {
        $insertQuery = $pdo->prepare(
            'INSERT INTO announcements
                (
                    course_id,
                    posted_by,
                    title,
                    content
                )
             VALUES
                (
                    :course_id,
                    :posted_by,
                    :title,
                    :content
                )'
        );

        $insertQuery->execute([
            'course_id' => (int) $courseId,
            'posted_by' => $user['id'],
            'title' => $title,
            'content' => $content,
        ]);

        $announcementId = (int) $pdo->lastInsertId();

        $studentQuery = $pdo->prepare(
            'SELECT student_id
             FROM enrollments
             WHERE course_id = :course_id'
        );

        $studentQuery->execute([
            'course_id' => (int) $courseId,
        ]);

        foreach ($studentQuery->fetchAll() as $student) {

            createNotification(
                $pdo,
                (int) $student['student_id'],
                'New Announcement',
                $title,
                'announcement',
                $announcementId
            );

        }

        setFlash(
            'success',
            'Announcement published successfully.'
        );

        redirect(
            'instructor/announcements.php?course_id='
            . (int) $courseId
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

    <title>
        Create Announcement | <?= escape(APP_NAME) ?>
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
                        href="<?= APP_URL ?>/instructor/announcements.php<?= ctype_digit($courseId)
                            ? '?course_id=' . (int) $courseId
                            : '' ?>"
                    >
                        ← Back to Announcements
                    </a>

                    <section class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>Create Announcement</h3>

                                <p class="panel-subtitle">
                                    This announcement becomes visible to
                                    enrolled students immediately.
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

                            <?php if ($courses === []): ?>
                                <div class="alert alert-error">
                                    Create a course before publishing an
                                    announcement.
                                </div>
                            <?php else: ?>
                                <form method="POST" novalidate>
                                    <div class="form-grid">
                                        <div class="form-group full">
                                            <label for="course_id">
                                                Course
                                            </label>

                                            <select
                                                id="course_id"
                                                name="course_id"
                                                required
                                            >
                                                <option value="">
                                                    Select a course
                                                </option>

                                                <?php foreach ($courses as $course): ?>
                                                    <option
                                                        value="<?= (int) $course['id'] ?>"
                                                        <?= (string) $course['id']
                                                            === $courseId
                                                            ? 'selected'
                                                            : '' ?>
                                                    >
                                                        <?= escape(
                                                            $course['course_code']
                                                            . ' — '
                                                            . $course['course_name']
                                                        ) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
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
                                                placeholder="Example: Midterm Examination Update"
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
                                                placeholder="Write the announcement details."
                                                required
                                            ><?= escape($content) ?></textarea>
                                        </div>

                                        <div class="form-group full form-actions">
                                            <a
                                                class="button button-secondary"
                                                href="<?= APP_URL ?>/instructor/announcements.php<?= ctype_digit($courseId)
                                                    ? '?course_id=' . (int) $courseId
                                                    : '' ?>"
                                            >
                                                Cancel
                                            </a>

                                            <button type="submit">
                                                Publish Announcement
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
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