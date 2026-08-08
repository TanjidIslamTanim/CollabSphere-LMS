<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$pageTitle = 'Create Course';
$pageSubtitle = 'Set up a new course for your students.';

$errors = [];

$courseCode = '';
$courseName = '';
$description = '';
$enrolmentKey = '';
$isPublished = true;

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
             LIMIT 1'
        );

        $duplicateQuery->execute([
            'course_code' => $courseCode,
        ]);

        if ($duplicateQuery->fetch()) {
            $errors[] =
                'A course already exists with this course code.';
        }
    }

    if ($errors === []) {
        $insertQuery = $pdo->prepare(
            'INSERT INTO courses
                (
                    instructor_id,
                    course_code,
                    course_name,
                    description,
                    enrolment_key,
                    is_published
                )
             VALUES
                (
                    :instructor_id,
                    :course_code,
                    :course_name,
                    :description,
                    :enrolment_key,
                    :is_published
                )'
        );

        $insertQuery->execute([
            'instructor_id' => $user['id'],
            'course_code' => $courseCode,
            'course_name' => $courseName,
            'description' =>
                $description !== '' ? $description : null,
            'enrolment_key' =>
                $enrolmentKey !== '' ? $enrolmentKey : null,
            'is_published' => $isPublished ? 1 : 0,
        ]);

        setFlash(
            'success',
            'The course was created successfully.'
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

    <title>Create Course | <?= escape(APP_NAME) ?></title>

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
                                <h3>Create New Course</h3>

                                <p class="panel-subtitle">
                                    Enter the course information below.
                                </p>
                            </div>
                        </header>

                        <div class="dashboard-panel-body">
                            <?php if ($errors !== []): ?>
                                <div class="alert alert-error">
                                    <ul class="error-list">
                                        <?php foreach ($errors as $error): ?>
                                            <li>
                                                <?= escape($error) ?>
                                            </li>
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
                                            placeholder="Example: XBAU2114N"
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
                                            placeholder="Software Development Methodologies"
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
                                            placeholder="Provide a short description of the course."
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
                                            placeholder="Optional private key for students"
                                            maxlength="100"
                                        >

                                        <small class="field-help">
                                            Leave empty when students may
                                            enrol without a key.
                                        </small>
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
                                                Publish this course immediately
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
                                            Create Course
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