<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$courseId = filter_input(
    INPUT_GET,
    'course_id',
    FILTER_VALIDATE_INT
);

if (!$courseId) {
    setFlash('error', 'Invalid course selected.');
    redirect('instructor/courses.php');
}

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
    'course_id' => $courseId,
    'instructor_id' => $user['id'],
]);

$course = $courseQuery->fetch();

if (!$course) {
    http_response_code(404);
    exit('Course not found or access denied.');
}

$pageTitle = 'Upload Material';
$pageSubtitle =
    $course['course_code'] . ' — ' . $course['course_name'];

$errors = [];

$title = '';
$description = '';
$priorityAccess = false;

$allowedExtensions = [
    'pdf',
    'ppt',
    'pptx',
    'doc',
    'docx',
    'zip',
];

$allowedMimeTypes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/zip',
    'application/x-zip-compressed',
];

$maximumFileSize = 20 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priorityAccess = isset($_POST['priority_access']);

    if (strlen($title) < 3) {
        $errors[] =
            'Material title must contain at least 3 characters.';
    }

    if (!isset($_FILES['material_file'])) {
        $errors[] = 'Please select a file.';
    } else {
        $uploadedFile = $_FILES['material_file'];

        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $errors[] =
                'The selected file could not be uploaded.';
        } elseif ($uploadedFile['size'] > $maximumFileSize) {
            $errors[] =
                'The file must not exceed 20 MB.';
        } else {
            $originalFileName = basename(
                $uploadedFile['name']
            );

            $extension = strtolower(
                pathinfo(
                    $originalFileName,
                    PATHINFO_EXTENSION
                )
            );

            if (!in_array(
                $extension,
                $allowedExtensions,
                true
            )) {
                $errors[] =
                    'Only PDF, PPT, PPTX, DOC, DOCX, and ZIP files are allowed.';
            }

            $fileInformation = new finfo(
                FILEINFO_MIME_TYPE
            );

            $mimeType = $fileInformation->file(
                $uploadedFile['tmp_name']
            );

            if (!in_array(
                $mimeType,
                $allowedMimeTypes,
                true
            )) {
                $errors[] =
                    'The selected file type is not supported.';
            }
        }
    }

    if ($errors === []) {
        if (!is_dir(MATERIAL_UPLOAD_PATH)) {
            mkdir(
                MATERIAL_UPLOAD_PATH,
                0755,
                true
            );
        }

        $storedFileName =
            bin2hex(random_bytes(16))
            . '.'
            . $extension;

        $destinationPath =
            MATERIAL_UPLOAD_PATH
            . DIRECTORY_SEPARATOR
            . $storedFileName;

        if (!move_uploaded_file(
            $uploadedFile['tmp_name'],
            $destinationPath
        )) {
            $errors[] =
                'The file could not be saved. Please try again.';
        } else {
            $relativePath =
                'public/uploads/materials/'
                . $storedFileName;

            try {
                $insertQuery = $pdo->prepare(
                    'INSERT INTO course_materials
                        (
                            course_id,
                            uploaded_by,
                            title,
                            description,
                            file_name,
                            file_path,
                            mime_type,
                            file_size,
                            priority_access
                        )
                     VALUES
                        (
                            :course_id,
                            :uploaded_by,
                            :title,
                            :description,
                            :file_name,
                            :file_path,
                            :mime_type,
                            :file_size,
                            :priority_access
                        )'
                );

                $insertQuery->execute([
                    'course_id' => $courseId,
                    'uploaded_by' => $user['id'],
                    'title' => $title,
                    'description' =>
                        $description !== ''
                            ? $description
                            : null,
                    'file_name' => $originalFileName,
                    'file_path' => $relativePath,
                    'mime_type' => $mimeType,
                    'file_size' =>
                        (int) $uploadedFile['size'],
                    'priority_access' =>
                        $priorityAccess ? 1 : 0,
                ]);

                setFlash(
                    'success',
                    'Course material uploaded successfully.'
                );

                redirect(
                    'instructor/materials.php?course_id='
                    . $courseId
                );
            } catch (PDOException $exception) {
                if (file_exists($destinationPath)) {
                    unlink($destinationPath);
                }

                $errors[] =
                    'The material could not be saved in the database.';
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
        Upload Material | <?= escape(APP_NAME) ?>
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
                        href="<?= APP_URL ?>/instructor/materials.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        ← Back to Materials
                    </a>

                    <section class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>Upload Course Material</h3>

                                <p class="panel-subtitle">
                                    Add learning resources for
                                    <?= escape($course['course_code']) ?>.
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

                            <form
                                method="POST"
                                enctype="multipart/form-data"
                                novalidate
                            >
                                <div class="form-grid">
                                    <div class="form-group full">
                                        <label for="title">
                                            Material Title
                                        </label>

                                        <input
                                            id="title"
                                            name="title"
                                            type="text"
                                            value="<?= escape($title) ?>"
                                            placeholder="Example: Week 1 Lecture Slides"
                                            maxlength="180"
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
                                            rows="4"
                                            placeholder="Briefly describe this material."
                                        ><?= escape($description) ?></textarea>
                                    </div>

                                    <div class="form-group full">
                                        <label for="material_file">
                                            Select File
                                        </label>

                                        <input
                                            id="material_file"
                                            name="material_file"
                                            type="file"
                                            accept=".pdf,.ppt,.pptx,.doc,.docx,.zip"
                                            required
                                        >

                                        <small class="field-help">
                                            Allowed: PDF, PPT, PPTX, DOC,
                                            DOCX, ZIP. Maximum size: 20 MB.
                                        </small>
                                    </div>

                                    <div class="form-group full">
                                        <label class="checkbox-row">
                                            <input
                                                name="priority_access"
                                                type="checkbox"
                                                value="1"
                                                <?= $priorityAccess
                                                    ? 'checked'
                                                    : '' ?>
                                            >

                                            <span>
                                                Mark as priority material
                                            </span>
                                        </label>

                                        <small class="field-help">
                                            Priority materials may later be
                                            shown earlier to membership users.
                                        </small>
                                    </div>

                                    <div class="form-group full form-actions">
                                        <a
                                            class="button button-secondary"
                                            href="<?= APP_URL ?>/instructor/materials.php?course_id=<?= (int) $course['id'] ?>"
                                        >
                                            Cancel
                                        </a>

                                        <button type="submit">
                                            Upload Material
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