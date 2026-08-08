<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$assignmentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$assignmentId) {
    setFlash('error', 'Invalid assignment selected.');
    redirect('instructor/assignments.php');
}

$query = $pdo->prepare(
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

$query->execute([
    'assignment_id' => $assignmentId,
    'instructor_id' => $user['id'],
]);

$assignment = $query->fetch();

if (!$assignment) {
    http_response_code(404);
    exit('Assignment not found or access denied.');
}

$pageTitle = 'Edit Assignment';
$pageSubtitle =
    $assignment['course_code']
    . ' — '
    . $assignment['course_name'];

$errors = [];

$title = $assignment['title'];
$description = $assignment['description'] ?? '';
$deadline = date(
    'Y-m-d\TH:i',
    strtotime($assignment['deadline'])
);
$maximumMarks = (string) $assignment['maximum_marks'];
$allowedFileTypes =
    $assignment['allowed_file_types']
    ?: 'pdf,doc,docx,zip';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $maximumMarks = trim($_POST['maximum_marks'] ?? '');
    $allowedFileTypes = trim(
        $_POST['allowed_file_types'] ?? ''
    );

    if (strlen($title) < 3) {
        $errors[] =
            'Assignment title must contain at least 3 characters.';
    }

    $deadlineTimestamp = strtotime($deadline);

    if (!$deadlineTimestamp) {
        $errors[] = 'Please enter a valid deadline.';
    }

    if (
        !is_numeric($maximumMarks)
        || (float) $maximumMarks <= 0
    ) {
        $errors[] =
            'Maximum marks must be greater than zero.';
    }

    if ($errors === []) {
        $updateQuery = $pdo->prepare(
            'UPDATE assignments
             SET
                title = :title,
                description = :description,
                deadline = :deadline,
                maximum_marks = :maximum_marks,
                allowed_file_types = :allowed_file_types
             WHERE id = :assignment_id
               AND created_by = :created_by'
        );

        $updateQuery->execute([
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
            'assignment_id' => $assignmentId,
            'created_by' => $user['id'],
        ]);

        setFlash(
            'success',
            'Assignment updated successfully.'
        );

        redirect(
            'instructor/assignments.php?course_id='
            . (int) $assignment['course_id']
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

    <title>Edit Assignment | <?= escape(APP_NAME) ?></title>

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
                        href="<?= APP_URL ?>/instructor/assignments.php?course_id=<?= (int) $assignment['course_id'] ?>"
                    >
                        ← Back to Assignments
                    </a>

                    <section class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>Edit Assignment</h3>

                                <p class="panel-subtitle">
                                    Update assignment instructions,
                                    deadline and marks.
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
                                        <label>Course</label>

                                        <input
                                            type="text"
                                            value="<?= escape(
                                                $assignment['course_code']
                                                . ' — '
                                                . $assignment['course_name']
                                            ) ?>"
                                            disabled
                                        >
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
                                    </div>

                                    <div class="form-group full form-actions">
                                        <a
                                            class="button button-secondary"
                                            href="<?= APP_URL ?>/instructor/assignments.php?course_id=<?= (int) $assignment['course_id'] ?>"
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