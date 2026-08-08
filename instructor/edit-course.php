<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$courseId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$courseId) {
    setFlash('error', 'Invalid course selected.');
    redirect('instructor/courses.php');
}

$courseQuery = $pdo->prepare(
    'SELECT *
     FROM courses
     WHERE id = :course_id
       AND instructor_id = :instructor_id
     LIMIT 1'
);

$courseQuery->execute([
    'course_id' => $courseId,
    'instructor_id' => $user['id'],
]);

$course = $courseQuery->fetch();

if (!$course) {
    http_response_code(404);
    exit('Course not found or access denied.');
}

$pageTitle = 'Edit Course';
$pageSubtitle = 'Update course details and publication settings.';

$errors = [];

$courseCode = $course['course_code'];
$courseName = $course['course_name'];
$description = $course['description'] ?? '';
$enrolmentKey = $course['enrolment_key'] ?? '';
$isPublished = (bool) $course['is_published'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseCode = strtoupper(
        trim($_POST['course_code'] ?? '')
    );

    $courseName = trim($_POST['course_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $enrolmentKey = trim($_POST['enrolment_key'] ?? '');
    $isPublished = isset($_POST['is_published']);

    if ($courseCode === '') {
        $errors[] = 'Course code is required.';
    } elseif (strlen($courseCode) > 30) {
        $errors[] = 'Course code must not exceed 30 characters.';
    }

    if (strlen($courseName) < 3) {
        $errors[] =
            'Course name must contain at least 3 characters.';
    }

    if (strlen($courseName) > 150) {
        $errors[] =
            'Course name must not exceed 150 characters.';
    }

    if (strlen($enrolmentKey) > 100) {
        $errors[] =
            'Enrolment key must not exceed 100 characters.';
    }

    if ($errors === []) {
        $duplicateQuery = $pdo->prepare(
            'SELECT id
             FROM courses
             WHERE course_code = :course_code
               AND id != :course_id
             LIMIT 1'
        );

        $duplicateQuery->execute([
            'course_code' => $courseCode,
            'course_id' => $courseId,
        ]);

        if ($duplicateQuery->fetch()) {
            $errors[] =
                'Another course already uses this course code.';
        }
    }

    if ($errors === []) {
        $updateQuery = $pdo->prepare(
            'UPDATE courses
             SET
                course_code = :course_code,
                course_name = :course_name,
                description = :description,
                enrolment_key = :enrolment_key,
                is_published = :is_published
             WHERE id = :course_id
               AND instructor_id = :instructor_id'
        );

        $updateQuery->execute([
            'course_code' => $courseCode,
            'course_name' => $courseName,
            'description' =>
                $description !== '' ? $description : null,
            'enrolment_key' =>
                $enrolmentKey !== '' ? $enrolmentKey : null,
            'is_published' => $isPublished ? 1 : 0,
            'course_id' => $courseId,
            'instructor_id' => $user['id'],
        ]);

        setFlash(
            'success',
            'Course details were updated successfully.'
        );

        redirect('instructor/courses.php');
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

    <title>Edit Course | <?= escape(APP_NAME) ?></title>

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
                        href="<?= APP_URL ?>/instructor/courses.php"
                    >
                        ← Back to Courses
                    </a>

                    <section class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>Edit Course</h3>

                                <p class="panel-subtitle">
                                    Update the course information below.
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
                                    <div class="form-group">
                                        <label for="course_code">
                                            Course Code
                                        </label>

                                        <input
                                            id="course_code"
                                            name="course_code"
                                            type="text"
                                            value="<?= escape($courseCode) ?>"
                                            maxlength="30"
                                            required
                                        >
                                    </div>

                                    <div class="form-group">
                                        <label for="course_name">
                                            Course Name
                                        </label>

                                        <input
                                            id="course_name"
                                            name="course_name"
                                            type="text"
                                            value="<?= escape($courseName) ?>"
                                            maxlength="150"
                                            required
                                        >
                                    </div>

                                    <div class="form-group full">
                                        <label for="description">
                                            Description
                                        </label>

                                        <textarea
                                            id="description"
                                            name="description"
                                            rows="5"
                                        ><?= escape($description) ?></textarea>
                                    </div>

                                    <div class="form-group full">
                                        <label for="enrolment_key">
                                            Enrolment Key
                                        </label>

                                        <input
                                            id="enrolment_key"
                                            name="enrolment_key"
                                            type="text"
                                            value="<?= escape($enrolmentKey) ?>"
                                            maxlength="100"
                                        >
                                    </div>

                                    <div class="form-group full">
                                        <label class="checkbox-row">
                                            <input
                                                name="is_published"
                                                type="checkbox"
                                                value="1"
                                                <?= $isPublished
                                                    ? 'checked'
                                                    : '' ?>
                                            >

                                            <span>
                                                Publish this course
                                            </span>
                                        </label>
                                    </div>

                                    <div class="form-group full form-actions">
                                        <a
                                            class="button button-secondary"
                                            href="<?= APP_URL ?>/instructor/courses.php"
                                        >
                                            Cancel
                                        </a>

                                        <button type="submit">
                                            Save Changes
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