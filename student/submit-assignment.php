<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('student');

$user = currentUser();

$assignmentId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$assignmentId) {
    setFlash('error', 'Invalid assignment selected.');
    redirect('student/assignments.php');
}

$query = $pdo->prepare(
    'SELECT
        assignments.*,
        courses.course_code,
        courses.course_name
     FROM assignments
     INNER JOIN courses
        ON courses.id = assignments.course_id
     INNER JOIN enrollments
        ON enrollments.course_id = courses.id
     WHERE assignments.id = :assignment_id
       AND enrollments.student_id = :student_id
     LIMIT 1'
);

$query->execute([
    'assignment_id' => $assignmentId,
    'student_id' => $user['id'],
]);

$assignment = $query->fetch();

if (!$assignment) {
    http_response_code(404);
    exit('Assignment not found or access denied.');
}

$pageTitle = 'Submit Assignment';
$pageSubtitle =
    $assignment['course_code']
    . ' — '
    . $assignment['title'];

$errors = [];

$historyQuery = $pdo->prepare(
    'SELECT *
     FROM submissions
     WHERE assignment_id = :assignment_id
       AND student_id = :student_id
     ORDER BY submission_number DESC'
);

$historyQuery->execute([
    'assignment_id' => $assignmentId,
    'student_id' => $user['id'],
]);

$submissionHistory = $historyQuery->fetchAll();

$submissionCount = count($submissionHistory);

$isMember =
    ($user['membership_type'] ?? 'non_member') === 'member';

$deadlineTimestamp = strtotime($assignment['deadline']);
$isPastDeadline = $deadlineTimestamp < time();

$maximumSubmissionVersions = $isMember ? null : 3;

$canSubmit =
    !$isPastDeadline
    && (
        $maximumSubmissionVersions === null
        || $submissionCount < $maximumSubmissionVersions
    );

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isPastDeadline) {
        $errors[] =
            'The deadline has passed. New submissions are not allowed.';
    }

    if (
        $maximumSubmissionVersions !== null
        && $submissionCount >= $maximumSubmissionVersions
    ) {
        $errors[] =
            'Non-members may submit an original version and up to two resubmissions.';
    }

    if (!isset($_FILES['submission_file'])) {
        $errors[] = 'Please select a submission file.';
    } else {
        $uploadedFile = $_FILES['submission_file'];

        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $errors[] =
                'The selected file could not be uploaded.';
        }

        if ($uploadedFile['size'] > 20 * 1024 * 1024) {
            $errors[] =
                'The submission file must not exceed 20 MB.';
        }

        $originalFileName = basename(
            $uploadedFile['name']
        );

        $extension = strtolower(
            pathinfo(
                $originalFileName,
                PATHINFO_EXTENSION
            )
        );

        $allowedExtensions = array_values(
            array_filter(
                array_map(
                    'trim',
                    explode(
                        ',',
                        strtolower(
                            $assignment['allowed_file_types']
                            ?: 'pdf,doc,docx,zip'
                        )
                    )
                )
            )
        );

        if (
            $extension === ''
            || !in_array(
                $extension,
                $allowedExtensions,
                true
            )
        ) {
            $errors[] =
                'This file type is not allowed for the assignment.';
        }
    }

    if ($errors === []) {
        if (!is_dir(ASSIGNMENT_UPLOAD_PATH)) {
            mkdir(
                ASSIGNMENT_UPLOAD_PATH,
                0755,
                true
            );
        }

        $storedFileName =
            bin2hex(random_bytes(16))
            . '.'
            . $extension;

        $destinationPath =
            ASSIGNMENT_UPLOAD_PATH
            . DIRECTORY_SEPARATOR
            . $storedFileName;

        if (!move_uploaded_file(
            $uploadedFile['tmp_name'],
            $destinationPath
        )) {
            $errors[] =
                'The submission file could not be saved.';
        } else {
            $mimeType = (
                new finfo(FILEINFO_MIME_TYPE)
            )->file($destinationPath);

            $relativePath =
                'public/uploads/assignments/'
                . $storedFileName;

            $submissionNumber = $submissionCount + 1;

            $status =
                time() > $deadlineTimestamp
                    ? 'late'
                    : 'submitted';

            $insertQuery = $pdo->prepare(
                'INSERT INTO submissions
                    (
                        assignment_id,
                        student_id,
                        file_name,
                        file_path,
                        mime_type,
                        file_size,
                        submission_number,
                        status
                    )
                 VALUES
                    (
                        :assignment_id,
                        :student_id,
                        :file_name,
                        :file_path,
                        :mime_type,
                        :file_size,
                        :submission_number,
                        :status
                    )'
            );

            $insertQuery->execute([
                'assignment_id' => $assignmentId,
                'student_id' => $user['id'],
                'file_name' => $originalFileName,
                'file_path' => $relativePath,
                'mime_type' => $mimeType,
                'file_size' =>
                    (int) $uploadedFile['size'],
                'submission_number' =>
                    $submissionNumber,
                'status' => $status,
            ]);

            setFlash(
                'success',
                'Assignment submitted successfully.'
            );

            redirect(
                'student/submit-assignment.php?id='
                . $assignmentId
            );
        }
    }
}

$flash = getFlash();

$historyQuery->execute([
    'assignment_id' => $assignmentId,
    'student_id' => $user['id'],
]);

$submissionHistory = $historyQuery->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Submit Assignment | <?= escape(APP_NAME) ?></title>

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
                    href="<?= APP_URL ?>/student/assignments.php"
                >
                    ← Back to Assignments
                </a>

                <section class="course-workspace-header">
                    <div>
                        <span class="course-code">
                            <?= escape($assignment['course_code']) ?>
                        </span>

                        <h2>
                            <?= escape($assignment['title']) ?>
                        </h2>

                        <p>
                            <?= escape(
                                $assignment['description']
                                ?: 'No instructions provided.'
                            ) ?>
                        </p>
                    </div>

                    <span
                        class="badge <?= $isPastDeadline
                            ? 'badge-danger'
                            : 'badge-info' ?>"
                    >
                        <?= $isPastDeadline ? 'Closed' : 'Open' ?>
                    </span>
                </section>

                <section class="student-course-content-grid">
                    <article class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <h3>Assignment Information</h3>
                        </header>

                        <div class="dashboard-panel-body">
                            <p>
                                <strong>Deadline:</strong>
                                <?= escape(
                                    date(
                                        'd M Y, g:i A',
                                        $deadlineTimestamp
                                    )
                                ) ?>
                            </p>

                            <p>
                                <strong>Maximum Marks:</strong>
                                <?= number_format(
                                    (float) $assignment['maximum_marks'],
                                    2
                                ) ?>
                            </p>

                            <p>
                                <strong>Allowed Files:</strong>
                                <?= escape(
                                    strtoupper(
                                        $assignment[
                                            'allowed_file_types'
                                        ]
                                    )
                                ) ?>
                            </p>

                            <p>
                                <strong>Membership:</strong>
                                <?= $isMember
                                    ? 'Member — unlimited resubmissions before deadline'
                                    : 'Non-member — maximum two resubmissions' ?>
                            </p>
                        </div>
                    </article>

                    <article class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <h3>Upload Submission</h3>
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

                            <?php if ($canSubmit): ?>
                                <form
                                    method="POST"
                                    enctype="multipart/form-data"
                                >
                                    <div class="form-group">
                                        <label for="submission_file">
                                            Submission File
                                        </label>

                                        <input
                                            id="submission_file"
                                            name="submission_file"
                                            type="file"
                                            required
                                        >

                                        <small class="field-help">
                                            Maximum size: 20 MB.
                                        </small>
                                    </div>

                                    <button
                                        class="button-full"
                                        type="submit"
                                    >
                                        <?= $submissionCount > 0
                                            ? 'Submit New Version'
                                            : 'Submit Assignment' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-error">
                                    You cannot submit another version.
                                    The deadline may have passed or your
                                    resubmission limit has been reached.
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <h3>Submission History</h3>
                        </header>

                        <?php if ($submissionHistory === []): ?>
                            <div class="professional-empty-state">
                                No submission has been uploaded.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Version</th>
                                            <th>File</th>
                                            <th>Submitted</th>
                                            <th>Status</th>
                                            <th>Grade</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach (
                                            $submissionHistory as $submission
                                        ): ?>
                                            <tr>
                                                <td>
                                                    <?= (int) $submission[
                                                        'submission_number'
                                                    ] ?>
                                                </td>

                                                <td>
                                                    <?= escape(
                                                        $submission[
                                                            'file_name'
                                                        ]
                                                    ) ?>
                                                </td>

                                                <td>
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
                                                </td>

                                                <td>
                                                    <?= escape(
                                                        ucfirst(
                                                            $submission[
                                                                'status'
                                                            ]
                                                        )
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?php if (
                                                        (bool) $submission[
                                                            'result_published'
                                                        ]
                                                    ): ?>
                                                        <?= escape(
                                                            number_format(
                                                                (float) $submission[
                                                                    'grade'
                                                                ],
                                                                2
                                                            )
                                                        ) ?>
                                                        /
                                                        <?= escape(
                                                            number_format(
                                                                (float) $assignment[
                                                                    'maximum_marks'
                                                                ],
                                                                2
                                                            )
                                                        ) ?>
                                                    <?php else: ?>
                                                        Not published
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>
            </main>
        </div>
    </div>

    <script
        src="<?= APP_URL ?>/public/assets/js/app.js"
    ></script>
</body>
</html>