<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$pageTitle = 'Announcements';
$pageSubtitle = 'Publish and manage updates for your courses.';

$flash = getFlash();

$selectedCourseId = filter_input(
    INPUT_GET,
    'course_id',
    FILTER_VALIDATE_INT
);

$selectedCourse = null;

if ($selectedCourseId) {
    $courseQuery = $pdo->prepare(
        'SELECT
            id,
            course_code,
            course_name
         FROM courses
         WHERE id = :course_id
           AND instructor_id = :instructor_id
         LIMIT 1'
    );

    $courseQuery->execute([
        'course_id' => $selectedCourseId,
        'instructor_id' => $user['id'],
    ]);

    $selectedCourse = $courseQuery->fetch();

    if (!$selectedCourse) {
        setFlash('error', 'Course not found or access denied.');
        redirect('instructor/announcements.php');
    }

    $pageSubtitle =
        $selectedCourse['course_code']
        . ' — '
        . $selectedCourse['course_name'];
}

$sql = '
    SELECT
        announcements.id,
        announcements.course_id,
        announcements.title,
        announcements.content,
        announcements.created_at,
        announcements.updated_at,
        courses.course_code,
        courses.course_name
    FROM announcements
    INNER JOIN courses
        ON courses.id = announcements.course_id
    WHERE courses.instructor_id = :instructor_id
';

$params = [
    'instructor_id' => $user['id'],
];

if ($selectedCourseId) {
    $sql .= '
        AND courses.id = :course_id
    ';

    $params['course_id'] = $selectedCourseId;
}

$sql .= '
    ORDER BY announcements.created_at DESC
';

$query = $pdo->prepare($sql);
$query->execute($params);

$announcements = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Announcements | <?= escape(APP_NAME) ?></title>

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

                <?php if ($selectedCourse !== null): ?>
                    <a
                        class="back-link"
                        href="<?= APP_URL ?>/instructor/course.php?id=<?= (int) $selectedCourse['id'] ?>"
                    >
                        ← Back to Course
                    </a>
                <?php endif; ?>

                <section class="page-heading-row">
                    <div>
                        <h2>
                            <?= $selectedCourse !== null
                                ? escape(
                                    $selectedCourse['course_code']
                                    . ' Announcements'
                                )
                                : 'Announcements' ?>
                        </h2>

                        <p>
                            Publish important notices and course updates
                            for enrolled students.
                        </p>
                    </div>

                    <a
                        class="button"
                        href="<?= APP_URL ?>/instructor/create-announcement.php<?= $selectedCourseId
                            ? '?course_id=' . $selectedCourseId
                            : '' ?>"
                    >
                        Create Announcement
                    </a>
                </section>

                <?php if ($announcements === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No announcements created</h3>

                            <p>
                                Create your first announcement for
                                enrolled students.
                            </p>

                            <a
                                class="button"
                                href="<?= APP_URL ?>/instructor/create-announcement.php<?= $selectedCourseId
                                    ? '?course_id=' . $selectedCourseId
                                    : '' ?>"
                            >
                                Create First Announcement
                            </a>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="announcement-grid">
                        <?php foreach ($announcements as $announcement): ?>
                            <article class="announcement-card">
                                <div class="announcement-card-header">
                                    <div>
                                        <span class="course-code">
                                            <?= escape(
                                                $announcement['course_code']
                                            ) ?>
                                        </span>

                                        <h3>
                                            <?= escape(
                                                $announcement['title']
                                            ) ?>
                                        </h3>
                                    </div>

                                    <span class="announcement-date">
                                        <?= escape(
                                            date(
                                                'd M Y, g:i A',
                                                strtotime(
                                                    $announcement[
                                                        'created_at'
                                                    ]
                                                )
                                            )
                                        ) ?>
                                    </span>
                                </div>

                                <div class="announcement-content">
                                    <?= nl2br(
                                        escape(
                                            $announcement['content']
                                        )
                                    ) ?>
                                </div>

                                <div class="announcement-footer">
                                    <span>
                                        <?= $announcement['updated_at']
                                            !== $announcement['created_at']
                                                ? 'Updated '
                                                    . escape(
                                                        date(
                                                            'd M Y, g:i A',
                                                            strtotime(
                                                                $announcement[
                                                                    'updated_at'
                                                                ]
                                                            )
                                                        )
                                                    )
                                                : 'Published immediately' ?>
                                    </span>

                                    <div class="announcement-actions">
                                        <a
                                            class="button button-small button-secondary"
                                            href="<?= APP_URL ?>/instructor/edit-announcement.php?id=<?= (int) $announcement['id'] ?>"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            method="POST"
                                            action="<?= APP_URL ?>/instructor/delete-announcement.php"
                                            onsubmit="return confirm(
                                                'Delete this announcement permanently?'
                                            );"
                                        >
                                            <input
                                                type="hidden"
                                                name="announcement_id"
                                                value="<?= (int) $announcement['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="course_id"
                                                value="<?= (int) $announcement['course_id'] ?>"
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