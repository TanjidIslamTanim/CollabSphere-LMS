<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('student');

$user = currentUser();

$pageTitle = 'Grades';
$pageSubtitle = 'View your published assignment results.';

$query = $pdo->prepare(
    'SELECT
        submissions.id,
        submissions.grade,
        submissions.feedback,
        submissions.graded_at,
        submissions.published_at,
        submissions.submission_number,
        assignments.title AS assignment_title,
        assignments.maximum_marks,
        courses.course_code,
        courses.course_name
     FROM submissions
     INNER JOIN assignments
        ON assignments.id = submissions.assignment_id
     INNER JOIN courses
        ON courses.id = assignments.course_id
     WHERE submissions.student_id = :student_id
       AND submissions.result_published = 1
     ORDER BY submissions.published_at DESC'
);

$query->execute([
    'student_id' => $user['id'],
]);

$grades = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Grades | <?= escape(APP_NAME) ?></title>

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
                        <h2>My Grades</h2>

                        <p>
                            View assignment marks and instructor
                            feedback.
                        </p>
                    </div>
                </section>

                <?php if ($grades === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No published results</h3>

                            <p>
                                Your instructor has not published any
                                grades yet.
                            </p>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="grade-result-grid">
                        <?php foreach ($grades as $grade): ?>
                            <?php
                            $percentage =
                                (float) $grade['maximum_marks'] > 0
                                    ? (
                                        (float) $grade['grade']
                                        / (float) $grade['maximum_marks']
                                    ) * 100
                                    : 0;
                            ?>

                            <article class="grade-result-card">
                                <span class="course-code">
                                    <?= escape(
                                        $grade['course_code']
                                    ) ?>
                                </span>

                                <h3>
                                    <?= escape(
                                        $grade['assignment_title']
                                    ) ?>
                                </h3>

                                <div class="grade-score">
                                    <?= number_format(
                                        (float) $grade['grade'],
                                        2
                                    ) ?>
                                    /
                                    <?= number_format(
                                        (float) $grade[
                                            'maximum_marks'
                                        ],
                                        2
                                    ) ?>
                                </div>

                                <div class="grade-percentage">
                                    <?= number_format(
                                        $percentage,
                                        1
                                    ) ?>%
                                </div>

                                <p>
                                    <strong>Course:</strong>
                                    <?= escape(
                                        $grade['course_name']
                                    ) ?>
                                </p>

                                <p>
                                    <strong>Feedback:</strong><br>
                                    <?= nl2br(
                                        escape(
                                            $grade['feedback']
                                            ?: 'No feedback provided.'
                                        )
                                    ) ?>
                                </p>

                                <small>
                                    Published:
                                    <?= escape(
                                        date(
                                            'd M Y, g:i A',
                                            strtotime(
                                                $grade[
                                                    'published_at'
                                                ]
                                            )
                                        )
                                    ) ?>
                                </small>
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