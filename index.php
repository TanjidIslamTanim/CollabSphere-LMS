<?php

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= htmlspecialchars(APP_NAME) ?></title>
</head>

<body>
    <h1><?= htmlspecialchars(APP_NAME) ?></h1>

    <p><?= htmlspecialchars(APP_TAGLINE) ?></p>

    <p>The application setup is working successfully.</p>
</body>
</html>