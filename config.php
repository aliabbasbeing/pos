<?php
// Core configuration and session bootstrap

// COMPANY DETAILS
define('COMPANY_NAME', 'Alfah Tech International SMC PVT LTD');
define('COMPANY_TAGLINE', 'Advanced Poultry Health Solutions');
define('COMPANY_CONTACTS', json_encode([
    'Lahore' => ['phone' => '(042) 36-28-1111', 'whatsapp' => '0337-961-6356'],
    'Islamabad' => ['phone' => '(051) 111-241-111', 'whatsapp' => '0335-166-1111'],
]));
define('COMPANY_ADDRESSES', json_encode([
    '17-A Allama Iqbal Road, Cantt. Lahore',
    'House No. 43, Street No. 37 I-8/2 Markaz Islamabad',
]));

// SITE CONSTANTS
define('SITE_NAME', 'Alfah POS');
define('BASE_URL', ''); // If hosted in subfolder, e.g., '/alfah-pos'

// DATABASE - EDIT THESE FOR YOUR ENV
define('DB_HOST', 'localhost');
define('DB_NAME', 'alfah_pos');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// SECURITY
define('SESSION_NAME', 'alfahpos_sess');
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