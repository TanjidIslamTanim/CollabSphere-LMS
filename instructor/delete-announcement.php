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

$announcementId = filter_input(
    INPUT_POST,
    'announcement_id',
    FILTER_VALIDATE_INT
);

$courseId = filter_input(
    INPUT_POST,
    'course_id',
    FILTER_VALIDATE_INT
);

if (!$announcementId) {
    setFlash('error', 'Invalid announcement selected.');
    redirect('instructor/announcements.php');
}

$deleteQuery = $pdo->prepare(
    'DELETE announcements
     FROM announcements
     INNER JOIN courses
        ON courses.id = announcements.course_id
     WHERE announcements.id = :announcement_id
       AND courses.instructor_id = :instructor_id'
);

$deleteQuery->execute([
    'announcement_id' => $announcementId,
    'instructor_id' => $user['id'],
]);

setFlash(
    $deleteQuery->rowCount() > 0 ? 'success' : 'error',
    $deleteQuery->rowCount() > 0
        ? 'Announcement deleted successfully.'
        : 'Announcement not found or access denied.'
);

redirect(
    'instructor/announcements.php'
    . ($courseId ? '?course_id=' . $courseId : '')
);