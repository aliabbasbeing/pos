<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// HTML escape helper
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// Flash messaging
function set_flash($key, $message) {
    $_SESSION['flash'][$key] = $message;
}
function get_flash($key) {
    if (!empty($_SESSION['flash'][$key])) {
        $msg = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
    return null;
}

// CSRF
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';
}
function csrf_verify($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

// Session auth and timeout
function ensure_session_active() {
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function is_logged_in() {
    return !empty($_SESSION['user']);
}
function current_user() {
    return $_SESSION['user'] ?? null;
}
function require_login() {
    ensure_session_active();
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}
function is_admin() {
    return is_logged_in() && (current_user()['role'] ?? '') === 'admin';
}
function enforce_role($roles = []) {
    if (!is_logged_in() || !in_array(current_user()['role'] ?? '', $roles, true)) {
        http_response_code(403);
        die('Access denied.');
    }
}
function format_currency($amount) {
    return 'Rs ' . number_format((float)$amount, 2);
}
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// Input validation
function require_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method Not Allowed');
    }
}
function validate_required($fields, $source) {
    $errors = [];
    foreach ($fields as $field => $label) {
        if (!isset($source[$field]) || trim((string)$source[$field]) === '') {
            $errors[$field] = "$label is required.";
        }
    }
    return $errors;
}

// Invoice generator
function generate_invoice_number(PDO $pdo) {
    $datePart = date('Ymd');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURDATE()");
    $stmt->execute();
    $count = (int)$stmt->fetchColumn() + 1;
    return 'INV-' . $datePart . '-' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);
}

// Utility: fetch select options
function get_customers(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, name, phone FROM customers ORDER BY name ASC");
    return $stmt->fetchAll();
}

// Product live search across multiple columns (name, composition, category, form, unit)
function search_products(PDO $pdo, $q, $limit = 10) {
    $like = '%' . $q . '%';
    $limit = (int)$limit;
    $sql = "
        SELECT id, name, category, unit, form, sell_price, stock_quantity
        FROM products
        WHERE name LIKE :like
           OR composition LIKE :like
           OR category LIKE :like
           OR form LIKE :like
           OR unit LIKE :like
        ORDER BY name ASC
        LIMIT $limit
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':like' => $like]);
    return $stmt->fetchAll();
}

// Paginated product list for browsing (with optional category filter)
function get_products_page(PDO $pdo, $q = '', $category = '', $page = 1, $perPage = 12) {
    $page = max(1, (int)$page);
    $perPage = max(1, min(100, (int)$perPage));
    $offset = ($page - 1) * $perPage;

    $where = [];
    $params = [];
    if ($category !== '') {
        $where[] = "category = :category";
        $params[':category'] = $category;
    }
    if ($q !== '') {
        $where[] = "(name LIKE :q OR composition LIKE :q OR form LIKE :q OR unit LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // Count
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM products $whereSql");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    // Data
    $sql = "SELECT id, name, category, unit, form, sell_price, stock_quantity
            FROM products
            $whereSql
            ORDER BY name ASC
            LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $pages = (int)ceil($total / $perPage);
    return [
        'rows' => $rows,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'pages' => $pages,
    ];
}