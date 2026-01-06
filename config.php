<?php
// Core configuration and session bootstrap

// COMPANY DETAILS
define('COMPANY_NAME', 'Vet Green Pharma PVT LTD');
define('COMPANY_TAGLINE', 'Professional Veterinary Solutions');
define('COMPANY_CONTACTS', json_encode([
    'Lahore' => ['phone' => '03335721301', 'whatsapp' => '03335721301'],
]));
define('COMPANY_ADDRESSES', json_encode([
    '237/2 Main Labrti, Gullberg III, Lahore',
]));

// SITE CONSTANTS
define('SITE_NAME', 'Vet Green Pharma POS');
define('BASE_URL', ''); // If hosted in subfolder, e.g., '/alfah-pos'

// DATABASE - EDIT THESE FOR YOUR ENV
define('DB_HOST', 'localhost');
define('DB_NAME', 'vetgreen_pos');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// SECURITY
define('SESSION_NAME', 'vetgreenpos_sess');
define('SESSION_TIMEOUT', 1800); // 30 minutes

// ERROR DISPLAY (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// SESSION SETUP
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']), // set true if using HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// TIMEZONE
date_default_timezone_set('UTC');