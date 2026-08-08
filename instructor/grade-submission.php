<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';
require_once __DIR__ . '/../app/Helpers/notification.php';

requireRole('instructor');

$user = currentUser();

$submissionId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$submissionId) {
    redirect('instructor/assignments.php');
}

$query = $pdo->prepare(
    'SELECT
        submissions.*,
        assignments.title AS assignment_title,
        assignments.maximum_marks,
        assignments.course_id,
        courses.course_code,
        users.full_name AS student_name,
        users.email AS student_email
     FROM submissions
     INNER JOIN assignments
        ON assignments.id = submissions.assignment_id
     INNER JOIN courses
        ON courses.id = assignments.course_id
     INNER JOIN users
        ON users.id = submissions.student_id
     WHERE submissions.id = :submission_id
       AND courses.instructor_id = :instructor_id
     LIMIT 1'
);

$query->execute([
    'submission_id' => $submissionId,
    'instructor_id' => $user['id'],
]);

$submission = $query->fetch();

if (!$submission) {
    http_response_code(404);
    exit('Submission not found or access denied.');
}

$pageTitle = 'Grade Submission';
$pageSubtitle =
    $submission['course_code']
    . ' — '
    . $submission['assignment_title'];

$errors = [];

$grade = $submission['grade'] !== null
    ? (string) $submission['grade']
    : '';

$feedback = $submission['feedback'] ?? '';
$resultPublished =
    (bool) $submission['result_published'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $grade = trim($_POST['grade'] ?? '');
    $feedback = trim($_POST['feedback'] ?? '');
    $resultPublished = isset($_POST['result_published']);

    if (
        !is_numeric($grade)
        || (float) $grade < 0
        || (float) $grade
            > (float) $submission['maximum_marks']
    ) {
        $errors[] =
            'Grade must be between 0 and '
            . number_format(
                (float) $submission['maximum_marks'],
                2
            )
            . '.';
    }

    if ($errors === []) {
        $updateQuery = $pdo->prepare(
            'UPDATE submissions
             SET
                grade = :grade,
                feedback = :feedback,
                graded_by = :graded_by,
                graded_at = NOW(),
                status = :status,
                result_published = :result_published,
                published_at = :published_at
             WHERE id = :submission_id'
        );

        $updateQuery->execute([
            'grade' => (float) $grade,
            'feedback' =>
                $feedback !== '' ? $feedback : null,
            'graded_by' => $user['id'],
            'status' => 'graded',
            'result_published' =>
                $resultPublished ? 1 : 0,
            'published_at' =>
                $resultPublished
                    ? date('Y-m-d H:i:s')
                    : null,
            'submission_id' => $submissionId,
        ]);

        if ($resultPublished) {

            createNotification(
                $pdo,
                (int) $submission['student_id'],
                'Assignment Graded',
                'Your assignment "' . $submission['assignment_title']
                . '" has been graded and published.',
                'grade',
                (int) $submission['assignment_id']
            );

        }

        setFlash(
            'success',
            $resultPublished
                ? 'Grade saved and published successfully.'
                : 'Grade saved as a draft.'
        );

        redirect(
            'instructor/submissions.php?assignment_id='
            . (int) $submission['assignment_id']
        );
    }
}

$filePath =
    __DIR__
    . '/../'
    . $submission['file_path'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Grade Submission | <?= escape(APP_NAME) ?></title>

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
                        href="<?= APP_URL ?>/instructor/submissions.php?assignment_id=<?= (int) $submission['assignment_id'] ?>"
                    >
                        ← Back to Submissions
                    </a>

                    <section class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>
                                    <?= escape(
                                        $submission['student_name']
                                    ) ?>
                                </h3>

                                <p class="panel-subtitle">
                                    Submission version
                                    <?= (int) $submission[
                                        'submission_number'
                                    ] ?>
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

                            <div class="submission-file-panel">
                                <div>
                                    <strong>
                                        <?= escape(
                                            $submission['file_name']
                                        ) ?>
                                    </strong>

                                    <p>
                                        Submitted:
                                        <?= escape(
                                            date(
                                                'd M Y, g:i A',
                                                strtotime(
                                                    $submission[
                                                        'submitted_at'
                                                    ]
                                                )
                                            )
                                        ) ?>
                                    </p>
                                </div>

                                <?php if (is_file($filePath)): ?>
                                    <a
                                        class="button button-small"
                                        href="<?= APP_URL ?>/<?= escape(
                                            $submission['file_path']
                                        ) ?>"
                                        download
                                    >
                                        Download File
                                    </a>
                                <?php endif; ?>
                            </div>

                            <form method="POST">
                                <div class="form-grid">
                                    <div class="form-group full">
                                        <label for="grade">
                                            Grade
                                            (Maximum:
                                            <?= number_format(
                                                (float) $submission[
                                                    'maximum_marks'
                                                ],
                                                2
                                            ) ?>)
                                        </label>

                                        <input
                                            id="grade"
                                            name="grade"
                                            type="number"
                                            min="0"
                                            max="<?= escape(
                                                (string) $submission[
                                                    'maximum_marks'
                                                ]
                                            ) ?>"
                                            step="0.01"
                                            value="<?= escape($grade) ?>"
                                            required
                                        >
                                    </div>

                                    <div class="form-group full">
                                        <label for="feedback">
                                            Instructor Feedback
                                        </label>

                                        <textarea
                                            id="feedback"
                                            name="feedback"
                                            rows="7"
                                        ><?= escape($feedback) ?></textarea>
                                    </div>

                                    <div class="form-group full">
                                        <label class="checkbox-row">
                                            <input
                                                name="result_published"
                                                type="checkbox"
                                                value="1"
                                                <?= $resultPublished
                                                    ? 'checked'
                                                    : '' ?>
                                            >

                                            <span>
                                                Publish this result to
                                                the student
                                            </span>
                                        </label>
                                    </div>

                                    <div class="form-group full form-actions">
                                        <a
                                            class="button button-secondary"
                                            href="<?= APP_URL ?>/instructor/submissions.php?assignment_id=<?= (int) $submission['assignment_id'] ?>"
                                        >
                                            Cancel
                                        </a>

                                        <button type="submit">
                                            Save Grade
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