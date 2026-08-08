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

$pageTitle = 'Course Materials';
$pageSubtitle =
    $course['course_code'] . ' — ' . $course['course_name'];

$flash = getFlash();

$materialsQuery = $pdo->prepare(
    'SELECT
        id,
        title,
        description,
        file_name,
        file_path,
        mime_type,
        file_size,
        priority_access,
        created_at
     FROM course_materials
     WHERE course_id = :course_id
     ORDER BY created_at DESC'
);

$materialsQuery->execute([
    'course_id' => $courseId,
]);

$materials = $materialsQuery->fetchAll();

function formatFileSize(?int $bytes): string
{
    if ($bytes === null || $bytes <= 0) {
        return 'Unknown size';
    }

    $units = ['B', 'KB', 'MB', 'GB'];
    $power = min(
        (int) floor(log($bytes, 1024)),
        count($units) - 1
    );

    $size = $bytes / (1024 ** $power);

    return number_format($size, $power === 0 ? 0 : 1)
        . ' '
        . $units[$power];
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
        Course Materials | <?= escape(APP_NAME) ?>
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
                    href="<?= APP_URL ?>/instructor/course.php?id=<?= (int) $course['id'] ?>"
                >
                    ← Back to Course
                </a>

                <section class="page-heading-row">
                    <div>
                        <h2>Course Materials</h2>

                        <p>
                            Upload and manage learning resources for
                            <?= escape($course['course_code']) ?>.
                        </p>
                    </div>

                    <a
                        class="button"
                        href="<?= APP_URL ?>/instructor/upload-material.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        Upload Material
                    </a>
                </section>

                <?php if ($materials === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No materials uploaded yet</h3>

                            <p>
                                Upload lecture slides, notes, documents,
                                or other learning resources.
                            </p>

                            <a
                                class="button"
                                href="<?= APP_URL ?>/instructor/upload-material.php?course_id=<?= (int) $course['id'] ?>"
                            >
                                Upload First Material
                            </a>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="materials-grid">
                        <?php foreach ($materials as $material): ?>
                            <article class="material-card">
                                <div class="material-icon">
                                    <?= escape(
                                        strtoupper(
                                            pathinfo(
                                                $material['file_name'],
                                                PATHINFO_EXTENSION
                                            )
                                        )
                                    ) ?>
                                </div>

                                <div class="material-content">
                                    <div class="material-heading">
                                        <div>
                                            <h3>
                                                <?= escape($material['title']) ?>
                                            </h3>

                                            <p>
                                                <?= escape(
                                                    $material['description']
                                                    ?: 'No description provided.'
                                                ) ?>
                                            </p>
                                        </div>

                                        <?php if ((bool) $material['priority_access']): ?>
                                            <span class="badge badge-info">
                                                Priority
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="material-meta">
                                        <span>
                                            <?= escape($material['file_name']) ?>
                                        </span>

                                        <span>
                                            <?= escape(
                                                formatFileSize(
                                                    $material['file_size'] !== null
                                                        ? (int) $material['file_size']
                                                        : null
                                                )
                                            ) ?>
                                        </span>

                                        <span>
                                            <?= escape(
                                                date(
                                                    'd M Y, g:i A',
                                                    strtotime(
                                                        $material['created_at']
                                                    )
                                                )
                                            ) ?>
                                        </span>
                                    </div>

                                    <div class="material-actions">
                                        <a
                                            class="button button-small"
                                            href="<?= APP_URL ?>/instructor/download-material.php?id=<?= (int) $material['id'] ?>"
                                        >
                                            Download
                                        </a>

                                        <form
                                            method="POST"
                                            action="<?= APP_URL ?>/instructor/delete-material.php"
                                            onsubmit="return confirm(
                                                'Delete this material permanently?'
                                            );"
                                        >
                                            <input
                                                type="hidden"
                                                name="material_id"
                                                value="<?= (int) $material['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="course_id"
                                                value="<?= (int) $course['id'] ?>"
                                            >

                                            <button
                                                class="button button-small button-danger"
                                                type="submit"
                                            >
                                                Delete
                                            </button>
                                        </form>
                                    </div>
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