<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';
require_once __DIR__ . '/../app/Helpers/notification.php';

requireRole('instructor');

$user = currentUser();

$pageTitle = 'Create Assignment';
$pageSubtitle = 'Create coursework for one of your courses.';

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
$description = '';
$deadline = '';
$maximumMarks = '100';
$allowedFileTypes = 'pdf,doc,docx,zip';

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
    $description = trim($_POST['description'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $maximumMarks = trim($_POST['maximum_marks'] ?? '');
    $allowedFileTypes = trim(
        $_POST['allowed_file_types'] ?? ''
    );

    if (!ctype_digit($courseId)) {
        $errors[] = 'Please select a valid course.';
    }

    if (strlen($title) < 3) {
        $errors[] =
            'Assignment title must contain at least 3 characters.';
    }

    if (strlen($title) > 180) {
        $errors[] =
            'Assignment title must not exceed 180 characters.';
    }

    $deadlineTimestamp = strtotime($deadline);

    if (!$deadlineTimestamp) {
        $errors[] = 'Please enter a valid deadline.';
    } elseif ($deadlineTimestamp <= time()) {
        $errors[] = 'The deadline must be in the future.';
    }

    if (
        !is_numeric($maximumMarks)
        || (float) $maximumMarks <= 0
    ) {
        $errors[] =
            'Maximum marks must be greater than zero.';
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
            'INSERT INTO assignments
                (
                    course_id,
                    created_by,
                    title,
                    description,
                    deadline,
                    maximum_marks,
                    allowed_file_types
                )
             VALUES
                (
                    :course_id,
                    :created_by,
                    :title,
                    :description,
                    :deadline,
                    :maximum_marks,
                    :allowed_file_types
                )'
        );

        $insertQuery->execute([
            'course_id' => (int) $courseId,
            'created_by' => $user['id'],
            'title' => $title,
            'description' =>
                $description !== '' ? $description : null,
            'deadline' => date(
                'Y-m-d H:i:s',
                $deadlineTimestamp
            ),
            'maximum_marks' => (float) $maximumMarks,
            'allowed_file_types' =>
                $allowedFileTypes !== ''
                    ? strtolower($allowedFileTypes)
                    : 'pdf,doc,docx,zip',
        ]);

        $assignmentId = (int) $pdo->lastInsertId();

        $courseInfoQuery = $pdo->prepare(
            'SELECT course_name FROM courses WHERE id = :course_id LIMIT 1'
        );
        $courseInfoQuery->execute([
            'course_id' => (int) $courseId,
        ]);
        $courseInfo = $courseInfoQuery->fetch();

        $studentQuery = $pdo->prepare(
            'SELECT student_id FROM enrollments WHERE course_id = :course_id'
        );
        $studentQuery->execute([
            'course_id' => (int) $courseId,
        ]);

        foreach ($studentQuery->fetchAll() as $student) {
            createNotification(
                $pdo,
                (int) $student['student_id'],
                'New Assignment',
                'A new assignment "' . $title . '" has been added to '
                . $courseInfo['course_name'] . '.',
                'assignment',
                $assignmentId
            );
        }

        setFlash(
            'success',
            'Assignment created successfully.'
        );

        redirect(
            'instructor/assignments.php?course_id='
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

    <title>Create Assignment | <?= escape(APP_NAME) ?></title>

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
                        href="<?= APP_URL ?>/instructor/assignments.php<?= ctype_digit($courseId)
                            ? '?course_id=' . (int) $courseId
                            : '' ?>"
                    >
                        ← Back to Assignments
                    </a>

                    <section class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>Create Assignment</h3>

                                <p class="panel-subtitle">
                                    Enter the assignment instructions,
                                    deadline and grading information.
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
                                    Create a course before creating an
                                    assignment.
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
                                                Assignment Title
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
                                            <label for="description">
                                                Instructions
                                            </label>

                                            <textarea
                                                id="description"
                                                name="description"
                                                rows="7"
                                            ><?= escape($description) ?></textarea>
                                        </div>

                                        <div class="form-group">
                                            <label for="deadline">
                                                Deadline
                                            </label>

                                            <input
                                                id="deadline"
                                                name="deadline"
                                                type="datetime-local"
                                                value="<?= escape($deadline) ?>"
                                                required
                                            >
                                        </div>

                                        <div class="form-group">
                                            <label for="maximum_marks">
                                                Maximum Marks
                                            </label>

                                            <input
                                                id="maximum_marks"
                                                name="maximum_marks"
                                                type="number"
                                                value="<?= escape($maximumMarks) ?>"
                                                min="1"
                                                step="0.01"
                                                required
                                            >
                                        </div>

                                        <div class="form-group full">
                                            <label for="allowed_file_types">
                                                Allowed File Types
                                            </label>

                                            <input
                                                id="allowed_file_types"
                                                name="allowed_file_types"
                                                type="text"
                                                value="<?= escape($allowedFileTypes) ?>"
                                            >

                                            <small class="field-help">
                                                Example:
                                                pdf,doc,docx,zip
                                            </small>
                                        </div>

                                        <div class="form-group full form-actions">
                                            <a
                                                class="button button-secondary"
                                                href="<?= APP_URL ?>/instructor/assignments.php<?= ctype_digit($courseId)
                                                    ? '?course_id=' . (int) $courseId
                                                    : '' ?>"
                                            >
                                                Cancel
                                            </a>

                                            <button type="submit">
                                                Create Assignment
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