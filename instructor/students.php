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

$pageTitle = 'Students';
$pageSubtitle = 'View enrolled students and academic participation.';

$selectedCourse = null;

if ($selectedCourseId) {
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
        'course_id' => $selectedCourseId,
        'instructor_id' => $user['id'],
    ]);

    $selectedCourse = $courseQuery->fetch();

    if (!$selectedCourse) {
        setFlash('error', 'Course not found or access denied.');
        redirect('instructor/students.php');
    }

    $pageSubtitle =
        $selectedCourse['course_code']
        . ' — '
        . $selectedCourse['course_name'];
}

$sql = '
    SELECT
        users.id,
        users.full_name,
        users.email,
        users.membership_type,
        student_profiles.student_number,
        courses.id AS course_id,
        courses.course_code,
        courses.course_name,

        COUNT(
            DISTINCT attendance_records.id
        ) AS attendance_records,

        SUM(
            attendance_records.status = "present"
        ) AS present_count,

        SUM(
            attendance_records.status = "late"
        ) AS late_count,

        SUM(
            attendance_records.status = "absent"
        ) AS absent_count,

        COUNT(
            DISTINCT submissions.id
        ) AS submission_count,

        AVG(
            CASE
                WHEN submissions.result_published = 1
                THEN (
                    submissions.grade
                    / NULLIF(assignments.maximum_marks, 0)
                ) * 100
                ELSE NULL
            END
        ) AS average_grade

    FROM enrollments

    INNER JOIN users
        ON users.id = enrollments.student_id

    LEFT JOIN student_profiles
        ON student_profiles.user_id = users.id

    INNER JOIN courses
        ON courses.id = enrollments.course_id

    LEFT JOIN attendance_sessions
        ON attendance_sessions.course_id = courses.id

    LEFT JOIN attendance_records
        ON attendance_records.attendance_session_id =
            attendance_sessions.id
       AND attendance_records.student_id = users.id

    LEFT JOIN assignments
        ON assignments.course_id = courses.id

    LEFT JOIN submissions
        ON submissions.assignment_id = assignments.id
       AND submissions.student_id = users.id

    WHERE courses.instructor_id = :instructor_id
';

$params = [
    'instructor_id' => $user['id'],
];

if ($selectedCourseId) {
    $sql .= '
        AND courses.id = :course_id
    ';

    $params['course_id'] = $selectedCourseId;
}

$sql .= '
    GROUP BY
        users.id,
        users.full_name,
        users.email,
        users.membership_type,
        student_profiles.student_number,
        courses.id,
        courses.course_code,
        courses.course_name

    ORDER BY
        courses.course_code ASC,
        users.full_name ASC
';

$query = $pdo->prepare($sql);
$query->execute($params);

$students = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Students | <?= escape(APP_NAME) ?></title>

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
                                    . ' Students'
                                )
                                : 'Enrolled Students' ?>
                        </h2>

                        <p>
                            Review student contact information,
                            attendance, submissions and results.
                        </p>
                    </div>
                </section>

                <?php if ($students === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No enrolled students</h3>

                            <p>
                                Students will appear here after joining
                                one of your published courses.
                            </p>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="dashboard-panel students-panel">
                        <div class="table-responsive">
                            <table class="data-table students-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Membership</th>
                                        <th>Attendance</th>
                                        <th>Submissions</th>
                                        <th>Average Grade</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <?php
                                        $present =
                                            (int) $student['present_count'];

                                        $late =
                                            (int) $student['late_count'];

                                        $absent =
                                            (int) $student['absent_count'];

                                        $countedAttendance =
                                            $present + $late + $absent;

                                        $attendancePercentage =
                                            $countedAttendance > 0
                                                ? (
                                                    ($present + $late)
                                                    / $countedAttendance
                                                ) * 100
                                                : null;

                                        $averageGrade =
                                            $student['average_grade'] !== null
                                                ? (float) $student[
                                                    'average_grade'
                                                ]
                                                : null;
                                        ?>

                                        <tr>
                                            <td>
                                                <div class="student-profile-cell">
                                                    <div class="student-avatar">
                                                        <?= escape(
                                                            strtoupper(
                                                                substr(
                                                                    $student[
                                                                        'full_name'
                                                                    ],
                                                                    0,
                                                                    1
                                                                )
                                                            )
                                                        ) ?>
                                                    </div>

                                                    <div>
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
                                                                    'student_number'
                                                                ]
                                                                ?: $student[
                                                                    'email'
                                                                ]
                                                            ) ?>
                                                        </small>

                                                        <?php if (
                                                            $student[
                                                                'student_number'
                                                            ]
                                                        ): ?>
                                                            <small>
                                                                <?= escape(
                                                                    $student[
                                                                        'email'
                                                                    ]
                                                                ) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <span class="course-code">
                                                    <?= escape(
                                                        $student[
                                                            'course_code'
                                                        ]
                                                    ) ?>
                                                </span>

                                                <small class="student-course-name">
                                                    <?= escape(
                                                        $student[
                                                            'course_name'
                                                        ]
                                                    ) ?>
                                                </small>
                                            </td>

                                            <td>
                                                <span
                                                    class="badge <?= $student[
                                                        'membership_type'
                                                    ] === 'member'
                                                        ? 'badge-success'
                                                        : 'badge-warning' ?>"
                                                >
                                                    <?= escape(
                                                        $student[
                                                            'membership_type'
                                                        ] === 'member'
                                                            ? 'Member'
                                                            : 'Non-member'
                                                    ) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php if (
                                                    $attendancePercentage !== null
                                                ): ?>
                                                    <strong>
                                                        <?= number_format(
                                                            $attendancePercentage,
                                                            1
                                                        ) ?>%
                                                    </strong>

                                                    <small class="table-secondary-text">
                                                        <?= $present ?> present,
                                                        <?= $late ?> late,
                                                        <?= $absent ?> absent
                                                    </small>
                                                <?php else: ?>
                                                    <span class="muted-value">
                                                        No records
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <strong>
                                                    <?= (int) $student[
                                                        'submission_count'
                                                    ] ?>
                                                </strong>
                                            </td>

                                            <td>
                                                <?php if (
                                                    $averageGrade !== null
                                                ): ?>
                                                    <strong
                                                        class="<?= $averageGrade >= 50
                                                            ? 'grade-pass'
                                                            : 'grade-fail' ?>"
                                                    >
                                                        <?= number_format(
                                                            $averageGrade,
                                                            1
                                                        ) ?>%
                                                    </strong>
                                                <?php else: ?>
                                                    <span class="muted-value">
                                                        No published grades
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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