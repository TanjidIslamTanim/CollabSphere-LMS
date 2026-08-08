<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('student');

$user = currentUser();

$courseId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$courseId) {
    setFlash('error', 'Invalid course selected.');
    redirect('student/courses.php');
}

$courseQuery = $pdo->prepare(
    'SELECT
        courses.id,
        courses.course_code,
        courses.course_name,
        courses.description,
        users.full_name AS instructor_name
     FROM enrollments
     INNER JOIN courses
        ON courses.id = enrollments.course_id
     INNER JOIN users
        ON users.id = courses.instructor_id
     WHERE enrollments.student_id = :student_id
       AND courses.id = :course_id
     LIMIT 1'
);

$courseQuery->execute([
    'student_id' => $user['id'],
    'course_id' => $courseId,
]);

$course = $courseQuery->fetch();

if (!$course) {
    http_response_code(404);
    exit('Course not found or you are not enrolled.');
}

$materialsQuery = $pdo->prepare(
    'SELECT
        id,
        title,
        description,
        file_name,
        file_size,
        priority_access,
        created_at
     FROM course_materials
     WHERE course_id = :course_id
     ORDER BY
        priority_access DESC,
        created_at DESC'
);

$materialsQuery->execute([
    'course_id' => $courseId,
]);

$materials = $materialsQuery->fetchAll();

$assignmentsQuery = $pdo->prepare(
    'SELECT
        id,
        title,
        description,
        deadline,
        maximum_marks
     FROM assignments
     WHERE course_id = :course_id
     ORDER BY deadline ASC'
);

$assignmentsQuery->execute([
    'course_id' => $courseId,
]);

$assignments = $assignmentsQuery->fetchAll();

$pageTitle = $course['course_code'];
$pageSubtitle = $course['course_name'];

function studentFormatFileSize(?int $bytes): string
{
    if ($bytes === null || $bytes <= 0) {
        return 'Unknown size';
    }

    $units = ['B', 'KB', 'MB', 'GB'];

    $power = min(
        (int) floor(log($bytes, 1024)),
        count($units) - 1
    );

    return number_format(
        $bytes / (1024 ** $power),
        $power === 0 ? 0 : 1
    ) . ' ' . $units[$power];
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
        <?= escape($course['course_name']) ?> |
        <?= escape(APP_NAME) ?>
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
                <a
                    class="back-link"
                    href="<?= APP_URL ?>/student/courses.php"
                >
                    ← Back to My Courses
                </a>

                <section class="course-workspace-header">
                    <div>
                        <span class="course-code">
                            <?= escape($course['course_code']) ?>
                        </span>

                        <h2>
                            <?= escape($course['course_name']) ?>
                        </h2>

                        <p>
                            <?= escape(
                                $course['description']
                                ?: 'No description provided.'
                            ) ?>
                        </p>

                        <div class="student-instructor-label">
                            Instructor:
                            <?= escape($course['instructor_name']) ?>
                        </div>
                    </div>

                    <span class="badge badge-success">
                        Enrolled
                    </span>
                </section>

                <section class="student-course-content-grid">
                    <article class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>Course Materials</h3>

                                <p class="panel-subtitle">
                                    Download notes, slides and learning
                                    resources.
                                </p>
                            </div>
                        </header>

                        <?php if ($materials === []): ?>
                            <div class="professional-empty-state">
                                No materials have been uploaded yet.
                            </div>
                        <?php else: ?>
                            <div class="student-material-list">
                                <?php foreach ($materials as $material): ?>
                                    <div class="student-material-row">
                                        <div class="student-material-icon">
                                            <?= escape(
                                                strtoupper(
                                                    pathinfo(
                                                        $material['file_name'],
                                                        PATHINFO_EXTENSION
                                                    )
                                                )
                                            ) ?>
                                        </div>

                                        <div class="student-material-details">
                                            <div>
                                                <h4>
                                                    <?= escape(
                                                        $material['title']
                                                    ) ?>
                                                </h4>

                                                <p>
                                                    <?= escape(
                                                        $material[
                                                            'description'
                                                        ]
                                                        ?: 'No description.'
                                                    ) ?>
                                                </p>
                                            </div>

                                            <div class="student-material-meta">
                                                <span>
                                                    <?= escape(
                                                        $material['file_name']
                                                    ) ?>
                                                </span>

                                                <span>
                                                    <?= escape(
                                                        studentFormatFileSize(
                                                            $material[
                                                                'file_size'
                                                            ] !== null
                                                                ? (int) $material[
                                                                    'file_size'
                                                                ]
                                                                : null
                                                        )
                                                    ) ?>
                                                </span>

                                                <?php if (
                                                    (bool) $material[
                                                        'priority_access'
                                                    ]
                                                ): ?>
                                                    <span
                                                        class="badge badge-info"
                                                    >
                                                        Priority
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <a
                                            class="button button-small"
                                            href="<?= APP_URL ?>/student/download-material.php?id=<?= (int) $material['id'] ?>"
                                        >
                                            Download
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>Assignments</h3>

                                <p class="panel-subtitle">
                                    View upcoming coursework.
                                </p>
                            </div>
                        </header>

                        <?php if ($assignments === []): ?>
                            <div class="professional-empty-state">
                                No assignments have been published yet.
                            </div>
                        <?php else: ?>
                            <div class="student-assignment-list">
                                <?php foreach ($assignments as $assignment): ?>
                                    <div class="student-assignment-row">
                                        <div>
                                            <h4>
                                                <?= escape(
                                                    $assignment['title']
                                                ) ?>
                                            </h4>

                                            <p>
                                                Due:
                                                <?= escape(
                                                    date(
                                                        'd M Y, g:i A',
                                                        strtotime(
                                                            $assignment[
                                                                'deadline'
                                                            ]
                                                        )
                                                    )
                                                ) ?>
                                            </p>
                                        </div>

                                        <strong>
                                            <?= escape(
                                                number_format(
                                                    (float) $assignment[
                                                        'maximum_marks'
                                                    ],
                                                    0
                                                )
                                            ) ?>
                                            marks
                                        </strong>
                                    </div>
                                <?php endforeach; ?>
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