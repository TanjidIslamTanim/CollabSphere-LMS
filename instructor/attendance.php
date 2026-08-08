<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$selectedCourseId = filter_input(
    INPUT_GET,
    'course_id',
    FILTER_VALIDATE_INT
);

$pageTitle = 'Attendance';
$pageSubtitle = 'Create sessions and manage student attendance.';

$flash = getFlash();
$selectedCourse = null;

if ($selectedCourseId) {
    $courseQuery = $pdo->prepare(
        'SELECT id, course_code, course_name
         FROM courses
         WHERE id = :course_id
           AND instructor_id = :instructor_id
         LIMIT 1'
    );

    $courseQuery->execute([
        'course_id' => $selectedCourseId,
        'instructor_id' => $user['id'],
    ]);

    $selectedCourse = $courseQuery->fetch();

    if (!$selectedCourse) {
        setFlash('error', 'Course not found or access denied.');
        redirect('instructor/attendance.php');
    }

    $pageSubtitle =
        $selectedCourse['course_code']
        . ' — '
        . $selectedCourse['course_name'];
}

$sql = '
    SELECT
        attendance_sessions.id,
        attendance_sessions.course_id,
        attendance_sessions.session_date,
        attendance_sessions.title,
        attendance_sessions.notes,
        attendance_sessions.created_at,
        courses.course_code,
        courses.course_name,
        COUNT(attendance_records.id) AS marked_students,
        SUM(attendance_records.status = "present") AS present_count,
        SUM(attendance_records.status = "absent") AS absent_count,
        SUM(attendance_records.status = "late") AS late_count,
        SUM(attendance_records.status = "excused") AS excused_count
    FROM attendance_sessions
    INNER JOIN courses
        ON courses.id = attendance_sessions.course_id
    LEFT JOIN attendance_records
        ON attendance_records.attendance_session_id =
            attendance_sessions.id
    WHERE courses.instructor_id = :instructor_id
';

$params = [
    'instructor_id' => $user['id'],
];

if ($selectedCourseId) {
    $sql .= ' AND courses.id = :course_id';

    $params['course_id'] = $selectedCourseId;
}

$sql .= '
    GROUP BY
        attendance_sessions.id,
        attendance_sessions.course_id,
        attendance_sessions.session_date,
        attendance_sessions.title,
        attendance_sessions.notes,
        attendance_sessions.created_at,
        courses.course_code,
        courses.course_name
    ORDER BY attendance_sessions.session_date DESC
';

$query = $pdo->prepare($sql);
$query->execute($params);

$sessions = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Attendance | <?= escape(APP_NAME) ?></title>

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

                <?php if ($selectedCourse !== null): ?>
                    <a
                        class="back-link"
                        href="<?= APP_URL ?>/instructor/course.php?id=<?= (int) $selectedCourse['id'] ?>"
                    >
                        ← Back to Course
                    </a>
                <?php endif; ?>

                <section class="page-heading-row">
                    <div>
                        <h2>
                            <?= $selectedCourse !== null
                                ? escape(
                                    $selectedCourse['course_code']
                                    . ' Attendance'
                                )
                                : 'Attendance' ?>
                        </h2>

                        <p>
                            Create class sessions and record student
                            attendance.
                        </p>
                    </div>

                    <a
                        class="button"
                        href="<?= APP_URL ?>/instructor/create-attendance.php<?= $selectedCourseId
                            ? '?course_id=' . $selectedCourseId
                            : '' ?>"
                    >
                        Create Session
                    </a>
                </section>

                <?php if ($sessions === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No attendance sessions</h3>

                            <p>
                                Create your first attendance session for
                                a course.
                            </p>

                            <a
                                class="button"
                                href="<?= APP_URL ?>/instructor/create-attendance.php<?= $selectedCourseId
                                    ? '?course_id=' . $selectedCourseId
                                    : '' ?>"
                            >
                                Create First Session
                            </a>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="attendance-grid">
                        <?php foreach ($sessions as $session): ?>
                            <article class="attendance-card">
                                <div class="attendance-card-header">
                                    <div>
                                        <span class="course-code">
                                            <?= escape(
                                                $session['course_code']
                                            ) ?>
                                        </span>

                                        <h3>
                                            <?= escape(
                                                $session['title']
                                                ?: 'Class Attendance'
                                            ) ?>
                                        </h3>
                                    </div>

                                    <span class="badge badge-info">
                                        <?= escape(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $session[
                                                        'session_date'
                                                    ]
                                                )
                                            )
                                        ) ?>
                                    </span>
                                </div>

                                <p class="attendance-notes">
                                    <?= escape(
                                        $session['notes']
                                        ?: 'No notes added.'
                                    ) ?>
                                </p>

                                <div class="attendance-summary-grid">
                                    <div>
                                        <span>Marked</span>
                                        <strong>
                                            <?= (int) $session[
                                                'marked_students'
                                            ] ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <span>Present</span>
                                        <strong>
                                            <?= (int) $session[
                                                'present_count'
                                            ] ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <span>Absent</span>
                                        <strong>
                                            <?= (int) $session[
                                                'absent_count'
                                            ] ?>
                                        </strong>
                                    </div>

                                    <div>
                                        <span>Late</span>
                                        <strong>
                                            <?= (int) $session[
                                                'late_count'
                                            ] ?>
                                        </strong>
                                    </div>
                                </div>

                                <div class="attendance-actions">
                                    <a
                                        class="button button-small"
                                        href="<?= APP_URL ?>/instructor/mark-attendance.php?session_id=<?= (int) $session['id'] ?>"
                                    >
                                        Mark Attendance
                                    </a>

                                    <form
                                        method="POST"
                                        action="<?= APP_URL ?>/instructor/delete-attendance.php"
                                        onsubmit="return confirm(
                                            'Delete this attendance session permanently?'
                                        );"
                                    >
                                        <input
                                            type="hidden"
                                            name="session_id"
                                            value="<?= (int) $session['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="course_id"
                                            value="<?= (int) $session['course_id'] ?>"
                                        >

                                        <button
                                            class="button button-small button-danger"
                                            type="submit"
                                        >
                                            Delete
                                        </button>
                                    </form>
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