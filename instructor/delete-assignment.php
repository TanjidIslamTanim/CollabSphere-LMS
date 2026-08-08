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

$assignmentId = filter_input(
    INPUT_POST,
    'assignment_id',
    FILTER_VALIDATE_INT
);

$courseId = filter_input(
    INPUT_POST,
    'course_id',
    FILTER_VALIDATE_INT
);

if (!$assignmentId) {
    setFlash('error', 'Invalid assignment selected.');
    redirect('instructor/assignments.php');
}

$fileQuery = $pdo->prepare(
    'SELECT submissions.file_path
     FROM submissions
     INNER JOIN assignments
        ON assignments.id = submissions.assignment_id
     INNER JOIN courses
        ON courses.id = assignments.course_id
     WHERE assignments.id = :assignment_id
       AND courses.instructor_id = :instructor_id'
);

$fileQuery->execute([
    'assignment_id' => $assignmentId,
    'instructor_id' => $user['id'],
]);

$submissionFiles = $fileQuery->fetchAll();

$deleteQuery = $pdo->prepare(
    'DELETE assignments
     FROM assignments
     INNER JOIN courses
        ON courses.id = assignments.course_id
     WHERE assignments.id = :assignment_id
       AND courses.instructor_id = :instructor_id'
);

$deleteQuery->execute([
    'assignment_id' => $assignmentId,
    'instructor_id' => $user['id'],
]);

if ($deleteQuery->rowCount() > 0) {
    foreach ($submissionFiles as $submissionFile) {
        $filePath =
            __DIR__
            . '/../'
            . $submissionFile['file_path'];

        if (is_file($filePath)) {
            unlink($filePath);
        }
    }

    setFlash(
        'success',
        'Assignment deleted successfully.'
    );
} else {
    setFlash(
        'error',
        'Assignment not found or access denied.'
    );
}

redirect(
    'instructor/assignments.php'
    . ($courseId ? '?course_id=' . $courseId : '')
);