<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$pageTitle = 'Create Attendance Session';
$pageSubtitle = 'Create a class attendance record.';

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
$sessionDate = date('Y-m-d');
$notes = '';

$courseQuery = $pdo->prepare(
    'SELECT id, course_code, course_name
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
    $sessionDate = trim($_POST['session_date'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!ctype_digit($courseId)) {
        $errors[] = 'Please select a valid course.';
    }

    if ($title !== '' && strlen($title) > 180) {
        $errors[] =
            'Session title must not exceed 180 characters.';
    }

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $sessionDate
    );

    if (
        !$dateObject
        || $dateObject->format('Y-m-d') !== $sessionDate
    ) {
        $errors[] = 'Please enter a valid session date.';
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
        try {
            $insertQuery = $pdo->prepare(
                'INSERT INTO attendance_sessions
                    (
                        course_id,
                        created_by,
                        session_date,
                        title,
                        notes
                    )
                 VALUES
                    (
                        :course_id,
                        :created_by,
                        :session_date,
                        :title,
                        :notes
                    )'
            );

            $insertQuery->execute([
                'course_id' => (int) $courseId,
                'created_by' => $user['id'],
                'session_date' => $sessionDate,
                'title' => $title !== '' ? $title : null,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            $sessionId = (int) $pdo->lastInsertId();

            setFlash(
                'success',
                'Attendance session created successfully.'
            );

            redirect(
                'instructor/mark-attendance.php?session_id='
                . $sessionId
            );
        } catch (PDOException $exception) {
            if ((int) $exception->getCode() === 23000) {
                $errors[] =
                    'An attendance session already exists for this course on the selected date.';
            } else {
                $errors[] =
                    'The attendance session could not be created.';
            }
        }
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
        Create Attendance | <?= escape(APP_NAME) ?>
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
                        href="<?= APP_URL ?>/instructor/attendance.php<?= ctype_digit($courseId)
                            ? '?course_id=' . (int) $courseId
                            : '' ?>"
                    >
                        ← Back to Attendance
                    </a>

                    <section class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>Create Attendance Session</h3>

                                <p class="panel-subtitle">
                                    Choose the course and class date.
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

                                    <div class="form-group">
                                        <label for="session_date">
                                            Session Date
                                        </label>

                                        <input
                                            id="session_date"
                                            name="session_date"
                                            type="date"
                                            value="<?= escape($sessionDate) ?>"
                                            required
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label for="title">
                                            Session Title
                                        </label>

                                        <input
                                            id="title"
                                            name="title"
                                            type="text"
                                            value="<?= escape($title) ?>"
                                            placeholder="Example: Week 1 Lecture"
                                            maxlength="180"
                                        >
                                    </div>

                                    <div class="form-group full">
                                        <label for="notes">
                                            Notes
                                        </label>

                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows="5"
                                            placeholder="Optional session notes."
                                        ><?= escape($notes) ?></textarea>
                                    </div>

                                    <div class="form-group full form-actions">
                                        <a
                                            class="button button-secondary"
                                            href="<?= APP_URL ?>/instructor/attendance.php<?= ctype_digit($courseId)
                                                ? '?course_id=' . (int) $courseId
                                                : '' ?>"
                                        >
                                            Cancel
                                        </a>

                                        <button type="submit">
                                            Create and Mark
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