<?php



declare(strict_types=1);



require_once __DIR__ . '/../config/app.php';

require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../app/Helpers/auth.php';



requireRole('instructor');



$user = currentUser();



$pageTitle = 'Courses';

$pageSubtitle = 'Create and manage your teaching courses.';



$flash = getFlash();



$query = $pdo->prepare(

    'SELECT

        courses.id,

        courses.course_code,

        courses.course_name,

        courses.description,

        courses.enrolment_key,

        courses.is_published,

        courses.created_at,

        COUNT(enrollments.id) AS enrolled_students

     FROM courses

     LEFT JOIN enrollments

        ON enrollments.course_id = courses.id

     WHERE courses.instructor_id = :instructor_id

     GROUP BY

        courses.id,

        courses.course_code,

        courses.course_name,

        courses.description,

        courses.enrolment_key,

        courses.is_published,

        courses.created_at

     ORDER BY courses.created_at DESC'

);



$query->execute([

    'instructor_id' => $user['id'],

]);



$courses = $query->fetchAll();

?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">



    <meta

        name="viewport"

        content="width=device-width, initial-scale=1.0"

    >



    <title>Courses | <?= escape(APP_NAME) ?></title>



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

                        <h2>My Courses</h2>



                        <p>

                            Manage course details, enrolments and

                            publication status.

                        </p>

                    </div>



                    <a

                        class="button"

                        href="<?= APP_URL ?>/instructor/create-course.php"

                    >

                        Create Course

                    </a>

                </section>



                <?php if ($courses === []): ?>

                    <section class="dashboard-panel">

                        <div class="professional-empty-state">

                            <h3>No courses created yet</h3>



                            <p>

                                Create your first course to begin adding

                                students, materials and assignments.

                            </p>



                            <a

                                class="button"

                                href="<?= APP_URL ?>/instructor/create-course.php"

                            >

                                Create Your First Course

                            </a>

                        </div>

                    </section>

                <?php else: ?>

                    <section class="course-grid">

                        <?php foreach ($courses as $course): ?>

                            <article class="course-card">

                                <div class="course-card-top">

                                    <div>

                                        <span class="course-code">

                                            <?= escape($course['course_code']) ?>

                                        </span>



                                        <h3>

                                            <?= escape($course['course_name']) ?>

                                        </h3>

                                    </div>



                                    <span

                                        class="badge <?= (bool) $course['is_published']

                                            ? 'badge-success'

                                            : 'badge-warning' ?>"

                                    >

                                        <?= (bool) $course['is_published']

                                            ? 'Published'

                                            : 'Draft' ?>

                                    </span>

                                </div>



                                <p class="course-description">

                                    <?= escape(

                                        $course['description'] !== null

                                        && $course['description'] !== ''

                                            ? $course['description']

                                            : 'No course description added.'

                                    ) ?>

                                </p>



                                <div class="course-meta-grid">

                                    <div>

                                        <span>Students</span>



                                        <strong>

                                            <?= (int) $course['enrolled_students'] ?>

                                        </strong>

                                    </div>



                                    <div>

                                        <span>Enrolment Key</span>



                                        <strong>

                                            <?= escape(

                                                $course['enrolment_key']

                                                ?: 'Not required'

                                            ) ?>

                                        </strong>

                                    </div>

                                </div>



                                <div class="course-card-actions">

                                    <a

                                        class="button button-small"

                                        href="<?= APP_URL ?>/instructor/course.php?id=<?= (int) $course['id'] ?>"

                                    >

                                        Open Course

                                    </a>



                                    <a

                                        class="button button-small button-secondary"

                                        href="<?= APP_URL ?>/instructor/edit-course.php?id=<?= (int) $course['id'] ?>"

                                    >

                                        Edit

                                    </a>



                                    <form

                                        method="POST"

                                        action="<?= APP_URL ?>/instructor/toggle-course-status.php"

                                    >

                                        <input

                                            type="hidden"

                                            name="course_id"

                                            value="<?= (int) $course['id'] ?>"

                                        >



                                        <button

                                            class="button button-small button-secondary"

                                            type="submit"

                                        >

                                            <?= (bool) $course['is_published']

                                                ? 'Unpublish'

                                                : 'Publish' ?>

                                        </button>

                                    </form>



                                    <form

                                        method="POST"

                                        action="<?= APP_URL ?>/instructor/delete-course.php"

                                        onsubmit="return confirm(

                                            'Delete this course permanently? This action cannot be undone.'

                                        );"

                                    >

                                        <input

                                            type="hidden"

                                            name="course_id"

                                            value="<?= (int) $course['id'] ?>"

                                        >



                                        <button

                                            class="button button-small button-danger"

                                            type="submit"

                                        >

                                            Delete

                                        </button>

                                    </form>

                                </div>

                            </article>

                        <?php endforeach; ?>

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