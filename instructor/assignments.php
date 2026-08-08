<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$pageTitle = 'Assignments';
$pageSubtitle = 'Create and manage coursework for your courses.';

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
        redirect('instructor/assignments.php');
    }

    $pageSubtitle =
        $selectedCourse['course_code']
        . ' — '
        . $selectedCourse['course_name'];
}

$sql = '
    SELECT
        assignments.id,
        assignments.title,
        assignments.description,
        assignments.deadline,
        assignments.maximum_marks,
        assignments.allowed_file_types,
        assignments.created_at,
        courses.id AS course_id,
        courses.course_code,
        courses.course_name,
        COUNT(DISTINCT submissions.student_id)
            AS submission_count
    FROM assignments
    INNER JOIN courses
        ON courses.id = assignments.course_id
    LEFT JOIN submissions
        ON submissions.assignment_id = assignments.id
    WHERE courses.instructor_id = :instructor_id
';

$queryParameters = [
    'instructor_id' => $user['id'],
];

if ($selectedCourseId) {
    $sql .= '
        AND courses.id = :course_id
    ';

    $queryParameters['course_id'] = $selectedCourseId;
}

$sql .= '
    GROUP BY
        assignments.id,
        assignments.title,
        assignments.description,
        assignments.deadline,
        assignments.maximum_marks,
        assignments.allowed_file_types,
        assignments.created_at,
        courses.id,
        courses.course_code,
        courses.course_name
    ORDER BY assignments.deadline ASC
';

$query = $pdo->prepare($sql);
$query->execute($queryParameters);

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
                                ? escape($selectedCourse['course_code'])
                                    . ' Assignments'
                                : 'Assignments' ?>
                        </h2>

                        <p>
                            Create coursework, set deadlines and review
                            student submissions.
                        </p>
                    </div>

                    <a
                        class="button"
                        href="<?= APP_URL ?>/instructor/create-assignment.php<?= $selectedCourseId
                            ? '?course_id=' . $selectedCourseId
                            : '' ?>"
                    >
                        Create Assignment
                    </a>
                </section>

                <?php if ($assignments === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No assignments created</h3>

                            <p>
                                Create your first assignment for
                                <?= $selectedCourse !== null
                                    ? escape($selectedCourse['course_name'])
                                    : 'one of your courses' ?>.
                            </p>

                            <a
                                class="button"
                                href="<?= APP_URL ?>/instructor/create-assignment.php<?= $selectedCourseId
                                    ? '?course_id=' . $selectedCourseId
                                    : '' ?>"
                            >
                                Create First Assignment
                            </a>
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
                                        <?= $isClosed ? 'Closed' : 'Open' ?>
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
                                            <?= escape(
                                                number_format(
                                                    (float) $assignment[
                                                        'maximum_marks'
                                                    ],
                                                    0
                                                )
                                            ) ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <span>Submissions</span>

                                        <strong>
                                            <?= (int) $assignment[
                                                'submission_count'
                                            ] ?>
                                        </strong>
                                    </div>
                                </div>

                                <div class="assignment-actions">
                                    <a
                                        class="button button-small"
                                        href="<?= APP_URL ?>/instructor/submissions.php?assignment_id=<?= (int) $assignment['id'] ?>"
                                    >
                                        View Submissions
                                    </a>

                                    <a
                                        class="button button-small button-secondary"
                                        href="<?= APP_URL ?>/instructor/edit-assignment.php?id=<?= (int) $assignment['id'] ?>"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        method="POST"
                                        action="<?= APP_URL ?>/instructor/delete-assignment.php"
                                        onsubmit="return confirm(
                                            'Delete this assignment permanently?'
                                        );"
                                    >
                                        <input
                                            type="hidden"
                                            name="assignment_id"
                                            value="<?= (int) $assignment['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="course_id"
                                            value="<?= (int) $assignment['course_id'] ?>"
                                        >

                                        <button
                                            class="button button-small button-danger"
                                            type="submit"
                                        >
                                            Delete
                                        </button>
                                    </form>
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