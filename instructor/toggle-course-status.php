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

$courseId = filter_input(
    INPUT_POST,
    'course_id',
    FILTER_VALIDATE_INT
);

if (!$courseId) {
    setFlash('error', 'Invalid course selected.');
    redirect('instructor/courses.php');
}

$query = $pdo->prepare(
    'UPDATE courses
     SET is_published =
        CASE
            WHEN is_published = 1 THEN 0
            ELSE 1
        END
     WHERE id = :course_id
       AND instructor_id = :instructor_id'
);

$query->execute([
    'course_id' => $courseId,
    'instructor_id' => $user['id'],
]);

if ($query->rowCount() === 0) {
    setFlash(
        'error',
        'Course not found or you do not have permission.'
    );
} else {
    setFlash(
        'success',
        'Course publication status was updated.'
    );
}

redirect('instructor/courses.php');