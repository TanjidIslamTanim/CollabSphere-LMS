<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$assignmentId = filter_input(
    INPUT_GET,
    'assignment_id',
    FILTER_VALIDATE_INT
);

if (!$assignmentId) {
    setFlash('error', 'Invalid assignment selected.');
    redirect('instructor/assignments.php');
}

$assignmentQuery = $pdo->prepare(
    'SELECT
        assignments.*,
        courses.course_code,
        courses.course_name
     FROM assignments
     INNER JOIN courses
        ON courses.id = assignments.course_id
     WHERE assignments.id = :assignment_id
       AND courses.instructor_id = :instructor_id
     LIMIT 1'
);

$assignmentQuery->execute([
    'assignment_id' => $assignmentId,
    'instructor_id' => $user['id'],
]);

$assignment = $assignmentQuery->fetch();

if (!$assignment) {
    http_response_code(404);
    exit('Assignment not found or access denied.');
}

$pageTitle = 'Submissions';
$pageSubtitle =
    $assignment['course_code']
    . ' — '
    . $assignment['title'];

$flash = getFlash();

$query = $pdo->prepare(
    'SELECT
        submissions.*,
        users.full_name,
        users.email,
        student_profiles.student_number
     FROM submissions
     INNER JOIN users
        ON users.id = submissions.student_id
     LEFT JOIN student_profiles
        ON student_profiles.user_id = users.id
     WHERE submissions.assignment_id = :assignment_id
     ORDER BY
        users.full_name ASC,
        submissions.submission_number DESC'
);

$query->execute([
    'assignment_id' => $assignmentId,
]);

$submissions = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Submissions | <?= escape(APP_NAME) ?></title>

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

                <a
                    class="back-link"
                    href="<?= APP_URL ?>/instructor/assignments.php?course_id=<?= (int) $assignment['course_id'] ?>"
                >
                    ← Back to Assignments
                </a>

                <section class="page-heading-row">
                    <div>
                        <h2><?= escape($assignment['title']) ?></h2>

                        <p>
                            Review submissions, enter feedback and
                            publish grades.
                        </p>
                    </div>
                </section>

                <?php if ($submissions === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No submissions received</h3>

                            <p>
                                Student submissions will appear here.
                            </p>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="dashboard-panel submissions-panel">
                        <div class="table-responsive">
                            <table class="data-table submissions-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Attempt</th>
                                        <th>Submitted On</th>
                                        <th>Status</th>
                                        <th>Score</th>
                                        <th>Publish Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($submissions as $submission): ?>
                                        <?php
                                        $statusClass = match (
                                            strtolower(
                                                (string) $submission['status']
                                            )
                                        ) {
                                            'graded' => 'badge-success',
                                            'late' => 'badge-danger',
                                            'submitted' => 'badge-info',
                                            default => 'badge-warning',
                                        };

                                        $published = (bool) $submission[
                                            'result_published'
                                        ];
                                        ?>

                                        <tr>
                                            <td>
                                                <div class="submission-student">
                                                    <strong>
                                                        <?= escape(
                                                            $submission[
                                                                'full_name'
                                                            ]
                                                        ) ?>
                                                    </strong>

                                                    <small>
                                                        <?= escape(
                                                            $submission[
                                                                'student_number'
                                                            ]
                                                            ?: $submission[
                                                                'email'
                                                            ]
                                                        ) ?>
                                                    </small>
                                                </div>
                                            </td>

                                            <td class="submission-center">
                                                <span class="attempt-badge">
                                                    <?= (int) $submission[
                                                        'submission_number'
                                                    ] ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="submitted-date">
                                                    <?= escape(
                                                        date(
                                                            'd M Y',
                                                            strtotime(
                                                                $submission[
                                                                    'submitted_at'
                                                                ]
                                                            )
                                                        )
                                                    ) ?>
                                                </span>

                                                <small class="submitted-time">
                                                    <?= escape(
                                                        date(
                                                            'g:i A',
                                                            strtotime(
                                                                $submission[
                                                                    'submitted_at'
                                                                ]
                                                            )
                                                        )
                                                    ) ?>
                                                </small>
                                            </td>

                                            <td>
                                                <span
                                                    class="badge <?= $statusClass ?>"
                                                >
                                                    <?= escape(
                                                        ucfirst(
                                                            $submission[
                                                                'status'
                                                            ]
                                                        )
                                                    ) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php if (
                                                    $submission['grade'] !== null
                                                ): ?>
                                                    <strong class="submission-score">
                                                        <?= escape(
                                                            number_format(
                                                                (float) $submission[
                                                                    'grade'
                                                                ],
                                                                2
                                                            )
                                                        ) ?>
                                                    </strong>

                                                    <span class="submission-max-score">
                                                        /
                                                        <?= escape(
                                                            number_format(
                                                                (float) $assignment[
                                                                    'maximum_marks'
                                                                ],
                                                                2
                                                            )
                                                        ) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="muted-value">
                                                        Not graded
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <span
                                                    class="badge <?= $published
                                                        ? 'badge-success'
                                                        : 'badge-warning' ?>"
                                                >
                                                    <?= $published
                                                        ? 'Published'
                                                        : 'Draft' ?>
                                                </span>
                                            </td>

                                            <td class="submission-action-cell">
                                                <a
                                                    class="button button-small submission-review-button"
                                                    href="<?= APP_URL ?>/instructor/grade-submission.php?id=<?= (int) $submission['id'] ?>"
                                                >
                                                    Review
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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