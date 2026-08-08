<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('student');

$user = currentUser();

$pageTitle = 'My Courses';
$pageSubtitle = 'Join courses and access your learning workspace.';

$flash = getFlash();
$errors = [];

$courseCode = '';
$enrolmentKey = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseCode = strtoupper(
        trim($_POST['course_code'] ?? '')
    );

    $enrolmentKey = trim(
        $_POST['enrolment_key'] ?? ''
    );

    if ($courseCode === '') {
        $errors[] = 'Please enter a course code.';
    }

    if ($errors === []) {
        $courseQuery = $pdo->prepare(
            'SELECT
                id,
                course_code,
                course_name,
                enrolment_key,
                is_published
             FROM courses
             WHERE course_code = :course_code
             LIMIT 1'
        );

        $courseQuery->execute([
            'course_code' => $courseCode,
        ]);

        $course = $courseQuery->fetch();

        if (!$course) {
            $errors[] =
                'No course was found with that course code.';
        } elseif (!(bool) $course['is_published']) {
            $errors[] =
                'This course is not currently available for enrolment.';
        } elseif (
            $course['enrolment_key'] !== null
            && $course['enrolment_key'] !== ''
            && !hash_equals(
                (string) $course['enrolment_key'],
                $enrolmentKey
            )
        ) {
            $errors[] = 'The enrolment key is incorrect.';
        } else {
            $existingQuery = $pdo->prepare(
                'SELECT id
                 FROM enrollments
                 WHERE course_id = :course_id
                   AND student_id = :student_id
                 LIMIT 1'
            );

            $existingQuery->execute([
                'course_id' => $course['id'],
                'student_id' => $user['id'],
            ]);

            if ($existingQuery->fetch()) {
                $errors[] =
                    'You are already enrolled in this course.';
            } else {
                $insertQuery = $pdo->prepare(
                    'INSERT INTO enrollments
                        (
                            course_id,
                            student_id
                        )
                     VALUES
                        (
                            :course_id,
                            :student_id
                        )'
                );

                $insertQuery->execute([
                    'course_id' => $course['id'],
                    'student_id' => $user['id'],
                ]);

                setFlash(
                    'success',
                    'You joined '
                    . $course['course_name']
                    . ' successfully.'
                );

                redirect('student/courses.php');
            }
        }
    }
}

$enrolledQuery = $pdo->prepare(
    'SELECT
        courses.id,
        courses.course_code,
        courses.course_name,
        courses.description,
        courses.created_at,
        users.full_name AS instructor_name,
        COUNT(DISTINCT course_materials.id)
            AS total_materials,
        COUNT(DISTINCT assignments.id)
            AS total_assignments
     FROM enrollments
     INNER JOIN courses
        ON courses.id = enrollments.course_id
     INNER JOIN users
        ON users.id = courses.instructor_id
     LEFT JOIN course_materials
        ON course_materials.course_id = courses.id
     LEFT JOIN assignments
        ON assignments.course_id = courses.id
     WHERE enrollments.student_id = :student_id
     GROUP BY
        courses.id,
        courses.course_code,
        courses.course_name,
        courses.description,
        courses.created_at,
        users.full_name
     ORDER BY enrollments.enrolled_at DESC'
);

$enrolledQuery->execute([
    'student_id' => $user['id'],
]);

$courses = $enrolledQuery->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Courses | <?= escape(APP_NAME) ?></title>

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

                <section class="student-course-layout">
                    <div>
                        <section class="page-heading-row">
                            <div>
                                <h2>My Courses</h2>

                                <p>
                                    Access your enrolled courses and
                                    learning materials.
                                </p>
                            </div>
                        </section>

                        <?php if ($courses === []): ?>
                            <section class="dashboard-panel">
                                <div class="professional-empty-state">
                                    <h3>No enrolled courses</h3>

                                    <p>
                                        Enter a course code and enrolment
                                        key to join your first course.
                                    </p>
                                </div>
                            </section>
                        <?php else: ?>
                            <section class="course-grid">
                                <?php foreach ($courses as $course): ?>
                                    <article class="course-card">
                                        <div class="course-card-top">
                                            <div>
                                                <span class="course-code">
                                                    <?= escape(
                                                        $course['course_code']
                                                    ) ?>
                                                </span>

                                                <h3>
                                                    <?= escape(
                                                        $course['course_name']
                                                    ) ?>
                                                </h3>
                                            </div>

                                            <span class="badge badge-success">
                                                Enrolled
                                            </span>
                                        </div>

                                        <p class="course-description">
                                            <?= escape(
                                                $course['description']
                                                ?: 'No course description added.'
                                            ) ?>
                                        </p>

                                        <div class="student-course-instructor">
                                            Instructor:
                                            <strong>
                                                <?= escape(
                                                    $course['instructor_name']
                                                ) ?>
                                            </strong>
                                        </div>

                                        <div class="course-meta-grid">
                                            <div>
                                                <span>Materials</span>

                                                <strong>
                                                    <?= (int) $course[
                                                        'total_materials'
                                                    ] ?>
                                                </strong>
                                            </div>

                                            <div>
                                                <span>Assignments</span>

                                                <strong>
                                                    <?= (int) $course[
                                                        'total_assignments'
                                                    ] ?>
                                                </strong>
                                            </div>
                                        </div>

                                        <a
                                            class="button student-open-course"
                                            href="<?= APP_URL ?>/student/course.php?id=<?= (int) $course['id'] ?>"
                                        >
                                            Open Course
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </section>
                        <?php endif; ?>
                    </div>

                    <aside class="dashboard-panel join-course-panel">
                        <header class="dashboard-panel-header">
                            <div>
                                <h3>Join a Course</h3>

                                <p class="panel-subtitle">
                                    Enter the details supplied by your
                                    instructor.
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
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="enrolment_key">
                                        Enrolment Key
                                    </label>

                                    <input
                                        id="enrolment_key"
                                        name="enrolment_key"
                                        type="text"
                                        value="<?= escape($enrolmentKey) ?>"
                                        placeholder="Enter the course key"
                                    >

                                    <small class="field-help">
                                        Leave this empty if the course
                                        does not require a key.
                                    </small>
                                </div>

                                <button
                                    class="button-full"
                                    type="submit"
                                >
                                    Join Course
                                </button>
                            </form>
                        </div>
                    </aside>
                </section>
            </main>
        </div>
    </div>

    <script
        src="<?= APP_URL ?>/public/assets/js/app.js"
    ></script>
</body>
</html>