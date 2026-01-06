<?php
require_once __DIR__ . '/functions.php';
require_login();
enforce_role(['admin']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('products.php'); }

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { redirect('products.php'); }

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Invalid CSRF token.';
    } else {
        $required = [
            'name' => 'Name',
            'category' => 'Category',
            'composition' => 'Composition',
            'unit' => 'Unit',
            'form' => 'Form',
            'buy_price' => 'Buy Price',
            'sell_price' => 'Sell Price',
            'stock_quantity' => 'Stock Quantity',
            'min_stock' => 'Minimum Stock',
        ];
        $errors = validate_required($required, $_POST);
        if (!$errors) {
            try {
                $stmt = $pdo->prepare("UPDATE products SET name=?, category=?, composition=?, unit=?, form=?, buy_price=?, sell_price=?, stock_quantity=?, min_stock=? WHERE id=?");
                $stmt->execute([
                    trim($_POST['name']),
                    trim($_POST['category']),
                    trim($_POST['composition']),
                    trim($_POST['unit']),
                    trim($_POST['form']),
                    (float)$_POST['buy_price'],
                    (float)$_POST['sell_price'],
                    (int)$_POST['stock_quantity'],
                    (int)$_POST['min_stock'],
                    $id
                ]);
                set_flash('message', 'Product updated.');
                redirect('products.php');
            } catch (Exception $e) {
                $errors['general'] = 'Failed to update product.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e(SITE_NAME) ?> - Edit Product</title>
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
        <a href="products.php" class="hover:underline">Back to Products</a>
        <a href="logout.php" class="bg-danger px-3 py-1 rounded-md hover:bg-red-700">Logout</a>
      </div>
    </div>
  </nav>

  <main class="max-w-3xl mx-auto px-4 pt-24 pb-10">
    <div class="card">
      <h1 class="font-heading text-xl mb-4">Edit Product</h1>
      <?php if (!empty($errors['general'])): ?><div class="alert-danger"><?= e($errors['general']) ?></div><?php endif; ?>
      <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?= csrf_input() ?>
        <div class="md:col-span-2">
          <label class="label">Name</label>
          <input type="text" name="name" class="input" required value="<?= e($product['name']) ?>">
          <?php if (!empty($errors['name'])): ?><div class="error"><?= e($errors['name']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="label">Category</label>
          <select name="category" class="input" required>
            <option value="antibiotics" <?= $product['category']==='antibiotics'?'selected':'' ?>>Antibiotics</option>
            <option value="neutration" <?= $product['category']==='neutration'?'selected':'' ?>>Neutration</option>
            <option value="feed_premix_powder" <?= $product['category']==='feed_premix_powder'?'selected':'' ?>>Feed Premix Powder</option>
          </select>
          <?php if (!empty($errors['category'])): ?><div class="error"><?= e($errors['category']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="label">Form</label>
          <select name="form" class="input" required>
            <option value="Water Soluble Powder" <?= $product['form']==='Water Soluble Powder'?'selected':'' ?>>Water Soluble Powder</option>
            <option value="Liquid Solution" <?= $product['form']==='Liquid Solution'?'selected':'' ?>>Liquid Solution</option>
          </select>
          <?php if (!empty($errors['form'])): ?><div class="error"><?= e($errors['form']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="label">Unit</label>
          <input type="text" name="unit" class="input" required value="<?= e($product['unit']) ?>">
          <?php if (!empty($errors['unit'])): ?><div class="error"><?= e($errors['unit']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="label">Buy Price</label>
          <input type="number" step="0.01" name="buy_price" class="input" required value="<?= e($product['buy_price']) ?>">
          <?php if (!empty($errors['buy_price'])): ?><div class="error"><?= e($errors['buy_price']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="label">Sell Price</label>
          <input type="number" step="0.01" name="sell_price" class="input" required value="<?= e($product['sell_price']) ?>">
          <?php if (!empty($errors['sell_price'])): ?><div class="error"><?= e($errors['sell_price']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="label">Stock Quantity</label>
          <input type="number" name="stock_quantity" class="input" required value="<?= (int)$product['stock_quantity'] ?>">
          <?php if (!empty($errors['stock_quantity'])): ?><div class="error"><?= e($errors['stock_quantity']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="label">Minimum Stock</label>
          <input type="number" name="min_stock" class="input" required value="<?= (int)$product['min_stock'] ?>">
          <?php if (!empty($errors['min_stock'])): ?><div class="error"><?= e($errors['min_stock']) ?></div><?php endif; ?>
        </div>
        <div class="md:col-span-2">
          <label class="label">Composition</label>
          <textarea name="composition" class="input" rows="4" required><?= e($product['composition']) ?></textarea>
          <?php if (!empty($errors['composition'])): ?><div class="error"><?= e($errors['composition']) ?></div><?php endif; ?>
        </div>
        <div class="md:col-span-2">
          <button class="btn-primary" type="submit">Update</button>
          <a href="products.php" class="btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </main>
</body>
</html>