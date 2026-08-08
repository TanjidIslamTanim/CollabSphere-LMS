<?php

declare(strict_types=1);

$user = currentUser();

$currentPage = basename(
    $_SERVER['PHP_SELF'] ?? ''
);

$currentFolder = basename(
    dirname($_SERVER['PHP_SELF'] ?? '')
);

function sidebarActive(
    string|array $pages,
    ?string $folder = null
): string {
    global $currentPage;
    global $currentFolder;

    $pageList = is_array($pages)
        ? $pages
        : [$pages];

    $pageMatches = in_array(
        $currentPage,
        $pageList,
        true
    );

    $folderMatches =
        $folder === null
        || $currentFolder === $folder;

    return $pageMatches && $folderMatches
        ? 'active'
        : '';
}
?>

<aside class="app-sidebar" id="app-sidebar">
    <div class="sidebar-brand">
        <img
            src="<?= APP_URL ?>/public/assets/images/collabsphere-logo.png"
            alt="CollabSphere logo"
            class="sidebar-logo"
        >

        <div class="sidebar-brand-text">
            <strong>CollabSphere</strong>
            <span>Learn. Connect. Collaborate.</span>
        </div>
    </div>

    <p class="sidebar-label">Workspace</p>

    <nav class="sidebar-navigation">
        <a
            class="sidebar-item <?= sidebarActive(
                'dashboard.php',
                (string) $user['role']
            ) ?>"
            href="<?= APP_URL ?>/<?= escape(
                $user['role']
            ) ?>/dashboard.php"
        >
            <span>Dashboard</span>
        </a>

        <?php if ($user['role'] === 'instructor'): ?>
            <a
                class="sidebar-item <?= sidebarActive(
                    [
                        'courses.php',
                        'course.php',
                        'create-course.php',
                        'edit-course.php',
                        'materials.php',
                        'upload-material.php',
                        'download-material.php',
                        'delete-material.php',
                    ],
                    'instructor'
                ) ?>"
                href="<?= APP_URL ?>/instructor/courses.php"
            >
                <span>Courses</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    [
                        'assignments.php',
                        'create-assignment.php',
                        'edit-assignment.php',
                        'delete-assignment.php',
                    ],
                    'instructor'
                ) ?>"
                href="<?= APP_URL ?>/instructor/assignments.php"
            >
                <span>Assignments</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    [
                        'submissions.php',
                    ],
                    'instructor'
                ) ?>"
                href="<?= APP_URL ?>/instructor/assignments.php"
            >
                <span>Submissions</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    [
                        'gradebook.php',
                        'grade-submission.php',
                    ],
                    'instructor'
                ) ?>"
                href="<?= APP_URL ?>/instructor/gradebook.php"
            >
                <span>Gradebook</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    [
                        'attendance.php',
                        'create-attendance.php',
                        'mark-attendance.php',
                        'delete-attendance.php',
                    ],
                    'instructor'
                ) ?>"
                href="<?= APP_URL ?>/instructor/attendance.php"
            >
                <span>Attendance</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    [
                        'announcements.php',
                        'create-announcement.php',
                        'edit-announcement.php',
                        'delete-announcement.php',
                    ],
                    'instructor'
                ) ?>"
                href="<?= APP_URL ?>/instructor/announcements.php"
            >
                <span>Announcements</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    'students.php',
                    'instructor'
                ) ?>"
                href="<?= APP_URL ?>/instructor/students.php"
            >
                <span>Students</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    'index.php',
                    'collaboration'
                ) ?>"
                href="<?= APP_URL ?>/collaboration/index.php"
            >
                <span>Collaboration</span>
            </a>
        <?php else: ?>
            <a
                class="sidebar-item <?= sidebarActive(
                    [
                        'courses.php',
                        'course.php',
                        'download-material.php',
                    ],
                    'student'
                ) ?>"
                href="<?= APP_URL ?>/student/courses.php"
            >
                <span>My Courses</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    [
                        'assignments.php',
                        'submit-assignment.php',
                    ],
                    'student'
                ) ?>"
                href="<?= APP_URL ?>/student/assignments.php"
            >
                <span>Assignments</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    'grades.php',
                    'student'
                ) ?>"
                href="<?= APP_URL ?>/student/grades.php"
            >
                <span>Grades</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    'attendance.php',
                    'student'
                ) ?>"
                href="<?= APP_URL ?>/student/attendance.php"
            >
                <span>Attendance</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    'announcements.php',
                    'student'
                ) ?>"
                href="<?= APP_URL ?>/student/announcements.php"
            >
                <span>Announcements</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    'index.php',
                    'collaboration'
                ) ?>"
                href="<?= APP_URL ?>/collaboration/index.php"
            >
                <span>Collaboration</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    'membership.php',
                    'student'
                ) ?>"
                href="<?= APP_URL ?>/student/membership.php"
            >
                <span>Membership</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-account">
        <p class="sidebar-label">Account</p>

        <nav class="sidebar-navigation">
            <a
                class="sidebar-item <?= sidebarActive(
                    [
                        'index.php',
                        'edit-profile.php',
                        'change-password.php',
                    ],
                    'profile'
                ) ?>"
                href="<?= APP_URL ?>/profile/index.php"
            >
                <span>Profile</span>
            </a>

            <a
                class="sidebar-item <?= sidebarActive(
                    'settings.php',
                    'profile'
                ) ?>"
                href="<?= APP_URL ?>/profile/settings.php"
            >
                <span>Settings</span>
            </a>

            <a
                class="sidebar-item sidebar-logout"
                href="<?= APP_URL ?>/logout.php"
            >
                <span>Log out</span>
            </a>
        </nav>
    </div>
</aside>