<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Helpers/auth.php';

requireGuest();

$error = '';
$email = '';
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $password === ''
    ) {
        $error =
            'Please enter a valid email address and password.';
    } else {
        $query = $pdo->prepare(
            'SELECT
                id,
                full_name,
                email,
                password_hash,
                role,
                membership_type,
                profile_photo,
                is_active
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $query->execute([
            'email' => $email,
        ]);

        $user = $query->fetch();

        if (
            !$user
            || !password_verify(
                $password,
                $user['password_hash']
            )
        ) {
            $error =
                'The email address or password is incorrect.';
        } elseif (!(bool) $user['is_active']) {
            $error =
                'This account is currently inactive.';
        } else {
            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'membership_type' =>
                    $user['membership_type'],
                'profile_photo' =>
                    $user['profile_photo'],
            ];

            redirectToDashboard();
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

    <title>
        Login | <?= escape(APP_NAME) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= APP_URL ?>/public/assets/css/app.css"
    >
</head>

<body>
    <main class="auth-page">
        <section class="auth-brand">
            <img
                src="<?= APP_URL ?>/public/assets/images/collabsphere-logo.png"
                alt="CollabSphere Logo"
                class="login-brand-logo"
            >

            <h1>
                <?= escape(APP_NAME) ?>
            </h1>

            <p>
                Access courses, assignments, materials and
                announcements through one modern collaboration
                workspace.
            </p>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="login-card-logo">
                    <img
                        src="<?= APP_URL ?>/public/assets/images/collabsphere-logo.png"
                        alt="CollabSphere Logo"
                    >
                </div>

                <h2>Welcome back</h2>

                <p>
                    Log in to continue to your workspace.
                </p>

                <?php if ($flash !== null): ?>
                    <div
                        class="alert <?= $flash['type'] === 'success'
                            ? 'alert-success'
                            : 'alert-error' ?>"
                    >
                        <?= escape($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error">
                        <?= escape($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="form-group">
                        <label for="email">
                            Email address
                        </label>

                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="<?= escape($email) ?>"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">
                            Password
                        </label>

                        <div class="password-input-wrapper">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                required
                            >

                            <button
                                class="password-toggle"
                                type="button"
                                data-password-toggle="password"
                            >
                                Show
                            </button>
                        </div>
                    </div>

                    <button type="submit">
                        Log in
                    </button>
                </form>

                <div class="auth-footer">
                    New to CollabSphere?

                    <a href="<?= APP_URL ?>/register.php">
                        Create an account
                    </a>
                </div>
            </div>
        </section>
    </main>

    <script>
        const toggleButton = document.querySelector(
            '[data-password-toggle]'
        );

        if (toggleButton) {
            toggleButton.addEventListener('click', () => {
                const inputId =
                    toggleButton.dataset.passwordToggle;

                const passwordInput =
                    document.getElementById(inputId);

                if (!passwordInput) {
                    return;
                }

                const isVisible =
                    passwordInput.type === 'text';

                passwordInput.type =
                    isVisible ? 'password' : 'text';

                toggleButton.textContent =
                    isVisible ? 'Show' : 'Hide';
            });
        }
    </script>
</body>
</html>