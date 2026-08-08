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

$materialId = filter_input(
    INPUT_POST,
    'material_id',
    FILTER_VALIDATE_INT
);

$courseId = filter_input(
    INPUT_POST,
    'course_id',
    FILTER_VALIDATE_INT
);

if (!$materialId || !$courseId) {
    setFlash('error', 'Invalid material selected.');
    redirect('instructor/courses.php');
}

$query = $pdo->prepare(
    'SELECT
        course_materials.file_path
     FROM course_materials
     INNER JOIN courses
        ON courses.id = course_materials.course_id
     WHERE course_materials.id = :material_id
       AND course_materials.course_id = :course_id
       AND courses.instructor_id = :instructor_id
     LIMIT 1'
);

$query->execute([
    'material_id' => $materialId,
    'course_id' => $courseId,
    'instructor_id' => $user['id'],
]);

$material = $query->fetch();

if (!$material) {
    setFlash(
        'error',
        'Material not found or access denied.'
    );

    redirect(
        'instructor/materials.php?course_id='
        . $courseId
    );
}

$deleteQuery = $pdo->prepare(
    'DELETE FROM course_materials
     WHERE id = :material_id'
);

$deleteQuery->execute([
    'material_id' => $materialId,
]);

$filePath =
    __DIR__
    . '/../'
    . $material['file_path'];

if (is_file($filePath)) {
    unlink($filePath);
}

setFlash(
    'success',
    'Course material deleted successfully.'
);

redirect(
    'instructor/materials.php?course_id='
    . $courseId
);