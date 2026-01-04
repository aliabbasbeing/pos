<?php
require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    redirect('pos.php');
} else {
    redirect('login.php');
}