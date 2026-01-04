<?php
require_once __DIR__ . '/functions.php';

if (is_logged_in()) {
    redirect('pos.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid session token. Please refresh and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            $error = 'Username and Password are required.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user'] = [
                        'id' => (int)$user['id'],
                        'username' => $user['username'],
                        'role' => $user['role'],
                    ];
                    $_SESSION['last_activity'] = time();
                    redirect('pos.php');
                } else {
                    $error = 'Invalid username or password.';
                }
            } catch (Exception $e) {
                $error = 'Login failed. Please try again.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e(SITE_NAME) ?> - Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: '#1E40AF',
        secondary: '#0EA5E9',
        success: '#10B981',
        warning: '#F59E0B',
        danger: '#EF4444',
        lightbg: '#F0F9FF',
        dark: '#1E3A8A',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui'],
        heading: ['Poppins', 'ui-sans-serif', 'system-ui']
      }
    }
  }
}
</script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="min-h-screen bg-lightbg flex items-center justify-center">
  <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
    <div class="text-center mb-6">
      <img src="assets/images/logo.png" alt="Logo" class="mx-auto h-20 object-contain">
      <h1 class="font-heading text-2xl text-primary mt-2"><?= e(COMPANY_NAME) ?></h1>
      <p class="text-sm text-gray-600"><?= e(COMPANY_TAGLINE) ?></p>
    </div>
    <?php if (isset($_GET['timeout'])): ?>
      <div class="p-3 mb-4 rounded bg-warning/10 text-warning text-sm">Your session timed out. Please login again.</div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="p-3 mb-4 rounded bg-danger/10 text-danger text-sm"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="space-y-4">
      <?= csrf_input() ?>
      <div>
        <label class="block text-sm mb-1 font-medium">Username</label>
        <input type="text" name="username" class="w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-secondary" required>
      </div>
      <div>
        <label class="block text-sm mb-1 font-medium">Password</label>
        <input type="password" name="password" class="w-full border rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-secondary" required>
      </div>
      <button type="submit" class="w-full bg-primary text-white rounded-md py-2 hover:bg-dark transition">Login</button>
    </form>
    <div class="mt-6 text-center text-xs text-gray-500">
      © <?= date('Y') ?> <?= e(COMPANY_NAME) ?>
    </div>
  </div>
</body>
</html>