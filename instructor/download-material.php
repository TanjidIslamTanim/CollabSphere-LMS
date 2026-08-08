<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireRole('instructor');

$user = currentUser();

$materialId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$materialId) {
    http_response_code(400);
    exit('Invalid material selected.');
}

$query = $pdo->prepare(
    'SELECT
        course_materials.file_name,
        course_materials.file_path,
        course_materials.mime_type
     FROM course_materials
     INNER JOIN courses
        ON courses.id = course_materials.course_id
     WHERE course_materials.id = :material_id
       AND courses.instructor_id = :instructor_id
     LIMIT 1'
);

$query->execute([
    'material_id' => $materialId,
    'instructor_id' => $user['id'],
]);

$material = $query->fetch();

if (!$material) {
    http_response_code(404);
    exit('Material not found or access denied.');
}

$filePath =
    __DIR__
    . '/../'
    . $material['file_path'];

if (!is_file($filePath)) {
    http_response_code(404);
    exit('The material file could not be found.');
}

header(
    'Content-Type: '
    . ($material['mime_type']
        ?: 'application/octet-stream')
);

header(
    'Content-Disposition: attachment; filename="'
    . basename($material['file_name'])
    . '"'
);

header('Content-Length: ' . filesize($filePath));
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;