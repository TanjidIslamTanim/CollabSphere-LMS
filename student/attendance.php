<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('student');

$user = currentUser();

$pageTitle = 'Attendance';
$pageSubtitle = 'View your attendance records and percentages.';

$summaryQuery = $pdo->prepare(
    'SELECT
        courses.id,
        courses.course_code,
        courses.course_name,
        COUNT(attendance_records.id) AS total_records,
        SUM(attendance_records.status = "present") AS present_count,
        SUM(attendance_records.status = "absent") AS absent_count,
        SUM(attendance_records.status = "late") AS late_count,
        SUM(attendance_records.status = "excused") AS excused_count
     FROM enrollments
     INNER JOIN courses
        ON courses.id = enrollments.course_id
     LEFT JOIN attendance_sessions
        ON attendance_sessions.course_id = courses.id
     LEFT JOIN attendance_records
        ON attendance_records.attendance_session_id =
            attendance_sessions.id
       AND attendance_records.student_id = :student_id_record
     WHERE enrollments.student_id = :student_id
     GROUP BY
        courses.id,
        courses.course_code,
        courses.course_name
     ORDER BY courses.course_name'
);

$summaryQuery->execute([
    'student_id_record' => $user['id'],
    'student_id' => $user['id'],
]);

$courseSummaries = $summaryQuery->fetchAll();

$recordsQuery = $pdo->prepare(
    'SELECT
        attendance_sessions.session_date,
        attendance_sessions.title,
        attendance_records.status,
        attendance_records.remarks,
        courses.course_code,
        courses.course_name
     FROM attendance_records
     INNER JOIN attendance_sessions
        ON attendance_sessions.id =
            attendance_records.attendance_session_id
     INNER JOIN courses
        ON courses.id = attendance_sessions.course_id
     WHERE attendance_records.student_id = :student_id
     ORDER BY attendance_sessions.session_date DESC'
);

$recordsQuery->execute([
    'student_id' => $user['id'],
]);

$records = $recordsQuery->fetchAll();
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
                <section class="page-heading-row">
                    <div>
                        <h2>My Attendance</h2>

                        <p>
                            Review your attendance status for each
                            enrolled course.
                        </p>
                    </div>
                </section>

                <?php if ($courseSummaries === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No attendance information</h3>

                            <p>
                                Attendance records will appear after your
                                instructor marks a class.
                            </p>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="attendance-summary-cards">
                        <?php foreach ($courseSummaries as $summary): ?>
                            <?php
                            $present = (int) $summary['present_count'];
                            $late = (int) $summary['late_count'];
                            $absent = (int) $summary['absent_count'];
                            $excused = (int) $summary['excused_count'];

                            $countedSessions =
                                $present + $late + $absent;

                            $percentage =
                                $countedSessions > 0
                                    ? (
                                        ($present + $late)
                                        / $countedSessions
                                    ) * 100
                                    : 0;
                            ?>

                            <article class="attendance-summary-card">
                                <span class="course-code">
                                    <?= escape(
                                        $summary['course_code']
                                    ) ?>
                                </span>

                                <h3>
                                    <?= escape(
                                        $summary['course_name']
                                    ) ?>
                                </h3>

                                <div class="attendance-percentage">
                                    <?= number_format(
                                        $percentage,
                                        1
                                    ) ?>%
                                </div>

                                <div class="attendance-summary-grid">
                                    <div>
                                        <span>Present</span>
                                        <strong><?= $present ?></strong>
                                    </div>

                                    <div>
                                        <span>Late</span>
                                        <strong><?= $late ?></strong>
                                    </div>

                                    <div>
                                        <span>Absent</span>
                                        <strong><?= $absent ?></strong>
                                    </div>

                                    <div>
                                        <span>Excused</span>
                                        <strong><?= $excused ?></strong>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>

                    <section class="dashboard-panel">
                        <header class="dashboard-panel-header">
                            <h3>Attendance History</h3>
                        </header>

                        <?php if ($records === []): ?>
                            <div class="professional-empty-state">
                                No attendance records have been marked.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table attendance-history-table">
                                    <thead>
                                        <tr>
                                            <th>Course</th>
                                            <th>Session</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <?php
                                            $statusClass = match (
                                                $record['status']
                                            ) {
                                                'present' => 'badge-success',
                                                'absent' => 'badge-danger',
                                                'late' => 'badge-warning',
                                                default => 'badge-info',
                                            };
                                            ?>

                                            <tr>
                                                <td>
                                                    <strong>
                                                        <?= escape(
                                                            $record[
                                                                'course_code'
                                                            ]
                                                        ) ?>
                                                    </strong>
                                                </td>

                                                <td>
                                                    <?= escape(
                                                        $record['title']
                                                        ?: 'Class Attendance'
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <?= escape(
                                                        date(
                                                            'd M Y',
                                                            strtotime(
                                                                $record[
                                                                    'session_date'
                                                                ]
                                                            )
                                                        )
                                                    ) ?>
                                                </td>

                                                <td>
                                                    <span
                                                        class="badge <?= $statusClass ?>"
                                                    >
                                                        <?= escape(
                                                            ucfirst(
                                                                $record[
                                                                    'status'
                                                                ]
                                                            )
                                                        ) ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <?= escape(
                                                        $record['remarks']
                                                        ?: '—'
                                                    ) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
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