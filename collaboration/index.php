<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/auth.php';

requireLogin();

$user = currentUser();

$pageTitle = 'Collaboration';
$pageSubtitle = 'Discuss course topics with instructors and students.';

$flash = getFlash();
$errors = [];

$selectedCourseId = filter_input(
    INPUT_GET,
    'course_id',
    FILTER_VALIDATE_INT
);

/*
|--------------------------------------------------------------------------
| Load courses available to the logged-in user
|--------------------------------------------------------------------------
*/

if ($user['role'] === 'instructor') {
    $courseQuery = $pdo->prepare(
        'SELECT
            id,
            course_code,
            course_name
         FROM courses
         WHERE instructor_id = :user_id
         ORDER BY course_name ASC'
    );
} else {
    $courseQuery = $pdo->prepare(
        'SELECT
            courses.id,
            courses.course_code,
            courses.course_name
         FROM courses
         INNER JOIN enrollments
            ON enrollments.course_id = courses.id
         WHERE enrollments.student_id = :user_id
         ORDER BY courses.course_name ASC'
    );
}

$courseQuery->execute([
    'user_id' => $user['id'],
]);

$courses = $courseQuery->fetchAll();

/*
|--------------------------------------------------------------------------
| Automatically select the first available course
|--------------------------------------------------------------------------
*/

if (!$selectedCourseId && $courses !== []) {
    $selectedCourseId = (int) $courses[0]['id'];
}

/*
|--------------------------------------------------------------------------
| Confirm that the user can access the selected course
|--------------------------------------------------------------------------
*/

$selectedCourse = null;

foreach ($courses as $course) {
    if ((int) $course['id'] === (int) $selectedCourseId) {
        $selectedCourse = $course;
        break;
    }
}

if ($selectedCourseId && !$selectedCourse) {
    setFlash(
        'error',
        'Course not found or access denied.'
    );

    redirect('collaboration/index.php');
}

/*
|--------------------------------------------------------------------------
| Create or delete a discussion post
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $postId = filter_input(
            INPUT_POST,
            'post_id',
            FILTER_VALIDATE_INT
        );

        if (!$postId) {
            $errors[] = 'Invalid discussion post.';
        } else {
            $deleteQuery = $pdo->prepare(
                'DELETE FROM collaboration_posts
                 WHERE id = :post_id
                   AND user_id = :user_id'
            );

            $deleteQuery->execute([
                'post_id' => $postId,
                'user_id' => $user['id'],
            ]);

            if ($deleteQuery->rowCount() > 0) {
                setFlash(
                    'success',
                    'Discussion post deleted successfully.'
                );
            } else {
                setFlash(
                    'error',
                    'You can only delete your own discussion posts.'
                );
            }

            redirect(
                'collaboration/index.php?course_id='
                . (int) $selectedCourseId
            );
        }
    } else {
        $postedCourseId = filter_input(
            INPUT_POST,
            'course_id',
            FILTER_VALIDATE_INT
        );

        $message = trim($_POST['message'] ?? '');

        $allowedCourse = false;

        foreach ($courses as $course) {
            if ((int) $course['id'] === (int) $postedCourseId) {
                $allowedCourse = true;
                break;
            }
        }

        if (!$postedCourseId || !$allowedCourse) {
            $errors[] = 'Please select a valid course.';
        }

        if (strlen($message) < 2) {
            $errors[] =
                'Discussion message must contain at least 2 characters.';
        }

        if (strlen($message) > 3000) {
            $errors[] =
                'Discussion message must not exceed 3000 characters.';
        }

        if ($errors === []) {
            $insertQuery = $pdo->prepare(
                'INSERT INTO collaboration_posts
                    (
                        course_id,
                        user_id,
                        message
                    )
                 VALUES
                    (
                        :course_id,
                        :user_id,
                        :message
                    )'
            );

            $insertQuery->execute([
                'course_id' => $postedCourseId,
                'user_id' => $user['id'],
                'message' => $message,
            ]);

            setFlash(
                'success',
                'Discussion post published successfully.'
            );

            redirect(
                'collaboration/index.php?course_id='
                . $postedCourseId
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load course discussion posts
|--------------------------------------------------------------------------
*/

$posts = [];

if ($selectedCourse) {
    $postsQuery = $pdo->prepare(
        'SELECT
            collaboration_posts.id,
            collaboration_posts.user_id,
            collaboration_posts.message,
            collaboration_posts.created_at,
            users.full_name,
            users.role
         FROM collaboration_posts
         INNER JOIN users
            ON users.id = collaboration_posts.user_id
         WHERE collaboration_posts.course_id = :course_id
         ORDER BY collaboration_posts.created_at DESC'
    );

    $postsQuery->execute([
        'course_id' => $selectedCourseId,
    ]);

    $posts = $postsQuery->fetchAll();
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

    <title>
        Collaboration | <?= escape(APP_NAME) ?>
    </title>

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

                <section class="page-heading-row">
                    <div>
                        <h2>Course Collaboration</h2>

                        <p>
                            Share questions, ideas and academic
                            discussions with your course community.
                        </p>
                    </div>
                </section>

                <?php if ($courses === []): ?>
                    <section class="dashboard-panel">
                        <div class="professional-empty-state">
                            <h3>No courses available</h3>

                            <p>
                                You need an available course before using
                                the collaboration workspace.
                            </p>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="collaboration-layout">
                        <aside class="collaboration-course-panel">
                            <h3>Your Courses</h3>

                            <div class="collaboration-course-list">
                                <?php foreach ($courses as $course): ?>
                                    <a
                                        class="collaboration-course-link <?= (int) $course['id']
                                            === (int) $selectedCourseId
                                                ? 'active'
                                                : '' ?>"
                                        href="<?= APP_URL ?>/collaboration/index.php?course_id=<?= (int) $course['id'] ?>"
                                    >
                                        <span>
                                            <?= escape(
                                                $course['course_code']
                                            ) ?>
                                        </span>

                                        <strong>
                                            <?= escape(
                                                $course['course_name']
                                            ) ?>
                                        </strong>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </aside>

                        <div class="collaboration-main">
                            <section class="dashboard-panel">
                                <header class="dashboard-panel-header">
                                    <div>
                                        <span class="course-code">
                                            <?= escape(
                                                $selectedCourse[
                                                    'course_code'
                                                ]
                                            ) ?>
                                        </span>

                                        <h3>
                                            <?= escape(
                                                $selectedCourse[
                                                    'course_name'
                                                ]
                                            ) ?>
                                        </h3>

                                        <p class="panel-subtitle">
                                            Start a new discussion or
                                            respond to your course community.
                                        </p>
                                    </div>
                                </header>

                                <div class="dashboard-panel-body">
                                    <?php if ($errors !== []): ?>
                                        <div class="alert alert-error">
                                            <ul class="error-list">
                                                <?php foreach ($errors as $error): ?>
                                                    <li>
                                                        <?= escape($error) ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST">
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="create"
                                        >

                                        <input
                                            type="hidden"
                                            name="course_id"
                                            value="<?= (int) $selectedCourseId ?>"
                                        >

                                        <div class="form-group">
                                            <label for="message">
                                                New Discussion Post
                                            </label>

                                            <textarea
                                                id="message"
                                                name="message"
                                                rows="5"
                                                maxlength="3000"
                                                placeholder="Ask a question or share an idea..."
                                                required
                                            ></textarea>
                                        </div>

                                        <div class="collaboration-post-action">
                                            <button type="submit">
                                                Publish Post
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </section>

                            <section class="collaboration-feed">
                                <?php if ($posts === []): ?>
                                    <article class="dashboard-panel">
                                        <div class="professional-empty-state">
                                            <h3>No discussions yet</h3>

                                            <p>
                                                Be the first person to
                                                start a course discussion.
                                            </p>
                                        </div>
                                    </article>
                                <?php else: ?>
                                    <?php foreach ($posts as $post): ?>
                                        <?php
                                        $initial = strtoupper(
                                            substr(
                                                $post['full_name'],
                                                0,
                                                1
                                            )
                                        );

                                        $isOwner =
                                            (int) $post['user_id']
                                            === (int) $user['id'];
                                        ?>

                                        <article class="collaboration-post">
                                            <div class="collaboration-avatar">
                                                <?= escape($initial) ?>
                                            </div>

                                            <div class="collaboration-post-content">
                                                <header class="collaboration-post-header">
                                                    <div>
                                                        <strong>
                                                            <?= escape(
                                                                $post[
                                                                    'full_name'
                                                                ]
                                                            ) ?>
                                                        </strong>

                                                        <span
                                                            class="badge <?= $post['role']
                                                                === 'instructor'
                                                                    ? 'badge-info'
                                                                    : 'badge-success' ?>"
                                                        >
                                                            <?= escape(
                                                                ucfirst(
                                                                    $post[
                                                                        'role'
                                                                    ]
                                                                )
                                                            ) ?>
                                                        </span>
                                                    </div>

                                                    <time>
                                                        <?= escape(
                                                            date(
                                                                'd M Y, g:i A',
                                                                strtotime(
                                                                    $post[
                                                                        'created_at'
                                                                    ]
                                                                )
                                                            )
                                                        ) ?>
                                                    </time>
                                                </header>

                                                <p>
                                                    <?= nl2br(
                                                        escape(
                                                            $post[
                                                                'message'
                                                            ]
                                                        )
                                                    ) ?>
                                                </p>

                                                <?php if ($isOwner): ?>
                                                    <form
                                                        method="POST"
                                                        class="collaboration-delete-form"
                                                        onsubmit="return confirm(
                                                            'Delete this discussion post?'
                                                        );"
                                                    >
                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="delete"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="course_id"
                                                            value="<?= (int) $selectedCourseId ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="post_id"
                                                            value="<?= (int) $post['id'] ?>"
                                                        >

                                                        <button
                                                            class="collaboration-delete-button"
                                                            type="submit"
                                                        >
                                                            Delete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </section>
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