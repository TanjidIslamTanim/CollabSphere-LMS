<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';
require_once __DIR__ . '/../app/Helpers/notification.php';

requireRole('instructor');

$user = currentUser();

$sessionId = filter_input(
    INPUT_GET,
    'session_id',
    FILTER_VALIDATE_INT
);

if (!$sessionId) {
    setFlash('error', 'Invalid attendance session.');
    redirect('instructor/attendance.php');
}

$sessionQuery = $pdo->prepare(
    'SELECT
        attendance_sessions.*,
        courses.course_code,
        courses.course_name
     FROM attendance_sessions
     INNER JOIN courses
        ON courses.id = attendance_sessions.course_id
     WHERE attendance_sessions.id = :session_id
       AND courses.instructor_id = :instructor_id
     LIMIT 1'
);

$sessionQuery->execute([
    'session_id' => $sessionId,
    'instructor_id' => $user['id'],
]);

$session = $sessionQuery->fetch();

if (!$session) {
    http_response_code(404);
    exit('Attendance session not found or access denied.');
}

$pageTitle = 'Mark Attendance';
$pageSubtitle =
    $session['course_code']
    . ' — '
    . date(
        'd M Y',
        strtotime($session['session_date'])
    );

$studentsQuery = $pdo->prepare(
    'SELECT
        users.id,
        users.full_name,
        users.email,
        student_profiles.student_number,
        attendance_records.status,
        attendance_records.remarks
     FROM enrollments
     INNER JOIN users
        ON users.id = enrollments.student_id
     LEFT JOIN student_profiles
        ON student_profiles.user_id = users.id
     LEFT JOIN attendance_records
        ON attendance_records.student_id = users.id
       AND attendance_records.attendance_session_id =
            :session_id
     WHERE enrollments.course_id = :course_id
     ORDER BY users.full_name ASC'
);

$studentsQuery->execute([
    'session_id' => $sessionId,
    'course_id' => $session['course_id'],
]);

$students = $studentsQuery->fetchAll();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $allowedStatuses = [
        'present',
        'absent',
        'late',
        'excused',
    ];

    try {
        $pdo->beginTransaction();

        $saveQuery = $pdo->prepare(
            'INSERT INTO attendance_records
                (
                    attendance_session_id,
                    student_id,
                    status,
                    remarks
                )
             VALUES
                (
                    :attendance_session_id,
                    :student_id,
                    :status,
                    :remarks
                )
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                remarks = VALUES(remarks),
                updated_at = CURRENT_TIMESTAMP'
        );

        foreach ($students as $student) {
            $studentId = (int) $student['id'];

            $status = $_POST['status'][$studentId]
                ?? 'present';

            $remarks = trim(
                $_POST['remarks'][$studentId]
                ?? ''
            );

            if (!in_array($status, $allowedStatuses, true)) {
                $status = 'present';
            }

            $saveQuery->execute([
                'attendance_session_id' => $sessionId,
                'student_id' => $studentId,
                'status' => $status,
                'remarks' =>
                    $remarks !== '' ? $remarks : null,
            ]);

            createNotification(
                $pdo,
                $studentId,
                'Attendance Updated',
                'Your attendance for '
                . $session['course_name']
                . ' on '
                . date(
                    'd M Y',
                    strtotime($session['session_date'])
                )
                . ' was marked as '
                . ucfirst($status)
                . '.',
                'attendance',
                $sessionId
            );
        }

        $pdo->commit();

        setFlash(
            'success',
            'Attendance saved successfully.'
        );

        redirect(
            'instructor/attendance.php?course_id='
            . (int) $session['course_id']
        );
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error =
            'Attendance could not be saved. Please try again.';
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

    <title>Mark Attendance | <?= escape(APP_NAME) ?></title>

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
                    href="<?= APP_URL ?>/instructor/attendance.php?course_id=<?= (int) $session['course_id'] ?>"
                >
                    ← Back to Attendance
                </a>

                <section class="page-heading-row">
                    <div>
                        <h2>
                            <?= escape(
                                $session['title']
                                ?: 'Class Attendance'
                            ) ?>
                        </h2>

                        <p>
                            <?= escape($session['course_name']) ?>
                            —
                            <?= escape(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $session['session_date']
                                    )
                                )
                            ) ?>
                        </p>
                    </div>
                </section>

                <?php if ($error !== null): ?>
                    <div class="alert alert-error">
                        <?= escape($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($students === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No enrolled students</h3>

                            <p>
                                Students must enrol before attendance can
                                be marked.
                            </p>
                        </div>
                    </section>
                <?php else: ?>
                    <form method="POST">
                        <section class="dashboard-panel attendance-panel">
                            <div class="table-responsive">
                                <table class="data-table attendance-marking-table">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Student ID</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($students as $student): ?>
                                            <?php
                                            $currentStatus =
                                                $student['status']
                                                ?: 'present';
                                            ?>

                                            <tr>
                                                <td>
                                                    <div class="attendance-student">
                                                        <strong>
                                                            <?= escape(
                                                                $student[
                                                                    'full_name'
                                                                ]
                                                            ) ?>
                                                        </strong>

                                                        <small>
                                                            <?= escape(
                                                                $student[
                                                                    'email'
                                                                ]
                                                            ) ?>
                                                        </small>
                                                    </div>
                                                </td>

                                                <td>
                                                    <span class="student-number">
                                                        <?= escape(
                                                            $student[
                                                                'student_number'
                                                            ]
                                                            ?: 'Not assigned'
                                                        ) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <select
                                                        class="attendance-status-select"
                                                        name="status[<?= (int) $student['id'] ?>]"
                                                    >
                                                        <option
                                                            value="present"
                                                            <?= $currentStatus
                                                                === 'present'
                                                                ? 'selected'
                                                                : '' ?>
                                                        >
                                                            Present
                                                        </option>

                                                        <option
                                                            value="absent"
                                                            <?= $currentStatus
                                                                === 'absent'
                                                                ? 'selected'
                                                                : '' ?>
                                                        >
                                                            Absent
                                                        </option>

                                                        <option
                                                            value="late"
                                                            <?= $currentStatus
                                                                === 'late'
                                                                ? 'selected'
                                                                : '' ?>
                                                        >
                                                            Late
                                                        </option>

                                                        <option
                                                            value="excused"
                                                            <?= $currentStatus
                                                                === 'excused'
                                                                ? 'selected'
                                                                : '' ?>
                                                        >
                                                            Excused
                                                        </option>
                                                    </select>
                                                </td>

                                                <td>
                                                    <input
                                                        class="attendance-remarks-input"
                                                        name="remarks[<?= (int) $student['id'] ?>]"
                                                        type="text"
                                                        value="<?= escape(
                                                            $student[
                                                                'remarks'
                                                            ]
                                                            ?? ''
                                                        ) ?>"
                                                        placeholder="Optional remarks"
                                                        maxlength="255"
                                                    >
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="attendance-save-bar">
                                <a
                                    class="button button-secondary"
                                    href="<?= APP_URL ?>/instructor/attendance.php?course_id=<?= (int) $session['course_id'] ?>"
                                >
                                    Cancel
                                </a>

                                <button
                                    class="attendance-save-button"
                                    type="submit"
                                >
                                    Save Attendance
                                </button>
                            </div>
                        </section>
                    </form>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script
        src="<?= APP_URL ?>/public/assets/js/app.js"
    ></script>
</body>
</html>