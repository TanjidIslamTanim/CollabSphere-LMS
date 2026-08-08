<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$pageTitle = 'Instructor Dashboard';
$pageSubtitle =
    'Manage your courses, students and academic activities.';

$courseQuery = $pdo->prepare(
    'SELECT COUNT(*)
     FROM courses
     WHERE instructor_id = :instructor_id'
);

$courseQuery->execute([
    'instructor_id' => $user['id'],
]);

$totalCourses = (int) $courseQuery->fetchColumn();

$studentQuery = $pdo->prepare(
    'SELECT COUNT(DISTINCT enrollments.student_id)
     FROM enrollments
     INNER JOIN courses
         ON courses.id = enrollments.course_id
     WHERE courses.instructor_id = :instructor_id'
);

$studentQuery->execute([
    'instructor_id' => $user['id'],
]);

$totalStudents = (int) $studentQuery->fetchColumn();

$assignmentQuery = $pdo->prepare(
    'SELECT COUNT(*)
     FROM assignments
     WHERE created_by = :instructor_id'
);

$assignmentQuery->execute([
    'instructor_id' => $user['id'],
]);

$totalAssignments = (int) $assignmentQuery->fetchColumn();

$pendingQuery = $pdo->prepare(
    "SELECT COUNT(*)
     FROM submissions
     INNER JOIN assignments
         ON assignments.id = submissions.assignment_id
     WHERE assignments.created_by = :instructor_id
       AND submissions.status != 'graded'"
);

$pendingQuery->execute([
    'instructor_id' => $user['id'],
]);

$pendingSubmissions = (int) $pendingQuery->fetchColumn();

$recentCoursesQuery = $pdo->prepare(
    'SELECT
        courses.id,
        courses.course_code,
        courses.course_name,
        courses.is_published,
        COUNT(enrollments.id) AS enrolled_students
     FROM courses
     LEFT JOIN enrollments
        ON enrollments.course_id = courses.id
     WHERE courses.instructor_id = :instructor_id
     GROUP BY
        courses.id,
        courses.course_code,
        courses.course_name,
        courses.is_published,
        courses.created_at
     ORDER BY courses.created_at DESC
     LIMIT 4'
);

$recentCoursesQuery->execute([
    'instructor_id' => $user['id'],
]);

$recentCourses = $recentCoursesQuery->fetchAll();
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
        Instructor Dashboard | <?= escape(APP_NAME) ?>
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
                <section class="welcome-banner">
                    <h2>
                        Welcome back,
                        <?= escape($user['full_name']) ?>
                    </h2>

                    <p>
                        Manage your teaching activities, review
                        submissions, record attendance and support your
                        students from one central workspace.
                    </p>
                </section>

                <section class="statistics-grid">
                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/instructor/courses.php"
                    >
                        <div class="statistics-label">
                            Total Courses
                        </div>

                        <div class="statistics-value">
                            <?= $totalCourses ?>
                        </div>

                        <div class="statistics-description">
                            View and manage your courses
                        </div>
                    </a>

                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/instructor/courses.php"
                    >
                        <div class="statistics-label">
                            Enrolled Students
                        </div>

                        <div class="statistics-value">
                            <?= $totalStudents ?>
                        </div>

                        <div class="statistics-description">
                            Across all your courses
                        </div>
                    </a>

                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/instructor/assignments.php"
                    >
                        <div class="statistics-label">
                            Assignments
                        </div>

                        <div class="statistics-value">
                            <?= $totalAssignments ?>
                        </div>

                        <div class="statistics-description">
                            Create and manage assignments
                        </div>
                    </a>

                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/instructor/assignments.php"
                    >
                        <div class="statistics-label">
                            Pending Reviews
                        </div>

                        <div class="statistics-value">
                            <?= $pendingSubmissions ?>
                        </div>

                        <div class="statistics-description">
                            Submissions awaiting grading
                        </div>
                    </a>
                </section>

                <section class="dashboard-content-grid">
                    <article class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <h3>Recent Courses</h3>

                            <a
                                href="<?= APP_URL ?>/instructor/create-course.php"
                                class="button button-small"
                            >
                                Create Course
                            </a>
                        </header>

                        <?php if ($recentCourses === []): ?>
                            <div class="professional-empty-state">
                                <h3>No courses created yet</h3>

                                <p>
                                    Create your first course to begin
                                    managing students and learning content.
                                </p>

                                <a
                                    href="<?= APP_URL ?>/instructor/create-course.php"
                                    class="button"
                                >
                                    Create First Course
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-course-list">
                                <?php foreach ($recentCourses as $course): ?>
                                    <a
                                        class="dashboard-course-row"
                                        href="<?= APP_URL ?>/instructor/course.php?id=<?= (int) $course['id'] ?>"
                                    >
                                        <div>
                                            <span class="course-code">
                                                <?= escape(
                                                    $course['course_code']
                                                ) ?>
                                            </span>

                                            <h4>
                                                <?= escape(
                                                    $course['course_name']
                                                ) ?>
                                            </h4>

                                            <p>
                                                <?= (int) $course[
                                                    'enrolled_students'
                                                ] ?>
                                                enrolled student(s)
                                            </p>
                                        </div>

                                        <span
                                            class="badge <?= (bool) $course[
                                                'is_published'
                                            ]
                                                ? 'badge-success'
                                                : 'badge-warning' ?>"
                                        >
                                            <?= (bool) $course[
                                                'is_published'
                                            ]
                                                ? 'Published'
                                                : 'Draft' ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <h3>Quick Actions</h3>
                        </header>

                        <div class="dashboard-panel-body quick-action-list">
                            <a
                                href="<?= APP_URL ?>/instructor/create-course.php"
                                class="button button-full"
                            >
                                Create Course
                            </a>

                            <a
                                href="<?= APP_URL ?>/instructor/create-assignment.php"
                                class="button button-full button-secondary"
                            >
                                Create Assignment
                            </a>

                            <a
                                href="<?= APP_URL ?>/instructor/assignments.php"
                                class="button button-full button-secondary"
                            >
                                View Assignments
                            </a>

                            <a
                                href="<?= APP_URL ?>/instructor/courses.php"
                                class="button button-full button-secondary"
                            >
                                Manage Course Content
                            </a>
                        </div>
                    </article>
                </section>
                <?php
                require __DIR__
                    . '/../includes/notification-panel.php';
                ?>
            </main>
        </div>
    </div>

    <script
        src="<?= APP_URL ?>/public/assets/js/app.js"
    ></script>
</body>
</html>