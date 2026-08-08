<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('student');

$user = currentUser();

$pageTitle = 'Student Dashboard';
$pageSubtitle = 'Track your courses, assignments and academic progress.';

$courseQuery = $pdo->prepare(
    'SELECT COUNT(*)
     FROM enrollments
     WHERE student_id = :student_id'
);

$courseQuery->execute([
    'student_id' => $user['id'],
]);

$totalCourses = (int) $courseQuery->fetchColumn();

$assignmentQuery = $pdo->prepare(
    'SELECT COUNT(*)
     FROM assignments
     INNER JOIN enrollments
        ON enrollments.course_id = assignments.course_id
     WHERE enrollments.student_id = :student_id'
);

$assignmentQuery->execute([
    'student_id' => $user['id'],
]);

$totalAssignments = (int) $assignmentQuery->fetchColumn();

$gradedQuery = $pdo->prepare(
    "SELECT COUNT(*)
     FROM submissions
     WHERE student_id = :student_id
       AND status = 'graded'"
);

$gradedQuery->execute([
    'student_id' => $user['id'],
]);

$gradedAssignments = (int) $gradedQuery->fetchColumn();

$materialQuery = $pdo->prepare(
    'SELECT COUNT(*)
     FROM course_materials
     INNER JOIN enrollments
        ON enrollments.course_id = course_materials.course_id
     WHERE enrollments.student_id = :student_id'
);

$materialQuery->execute([
    'student_id' => $user['id'],
]);

$totalMaterials = (int) $materialQuery->fetchColumn();

$recentCoursesQuery = $pdo->prepare(
    'SELECT
        courses.id,
        courses.course_code,
        courses.course_name,
        users.full_name AS instructor_name
     FROM enrollments
     INNER JOIN courses
        ON courses.id = enrollments.course_id
     INNER JOIN users
        ON users.id = courses.instructor_id
     WHERE enrollments.student_id = :student_id
     ORDER BY enrollments.enrolled_at DESC
     LIMIT 4'
);

$recentCoursesQuery->execute([
    'student_id' => $user['id'],
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

    <title>Student Dashboard | <?= escape(APP_NAME) ?></title>

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
                        Access your courses, learning materials,
                        assignments, attendance and grades from one
                        central workspace.
                    </p>
                </section>

                <section class="statistics-grid">
                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/student/courses.php"
                    >
                        <div class="statistics-label">
                            My Courses
                        </div>

                        <div class="statistics-value">
                            <?= $totalCourses ?>
                        </div>

                        <div class="statistics-description">
                            Courses currently enrolled
                        </div>
                    </a>

                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/student/assignments.php"
                    >
                        <div class="statistics-label">
                            Assignments
                        </div>

                        <div class="statistics-value">
                            <?= $totalAssignments ?>
                        </div>

                        <div class="statistics-description">
                            Available coursework
                        </div>
                    </a>

                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/student/grades.php"
                    >
                        <div class="statistics-label">
                            Graded Work
                        </div>

                        <div class="statistics-value">
                            <?= $gradedAssignments ?>
                        </div>

                        <div class="statistics-description">
                            Results currently available
                        </div>
                    </a>

                    <a
                        class="statistics-card dashboard-stat-link"
                        href="<?= APP_URL ?>/student/courses.php"
                    >
                        <div class="statistics-label">
                            Materials
                        </div>

                        <div class="statistics-value">
                            <?= $totalMaterials ?>
                        </div>

                        <div class="statistics-description">
                            Learning resources available
                        </div>
                    </a>
                </section>

                <section class="dashboard-content-grid">
                    <article class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <h3>My Recent Courses</h3>

                            <a
                                class="button button-small"
                                href="<?= APP_URL ?>/student/courses.php"
                            >
                                View All Courses
                            </a>
                        </header>

                        <?php if ($recentCourses === []): ?>
                            <div class="professional-empty-state">
                                <h3>No courses joined yet</h3>

                                <p>
                                    Join your first course using the
                                    course code and enrolment key.
                                </p>

                                <a
                                    class="button"
                                    href="<?= APP_URL ?>/student/courses.php"
                                >
                                    Join a Course
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="student-dashboard-course-list">
                                <?php foreach ($recentCourses as $course): ?>
                                    <a
                                        class="student-dashboard-course"
                                        href="<?= APP_URL ?>/student/course.php?id=<?= (int) $course['id'] ?>"
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
                                                Instructor:
                                                <?= escape(
                                                    $course['instructor_name']
                                                ) ?>
                                            </p>
                                        </div>

                                        <span>Open</span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>

                    <article class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <h3>Account Summary</h3>
                        </header>

                        <div class="dashboard-panel-body">
                            <p>
                                Membership:
                                <strong>
                                    <?= escape(
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $user['membership_type']
                                            )
                                        )
                                    ) ?>
                                </strong>
                            </p>

                            <a
                                class="button button-full"
                                href="<?= APP_URL ?>/student/courses.php"
                            >
                                Open My Courses
                            </a>

                            <a
                                class="button button-full button-secondary"
                                href="<?= APP_URL ?>/student/membership.php"
                            >
                                Manage Membership
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