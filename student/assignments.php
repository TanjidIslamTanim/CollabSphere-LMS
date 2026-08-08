<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('student');

$user = currentUser();

$pageTitle = 'Assignments';
$pageSubtitle =
    'View coursework, deadlines and submission status.';

$flash = getFlash();

$query = $pdo->prepare(
    'SELECT
        assignments.id,
        assignments.title,
        assignments.description,
        assignments.deadline,
        assignments.maximum_marks,
        assignments.allowed_file_types,
        courses.course_code,
        courses.course_name,
        (
            SELECT COUNT(*)
            FROM submissions
            WHERE submissions.assignment_id = assignments.id
              AND submissions.student_id = :student_id_count
        ) AS submission_count,
        (
            SELECT submissions.status
            FROM submissions
            WHERE submissions.assignment_id = assignments.id
              AND submissions.student_id = :student_id_status
            ORDER BY submissions.submission_number DESC
            LIMIT 1
        ) AS latest_status,
        (
            SELECT submissions.grade
            FROM submissions
            WHERE submissions.assignment_id = assignments.id
              AND submissions.student_id = :student_id_grade
              AND submissions.result_published = 1
            ORDER BY submissions.submission_number DESC
            LIMIT 1
        ) AS published_grade
     FROM assignments
     INNER JOIN courses
        ON courses.id = assignments.course_id
     INNER JOIN enrollments
        ON enrollments.course_id = courses.id
     WHERE enrollments.student_id = :student_id
     ORDER BY assignments.deadline ASC'
);

$query->execute([
    'student_id_count' => $user['id'],
    'student_id_status' => $user['id'],
    'student_id_grade' => $user['id'],
    'student_id' => $user['id'],
]);

$assignments = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Assignments | <?= escape(APP_NAME) ?></title>

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
                        <h2>My Assignments</h2>

                        <p>
                            View assignment instructions, deadlines and
                            submission status.
                        </p>
                    </div>
                </section>

                <?php if ($assignments === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No assignments available</h3>

                            <p>
                                Your instructors have not created any
                                assignments yet.
                            </p>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="assignment-grid">
                        <?php foreach ($assignments as $assignment): ?>
                            <?php
                            $deadlineTimestamp = strtotime(
                                $assignment['deadline']
                            );

                            $isClosed = $deadlineTimestamp < time();
                            $hasSubmitted =
                                (int) $assignment['submission_count'] > 0;
                            ?>

                            <article class="assignment-card">
                                <div class="assignment-card-header">
                                    <div>
                                        <span class="course-code">
                                            <?= escape(
                                                $assignment['course_code']
                                            ) ?>
                                        </span>

                                        <h3>
                                            <?= escape(
                                                $assignment['title']
                                            ) ?>
                                        </h3>
                                    </div>

                                    <span
                                        class="badge <?= $isClosed
                                            ? 'badge-danger'
                                            : 'badge-info' ?>"
                                    >
                                        <?= $isClosed
                                            ? 'Closed'
                                            : 'Open' ?>
                                    </span>
                                </div>

                                <p class="assignment-description">
                                    <?= escape(
                                        $assignment['description']
                                        ?: 'No instructions provided.'
                                    ) ?>
                                </p>

                                <div class="assignment-details">
                                    <div>
                                        <span>Course</span>

                                        <strong>
                                            <?= escape(
                                                $assignment['course_name']
                                            ) ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <span>Deadline</span>

                                        <strong>
                                            <?= escape(
                                                date(
                                                    'd M Y, g:i A',
                                                    $deadlineTimestamp
                                                )
                                            ) ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <span>Maximum Marks</span>

                                        <strong>
                                            <?= number_format(
                                                (float) $assignment[
                                                    'maximum_marks'
                                                ],
                                                0
                                            ) ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <span>Status</span>

                                        <strong>
                                            <?= $hasSubmitted
                                                ? escape(
                                                    ucfirst(
                                                        $assignment[
                                                            'latest_status'
                                                        ]
                                                        ?: 'Submitted'
                                                    )
                                                )
                                                : 'Not submitted' ?>
                                        </strong>
                                    </div>
                                </div>

                                <?php if (
                                    $assignment['published_grade'] !== null
                                ): ?>
                                    <div class="student-result-summary">
                                        Published Grade:
                                        <strong>
                                            <?= number_format(
                                                (float) $assignment[
                                                    'published_grade'
                                                ],
                                                2
                                            ) ?>
                                            /
                                            <?= number_format(
                                                (float) $assignment[
                                                    'maximum_marks'
                                                ],
                                                2
                                            ) ?>
                                        </strong>
                                    </div>
                                <?php endif; ?>

                                <a
                                    class="button student-open-course"
                                    href="<?= APP_URL ?>/student/submit-assignment.php?id=<?= (int) $assignment['id'] ?>"
                                >
                                    <?= $hasSubmitted
                                        ? 'View / Resubmit'
                                        : 'Open Assignment' ?>
                                </a>
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