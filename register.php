<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Helpers/auth.php';

requireGuest();

$errors = [];

$fullName = '';
$email = '';
$role = 'student';
$identityNumber = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $passwordConfirmation = $_POST['password_confirmation'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $identityNumber = trim($_POST['identity_number'] ?? '');

    if (strlen($fullName) < 3) {
        $errors[] = 'Full name must contain at least 3 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!in_array($role, ['student', 'instructor'], true)) {
        $errors[] = 'Please select a valid account role.';
    }

    if ($identityNumber === '') {
        $errors[] = 'Student or staff number is required.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must contain at least 8 characters.';
    }

    if ($password !== $passwordConfirmation) {
        $errors[] = 'Password confirmation does not match.';
    }

    if ($errors === []) {
        $emailQuery = $pdo->prepare(
            'SELECT id FROM users WHERE email = :email LIMIT 1'
        );

        $emailQuery->execute(['email' => $email]);

        if ($emailQuery->fetch()) {
            $errors[] = 'An account already exists with this email.';
        }
    }

    if ($errors === []) {
        try {
            $pdo->beginTransaction();

            $userQuery = $pdo->prepare(
                'INSERT INTO users
                    (
                        full_name,
                        email,
                        password_hash,
                        role,
                        membership_type
                    )
                 VALUES
                    (
                        :full_name,
                        :email,
                        :password_hash,
                        :role,
                        :membership_type
                    )'
            );

            $userQuery->execute([
                'full_name' => $fullName,
                'email' => $email,
                'password_hash' => password_hash(
                    $password,
                    PASSWORD_DEFAULT
                ),
                'role' => $role,
                'membership_type' => 'non_member',
            ]);

            $userId = (int) $pdo->lastInsertId();

            if ($role === 'student') {
                $profileQuery = $pdo->prepare(
                    'INSERT INTO student_profiles
                        (
                            user_id,
                            student_number,
                            collaboration_mode
                        )
                     VALUES
                        (
                            :user_id,
                            :student_number,
                            :collaboration_mode
                        )'
                );

                $profileQuery->execute([
                    'user_id' => $userId,
                    'student_number' => $identityNumber,
                    'collaboration_mode' => 'online',
                ]);
            } else {
                $profileQuery = $pdo->prepare(
                    'INSERT INTO instructor_profiles
                        (
                            user_id,
                            staff_number
                        )
                     VALUES
                        (
                            :user_id,
                            :staff_number
                        )'
                );

                $profileQuery->execute([
                    'user_id' => $userId,
                    'staff_number' => $identityNumber,
                ]);
            }

            $pdo->commit();

            setFlash(
                'success',
                'Your account was created successfully. You may now log in.'
            );

            redirect('login.php');
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ((int) $exception->getCode() === 23000) {
                $errors[] =
                    'The email or identification number is already registered.';
            } else {
                $errors[] =
                    'Registration could not be completed. Please try again.';
            }
        }
    }
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

    <title>Create Account | <?= escape(APP_NAME) ?></title>

    <link
        rel="stylesheet"
        href="<?= APP_URL ?>/public/assets/css/app.css"
    >
</head>

<body>
    <main class="auth-page">
        <section class="auth-brand">
            <div class="brand-logo">CS</div>

            <h1><?= escape(APP_NAME) ?></h1>

            <p>
                Create your learning profile, join your courses and
                collaborate through one secure platform.
            </p>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <h2>Create an account</h2>

                <p>Enter your information to join CollabSphere.</p>

                <?php if ($errors !== []): ?>
                    <div class="alert alert-error">
                        <ul class="error-list">
                            <?php foreach ($errors as $error): ?>
                                <li><?= escape($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label for="full_name">Full name</label>

                            <input
                                id="full_name"
                                name="full_name"
                                type="text"
                                value="<?= escape($fullName) ?>"
                                required
                            >
                        </div>

                        <div class="form-group full">
                            <label for="email">Email address</label>

                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="<?= escape($email) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="role">Account role</label>

                            <select id="role" name="role" required>
                                <option
                                    value="student"
                                    <?= $role === 'student' ? 'selected' : '' ?>
                                >
                                    Student
                                </option>

                                <option
                                    value="instructor"
                                    <?= $role === 'instructor' ? 'selected' : '' ?>
                                >
                                    Instructor
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label id="identity-label" for="identity_number">
                                Student number
                            </label>

                            <input
                                id="identity_number"
                                name="identity_number"
                                type="text"
                                value="<?= escape($identityNumber) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>

                            <input
                                id="password"
                                name="password"
                                type="password"
                                minlength="8"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation">
                                Confirm password
                            </label>

                            <input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                minlength="8"
                                required
                            >
                        </div>

                        <div class="form-group full">
                            <button type="submit">Create account</button>
                        </div>
                    </div>
                </form>

                <div class="auth-footer">
                    Already registered?
                    <a href="<?= APP_URL ?>/login.php">Log in</a>
                </div>
            </div>
        </section>
    </main>

    <script>
        const roleSelect = document.getElementById('role');
        const identityLabel = document.getElementById('identity-label');

        function updateIdentityLabel() {
            identityLabel.textContent =
                roleSelect.value === 'student'
                    ? 'Student number'
                    : 'Staff number';
        }

        roleSelect.addEventListener('change', updateIdentityLabel);
        updateIdentityLabel();
    </script>
</body>
</html>