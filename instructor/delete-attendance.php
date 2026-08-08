<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$user = currentUser();

$sessionId = filter_input(
    INPUT_POST,
    'session_id',
    FILTER_VALIDATE_INT
);

$courseId = filter_input(
    INPUT_POST,
    'course_id',
    FILTER_VALIDATE_INT
);

if (!$sessionId) {
    setFlash('error', 'Invalid attendance session.');
    redirect('instructor/attendance.php');
}

$deleteQuery = $pdo->prepare(
    'DELETE attendance_sessions
     FROM attendance_sessions
     INNER JOIN courses
        ON courses.id = attendance_sessions.course_id
     WHERE attendance_sessions.id = :session_id
       AND courses.instructor_id = :instructor_id'
);

$deleteQuery->execute([
    'session_id' => $sessionId,
    'instructor_id' => $user['id'],
]);

setFlash(
    $deleteQuery->rowCount() > 0 ? 'success' : 'error',
    $deleteQuery->rowCount() > 0
        ? 'Attendance session deleted successfully.'
        : 'Attendance session not found or access denied.'
);

redirect(
    'instructor/attendance.php'
    . ($courseId ? '?course_id=' . $courseId : '')
);