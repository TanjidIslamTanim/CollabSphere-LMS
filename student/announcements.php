<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('student');

$user = currentUser();

$pageTitle = 'Announcements';
$pageSubtitle = 'View notices from your enrolled courses.';

$query = $pdo->prepare(
    'SELECT
        announcements.id,
        announcements.title,
        announcements.content,
        announcements.created_at,
        announcements.updated_at,
        courses.course_code,
        courses.course_name,
        users.full_name AS instructor_name
     FROM announcements
     INNER JOIN courses
        ON courses.id = announcements.course_id
     INNER JOIN enrollments
        ON enrollments.course_id = courses.id
     INNER JOIN users
        ON users.id = announcements.posted_by
     WHERE enrollments.student_id = :student_id
     ORDER BY announcements.created_at DESC'
);

$query->execute([
    'student_id' => $user['id'],
]);

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
                <section class="page-heading-row">
                    <div>
                        <h2>Course Announcements</h2>

                        <p>
                            View updates and important notices from your
                            instructors.
                        </p>
                    </div>
                </section>

                <?php if ($announcements === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No announcements available</h3>

                            <p>
                                New course announcements will appear
                                here.
                            </p>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="announcement-grid">
                        <?php foreach ($announcements as $announcement): ?>
                            <article class="announcement-card student-announcement-card">
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
                                        Posted by
                                        <strong>
                                            <?= escape(
                                                $announcement[
                                                    'instructor_name'
                                                ]
                                            ) ?>
                                        </strong>
                                    </span>

                                    <span>
                                        <?= escape(
                                            $announcement['course_name']
                                        ) ?>
                                    </span>
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