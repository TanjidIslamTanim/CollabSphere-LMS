<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$selectedCourseId = filter_input(
    INPUT_GET,
    'course_id',
    FILTER_VALIDATE_INT
);

$pageTitle = 'Gradebook';
$pageSubtitle = 'Review grades and published student results.';

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
        redirect('instructor/gradebook.php');
    }

    $pageSubtitle =
        $selectedCourse['course_code']
        . ' — '
        . $selectedCourse['course_name'];
}

$sql = '
    SELECT
        submissions.id AS submission_id,
        submissions.assignment_id,
        submissions.student_id,
        submissions.submission_number,
        submissions.status,
        submissions.grade,
        submissions.feedback,
        submissions.result_published,
        submissions.graded_at,
        submissions.published_at,

        assignments.title AS assignment_title,
        assignments.maximum_marks,

        courses.id AS course_id,
        courses.course_code,
        courses.course_name,

        users.full_name AS student_name,
        users.email AS student_email,

        student_profiles.student_number

    FROM submissions

    INNER JOIN assignments
        ON assignments.id = submissions.assignment_id

    INNER JOIN courses
        ON courses.id = assignments.course_id

    INNER JOIN users
        ON users.id = submissions.student_id

    LEFT JOIN student_profiles
        ON student_profiles.user_id = users.id

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
    ORDER BY
        courses.course_code ASC,
        assignments.title ASC,
        users.full_name ASC,
        submissions.submission_number DESC
';

$query = $pdo->prepare($sql);
$query->execute($params);

$gradeRecords = $query->fetchAll();

$summarySql = '
    SELECT
        COUNT(DISTINCT assignments.id) AS total_assignments,
        COUNT(DISTINCT submissions.id) AS total_submissions,

        SUM(
            submissions.status = "graded"
        ) AS graded_submissions,

        SUM(
            submissions.result_published = 1
        ) AS published_results,

        AVG(
            CASE
                WHEN submissions.grade IS NOT NULL
                THEN (
                    submissions.grade
                    / NULLIF(assignments.maximum_marks, 0)
                ) * 100
                ELSE NULL
            END
        ) AS average_percentage

    FROM assignments

    INNER JOIN courses
        ON courses.id = assignments.course_id

    LEFT JOIN submissions
        ON submissions.assignment_id = assignments.id

    WHERE courses.instructor_id = :instructor_id
';

$summaryParams = [
    'instructor_id' => $user['id'],
];

if ($selectedCourseId) {
    $summarySql .= '
        AND courses.id = :course_id
    ';

    $summaryParams['course_id'] = $selectedCourseId;
}

$summaryQuery = $pdo->prepare($summarySql);
$summaryQuery->execute($summaryParams);

$summary = $summaryQuery->fetch();

$totalAssignments = (int) ($summary['total_assignments'] ?? 0);
$totalSubmissions = (int) ($summary['total_submissions'] ?? 0);
$gradedSubmissions = (int) ($summary['graded_submissions'] ?? 0);
$publishedResults = (int) ($summary['published_results'] ?? 0);

$averagePercentage =
    $summary['average_percentage'] !== null
        ? (float) $summary['average_percentage']
        : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Gradebook | <?= escape(APP_NAME) ?></title>

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
                                    . ' Gradebook'
                                )
                                : 'Gradebook' ?>
                        </h2>

                        <p>
                            Review student scores, grading progress and
                            result publication status.
                        </p>
                    </div>
                </section>

                <section class="statistics-grid">
                    <article class="statistics-card">
                        <div class="statistics-label">
                            Assignments
                        </div>

                        <div class="statistics-value">
                            <?= $totalAssignments ?>
                        </div>

                        <div class="statistics-description">
                            Assignment records included
                        </div>
                    </article>

                    <article class="statistics-card">
                        <div class="statistics-label">
                            Submissions
                        </div>

                        <div class="statistics-value">
                            <?= $totalSubmissions ?>
                        </div>

                        <div class="statistics-description">
                            Total submitted attempts
                        </div>
                    </article>

                    <article class="statistics-card">
                        <div class="statistics-label">
                            Graded
                        </div>

                        <div class="statistics-value">
                            <?= $gradedSubmissions ?>
                        </div>

                        <div class="statistics-description">
                            Submissions already graded
                        </div>
                    </article>

                    <article class="statistics-card">
                        <div class="statistics-label">
                            Published Results
                        </div>

                        <div class="statistics-value">
                            <?= $publishedResults ?>
                        </div>

                        <div class="statistics-description">
                            Results visible to students
                        </div>
                    </article>
                </section>

                <?php if ($averagePercentage !== null): ?>
                    <section class="gradebook-average-panel">
                        <div>
                            <span>Overall Graded Average</span>

                            <strong>
                                <?= number_format(
                                    $averagePercentage,
                                    1
                                ) ?>%
                            </strong>
                        </div>

                        <p>
                            Calculated from all graded submissions currently
                            included in this gradebook.
                        </p>
                    </section>
                <?php endif; ?>

                <?php if ($gradeRecords === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No grade records available</h3>

                            <p>
                                Student submissions and grades will appear
                                here after assignments are submitted.
                            </p>

                            <a
                                class="button"
                                href="<?= APP_URL ?>/instructor/assignments.php<?= $selectedCourseId
                                    ? '?course_id=' . $selectedCourseId
                                    : '' ?>"
                            >
                                Open Assignments
                            </a>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="dashboard-panel gradebook-panel">
                        <div class="table-responsive">
                            <table class="data-table gradebook-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Assignment</th>
                                        <th>Attempt</th>
                                        <th>Score</th>
                                        <th>Percentage</th>
                                        <th>Status</th>
                                        <th>Result</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($gradeRecords as $record): ?>
                                        <?php
                                        $percentage = null;

                                        if (
                                            $record['grade'] !== null
                                            && (float) $record['maximum_marks'] > 0
                                        ) {
                                            $percentage =
                                                (
                                                    (float) $record['grade']
                                                    / (float) $record[
                                                        'maximum_marks'
                                                    ]
                                                ) * 100;
                                        }

                                        $statusClass = match (
                                            strtolower(
                                                (string) $record['status']
                                            )
                                        ) {
                                            'graded' => 'badge-success',
                                            'late' => 'badge-danger',
                                            'submitted' => 'badge-info',
                                            default => 'badge-warning',
                                        };

                                        $published = (bool) $record[
                                            'result_published'
                                        ];
                                        ?>

                                        <tr>
                                            <td>
                                                <div class="student-profile-cell">
                                                    <div class="student-avatar">
                                                        <?= escape(
                                                            strtoupper(
                                                                substr(
                                                                    $record[
                                                                        'student_name'
                                                                    ],
                                                                    0,
                                                                    1
                                                                )
                                                            )
                                                        ) ?>
                                                    </div>

                                                    <div>
                                                        <strong>
                                                            <?= escape(
                                                                $record[
                                                                    'student_name'
                                                                ]
                                                            ) ?>
                                                        </strong>

                                                        <small>
                                                            <?= escape(
                                                                $record[
                                                                    'student_number'
                                                                ]
                                                                ?: $record[
                                                                    'student_email'
                                                                ]
                                                            ) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <span class="course-code">
                                                    <?= escape(
                                                        $record[
                                                            'course_code'
                                                        ]
                                                    ) ?>
                                                </span>

                                                <small class="table-secondary-text">
                                                    <?= escape(
                                                        $record[
                                                            'course_name'
                                                        ]
                                                    ) ?>
                                                </small>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= escape(
                                                        $record[
                                                            'assignment_title'
                                                        ]
                                                    ) ?>
                                                </strong>

                                                <small class="table-secondary-text">
                                                    Maximum:
                                                    <?= number_format(
                                                        (float) $record[
                                                            'maximum_marks'
                                                        ],
                                                        2
                                                    ) ?>
                                                </small>
                                            </td>

                                            <td class="gradebook-center">
                                                <span class="attempt-badge">
                                                    <?= (int) $record[
                                                        'submission_number'
                                                    ] ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php if (
                                                    $record['grade'] !== null
                                                ): ?>
                                                    <strong>
                                                        <?= number_format(
                                                            (float) $record[
                                                                'grade'
                                                            ],
                                                            2
                                                        ) ?>
                                                    </strong>

                                                    <span class="submission-max-score">
                                                        /
                                                        <?= number_format(
                                                            (float) $record[
                                                                'maximum_marks'
                                                            ],
                                                            2
                                                        ) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="muted-value">
                                                        Not graded
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if (
                                                    $percentage !== null
                                                ): ?>
                                                    <strong
                                                        class="<?= $percentage >= 50
                                                            ? 'grade-pass'
                                                            : 'grade-fail' ?>"
                                                    >
                                                        <?= number_format(
                                                            $percentage,
                                                            1
                                                        ) ?>%
                                                    </strong>
                                                <?php else: ?>
                                                    <span class="muted-value">
                                                        —
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <span
                                                    class="badge <?= $statusClass ?>"
                                                >
                                                    <?= escape(
                                                        ucfirst(
                                                            $record['status']
                                                        )
                                                    ) ?>
                                                </span>
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

                                            <td>
                                                <a
                                                    class="button button-small"
                                                    href="<?= APP_URL ?>/instructor/grade-submission.php?id=<?= (int) $record['submission_id'] ?>"
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