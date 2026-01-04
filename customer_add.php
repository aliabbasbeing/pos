<?php
require_once __DIR__ . '/functions.php';
require_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid CSRF token.';
    } else {
        $required = ['name' => 'Name', 'phone' => 'Phone'];
        $errors = validate_required($required, $_POST);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if (!$errors) {
            try {
                $stmt = $pdo->prepare("INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)");
                $stmt->execute([$name, $phone, $address]);
                set_flash('message', 'Customer added.');
                redirect('customers.php');
            } catch (PDOException $e) {
                if ((int)$e->getCode() === 23000) {
                    $errors['phone'] = 'Phone already exists.';
                } else {
                    $errors['general'] = 'Failed to add customer.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e(SITE_NAME) ?> - Add Customer</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = { theme:{ extend:{ colors:{ primary:'#1E40AF', secondary:'#0EA5E9', success:'#10B981', warning:'#F59E0B', danger:'#EF4444', lightbg:'#F0F9FF', dark:'#1E3A8A' }, fontFamily:{ sans:['Inter'], heading:['Poppins'] } } } }
</script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-lightbg min-h-screen">
  <nav class="bg-primary text-white fixed top-0 left-0 right-0 z-10">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <img src="assets/images/logo.png" class="h-8 w-8" alt="Logo">
        <div>
          <div class="font-heading font-semibold"><?= e(COMPANY_NAME) ?></div>
          <div class="text-xs opacity-80"><?= e(COMPANY_TAGLINE) ?></div>
        </div>
      </div>
      <div class="flex items-center gap-4 text-sm">
        <a href="customers.php" class="hover:underline">Back to Customers</a>
        <a href="logout.php" class="bg-danger px-3 py-1 rounded-md hover:bg-red-700">Logout</a>
      </div>
    </div>
  </nav>

  <main class="max-w-3xl mx-auto px-4 pt-24 pb-10">
    <div class="card">
      <h1 class="font-heading text-xl mb-4">Add Customer</h1>
      <?php if (!empty($errors['general'])): ?><div class="alert-danger"><?= e($errors['general']) ?></div><?php endif; ?>
      <form method="post" class="grid grid-cols-1 gap-4">
        <?= csrf_input() ?>
        <div>
          <label class="label">Name</label>
          <input type="text" name="name" class="input" required>
          <?php if (!empty($errors['name'])): ?><div class="error"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="label">Phone</label>
          <input type="text" name="phone" class="input" required>
          <?php if (!empty($errors['phone'])): ?><div class="error"><?= e($errors['phone']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="label">Address</label>
          <textarea name="address" class="input" rows="3"></textarea>
        </div>
        <div>
          <button class="btn-primary" type="submit">Save</button>
          <a class="btn-secondary" href="customers.php">Cancel</a>
        </div>
      </form>
    </div>
  </main>
</body>
</html>