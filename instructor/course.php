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

$query = $pdo->prepare(
    'SELECT
        courses.*,
        COUNT(DISTINCT enrollments.student_id)
            AS enrolled_students,
        COUNT(DISTINCT assignments.id)
            AS total_assignments,
        COUNT(DISTINCT course_materials.id)
            AS total_materials,
        COUNT(DISTINCT attendance_sessions.id)
            AS total_attendance_sessions
     FROM courses
     LEFT JOIN enrollments
        ON enrollments.course_id = courses.id
     LEFT JOIN assignments
        ON assignments.course_id = courses.id
     LEFT JOIN course_materials
        ON course_materials.course_id = courses.id
     LEFT JOIN attendance_sessions
        ON attendance_sessions.course_id = courses.id
     WHERE courses.id = :course_id
       AND courses.instructor_id = :instructor_id
     GROUP BY
        courses.id,
        courses.instructor_id,
        courses.course_code,
        courses.course_name,
        courses.description,
        courses.enrolment_key,
        courses.is_published,
        courses.created_at,
        courses.updated_at
     LIMIT 1'
);

$query->execute([
    'course_id' => $courseId,
    'instructor_id' => $user['id'],
]);

$course = $query->fetch();

if (!$course) {
    http_response_code(404);

    exit('Course not found or access denied.');
}

$pageTitle = $course['course_code'];
$pageSubtitle = $course['course_name'];
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
                    href="<?= APP_URL ?>/instructor/courses.php"
                >
                    ← Back to Courses
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
                    </div>

                    <span
                        class="badge <?= (bool) $course['is_published']
                            ? 'badge-success'
                            : 'badge-warning' ?>"
                    >
                        <?= (bool) $course['is_published']
                            ? 'Published'
                            : 'Draft' ?>
                    </span>
                </section>

                <section class="statistics-grid">
                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/instructor/students.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        <div class="statistics-label">
                            Enrolled Students
                        </div>

                        <div class="statistics-value">
                            <?= (int) $course['enrolled_students'] ?>
                        </div>

                        <div class="statistics-description">
                            View enrolled students
                        </div>
                    </a>

                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/instructor/assignments.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        <div class="statistics-label">
                            Assignments
                        </div>

                        <div class="statistics-value">
                            <?= (int) $course['total_assignments'] ?>
                        </div>

                        <div class="statistics-description">
                            Manage course assignments
                        </div>
                    </a>

                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/instructor/materials.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        <div class="statistics-label">
                            Materials
                        </div>

                        <div class="statistics-value">
                            <?= (int) $course['total_materials'] ?>
                        </div>

                        <div class="statistics-description">
                            Manage learning resources
                        </div>
                    </a>

                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/instructor/attendance.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        <div class="statistics-label">
                            Attendance Sessions
                        </div>

                        <div class="statistics-value">
                            <?= (int) $course['total_attendance_sessions'] ?>
                        </div>

                        <div class="statistics-description">
                            Create or review attendance
                        </div>
                    </a>
                </section>

                <section class="course-enrolment-panel">
                    <div>
                        <span>Enrolment Key</span>

                        <strong>
                            <?= escape(
                                $course['enrolment_key']
                                ?: 'Open enrolment'
                            ) ?>
                        </strong>
                    </div>

                    <a
                        class="button button-small button-secondary"
                        href="<?= APP_URL ?>/instructor/edit-course.php?id=<?= (int) $course['id'] ?>"
                    >
                        Edit Course
                    </a>
                </section>

                <section class="workspace-module-grid">
                    <a
                        class="workspace-module-card"
                        href="<?= APP_URL ?>/instructor/materials.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        <h3>Course Materials</h3>

                        <p>
                            Upload lecture notes, presentation slides,
                            documents and learning resources.
                        </p>
                    </a>

                    <a
                        class="workspace-module-card"
                        href="<?= APP_URL ?>/instructor/assignments.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        <h3>Assignments</h3>

                        <p>
                            Create coursework, set deadlines and review
                            student submissions.
                        </p>
                    </a>

                    <a
                        class="workspace-module-card"
                        href="<?= APP_URL ?>/instructor/attendance.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        <h3>Attendance</h3>

                        <p>
                            Create class sessions and record present,
                            absent, late or excused students.
                        </p>
                    </a>

                    <a
                        class="workspace-module-card"
                        href="<?= APP_URL ?>/instructor/announcements.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        <h3>Announcements</h3>

                        <p>
                            Publish course notices and important updates
                            for enrolled students.
                        </p>
                    </a>

                    <a
                        class="workspace-module-card"
                        href="<?= APP_URL ?>/instructor/gradebook.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        <h3>Gradebook</h3>

                        <p>
                            Review marks, instructor feedback and
                            published student results.
                        </p>
                    </a>

                    <a
                        class="workspace-module-card"
                        href="<?= APP_URL ?>/instructor/students.php?course_id=<?= (int) $course['id'] ?>"
                    >
                        <h3>Students</h3>

                        <p>
                            View enrolled students, contact details and
                            academic participation.
                        </p>
                    </a>
                </section>
            </main>
        </div>
    </div>

    <script
        src="<?= APP_URL ?>/public/assets/js/app.js"
    ></script>
</body>
</html>