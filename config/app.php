<?php

declare(strict_types=1);

const APP_NAME = 'CollabSphere';
const APP_TAGLINE = 'Learn. Connect. Collaborate.';
const APP_URL = 'http://localhost/collabsphere';

const UPLOAD_ROOT = __DIR__ . '/../public/uploads';
const MATERIAL_UPLOAD_PATH = UPLOAD_ROOT . '/materials';
const ASSIGNMENT_UPLOAD_PATH = UPLOAD_ROOT . '/assignments';
const PROFILE_UPLOAD_PATH = UPLOAD_ROOT . '/profiles';

date_default_timezone_set('Asia/Kuala_Lumpur');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}